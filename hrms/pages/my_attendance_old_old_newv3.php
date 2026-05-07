<?php
// ============================================
// FILE: pages/my_attendance.php
// 2 tabs: View Attendance | Clock In/Out
// Uses get_linked_employee() from roles.php correctly
// PHP 5.6 safe — zero ?? operators
// No dt-export — no footer.php DataTable conflict
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// ── Resolve employee record ─────────────────────────────────────
$emp_row = get_linked_employee($pdo);

// Admin/superadmin with no employee record → show info message
if ($emp_row === null) {
    ?><!DOCTYPE html>
<html lang="en"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Attendance | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head><body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
<div class="content-wrapper"><div class="content-header"><div class="container-fluid">
  <h1 class="m-0">My Attendance</h1></div></div>
<div class="content"><div class="container-fluid mt-3">
  <div class="alert alert-info">
    <i class="fas fa-info-circle mr-2"></i>
    Admin accounts are not linked to employee records.
    Use <a href="attendance.php">Attendance Log</a> to view all employee attendance.
  </div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
</div></body></html><?php
    exit;
}

// Not linked to any employee record
if ($emp_row === false) {
    ?><!DOCTYPE html>
<html lang="en"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Attendance | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head><body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
<div class="content-wrapper"><div class="content-header"><div class="container-fluid">
  <h1 class="m-0">My Attendance</h1></div></div>
<div class="content"><div class="container-fluid mt-3">
  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle mr-2"></i>
    Your account is not linked to an employee record. Please contact HR.
  </div>
</div></div></div>
<?php include '../includes/footer.php'; ?>
</div></body></html><?php
    exit;
}

// ── We have a valid employee row ────────────────────────────────
$emp_id   = (int)$emp_row['id'];
$emp_name = isset($emp_row['full_name'])   ? $emp_row['full_name']   : '';
$emp_code = isset($emp_row['employee_id']) ? $emp_row['employee_id'] : '';

// ── Month/year filter ───────────────────────────────────────────
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('m');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

// ── Attendance records ──────────────────────────────────────────
$records = db_fetchall($pdo,
    "SELECT * FROM attendance
     WHERE employee_id = ?
       AND MONTH(attendance_date) = ?
       AND YEAR(attendance_date)  = ?
     ORDER BY attendance_date DESC",
    array($emp_id, $month, $year)
);

// ── Monthly summary ─────────────────────────────────────────────
$summary = db_fetch($pdo,
    "SELECT
        SUM(status='Present')  AS present,
        SUM(status='Absent')   AS absent,
        SUM(status='Late')     AS late,
        SUM(status='Half-day') AS halfday,
        SUM(tardiness_minutes) AS total_tardiness,
        SUM(overtime_hours)    AS total_overtime
     FROM attendance
     WHERE employee_id = ?
       AND MONTH(attendance_date) = ?
       AND YEAR(attendance_date)  = ?",
    array($emp_id, $month, $year)
);

// ── Safely extract summary — no ?? operator ─────────────────────
$s_present   = ($summary && isset($summary['present']))         ? (int)$summary['present']         : 0;
$s_absent    = ($summary && isset($summary['absent']))          ? (int)$summary['absent']          : 0;
$s_late      = ($summary && isset($summary['late']))            ? (int)$summary['late']            : 0;
$s_halfday   = ($summary && isset($summary['halfday']))         ? (int)$summary['halfday']         : 0;
$s_tardiness = ($summary && isset($summary['total_tardiness'])) ? (int)$summary['total_tardiness'] : 0;
$s_overtime  = ($summary && isset($summary['total_overtime']))  ? (float)$summary['total_overtime']: 0;

// ── Today's attendance ──────────────────────────────────────────
$today_att = db_fetch($pdo,
    "SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()",
    array($emp_id)
);

// Safely extract today values — no ?? operator
$t_time_in  = '';
$t_time_out = '';
$t_tardy    = 0;
$t_ot       = 0;
if ($today_att) {
    if (isset($today_att['time_in'])  && $today_att['time_in']  && $today_att['time_in']  !== '00:00:00') $t_time_in  = $today_att['time_in'];
    if (isset($today_att['time_out']) && $today_att['time_out'] && $today_att['time_out'] !== '00:00:00') $t_time_out = $today_att['time_out'];
    if (isset($today_att['tardiness_minutes'])) $t_tardy = (int)$today_att['tardiness_minutes'];
    if (isset($today_att['overtime_hours']))    $t_ot    = (float)$today_att['overtime_hours'];
}

