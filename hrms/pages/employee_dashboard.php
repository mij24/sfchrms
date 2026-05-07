<?php
// ============================================
// FILE: pages/employee_dashboard.php
// Dashboard for: employee, clinic_staff,
//   clinic_head, board_director
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// Timezone
$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz='Asia/Manila'; $_hrms_delta=0;
foreach($_tz_row as $_r){
    if($_r['setting_key']==='system_timezone'&&$_r['setting_value']) $_hrms_tz=$_r['setting_value'];
    if($_r['setting_key']==='clock_delta_seconds') $_hrms_delta=(int)$_r['setting_value'];
}
try{ date_default_timezone_set($_hrms_tz); }catch(Exception $e){ date_default_timezone_set('Asia/Manila'); }

$my_uid    = (int)$_SESSION['user_id'];
$my_role   = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';
$my_user   = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array($my_uid));
$my_emp_id = $my_user ? (int)$my_user['employee_id'] : 0;
$today     = date('Y-m-d', time()+$_hrms_delta);

$emp = $my_emp_id ? db_fetch($pdo,
    "SELECT e.*, d.name AS dept_name, p.title AS position_name
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN positions p ON e.position_id=p.id
     WHERE e.id=?", array($my_emp_id)
) : null;

// Inbox
$tl_pending  = $my_emp_id ? (int)db_value($pdo,
    "SELECT COUNT(*) FROM teaching_loads WHERE employee_id=? AND status='For Faculty Review'",
    array($my_emp_id)) : 0;
$req_pending = $my_emp_id ? (int)db_value($pdo,
    "SELECT COUNT(*) FROM employee_requests WHERE employee_id=? AND status IN ('Pending','Pending Clarification')",
    array($my_emp_id)) : 0;
$inbox_total = $tl_pending + $req_pending;

// Today's attendance
$my_attendance = $my_emp_id ? db_fetch($pdo,
    "SELECT * FROM attendance WHERE employee_id=? AND attendance_date=?",
    array($my_emp_id,$today)) : null;

// Leave credits
$leave_credits = $my_emp_id ? db_fetch($pdo,
    "SELECT * FROM leave_credits WHERE employee_id=? AND year=YEAR(CURDATE())",
    array($my_emp_id)) : null;

// Teaching loads
$my_loads = array();
try {
    $my_loads = $my_emp_id ? db_fetchall($pdo,
        "SELECT * FROM teaching_loads
         WHERE employee_id=? AND status IN ('Dept Head Approved','For Registrar Verification',
               'Registrar Verified','For VP Academic Review','VP Academic Recommended',
               'For President Approval','President Approved','Approved')
         ORDER BY created_at DESC LIMIT 5",
        array($my_emp_id)) : array();
} catch(Exception $e){ $my_loads = array(); }

// My recent requests
$my_requests = array();
try {
    $my_requests = $my_emp_id ? db_fetchall($pdo,
        "SELECT er.*, rt.name AS type_name FROM employee_requests er
         JOIN request_types rt ON er.request_type_id=rt.id
         WHERE er.employee_id=? ORDER BY er.created_at DESC LIMIT 5",
        array($my_emp_id)) : array();
} catch(Exception $e){ $my_requests = array(); }

// Announcements
$announcements = array();
try {
    $announcements = db_fetchall($pdo,
        "SELECT * FROM announcements WHERE is_active=1 ORDER BY created_at DESC LIMIT 5") ?: array();
} catch(Exception $e){ $announcements = array(); }

$role_label   = isset($ROLE_LABELS[$my_role]) ? $ROLE_LABELS[$my_role] : ucwords(str_replace('_',' ',$my_role));
$status_color = array(
    'Pending'=>'warning','Approved'=>'success',
    'Rejected'=>'danger','Returned'=>'warning','Cancelled'=>'secondary'
);

