<?php
// ============================================
// FILE: pages/offsetting.php
// File overtime hours to offset future absences
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$get_action = isset($_GET['action']) ? $_GET['action']    : '';

if ($get_action === 'approve' && $get_id) {
    $rec = db_fetch($pdo,"SELECT * FROM offsetting WHERE id=?",array($get_id));
    if ($rec) {
        db_run($pdo,
            "UPDATE offsetting SET status='Approved',approved_by=?,approved_at=NOW(),hours_remaining=? WHERE id=?",
            array((int)$_SESSION['user_id'],$rec['offset_hours'],$get_id)
        );
    }
    prg_redirect('offsetting.php','Offsetting request approved.');
}
if ($get_action === 'reject' && $get_id) {
    db_run($pdo,"UPDATE offsetting SET status='Cancelled' WHERE id=?",array($get_id));
    prg_redirect('offsetting.php','Offsetting request rejected.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $emp_id    = (int)$_POST['employee_id'];
    $off_date  = trim($_POST['offset_date']);
    $off_hrs   = (float)$_POST['offset_hours'];
    $reason    = trim($_POST['reason']);
    $target    = !empty($_POST['target_date']) ? trim($_POST['target_date']) : null;
    $target_use= trim($_POST['target_use']);

    if (!$emp_id || $off_hrs <= 0) {
        prg_redirect_error('offsetting.php','Select employee and enter valid hours.');
    }

    db_run($pdo,
        "INSERT INTO offsetting (employee_id,offset_date,offset_hours,reason,target_date,target_use)
         VALUES (?,?,?,?,?,?)",
        array($emp_id,$off_date,$off_hrs,$reason,$target,$target_use)
    );
    prg_redirect('offsetting.php','Offsetting request filed.');
}

$employees = db_fetchall($pdo,
    "SELECT id,full_name,employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name"
);
$records   = db_fetchall($pdo,
    "SELECT o.*,e.full_name,e.employee_id AS emp_code
     FROM offsetting o JOIN employees e ON o.employee_id=e.id
     ORDER BY o.created_at DESC"
);
$stat_color = array('Pending'=>'warning','Approved'=>'success','Used'=>'info','Cancelled'=>'secondary');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Offsetting | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Offsetting Records</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>
    <div class="row">
      <div class="col-md-4">
        <div class="card card-info card-outline">
          <div class="card-header"><h3 class="card-title">File offsetting request</h3></div>
          <div class="card-body">
            <div class="alert alert-light" style="font-size:12px;">
              <i class="fas fa-info-circle mr-1 text-info"></i>
              An <strong>offsetting record</strong> lets an employee bank extra hours worked
              to use as leave credit in the future. HR approves the request.
            </div>
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <div class="form-group">
                <label>Employee <span class="text-danger">*</span></label>
                <select name="employee_id" class="form-control" required>
                  <option value="">Select...</option>
                  <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>">
                      <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Date worked (offset date) <span class="text-danger">*</span></label>
                <input type="date" name="offset_date" class="form-control"
                       value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="form-group">
                <label>Hours to bank <span class="text-danger">*</span></label>
                <input type="number" name="offset_hours" class="form-control"
                       step="0.5" min="0.5" max="24" required>
              </div>
              <div class="form-group">
                <label>Reason / Description</label>
                <textarea name="reason" class="form-control" rows="2"
                          placeholder="e.g. Worked Saturday for special project"></textarea>
              </div>
              <div class="form-group">
                <label>Target usage date (optional)</label>
                <input type="date" name="target_date" class="form-control">
              </div>
              <div class="form-group">
                <label>Intended use</label>
                <input type="text" name="target_use" class="form-control"
                       placeholder="e.g. Half day Nov 15">
              </div>
              <button type="submit" class="btn btn-info btn-block">
                <i class="fas fa-clock mr-1"></i> File offsetting
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Offsetting records</h3></div>
          <div class="card-body" style="padding:16px 20px 0;">
            <table class="table table-sm table-hover dt-export" id="offTable">
              <thead class="thead-light">
                <tr><th>Employee</th><th>Date worked</th><th class="text-center">Hours</th>
                    <th class="text-center">Remaining</th><th>Target use</th>
                    <th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($records as $r):
                  $sc = isset($stat_color[$r['status']]) ? $stat_color[$r['status']] : 'secondary';
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($r['full_name']) ?></strong><br>
                      <small class="text-muted"><?= htmlspecialchars($r['emp_code']) ?></small></td>
                  <td><?= date('M d, Y',strtotime($r['offset_date'])) ?></td>
                  <td class="text-center"><?= $r['offset_hours'] ?>h</td>
                  <td class="text-center">
                    <?= $r['status']==='Approved'
                        ? '<strong>'.($r['hours_remaining'] ?: $r['offset_hours']).'h</strong>'
                        : '—' ?>
                  </td>
                  <td style="font-size:12px;"><?= htmlspecialchars($r['target_use'] ?: '—') ?></td>
                  <td><span class="badge badge-<?= $sc ?>"><?= $r['status'] ?></span></td>
                  <td style="white-space:nowrap;">
                    <?php if ($r['status']==='Pending'): ?>
                    <a href="offsetting.php?action=approve&id=<?= $r['id'] ?>"
                       class="btn btn-success btn-xs"
                       onclick="return confirm('Approve this offsetting?')">
                      <i class="fas fa-check"></i>
                    </a>
                    <a href="offsetting.php?action=reject&id=<?= $r['id'] ?>"
                       class="btn btn-danger btn-xs"
                       onclick="return confirm('Reject?')">
                      <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>