<?php
// ============================================
// FILE: pages/my_attendance.php
// Employee self-service — view own attendance
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
enforce_page_role();

$user   = db_fetch($pdo, "SELECT * FROM users WHERE id=?", array((int)$_SESSION['user_id']));
$emp_id = $user ? (int)$user['employee_id'] : 0;

if (!$emp_id) { ?>
  <!DOCTYPE html><html><body>
  <div class="alert alert-warning m-4">Your account is not linked to an employee record.</div>
  </body></html>
<?php exit; }

$month  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year   = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

$records = db_fetchall($pdo,
    "SELECT * FROM attendance
     WHERE employee_id=?
       AND MONTH(attendance_date)=?
       AND YEAR(attendance_date)=?
     ORDER BY attendance_date DESC",
    array($emp_id, $month, $year)
);

$summary = db_fetch($pdo,
    "SELECT
        SUM(status='Present')  AS present,
        SUM(status='Absent')   AS absent,
        SUM(status='Late')     AS late,
        SUM(status='Half-day') AS halfday,
        SUM(tardiness_minutes) AS total_tardiness,
        SUM(overtime_hours)    AS total_overtime
     FROM attendance
     WHERE employee_id=?
       AND MONTH(attendance_date)=?
       AND YEAR(attendance_date)=?",
    array($emp_id, $month, $year)
);

$months_list = array(
    1=>'January',2=>'February',3=>'March',4=>'April',
    5=>'May',6=>'June',7=>'July',8=>'August',
    9=>'September',10=>'October',11=>'November',12=>'December'
);