// Leave balances
$vac_bal  = $leave_credits ? max(0, ((isset($leave_credits['vacation_leave']) ? (float)$leave_credits['vacation_leave'] : 0) - (isset($leave_credits['vacation_used']) ? (float)$leave_credits['vacation_used'] : 0))) : 0;
$sick_bal = $leave_credits ? max(0, ((isset($leave_credits['sick_leave'])     ? (float)$leave_credits['sick_leave']     : 0) - (isset($leave_credits['sick_used'])     ? (float)$leave_credits['sick_used']     : 0))) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .quick-stat{border-radius:10px;padding:14px 16px;background:#fff;
      border:1px solid #dee2e6;text-align:center;margin-bottom:12px;}
    .quick-stat .n{font-size:28px;font-weight:700;line-height:1;}
    .quick-stat .l{font-size:11px;color:#6c757d;margin-top:3px;}
    .ann-item{border-left:3px solid #ffc107;border-radius:0 6px 6px 0;
      padding:8px 12px;margin-bottom:8px;background:#fffdf0;}
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
<div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- Quick stats -->
    <div class="row mb-2">
      <div class="col-6 col-md-3">
        <div class="quick-stat">
          <div class="n <?= $inbox_total>0?'text-warning':'' ?>"><?= $inbox_total ?></div>
          <div class="l">Inbox Pending</div>
          <div class="mt-2"><a href="my_inbox.php" class="btn btn-xs btn-outline-warning">Open Inbox</a></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="quick-stat">
          <div class="n text-info"><?= count($my_requests) ?></div>
          <div class="l">My Requests</div>
          <div class="mt-2"><a href="my_requests.php" class="btn btn-xs btn-outline-info">New Request</a></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="quick-stat">
          <div class="n text-success"><?= $vac_bal ?></div>
          <div class="l">Vacation Credits</div>
          <div class="mt-2"><a href="my_leave.php" class="btn btn-xs btn-outline-success">My Leave</a></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="quick-stat">
          <div class="n text-danger"><?= $sick_bal ?></div>
          <div class="l">Sick Leave Credits</div>
          <div class="mt-2"><a href="my_schedule.php" class="btn btn-xs btn-outline-secondary">My Schedule</a></div>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- My Teaching Loads -->
      <?php if (!empty($my_loads)): ?>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">My Teaching Loads</h3>
            <a href="my_schedule.php" class="btn btn-xs btn-outline-primary">View Schedule</a>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0" style="font-size:12px;">
              <thead class="thead-light">
                <tr><th>Subject</th><th>Day / Time</th><th>Status</th></tr>
              </thead>
              <tbody>
                <?php foreach ($my_loads as $l): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($l['subject_title'] ?: 'Multiple subjects') ?></strong></td>
                  <td><?= htmlspecialchars($l['day_of_week'] ?: '—') ?>
                    <br><small><?= $l['time_start'] ? date('h:i A',strtotime($l['time_start'])) : '' ?></small>
                  </td>
                  <td><span class="badge badge-success" style="font-size:9px;"><?= htmlspecialchars($l['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- My Recent Requests -->
      <div class="col-md-<?= !empty($my_loads) ? '6' : '8' ?>">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">My Recent Requests</h3>
            <a href="my_requests.php" class="btn btn-xs btn-outline-primary">+ New</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($my_requests)): ?>
            <div class="text-center text-muted py-3" style="font-size:13px;">No requests filed yet.</div>
            <?php else: ?>
            <table class="table table-sm mb-0" style="font-size:12px;">
              <thead class="thead-light">
                <tr><th>Reference</th><th>Type</th><th>Status</th><th>Date</th></tr>
              </thead>
              <tbody>
                <?php foreach ($my_requests as $r):
                  $sc = isset($status_color[$r['status']]) ? $status_color[$r['status']] : 'secondary'; ?>
                <tr>
                  <td><code style="font-size:11px;"><?= htmlspecialchars($r['reference_no']) ?></code></td>
                  <td><?= htmlspecialchars($r['type_name']) ?></td>
                  <td><span class="badge badge-<?= $sc ?>" style="font-size:9px;"><?= $r['status'] ?></span></td>
                  <td style="font-size:11px;color:#6c757d;"><?= date('M d',strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Announcements -->
      <div class="col-md-4">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Announcements</h3></div>
          <div class="card-body p-2">
            <?php if (empty($announcements)): ?>
            <div class="text-center text-muted py-3" style="font-size:13px;">No announcements.</div>
            <?php else: ?>
            <?php foreach ($announcements as $a): ?>
            <div class="ann-item mb-2">
              <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars(isset($a['title']) ? $a['title'] : 'Announcement') ?></div>
              <div style="font-size:11px;color:#6c757d;"><?= date('M d, Y',strtotime($a['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick shortcuts -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Quick Access</h3></div>
      <div class="card-body py-2">
        <div class="d-flex flex-wrap" style="gap:8px;">
          <a href="my_inbox.php" class="btn btn-sm btn-outline-warning">
            My Inbox
            <?php if ($inbox_total > 0): ?>
            <span class="badge badge-warning ml-1"><?= $inbox_total ?></span>
            <?php endif; ?>
          </a>
          <a href="my_schedule.php"    class="btn btn-sm btn-outline-info">Schedule</a>
          <a href="my_attendance.php"  class="btn btn-sm btn-outline-success">Attendance</a>
          <a href="my_leave.php"       class="btn btn-sm btn-outline-primary">My Leave</a>
          <a href="my_payslips.php"    class="btn btn-sm btn-outline-secondary">Payslips</a>
          <a href="my_requests.php"    class="btn btn-sm btn-outline-danger">New Request</a>
          <a href="attendance_selfservice.php" class="btn btn-sm btn-success">Clock In/Out</a>
        </div>
      </div>
    </div>

</div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>