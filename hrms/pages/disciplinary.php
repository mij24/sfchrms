<?php
// ============================================
// FILE: pages/disciplinary.php
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$error = ''; $success = '';
$edit  = null;

$get_id     = isset($_GET['id'])     ? (int)$_GET['id'] : 0;
$get_action = isset($_GET['action']) ? $_GET['action']   : '';

if ($get_action === 'delete' && $get_id) {
    $conn->query("DELETE FROM disciplinary WHERE id=$get_id");
    $success = "Record deleted.";
}
if ($get_action === 'edit' && $get_id) {
    $edit = $conn->query("SELECT * FROM disciplinary WHERE id=$get_id")->fetch_assoc();
}
if ($get_action === 'resolve' && $get_id) {
    $conn->query("UPDATE disciplinary SET status='Resolved' WHERE id=$get_id");
    $success = "Case marked as resolved.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	csrf_verify();
    $emp_id       = (int)$_POST['employee_id'];
    $inc_date     = $conn->real_escape_string($_POST['incident_date']);
    $viol_type    = $conn->real_escape_string(trim($_POST['violation_type']));
    $description  = $conn->real_escape_string(trim($_POST['description']));
    $sanction     = $conn->real_escape_string($_POST['sanction']);
    $nte          = isset($_POST['nte_issued']) ? 1 : 0;
    $nte_date     = $conn->real_escape_string($_POST['nte_date']);
    $resolution   = $conn->real_escape_string(trim($_POST['resolution']));
    $filed_by     = (int)$_SESSION['user_id'];
    $post_id      = isset($_POST['disc_id']) ? (int)$_POST['disc_id'] : 0;

    if ($post_id) {
        $conn->query("UPDATE disciplinary SET employee_id=$emp_id, incident_date='$inc_date',
            violation_type='$viol_type', description='$description', sanction='$sanction',
            nte_issued=$nte, nte_date='$nte_date', resolution='$resolution' WHERE id=$post_id");
        //$success = "Record updated."; $edit = null;
		prg_redirect('disciplinary.php', 'Record updated.');
    } else {
        $conn->query("INSERT INTO disciplinary
            (employee_id, incident_date, violation_type, description, sanction, nte_issued, nte_date, resolution, filed_by)
            VALUES ($emp_id,'$inc_date','$viol_type','$description','$sanction',$nte,'$nte_date','$resolution',$filed_by)");
        //$success = "Disciplinary record filed.";
		prg_redirect('disciplinary.php', 'Record filed.');
    }
}

$employees = $conn->query("SELECT id, full_name, employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name");
$records   = $conn->query("
    SELECT d.*, e.full_name, e.employee_id AS emp_code
    FROM disciplinary d JOIN employees e ON d.employee_id=e.id
    ORDER BY d.incident_date DESC
");
$sanctions  = array('Verbal warning','Written warning','Suspension','Demotion','Termination');
$stat_color = array('Open'=>'danger','Resolved'=>'success','Closed'=>'secondary');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Disciplinary | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Disciplinary Records</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash()?>
	  
	  <!--<?php// if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $success ?></div><?php// endif; ?>-->
    <div class="row">
      <div class="col-md-4">
        <div class="card card-danger card-outline">
          <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit record' : 'File incident' ?></h3></div>
          <div class="card-body">
            <form method="POST" action="">
				<?php csrf_field(); ?>
              <?php if ($edit): ?><input type="hidden" name="disc_id" value="<?= $edit['id'] ?>"><?php endif; ?>
              <div class="form-group">
                <label>Employee <span class="text-danger">*</span></label>
                <select name="employee_id" class="form-control" required>
                  <option value="">Select...</option>
                  <?php while ($e = $employees->fetch_assoc()): ?>
                    <option value="<?= $e['id'] ?>" <?= ($edit && $edit['employee_id']==$e['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Incident date</label>
                <input type="date" name="incident_date" class="form-control" value="<?= $edit ? $edit['incident_date'] : date('Y-m-d') ?>">
              </div>
              <div class="form-group">
                <label>Violation type <span class="text-danger">*</span></label>
                <input type="text" name="violation_type" class="form-control" required value="<?= $edit ? htmlspecialchars($edit['violation_type']) : '' ?>" placeholder="e.g. Tardiness, AWOL, Insubordination">
              </div>
              <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
              </div>
              <div class="form-group">
                <label>Sanction</label>
                <select name="sanction" class="form-control">
                  <?php foreach ($sanctions as $s): ?><option <?= ($edit && $edit['sanction']===$s) ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" name="nte_issued" class="custom-control-input" id="nte"
                         <?= ($edit && $edit['nte_issued']) ? 'checked' : '' ?>>
                  <label class="custom-control-label" for="nte">NTE (Notice to Explain) issued</label>
                </div>
              </div>
              <div class="form-group">
                <label>NTE date</label>
                <input type="date" name="nte_date" class="form-control" value="<?= $edit ? $edit['nte_date'] : '' ?>">
              </div>
              <div class="form-group">
                <label>Resolution / Remarks</label>
                <textarea name="resolution" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['resolution']) : '' ?></textarea>
              </div>
              <button type="submit" class="btn btn-danger btn-block"><i class="fas fa-save mr-1"></i> <?= $edit ? 'Update' : 'File record' ?></button>
              <?php if ($edit): ?><a href="disciplinary.php" class="btn btn-secondary btn-block mt-1">Cancel</a><?php endif; ?>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">All disciplinary records</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0" id="discTable">
              <thead class="thead-light">
                <tr><th>Employee</th><th>Violation</th><th>Sanction</th><th>NTE</th><th>Date</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php while ($r = $records->fetch_assoc()):
                  $sc = isset($stat_color[$r['status']]) ? $stat_color[$r['status']] : 'secondary';
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($r['full_name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($r['emp_code']) ?></small></td>
                  <td><?= htmlspecialchars($r['violation_type']) ?></td>
                  <td><span class="badge badge-warning"><?= htmlspecialchars($r['sanction']) ?></span></td>
                  <td><?= $r['nte_issued'] ? '<span class="badge badge-info">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?></td>
                  <td><?= $r['incident_date'] ? date('M d, Y', strtotime($r['incident_date'])) : '—' ?></td>
                  <td><span class="badge badge-<?= $sc ?>"><?= $r['status'] ?></span></td>
                  <td style="white-space:nowrap;">
                    <a href="disciplinary.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <?php if ($r['status']==='Open'): ?>
                    <a href="disciplinary.php?action=resolve&id=<?= $r['id'] ?>" class="btn btn-success btn-xs" onclick="return confirm('Mark as resolved?')"><i class="fas fa-check"></i></a>
                    <?php endif; ?>
                    <a href="disciplinary.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete this record?')"><i class="fas fa-trash"></i></a>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>$(function(){ $('#discTable').DataTable({ pageLength:15, order:[[4,'desc']] }); });</script>
</body></html>