// ── Today's clock log ───────────────────────────────────────────
$today_logs = array();
try {
    $today_logs = db_fetchall($pdo,
        "SELECT * FROM mobile_attendance_log
         WHERE employee_id = ? AND DATE(log_datetime) = CURDATE()
         ORDER BY log_datetime ASC",
        array($emp_id)
    );
} catch (Exception $e) {
    $today_logs = array();
}

// ── Clock counts ────────────────────────────────────────────────
$cnt_in  = 0;
$cnt_out = 0;
foreach ($today_logs as $lg) {
    if (isset($lg['action']) && $lg['action'] === 'Clock In')  $cnt_in++;
    if (isset($lg['action']) && $lg['action'] === 'Clock Out') $cnt_out++;
}

// ── Button states ───────────────────────────────────────────────
$can_am_in  = !$today_att || !$t_time_in;
$can_am_out = ($today_att && $t_time_in && !$t_time_out);
$can_pm_in  = (!$can_am_in && !$can_am_out && $cnt_in <= $cnt_out);
$can_pm_out = (!$can_am_in && !$can_am_out && $cnt_in > $cnt_out);

// ── QR code ─────────────────────────────────────────────────────
$my_qr_token = '';
try {
    $qr_row = db_fetch($pdo,
        "SELECT qr_token FROM employee_qr_codes WHERE employee_id = ? AND is_active = 1 LIMIT 1",
        array($emp_id)
    );
    if ($qr_row && isset($qr_row['qr_token'])) $my_qr_token = $qr_row['qr_token'];
} catch (Exception $e) {
    $my_qr_token = '';
}

// ── CSRF token ──────────────────────────────────────────────────
$csrf_tok = csrf_token();

