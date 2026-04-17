<?php
// ============================================
// FILE: pages/my_leave.php
// Employee self-service — leave requests
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';

$user   = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array((int)$_SESSION['user_id']));
$emp_id = $user ? (int)$user['employee_id'] : 0;
if (!$emp_id) { echo '<div class="alert alert-warning m-4">Not linked to employee record.</div>'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ltype   = clean_string($_POST['leave_type']);
    $from    = clean_date($_POST['date_from']);
    $to      = clean_date($_POST['date_to']);
    $days    = clean_float($_POST['days_applied']);
    $reason  = clean_string($_POST['reason'],500);
    db_run($pdo,
        "INSERT INTO leave_requests (employee_id,leave_type,date_from,date_to,days_applied,reason)
         VALUES (?,?,?,?,?,?)",
        array($emp_id,$ltype,$from,$to,$days,$reason)
    );
    prg_redirect('my_leave.php','Leave request submitted.');
}

$credits = db_fetch($pdo,
    "SELECT * FROM leave_credits WHERE employee_id=? AND year=YEAR(CURDATE())",
    array($emp_id)
);
$my_requests = db_fetchall($pdo,
    "SELECT * FROM leave_requests WHERE employee_id=? ORDER BY created_at DESC",
    array($emp_id)
);
$stat_badge = array('Pending'=>'warning','Approved'=>'success','Rejected'=>'danger');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Leave | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">My Leave</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- Leave credits -->
    <?php if ($credits): ?>
    <div class="row mb-3">
      <?php
      $lv = array(
        array('Vacation','vacation_leave','vacation_used','success'),
        array('Sick','sick_leave','sick_used','info'),
        array('Emergency','emergency_leave','emergency_used','warning'),
      );
      foreach ($lv as $l):
        $tot = (float)$credits[$l[1]];
        $used= (float)$credits[$l[2]];
        $rem = $tot - $used;
        $pct = $tot > 0 ? round(($used/$tot)*100) : 0;
      ?>
      <div class="col-md-4">
        <div class="card">
          <div class="card-body py-3">
            <div class="d-flex justify-content-between">
              <div class="info-label" style="font-size:11px;color:#aaa;text-transform:uppercase;"><?= $l[0] ?> leave</div>
              <small class="text-muted"><?= $used ?> used</small>
            </div>
            <div style="font-size:28px;font-weight:700;"><?= number_format($rem,1) ?> <small style="font-size:13px;color:#aaa;">/ <?= $tot ?> days</small></div>
            <div class="progress mt-1" style="height:5px;"><div class="progress-bar bg-<?= $l[3] ?>" style="width:<?= $pct ?>%"></div></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="row">
      <!-- File request form -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">File leave request</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <div class="form-group"><label>Leave type</label>
                <select name="leave_type" class="form-control">
                  <?php foreach (array('Vacation leave','Sick leave','Emergency leave','Maternity leave','Paternity leave') as $lt): ?>
                    <option><?= $lt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="row">
                <div class="col-6"><div class="form-group"><label>From</label><input type="date" name="date_from" class="form-control" required></div></div>
                <div class="col-6"><div class="form-group"><label>To</label><input type="date" name="date_to" class="form-control" required></div></div>
              </div>
              <div class="form-group"><label>Days applied</label><input type="number" name="days_applied" class="form-control" step="0.5" min="0.5" value="1"></div>
              <div class="form-group"><label>Reason</label><textarea name="reason" class="form-control" rows="3"></textarea></div>
              <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-paper-plane mr-1"></i> Submit request</button>
            </form>
          </div>
        </div>
      </div>

      <!-- My requests -->
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">My leave requests</h3></div>
          <div class="card-body" style="padding:16px 16px 0;">
            <table class="table table-sm table-hover dt-export" id="myLeaveTable">
              <thead class="thead-light">
                <tr><th>Type</th><th>From</th><th>To</th><th class="text-center">Days</th><th>Status</th><th>Reason</th></tr>
              </thead>
              <tbody>
                <?php foreach ($my_requests as $lr):
                  $sb = isset($stat_badge[$lr['status']]) ? $stat_badge[$lr['status']] : 'secondary';
                ?>
                <tr>
                  <td><?= htmlspecialchars($lr['leave_type']) ?></td>
                  <td><?= date('M d, Y',strtotime($lr['date_from'])) ?></td>
                  <td><?= date('M d, Y',strtotime($lr['date_to'])) ?></td>
                  <td class="text-center"><?= $lr['days_applied'] ?></td>
                  <td><span class="badge badge-<?= $sb ?>"><?= $lr['status'] ?></span></td>
                  <td><?= htmlspecialchars($lr['reason']?$lr['reason']:'—') ?></td>
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