$stat_badge = array(
    'Present'  => 'success', 'Absent'   => 'danger',
    'Late'     => 'warning', 'Half-day' => 'info',
    'On leave' => 'primary', 'Holiday'  => 'secondary',
    'Rest day' => 'dark'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Attendance | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">My Attendance</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active">My attendance</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="content"><div class="container-fluid">

    <!-- Filter -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="" class="form-inline" style="gap:10px;">
          <label class="mr-1">Month:</label>
          <select name="month" class="form-control form-control-sm" onchange="this.form.submit()">
            <?php foreach ($months_list as $mn => $ml): ?>
              <option value="<?= $mn ?>" <?= $mn===$month?'selected':'' ?>><?= $ml ?></option>
            <?php endforeach; ?>
          </select>
          <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
            <?php for ($y=(int)date('Y'); $y>=(int)date('Y')-2; $y--): ?>
              <option <?= $y===$year?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
          <a href="attendance_selfservice.php" class="btn btn-sm btn-success ml-auto">
            <i class="fas fa-clock mr-1"></i> Clock In / Out
          </a>
        </form>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="row mb-3">
      <?php
      $cards = array(
        array('Present',          (int)(isset($summary['present'])         ? $summary['present']         : 0),                  'success'),
        array('Absent',           (int)(isset($summary['absent'])          ? $summary['absent']          : 0),                   'danger'),
        array('Late',             (int)(isset($summary['late'])            ? $summary['late']            : 0),                     'warning'),
        array('Half-day',         (int)(isset($summary['halfday'])         ? $summary['halfday']         : 0),                  'info'),
        array('Tardiness (mins)', (int)(isset($summary['total_tardiness']) ? $summary['total_tardiness'] : 0),          'secondary'),
        array('Overtime (hrs)',   number_format((float)(isset($summary['total_overtime'])? $summary['total_overtime']  : 0),2),'primary'),
      );
      foreach ($cards as $c):
      ?>
      <div class="col-6 col-md-2 mb-2">
        <div class="card mb-0 text-center">
          <div class="card-body py-2">
            <div style="font-size:22px;font-weight:700;color:var(--<?= $c[2] ?>);"><?= $c[1] ?></div>
            <div style="font-size:11px;color:#6c757d;"><?= $c[0] ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Calendar-style monthly view -->
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">
          <?= $months_list[$month] ?> <?= $year ?> &mdash; attendance overview
        </h3>
      </div>
      <div class="card-body">
        <?php
        // Build a lookup of date => status
        $date_map = array();
        foreach ($records as $r) {
            $date_map[$r['attendance_date']] = $r;
        }

        $days_in_month = (int)date('t', mktime(0,0,0,$month,1,$year));
        $first_dow     = (int)date('w', mktime(0,0,0,$month,1,$year)); // 0=Sun

        $color_map = array(
            'Present'=>'#28a745','Absent'=>'#dc3545','Late'=>'#ffc107',
            'Half-day'=>'#17a2b8','On leave'=>'#007bff',
            'Holiday'=>'#6c757d','Rest day'=>'#343a40'
        );
        ?>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;">
          <?php foreach (array('Sun','Mon','Tue','Wed','Thu','Fri','Sat') as $d): ?>
            <div style="font-size:11px;font-weight:600;color:#6c757d;padding:4px 0;"><?= $d ?></div>
          <?php endforeach; ?>

          <?php
          // Empty cells before first day
          for ($i=0; $i<$first_dow; $i++): ?>
            <div></div>
          <?php endfor; ?>

          <?php for ($day=1; $day<=$days_in_month; $day++):
            $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $rec = isset($date_map[$date_str]) ? $date_map[$date_str] : null;
            $bg  = $rec ? (isset($color_map[$rec['status']]) ? $color_map[$rec['status']] : '#e9ecef') : '#f8f9fa';
            $fc  = $rec ? '#fff' : '#adb5bd';
            $title = $rec ? $rec['status'] : '';
            $is_today = ($date_str === date('Y-m-d'));
          ?>
            <div title="<?= $title ?>"
                 style="background:<?= $bg ?>;color:<?= $fc ?>;
                        border-radius:6px;padding:6px 2px;font-size:12px;
                        <?= $is_today ? 'border:2px solid #007bff;font-weight:700;' : '' ?>">
              <?= $day ?>
              <?php if ($rec && $rec['tardiness_minutes'] > 0): ?>
                <div style="font-size:9px;opacity:.85;"><?= $rec['tardiness_minutes'] ?>m</div>
              <?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>

        <!-- Legend -->
        <div class="d-flex flex-wrap mt-3" style="gap:10px;">
          <?php foreach ($color_map as $label => $color): ?>
            <div style="display:flex;align-items:center;gap:4px;font-size:12px;">
              <div style="width:12px;height:12px;border-radius:3px;background:<?= $color ?>;"></div>
              <?= $label ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Detail table -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Daily detail</h3></div>
      <div class="card-body" style="padding:16px 20px 0;">
        <table class="table table-sm table-bordered table-hover" id="attTable">
          <thead class="thead-light">
            <tr>
              <th>Date</th><th>Day</th>
              <th>AM In</th><th>AM Out</th>
              <th>PM In</th><th>PM Out</th>
              <th>Status</th><th>Tardiness</th><th>Overtime</th><th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r):
              $sb  = isset($stat_badge[$r['status']]) ? $stat_badge[$r['status']] : 'secondary';
              $ti  = (!empty($r['time_in'])     && $r['time_in']     !=='00:00:00') ? date('h:i A',strtotime($r['time_in']))     : '—';
              $to  = (!empty($r['time_out'])    && $r['time_out']    !=='00:00:00') ? date('h:i A',strtotime($r['time_out']))    : '—';
              $pmi = (!empty($r['pm_time_in'])  && $r['pm_time_in']  !=='00:00:00') ? date('h:i A',strtotime($r['pm_time_in']))  : '—';
              $pmo = (!empty($r['pm_time_out']) && $r['pm_time_out'] !=='00:00:00') ? date('h:i A',strtotime($r['pm_time_out'])) : '—';
            ?>
            <tr>
              <td><?= date('M d, Y', strtotime($r['attendance_date'])) ?></td>
              <td><?= date('l', strtotime($r['attendance_date'])) ?></td>
              <td><?= $ti ?></td>
              <td><?= $to ?></td>
              <td><?= $pmi ?></td>
              <td><?= $pmo ?></td>
              <td><span class="badge badge-<?= $sb ?>"><?= $r['status'] ?></span></td>
              <td><?= $r['tardiness_minutes']>0 ? $r['tardiness_minutes'].' mins' : '—' ?></td>
              <td><?= $r['overtime_hours']>0    ? $r['overtime_hours'].' hrs'   : '—' ?></td>
              <td><?= htmlspecialchars($r['remarks'] ? $r['remarks'] : '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($records)): ?>
              <tr><td colspan="10" class="text-center text-muted py-3">No attendance records for this period.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>