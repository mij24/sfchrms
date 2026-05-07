<?php
// FILE: pages/president_dashboard.php — Dashboard for President
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

if (!(isset($_SESSION['role']) && $_SESSION['role']==='president')) {
    http_response_code(403); include '../includes/403.php'; exit;
}

$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz='Asia/Manila'; $_hrms_delta=0;
foreach($_tz_row as $_r){
    if($_r['setting_key']==='system_timezone' && $_r['setting_value']) $_hrms_tz=$_r['setting_value'];
    if($_r['setting_key']==='clock_delta_seconds') $_hrms_delta=(int)$_r['setting_value'];
}
try{ date_default_timezone_set($_hrms_tz); }catch(Exception $e){ date_default_timezone_set('Asia/Manila'); }

$my_uid  = (int)$_SESSION['user_id'];
$my_user = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array($my_uid));
$my_emp_id = $my_user ? (int)$my_user['employee_id'] : 0;
$today   = date('Y-m-d', time()+$_hrms_delta);


$tl_for_pres=0; $total_emp=0; $pending_all=0; $approved_year=0;
try {
    $tl_for_pres=(int)db_value($pdo,"SELECT COUNT(*) FROM teaching_loads WHERE status='For President Approval'");
    $total_emp=(int)db_value($pdo,"SELECT COUNT(*) FROM employees WHERE employment_status='Active'");
    $pending_all=(int)db_value($pdo,"SELECT COUNT(*) FROM employee_requests WHERE status IN ('Pending','In Progress')");
    $approved_year=(int)db_value($pdo,"SELECT COUNT(*) FROM employee_requests WHERE status='Approved' AND YEAR(created_at)=YEAR(CURDATE())");
} catch(Exception $e){}

$pending_req = 0; $approved_req = 0; $rejected_req = 0;
try {
    $pending_req  = (int)db_value($pdo,"SELECT COUNT(*) FROM employee_requests WHERE employee_id=? AND status IN ('Pending','In Progress')",array($my_emp_id));
    $approved_req = (int)db_value($pdo,"SELECT COUNT(*) FROM employee_requests WHERE employee_id=? AND status='Approved' AND YEAR(created_at)=YEAR(CURDATE())",array($my_emp_id));
    $rejected_req = (int)db_value($pdo,"SELECT COUNT(*) FROM employee_requests WHERE employee_id=? AND status IN ('Rejected','Returned')",array($my_emp_id));
} catch(Exception $e) { $pending_req=$approved_req=$rejected_req=0; }
$inbox_tl = 0;
try {
    $inbox_tl = (int)db_value($pdo,"SELECT COUNT(*) FROM teaching_loads WHERE employee_id=? AND status='For Faculty Review'",array($my_emp_id));
} catch(Exception $e) { $inbox_tl=0; }
$inbox_total = $pending_req + $inbox_tl;