// ── Helpers ─────────────────────────────────────────────────────
$months_list = array(
    1=>'January',  2=>'February', 3=>'March',    4=>'April',
    5=>'May',      6=>'June',     7=>'July',      8=>'August',
    9=>'September',10=>'October', 11=>'November', 12=>'December'
);
$stat_badge = array(
    'Present'=>'success','Absent'=>'danger','Late'=>'warning',
    'Half-day'=>'info','On leave'=>'primary','Holiday'=>'secondary','Rest day'=>'dark'
);
$color_map = array(
    'Present'=>'#28a745','Absent'=>'#dc3545','Late'=>'#ffc107',
    'Half-day'=>'#17a2b8','On leave'=>'#007bff','Holiday'=>'#6c757d','Rest day'=>'#343a40'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Attendance | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
  .att-tab-bar{background:#fff;border-bottom:2px solid #e9ecef;padding:0 16px;}
  .att-tab-bar .nav-link{color:#6c757d;font-size:13px;font-weight:600;padding:12px 20px;
    border:none;border-radius:0;border-bottom:3px solid transparent;}
  .att-tab-bar .nav-link.active{color:#1a73e8;border-bottom-color:#1a73e8;background:none;}
  .att-tab-bar .nav-link:hover{color:#1a73e8;}
  .clock-tile{background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:14px;
    color:#fff;padding:24px 20px;text-align:center;}
  .ct-time{font-size:44px;font-weight:200;letter-spacing:4px;line-height:1.1;}
  .ct-date{font-size:12px;opacity:.6;margin-top:5px;}
  .ct-emp{font-size:11px;opacity:.4;margin-top:3px;}
  .ts-row{display:flex;gap:8px;margin-top:14px;}
  .ts-box{flex:1;background:rgba(255,255,255,.08);border-radius:10px;padding:10px 4px;}
  .ts-lbl{font-size:9px;text-transform:uppercase;opacity:.55;}
  .ts-val{font-size:16px;font-weight:700;margin-top:3px;}
  .ts-sub{font-size:10px;margin-top:2px;}
  .btn-clk{width:100%;border:0;font-weight:700;font-size:14px;padding:12px;
    border-radius:12px;cursor:pointer;color:#fff;margin-bottom:0;}
  .btn-clk:disabled{cursor:not-allowed;opacity:.45;}
  .bc-am-in{background:#00c853;} .bc-am-out{background:#f44336;}
  .bc-pm-in{background:#0288d1;} .bc-pm-out{background:#7b1fa2;}
  .cam-wrap{position:relative;background:#111;border-radius:12px;
    overflow:hidden;width:100%;padding-bottom:75%;margin-bottom:12px;}
  .cam-inner{position:absolute;top:0;left:0;right:0;bottom:0;display:flex;
    flex-direction:column;align-items:center;justify-content:center;
    color:#fff;font-size:12px;text-align:center;padding:14px;}
  .geo-box{background:#f8f9fa;border-radius:10px;padding:10px 14px;font-size:12px;}
  .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;text-align:center;}
  .cal-hdr{font-size:11px;font-weight:700;color:#6c757d;padding:4px 0;}
  .cal-day{border-radius:6px;padding:5px 2px;font-size:12px;line-height:1.3;}
  .lg-in{background:#d4edda;color:#155724;border-radius:6px;padding:2px 8px;
    font-size:11px;display:inline-block;}
  .lg-out{background:#f8d7da;color:#721c24;border-radius:6px;padding:2px 8px;
    font-size:11px;display:inline-block;}
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">My Attendance</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">My Attendance</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">

    <!-- TAB BAR -->
    <div class="card mb-0" style="border-radius:10px 10px 0 0;border-bottom:0;">
      <div class="card-body p-0">
        <div class="att-tab-bar">
          <ul class="nav" id="attTabs">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="tab" href="#pane-view">
                <i class="fas fa-calendar-alt mr-1"></i> View Attendance
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="tab" href="#pane-clock" id="clockTabLink">
                <i class="fas fa-clock mr-1"></i> Clock In / Out
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div class="tab-content" style="background:#f4f6f9;padding:20px 0 30px;">

      <!-- ════ PANE 1: VIEW ATTENDANCE ════ -->
      <div class="tab-pane fade show active" id="pane-view">
        <div class="container-fluid">

          <!-- Month/Year filter -->
          <div class="card mb-3">
            <div class="card-body py-2">
              <form method="GET" action="" class="form-inline" style="gap:10px;">
                <label class="font-weight-bold mr-1" style="font-size:13px;">Month:</label>
                <select name="month" class="form-control form-control-sm" onchange="this.form.submit()">
                  <?php foreach ($months_list as $mn=>$ml): ?>
                    <option value="<?= $mn ?>" <?= $mn===$month?'selected':'' ?>><?= $ml ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                  <?php for ($y=(int)date('Y'); $y>=(int)date('Y')-3; $y--): ?>
                    <option <?= $y===$year?'selected':'' ?>><?= $y ?></option>
                  <?php endfor; ?>
                </select>
                <span class="text-muted ml-1" style="font-size:12px;"><?= count($records) ?> records</span>
                <button type="button" class="btn btn-sm btn-success ml-auto"
                        onclick="$('#clockTabLink').tab('show')">
                  <i class="fas fa-clock mr-1"></i> Clock In/Out
                </button>
              </form>
            </div>
          </div>

          <!-- Summary cards -->
          <div class="row mb-3">
            <?php
            $cards = array(
              array('Present',         $s_present,                    'success'),
              array('Absent',          $s_absent,                     'danger'),
              array('Late',            $s_late,                       'warning'),
              array('Half-day',        $s_halfday,                    'info'),
              array('Tardiness (min)', $s_tardiness,                  'secondary'),
              array('Overtime (hrs)',  number_format($s_overtime, 2), 'primary'),
            );
            foreach ($cards as $c): ?>
            <div class="col-6 col-md-2 mb-2">
              <div class="card mb-0 text-center">
                <div class="card-body py-2 px-1">
                  <div style="font-size:22px;font-weight:700;"><?= $c[1] ?></div>
                  <div style="font-size:11px;color:#6c757d;"><?= $c[0] ?></div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Calendar -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title"><?= $months_list[$month] ?> <?= $year ?> — Attendance Overview</h3>
            </div>
            <div class="card-body">
              <?php
              $date_map  = array();
              foreach ($records as $r) $date_map[$r['attendance_date']] = $r;
              $days_in   = (int)date('t', mktime(0,0,0,$month,1,$year));
              $first_dow = (int)date('w', mktime(0,0,0,$month,1,$year));
              ?>
              <div class="cal-grid">
                <?php foreach (array('Sun','Mon','Tue','Wed','Thu','Fri','Sat') as $dh): ?>
                  <div class="cal-hdr"><?= $dh ?></div>
                <?php endforeach; ?>
                <?php for ($i=0; $i<$first_dow; $i++): ?><div></div><?php endfor; ?>
                <?php for ($day=1; $day<=$days_in; $day++):
                  $ds   = sprintf('%04d-%02d-%02d', $year, $month, $day);
                  $rec  = isset($date_map[$ds]) ? $date_map[$ds] : null;
                  $r_st = ($rec && isset($rec['status'])) ? $rec['status'] : '';
                  $bg   = ($rec && isset($color_map[$r_st])) ? $color_map[$r_st] : '#f8f9fa';
                  $fc   = $rec ? '#fff' : '#adb5bd';
                  $itd  = ($ds === date('Y-m-d'));
                  $r_tm = ($rec && isset($rec['tardiness_minutes'])) ? (int)$rec['tardiness_minutes'] : 0;
                ?>
                <div class="cal-day"
                     title="<?= $r_st ? htmlspecialchars($r_st) : $ds ?>"
                     style="background:<?= $bg ?>;color:<?= $fc ?>;<?= $itd?'border:2px solid #007bff;font-weight:800;':'' ?>">
                  <?= $day ?>
                  <?php if ($r_tm > 0): ?>
                    <div style="font-size:9px;opacity:.8;"><?= $r_tm ?>m</div>
                  <?php endif; ?>
                </div>
                <?php endfor; ?>
              </div>
              <div class="d-flex flex-wrap mt-3" style="gap:10px;">
                <?php foreach ($color_map as $lbl=>$col): ?>
                  <div style="display:flex;align-items:center;gap:4px;font-size:12px;">
                    <div style="width:12px;height:12px;border-radius:3px;background:<?= $col ?>;"></div>
                    <?= $lbl ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Daily Time Log — NO .dt-export class (footer.php conflict) -->
          <div class="card">
            <div class="card-header"><h3 class="card-title">Daily Time Log</h3></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover mb-0" id="myAttTbl">
                  <thead class="thead-light">
                    <tr>
                      <th>Date</th><th>Day</th><th>Time In</th><th>Time Out</th>
                      <th>Status</th><th>Tardiness</th><th>Overtime</th><th>Remarks</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($records)):
                      foreach ($records as $r):
                        $r_st  = isset($r['status'])            ? $r['status']            : '';
                        $r_rem = isset($r['remarks'])           ? $r['remarks']           : '';
                        $r_tm  = isset($r['tardiness_minutes']) ? (int)$r['tardiness_minutes']  : 0;
                        $r_ot  = isset($r['overtime_hours'])    ? (float)$r['overtime_hours']   : 0;
                        $sb    = isset($stat_badge[$r_st])      ? $stat_badge[$r_st]      : 'secondary';
                        $ti    = (isset($r['time_in'])  && $r['time_in']  && $r['time_in']  !== '00:00:00') ? date('h:i A',strtotime($r['time_in']))  : '—';
                        $to    = (isset($r['time_out']) && $r['time_out'] && $r['time_out'] !== '00:00:00') ? date('h:i A',strtotime($r['time_out'])) : '—';
                    ?>
                    <tr>
                      <td><?= date('M d, Y', strtotime($r['attendance_date'])) ?></td>
                      <td><?= date('D',       strtotime($r['attendance_date'])) ?></td>
                      <td><?= $ti ?></td>
                      <td><?= $to ?></td>
                      <td><span class="badge badge-<?= $sb ?>"><?= htmlspecialchars($r_st) ?></span></td>
                      <td><?= $r_tm > 0 ? $r_tm.' min' : '—' ?></td>
                      <td><?= $r_ot > 0 ? $r_ot.' hrs' : '—' ?></td>
                      <td><?= htmlspecialchars($r_rem ? $r_rem : '—') ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                      <td colspan="8" class="text-center text-muted py-4">
                        <i class="fas fa-calendar-times fa-2x d-block mb-2" style="opacity:.2;"></i>
                        No records for <?= $months_list[$month] ?> <?= $year ?>.
                      </td>
                    </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      </div><!-- /pane-view -->

      <!-- ════ PANE 2: CLOCK IN/OUT ════ -->
      <div class="tab-pane fade" id="pane-clock">
        <div class="container-fluid">
          <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">

              <!-- Live clock tile -->
              <div class="clock-tile mb-3">
                <div class="ct-time" id="ctTime"><?= date('h:i:s A') ?></div>
                <div class="ct-date"><?= date('l, F d, Y') ?></div>
                <div class="ct-emp"><?= htmlspecialchars($emp_name) ?> &middot; <?= htmlspecialchars($emp_code) ?></div>
                <div class="ts-row">
                  <div class="ts-box">
                    <div class="ts-lbl">Time In</div>
                    <div class="ts-val"><?= $t_time_in  ? date('h:i A',strtotime($t_time_in))  : '—' ?></div>
                    <?php if ($t_tardy > 0): ?>
                      <div class="ts-sub" style="color:#ff6b6b;"><?= $t_tardy ?> min late</div>
                    <?php endif; ?>
                  </div>
                  <div class="ts-box">
                    <div class="ts-lbl">Time Out</div>
                    <div class="ts-val"><?= $t_time_out ? date('h:i A',strtotime($t_time_out)) : '—' ?></div>
                  </div>
                  <div class="ts-box">
                    <div class="ts-lbl">Clocks</div>
                    <div class="ts-val"><?= $cnt_in + $cnt_out ?></div>
                    <div class="ts-sub"><?= $cnt_in ?>in / <?= $cnt_out ?>out</div>
                  </div>
                  <div class="ts-box">
                    <div class="ts-lbl">OT</div>
                    <div class="ts-val"><?= $t_ot > 0 ? $t_ot.'h' : '—' ?></div>
                  </div>
                </div>
              </div>

              <!-- Today's clock log -->
              <?php if (!empty($today_logs)): ?>
              <div class="card mb-3">
                <div class="card-header" style="padding:8px 14px;">
                  <h3 class="card-title" style="font-size:13px;">
                    <i class="fas fa-list-ul mr-1"></i> Today's Clock Log
                  </h3>
                </div>
                <div class="card-body p-0">
                  <table class="table table-sm mb-0">
                    <tbody>
                      <?php foreach ($today_logs as $lg):
                        $lg_act   = isset($lg['action'])       ? $lg['action']       : '';
                        $lg_photo = isset($lg['photo_path'])   ? $lg['photo_path']   : '';
                        $lg_lat   = isset($lg['latitude'])     ? $lg['latitude']     : '';
                        $lg_lng   = isset($lg['longitude'])    ? $lg['longitude']    : '';
                        $lg_time  = isset($lg['log_datetime']) ? $lg['log_datetime'] : '';
                      ?>
                      <tr>
                        <td width="65">
                          <?php if ($lg_act === 'Clock In'): ?>
                            <span class="lg-in"><i class="fas fa-sign-in-alt mr-1"></i>In</span>
                          <?php else: ?>
                            <span class="lg-out"><i class="fas fa-sign-out-alt mr-1"></i>Out</span>
                          <?php endif; ?>
                        </td>
                        <td style="font-weight:600;font-size:13px;">
                          <?= $lg_time ? date('h:i A', strtotime($lg_time)) : '—' ?>
                        </td>
                        <td style="font-size:11px;color:#6c757d;">
                          <?= $lg_time ? date('D, M d', strtotime($lg_time)) : '' ?>
                        </td>
                        <td width="34">
                          <?php if ($lg_photo): ?>
                            <a href="../uploads/mobile_selfie/<?= htmlspecialchars($lg_photo) ?>" target="_blank">
                              <img src="../uploads/mobile_selfie/<?= htmlspecialchars($lg_photo) ?>"
                                   style="width:26px;height:26px;object-fit:cover;border-radius:4px;">
                            </a>
                          <?php endif; ?>
                        </td>
                        <td width="30">
                          <?php if ($lg_lat): ?>
                            <a href="https://maps.google.com/?q=<?= $lg_lat ?>,<?= $lg_lng ?>"
                               target="_blank">
                              <i class="fas fa-map-marker-alt text-danger"></i>
                            </a>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <?php endif; ?>

              <!-- Camera card -->
              <div class="card mb-3">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-camera mr-1"></i> Selfie Clock</h3>
                </div>
                <div class="card-body">

                  <!-- Camera error -->
                  <div id="camErrBox" class="alert alert-warning" style="display:none;font-size:12px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Camera unavailable:</strong> <span id="camErrMsg"></span><br>
                    <small>
                      You can still clock in/out without a selfie.<br>
                      &bull; Desktop: click camera icon in address bar &rarr; Allow &rarr; Refresh.<br>
                      &bull; Mobile: Settings &rarr; Browser &rarr; Permissions &rarr; Camera &rarr; Allow.<br>
                      &bull; Camera requires HTTPS. On HTTP it is blocked by the browser.
                    </small>
                  </div>

                  <!-- Camera preview -->
                  <div class="cam-wrap" id="camWrap">
                    <div class="cam-inner" id="camInner">
                      <video id="camVideo" autoplay playsinline muted
                             style="position:absolute;top:0;left:0;width:100%;height:100%;
                                    object-fit:cover;display:none;"></video>
                      <canvas id="camCanvas" style="display:none;"></canvas>
                      <i class="fas fa-camera fa-2x mb-2" style="opacity:.25;"></i>
                      <div id="camStatus" style="font-size:12px;">Initializing camera...</div>
                    </div>
                  </div>

                  <img id="photoPreview" src="" alt="Selfie"
                       style="display:none;width:100%;max-height:180px;object-fit:cover;
                              border-radius:10px;border:2px solid #28a745;margin-bottom:10px;">

                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <div id="camNote" style="font-size:11px;color:#6c757d;">
                      Selfie captured automatically on clock action.
                    </div>
                    <button type="button" id="retakeBtn"
                            class="btn btn-xs btn-outline-secondary ml-2"
                            style="display:none;" onclick="retakePhoto()">
                      <i class="fas fa-redo mr-1"></i> Retake
                    </button>
                  </div>

                  <!-- Geolocation -->
                  <div class="geo-box mb-3">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                      <span><i class="fas fa-map-marker-alt mr-1 text-danger"></i> Location</span>
                      <button type="button" class="btn btn-xs btn-outline-secondary" onclick="getGeo()">
                        <i class="fas fa-crosshairs mr-1"></i> Get location
                      </button>
                    </div>
                    <div id="geoStatus" style="color:#6c757d;margin-top:4px;">Not yet captured.</div>
                    <div id="geoCoords" style="font-size:11px;color:#aaa;"></div>
                  </div>

                  <div id="clockResult" class="alert" style="display:none;font-size:13px;"></div>
                  <div id="clockSpinner"
                       style="display:none;text-align:center;padding:10px;font-size:13px;color:#555;">
                    <i class="fas fa-spinner fa-spin mr-1"></i> Processing...
                  </div>

                  <!-- 4 clock buttons -->
                  <div class="row mb-2">
                    <div class="col-6" style="padding-right:4px;">
                      <button type="button" class="btn-clk bc-am-in" id="btnAMIn"
                              <?= $can_am_in  ? '' : 'disabled' ?>
                              onclick="doClockAction('in')">
                        <i class="fas fa-sign-in-alt mr-1"></i> AM Clock In
                      </button>
                    </div>
                    <div class="col-6" style="padding-left:4px;">
                      <button type="button" class="btn-clk bc-am-out" id="btnAMOut"
                              <?= $can_am_out ? '' : 'disabled' ?>
                              onclick="doClockAction('out')">
                        <i class="fas fa-sign-out-alt mr-1"></i> AM Clock Out
                      </button>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-6" style="padding-right:4px;">
                      <button type="button" class="btn-clk bc-pm-in" id="btnPMIn"
                              <?= $can_pm_in  ? '' : 'disabled' ?>
                              onclick="doClockAction('pm_in')">
                        <i class="fas fa-sign-in-alt mr-1"></i> PM Clock In
                      </button>
                    </div>
                    <div class="col-6" style="padding-left:4px;">
                      <button type="button" class="btn-clk bc-pm-out" id="btnPMOut"
                              <?= $can_pm_out ? '' : 'disabled' ?>
                              onclick="doClockAction('out')">
                        <i class="fas fa-sign-out-alt mr-1"></i> PM Clock Out
                      </button>
                    </div>
                  </div>

                  <div style="background:#e8f5e9;border-radius:8px;padding:8px 10px;
                              font-size:11px;color:#2e7d32;margin-top:10px;">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>AM In</strong> = morning start &nbsp;|&nbsp;
                    <strong>AM Out</strong> = lunch break &nbsp;|&nbsp;
                    <strong>PM In</strong> = after lunch, must be before 1:00 PM &nbsp;|&nbsp;
                    <strong>PM Out</strong> = end of shift.
                  </div>

                </div>
              </div>

              <!-- QR alternative -->
              <?php if ($my_qr_token):
                $qr_data = urlencode('http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/qr_scanner.php?token='.$my_qr_token);
                $qr_url  = 'https://chart.googleapis.com/chart?chs=130x130&cht=qr&chl='.$qr_data.'&choe=UTF-8';
              ?>
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-qrcode mr-1"></i> QR Code Alternative</h3>
                </div>
                <div class="card-body text-center">
                  <img src="<?= $qr_url ?>" style="width:110px;height:110px;" alt="QR">
                  <div style="font-size:12px;color:#6c757d;margin-top:6px;">Scan at kiosk to clock in/out.</div>
                </div>
              </div>
              <?php endif; ?>

            </div>
          </div>
        </div>
      </div><!-- /pane-clock -->

    </div><!-- /tab-content -->
  </div></div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
var _csrf = '<?= addslashes($csrf_tok) ?>';

/* Live clock */
function tickClock() {
    var d=new Date(), h=d.getHours(), m=d.getMinutes(), s=d.getSeconds();
    var ap=h>=12?'PM':'AM'; h=h%12; if(h===0)h=12;
    var el=document.getElementById('ctTime');
    if(el) el.textContent=zp(h)+':'+zp(m)+':'+zp(s)+' '+ap;
    setTimeout(tickClock,1000);
}
function zp(n){return n<10?'0'+n:''+n;}
tickClock();

/* Camera */
var camStream=null, capturedB64=null;
var geoLat=null, geoLng=null, geoAccuracy=null, geoAddr=null;

function initCamera() {
    var video=document.getElementById('camVideo');
    var status=document.getElementById('camStatus');
    var camNote=document.getElementById('camNote');
    var errBox=document.getElementById('camErrBox');
    var errMsg=document.getElementById('camErrMsg');

    if(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false})
            .then(function(s){
                camStream=s; video.srcObject=s; video.style.display='block';
                if(status) status.style.display='none';
                if(errBox) errBox.style.display='none';
                if(camNote) camNote.textContent='Camera ready. Selfie taken on clock action.';
            })
            .catch(function(err){showCamErr(err);});
        return;
    }
    var gum=navigator.getUserMedia||navigator.webkitGetUserMedia||navigator.mozGetUserMedia;
    if(gum){
        gum.call(navigator,{video:true},
            function(s){
                camStream=s;
                try{video.srcObject=s;}catch(e){video.src=URL.createObjectURL(s);}
                video.style.display='block';
                if(status) status.style.display='none';
                if(errBox) errBox.style.display='none';
                if(camNote) camNote.textContent='Camera ready.';
            },
            function(err){showCamErr(err);}
        );
        return;
    }
    showCamErr({name:'NotSupportedError'});
}

function showCamErr(err) {
    var errBox=document.getElementById('camErrBox');
    var errMsg=document.getElementById('camErrMsg');
    var status=document.getElementById('camStatus');
    var camNote=document.getElementById('camNote');
    var msg='';
    if(err.name==='NotAllowedError'||err.name==='PermissionDeniedError') msg='Permission denied.';
    else if(err.name==='NotFoundError'||err.name==='DevicesNotFoundError') msg='No camera found.';
    else if(err.name==='NotReadableError') msg='Camera in use by another app.';
    else if(location.protocol==='http:') msg='Camera requires HTTPS. System is on HTTP.';
    else msg=err.message||err.name||'Unknown error.';
    if(errMsg) errMsg.textContent=' '+msg;
    if(errBox) errBox.style.display='block';
    if(status) status.textContent='Camera unavailable';
    if(camNote) camNote.style.display='none';
}

function capturePhoto() {
    var video=document.getElementById('camVideo');
    var canvas=document.getElementById('camCanvas');
    if(!camStream) return null;
    canvas.width=video.videoWidth||480; canvas.height=video.videoHeight||360;
    var ctx=canvas.getContext('2d');
    ctx.drawImage(video,0,0);
    ctx.fillStyle='rgba(0,0,0,.5)';
    ctx.fillRect(0,canvas.height-24,canvas.width,24);
    ctx.fillStyle='#fff'; ctx.font='10px monospace';
    ctx.fillText(new Date().toLocaleString(),6,canvas.height-8);
    capturedB64=canvas.toDataURL('image/jpeg',0.75);
    var prev=document.getElementById('photoPreview');
    if(prev){prev.src=capturedB64;prev.style.display='block';}
    if(video) video.style.display='none';
    var rb=document.getElementById('retakeBtn');
    if(rb) rb.style.display='inline-block';
    return capturedB64;
}

function retakePhoto() {
    capturedB64=null;
    var prev=document.getElementById('photoPreview');
    var video=document.getElementById('camVideo');
    var rb=document.getElementById('retakeBtn');
    if(prev) prev.style.display='none';
    if(video) video.style.display='block';
    if(rb) rb.style.display='none';
}

function getGeo() {
    var el=document.getElementById('geoStatus');
    if(!el) return;
    el.textContent='Getting location...';
    if(!navigator.geolocation){el.textContent='Geolocation not supported.';return;}
    navigator.geolocation.getCurrentPosition(
        function(pos){
            geoLat=pos.coords.latitude; geoLng=pos.coords.longitude; geoAccuracy=pos.coords.accuracy;
            el.innerHTML='<i class="fas fa-check-circle text-success mr-1"></i>Location captured.';
            var co=document.getElementById('geoCoords');
            if(co) co.textContent=geoLat.toFixed(5)+', '+geoLng.toFixed(5)+' (+-'+Math.round(geoAccuracy)+'m)';
            fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+geoLat+'&lon='+geoLng)
                .then(function(r){return r.json();})
                .then(function(d){geoAddr=d.display_name||'';})
                .catch(function(){});
        },
        function(err){el.textContent='Location error: '+err.message;},
        {enableHighAccuracy:true,timeout:12000}
    );
}

var _btnIds=['btnAMIn','btnAMOut','btnPMIn','btnPMOut'];
function setBtns(disabled) {
    for(var i=0;i<_btnIds.length;i++){
        var b=document.getElementById(_btnIds[i]);
        if(b) b.disabled=disabled;
    }
}

function doClockAction(action) {
    if(!capturedB64 && camStream) capturePhoto();
    setBtns(true);
    var sp=document.getElementById('clockSpinner');
    var res=document.getElementById('clockResult');
    if(sp) sp.style.display='block';
    if(res) res.style.display='none';

    var fd=new FormData();
    fd.append('ajax_clock','1');
    fd.append('action',action);
    fd.append('csrf_token',_csrf);
    if(capturedB64) fd.append('photo_b64',capturedB64.replace(/^data:image\/\w+;base64,/,''));
    if(geoLat)      fd.append('lat',geoLat);
    if(geoLng)      fd.append('lng',geoLng);
    if(geoAccuracy) fd.append('accuracy',geoAccuracy);
    if(geoAddr)     fd.append('address',geoAddr);
    fd.append('device',navigator.userAgent.substring(0,200));

    fetch('attendance_selfservice.php',{method:'POST',body:fd})
        .then(function(r){
            var ct=r.headers.get('content-type')||'';
            if(ct.indexOf('json')===-1){
                return r.text().then(function(t){throw new Error('Server error: '+t.substring(0,200));});
            }
            return r.json();
        })
        .then(function(data){
            if(sp) sp.style.display='none';
            if(res){
                res.style.display='block';
                res.className='alert '+(data.ok?'alert-success':'alert-danger');
                res.textContent=data.msg;
            }
            if(data.ok){setTimeout(function(){location.reload();},2000);}
            else{setBtns(false);}
        })
        .catch(function(e){
            if(sp) sp.style.display='none';
            if(res){res.style.display='block';res.className='alert alert-danger';res.textContent='Error: '+e.message;}
            setBtns(false);
        });
}

$(function(){
    /* Init camera when Clock tab shown */
    $('a[href="#pane-clock"], #clockTabLink').on('shown.bs.tab',function(){
        if(!camStream){initCamera();getGeo();}
    });
    /* DataTable — dom:'lftip' = no Buttons, avoids footer.php global conflict */
    if(typeof $.fn.DataTable!=='undefined' && $('#myAttTbl').length){
        $('#myAttTbl').DataTable({pageLength:25,order:[[0,'desc']],dom:'lftip'});
    }
});
</script>
</body>
</html>