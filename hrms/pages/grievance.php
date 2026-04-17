<?php
// ============================================
// FILE: pages/grievance.php
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

if ($get_action === 'delete' && $get_id) { $conn->query("DELETE FROM grievances WHERE id=$get_id"); $success = "Grievance deleted."; }
if ($get_action === 'edit'   && $get_id) { $edit = $conn->query("SELECT * FROM grievances WHERE id=$get_id")->fetch_assoc(); }
if ($get_action === 'resolve'&& $get_id) {
    $user = (int)$_SESSION['user_id'];
    $conn->query("UPDATE grievances SET status='Resolved', resolved_by=$user, resolved_at=NOW() WHERE id=$get_id");
    //$success = "Grievance marked as resolved.";
	prg_redirect('grievance.php', 'Grievance resolved.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	csrf_verify();
    $emp_id   = (int)$_POST['employee_id'];
    $subject  = $conn->real_escape_string(trim($_POST['subject']));
    $desc     = $conn->real_escape_string(trim($_POST['description']));
    $category = $conn->real_escape_string($_POST['category']);
    $status   = $conn->real_escape_string($_POST['status']);
    $res      = $conn->real_escape_string(trim($_POST['resolution']));
    $post_id  = isset($_POST['grv_id']) ? (int)$_POST['grv_id'] : 0;

    if ($post_id) {
        $conn->query("UPDATE grievances SET employee_id=$emp_id, subject='$subject', description='$desc', category='$category', status='$status', resolution='$res' WHERE id=$post_id");
        //$success = "Grievance updated."; $edit = null;
		prg_redirect('grievance.php', 'Grievance updated.');
    } else {
        $conn->query("INSERT INTO grievances (employee_id,subject,description,category,resolution) VALUES ($emp_id,'$subject','$desc','$category','$res')");
        //$success = "Grievance filed.";
		prg_redirect('grievance.php', 'Grievance filed.');
    }
}

$employees  = $conn->query("SELECT id, full_name, employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name");
$grievances = $conn->query("SELECT g.*, e.full_name, e.employee_id AS emp_code FROM grievances g JOIN employees e ON g.employee_id=e.id ORDER BY g.created_at DESC");
$categories = array('Work environment','Compensation','Management','Harassment','Discrimination','Other');
$statuses   = array('Filed','Under review','Resolved','Closed');
$stat_color = array('Filed'=>'warning','Under review'=>'info','Resolved'=>'success','Closed'=>'secondary');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Grievance | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Grievance &amp; Concerns</h1></div></div>
  <div class="content"><div class="container-fluid">
	  <?= prg_flash() ?>
    <!--<?php// if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $success ?></div><?php //endif; ?>-->
    <div class="row">
      <div class="col-md-4">
        <div class="card card-warning card-outline">
          <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit grievance' : 'File grievance' ?></h3></div>
          <div class="card-body">
            <form method="POST" action="">
				<?php csrf_field(); ?>
              <?php if ($edit): ?><input type="hidden" name="grv_id" value="<?= $edit['id'] ?>"><?php endif; ?>
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
                <label>Subject <span class="text-danger">*</span></label>
                <input type="text" name="subject" class="form-control" required value="<?= $edit ? htmlspecialchars($edit['subject']) : '' ?>">
              </div>
              <div class="form-group">
                <label>Category</label>
                <select name="category" class="form-control">
                  <?php foreach ($categories as $cat): ?><option <?= ($edit && $edit['category']===$cat) ? 'selected' : '' ?>><?= $cat ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
              </div>
              <?php if ($edit): ?>
              <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                  <?php foreach ($statuses as $st): ?><option <?= ($edit['status']===$st) ? 'selected' : '' ?>><?= $st ?></option><?php endforeach; ?>
                </select>
              </div>
              <?php else: ?>
                <input type="hidden" name="status" value="Filed">
              <?php endif; ?>
              <div class="form-group">
                <label>Resolution / Action taken</label>
                <textarea name="resolution" class="form-control" rows="2"><?= $edit ? htmlspecialchars($edit['resolution']) : '' ?></textarea>
              </div>
              <button type="submit" class="btn btn-warning btn-block"><i class="fas fa-save mr-1"></i><?= $edit ? 'Update' : 'File grievance' ?></button>
              <?php if ($edit): ?><a href="grievance.php" class="btn btn-secondary btn-block mt-1">Cancel</a><?php endif; ?>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">All grievances</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0" id="grvTable">
              <thead class="thead-light">
                <tr><th>Employee</th><th>Subject</th><th>Category</th><th>Status</th><th>Filed</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php while ($g = $grievances->fetch_assoc()):
                  $sc = isset($stat_color[$g['status']]) ? $stat_color[$g['status']] : 'secondary';
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($g['full_name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($g['emp_code']) ?></small></td>
                  <td><?= htmlspecialchars($g['subject']) ?></td>
                  <td><span class="badge badge-light"><?= htmlspecialchars($g['category']) ?></span></td>
                  <td><span class="badge badge-<?= $sc ?>"><?= $g['status'] ?></span></td>
                  <td style="font-size:12px;"><?= date('M d, Y', strtotime($g['created_at'])) ?></td>
                  <td style="white-space:nowrap;">
                    <a href="grievance.php?action=edit&id=<?= $g['id'] ?>" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <?php if ($g['status']==='Filed' || $g['status']==='Under review'): ?>
                    <a href="grievance.php?action=resolve&id=<?= $g['id'] ?>" class="btn btn-success btn-xs" onclick="return confirm('Mark as resolved?')"><i class="fas fa-check"></i></a>
                    <?php endif; ?>
                    <a href="grievance.php?action=delete&id=<?= $g['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
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
<script>$(function(){ $('#grvTable').DataTable({ pageLength:15, order:[[4,'desc']] }); });</script>
</body></html>