// Announcements
$announcements = array();
try {
    $announcements = db_fetchall($pdo,
        "SELECT a.*, u.username AS posted_by_name FROM announcements a
         LEFT JOIN users u ON a.posted_by=u.id
         WHERE a.is_active=1 ORDER BY a.created_at DESC LIMIT 5");
} catch(Exception $e){ $announcements = array(); }

$priority_colors = array('Normal'=>'secondary','Important'=>'warning','Urgent'=>'danger');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars('President') ?> Dashboard | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .dash-banner {
      background: linear-gradient(135deg, #343a40dd, #343a4088);
      color:#fff; border-radius:12px; padding:22px 28px; margin-bottom:22px;
      box-shadow:0 4px 15px rgba(0,0,0,.15);
    }
    .dash-banner .clock { font-size:34px; font-weight:700; font-family:monospace; letter-spacing:2px; }
    .dash-banner .role-label { font-size:12px; opacity:.65; margin-top:2px; }
    .dash-banner .user-name { font-size:20px; font-weight:600; }
    .quick-btn { border-radius:10px; transition:.15s; }
    .quick-btn:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.15); }
    .ann-card { border-left:4px solid; border-radius:0 8px 8px 0; margin-bottom:10px; padding:10px 14px; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0"><i class="fas fa-crown mr-2" style="color:#343a40;"></i>President Dashboard</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">Dashboard</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- Banner -->
    <div class="dash-banner">
      <div class="row align-items-center">
        <div class="col">
          <div class="clock" id="dashClock">--:--:--</div>
          <div style="font-size:13px;opacity:.6;margin-top:2px;" id="dashDate"></div>
        </div>
        <div class="col-auto text-right">
          <div class="user-name"><?= htmlspecialchars($my_user ? $my_user['username'] : '') ?></div>
          <div class="role-label">School President</div>
        </div>
      </div>
    </div>

    <!-- Stats row -->
    <div class="row">

      <div class="col-6 col-md-3 mb-3">
        <div class="info-box shadow-sm">
          <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Active Employees</span>
            <span class="info-box-number"><?= $total_emp ?></span>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3 mb-3">
        <div class="info-box shadow-sm">
          <span class="info-box-icon bg-warning"><i class="fas fa-chalkboard-teacher"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">TL For Approval</span>
            <span class="info-box-number"><?= $tl_for_pres ?></span>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3 mb-3">
        <div class="info-box shadow-sm">
          <span class="info-box-icon bg-warning"><i class="fas fa-file-alt"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Pending (System-wide)</span>
            <span class="info-box-number"><?= $pending_all ?></span>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3 mb-3">
        <div class="info-box shadow-sm">
          <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Approved (Year)</span>
            <span class="info-box-number"><?= $approved_year ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Quick Access -->
      <div class="col-md-8">
        <div class="card card-outline" style="border-top:3px solid #343a40;">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-bolt mr-2" style="color:#343a40;"></i>Quick Access</h3>
          </div>
          <div class="card-body">
            <div class="row">

            <div class="col-6 col-sm-4 col-md-3 mb-3">
              <a href="teaching_load.php" class="btn btn-outline-warning btn-block py-3">
                <i class="fas fa-chalkboard-teacher fa-lg d-block mb-1"></i>
                <span style="font-size:12px;">Teaching Loads</span>
              </a>
            </div>
            <div class="col-6 col-sm-4 col-md-3 mb-3">
              <a href="employee_requests.php" class="btn btn-outline-danger btn-block py-3">
                <i class="fas fa-file-signature fa-lg d-block mb-1"></i>
                <span style="font-size:12px;">Requests</span>
              </a>
            </div>
            <div class="col-6 col-sm-4 col-md-3 mb-3">
              <a href="employees.php" class="btn btn-outline-info btn-block py-3">
                <i class="fas fa-users fa-lg d-block mb-1"></i>
                <span style="font-size:12px;">Employees</span>
              </a>
            </div>
            <div class="col-6 col-sm-4 col-md-3 mb-3">
              <a href="analytics.php" class="btn btn-outline-primary btn-block py-3">
                <i class="fas fa-chart-line fa-lg d-block mb-1"></i>
                <span style="font-size:12px;">Analytics</span>
              </a>
            </div>
            <div class="col-6 col-sm-4 col-md-3 mb-3">
              <a href="reports.php" class="btn btn-outline-secondary btn-block py-3">
                <i class="fas fa-chart-bar fa-lg d-block mb-1"></i>
                <span style="font-size:12px;">Reports</span>
              </a>
            </div>
            <div class="col-6 col-sm-4 col-md-3 mb-3">
              <a href="my_inbox.php" class="btn btn-outline-warning btn-block py-3">
                <i class="fas fa-inbox fa-lg d-block mb-1"></i>
                <span style="font-size:12px;">My Inbox</span>
              </a>
            </div>
            <div class="col-6 col-sm-4 col-md-3 mb-3">
              <a href="my_leave.php" class="btn btn-outline-danger btn-block py-3">
                <i class="fas fa-calendar-minus fa-lg d-block mb-1"></i>
                <span style="font-size:12px;">My Leave</span>
              </a>
            </div>
            <div class="col-6 col-sm-4 col-md-3 mb-3">
              <a href="my_attendance.php" class="btn btn-outline-info btn-block py-3">
                <i class="fas fa-clock fa-lg d-block mb-1"></i>
                <span style="font-size:12px;">Attendance</span>
              </a>
            </div>
            <div class="col-6 col-sm-4 col-md-3 mb-3">
              <a href="my_payslips.php" class="btn btn-outline-secondary btn-block py-3">
                <i class="fas fa-file-invoice fa-lg d-block mb-1"></i>
                <span style="font-size:12px;">Payslips</span>
              </a>
            </div>
            <div class="col-6 col-sm-4 col-md-3 mb-3">
              <a href="my_requests.php" class="btn btn-outline-primary btn-block py-3">
                <i class="fas fa-file-alt fa-lg d-block mb-1"></i>
                <span style="font-size:12px;">New Request</span>
              </a>
            </div>
            <div class="col-6 col-sm-4 col-md-3 mb-3">
              <a href="my_schedule.php" class="btn btn-outline-success btn-block py-3">
                <i class="fas fa-calendar-check fa-lg d-block mb-1"></i>
                <span style="font-size:12px;">My Schedule</span>
              </a>
            </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Announcements -->
      <div class="col-md-4">
        <div class="card card-outline" style="border-top:3px solid #343a40;">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="fas fa-bullhorn mr-2" style="color:#343a40;"></i>Announcements</h3>
          </div>
          <div class="card-body p-2">
            <?php if (empty($announcements)): ?>
              <div class="text-center text-muted py-4" style="font-size:13px;">
                <i class="fas fa-bell-slash fa-2x d-block mb-2" style="opacity:.2;"></i>
                No announcements at this time.
              </div>
            <?php else: ?>
              <?php foreach ($announcements as $ann):
                $pc = isset($priority_colors[$ann['priority']]) ? $priority_colors[$ann['priority']] : 'secondary';
              ?>
              <div class="ann-card" style="border-color:var(--<?= $pc ?>);">
                <div class="d-flex justify-content-between align-items-start">
                  <strong style="font-size:13px;"><?= htmlspecialchars($ann['title']) ?></strong>
                  <span class="badge badge-<?= $pc ?> ml-1" style="font-size:9px;"><?= $ann['priority'] ?></span>
                </div>
                <div style="font-size:12px;color:#555;margin-top:3px;"><?= nl2br(htmlspecialchars(substr($ann['body'],0,120))) ?><?= strlen($ann['body'])>120?'...':'' ?></div>
                <div style="font-size:10px;color:#adb5bd;margin-top:4px;">
                  <?= date('M d, Y', strtotime($ann['created_at'])) ?>
                  <?php if ($ann['posted_by_name']): ?> &middot; <?= htmlspecialchars($ann['posted_by_name']) ?><?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
var _delta = <?php echo (int)$_hrms_delta; ?>;
(function tick(){
    var d=new Date(Date.now()+_delta*1000);
    var h=String(d.getHours()).padStart(2,'0');
    var m=String(d.getMinutes()).padStart(2,'0');
    var s=String(d.getSeconds()).padStart(2,'0');
    var days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var months=['January','February','March','April','May','June','July','August','September','October','November','December'];
    var ec=document.getElementById('dashClock');
    var ed=document.getElementById('dashDate');
    if(ec)ec.textContent=h+':'+m+':'+s;
    if(ed)ed.textContent=days[d.getDay()]+', '+months[d.getMonth()]+' '+d.getDate()+', '+d.getFullYear();
    setTimeout(tick,1000);
})();
</script>
</body>
</html>