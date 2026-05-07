<?php
// ============================================
// FILE: pages/attendance_log.php
// Full attendance history with filters + export
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// Filters
$filter_emp   = isset($_GET['employee_id']) ? (int)$_GET['employee_id']             : 0;
$filter_month = isset($_GET['month'])       ? (int)$_GET['month']                   : (int)date('m');
$filter_year  = isset($_GET['year'])        ? (int)$_GET['year']                    : (int)date('Y');
$filter_status= isset($_GET['status'])      ? trim($_GET['status'])                 : '';

// Build WHERE
$where  = array("MONTH(a.attendance_date) = ?", "YEAR(a.attendance_date) = ?");
$params = array($filter_month, $filter_year);

if ($filter_emp) {
    $where[]  = "a.employee_id = ?";
    $params[] = $filter_emp;
}
if ($filter_status) {
    $where[]  = "a.status = ?";
    $params[] = $filter_status;
}

$where_sql = "WHERE " . implode(" AND ", $where);

$logs = db_fetchall($pdo,
    "SELECT a.*, e.full_name, e.employee_id AS emp_code,
            d.name AS dept_name
     FROM attendance a
     JOIN employees  e ON a.employee_id   = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     $where_sql
     ORDER BY a.attendance_date DESC, e.full_name",
    $params
);

// Summary for filtered period
$summary = db_fetch($pdo,
    "SELECT
        SUM(a.status='Present')  AS present,
        SUM(a.status='Absent')   AS absent,
        SUM(a.status='Late')     AS late,
        SUM(a.tardiness_minutes) AS tardiness,
        SUM(a.overtime_hours)    AS overtime
     FROM attendance a
     JOIN employees e ON a.employee_id = e.id
     $where_sql",
    $params
);

$employees = db_fetchall($pdo,
    "SELECT id, full_name, employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name"
);

$months = array(1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
                7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December');
$statuses = array('Present','Absent','Late','Half-day','On leave','Holiday','Rest day');
$stat_badge = array('Present'=>'success','Absent'=>'danger','Late'=>'warning',
                    'Half-day'=>'info','On leave'=>'primary','Holiday'=>'secondary','Rest day'=>'dark');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attendance Log | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">Attendance Log</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">Attendance log</li>
        </ol>
      </div>
    </div>
  </div></div>
  <div class="content"><div class="container-fluid">

    <!-- Filters -->
    <div class="card card-outline card-primary">
      <div class="card-header"><h3 class="card-title">Filter attendance records</h3></div>
      <div class="card-body">
        <form method="GET" action="" class="form-inline flex-wrap" style="gap:10px;">
          <select name="employee_id" class="form-control form-control-sm">
            <option value="0">All employees</option>
            <?php foreach ($employees as $e): ?>
              <option value="<?= $e['id'] ?>" <?= $filter_emp == $e['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($e['full_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select name="month" class="form-control form-control-sm">
            <?php foreach ($months as $mn => $ml): ?>
              <option value="<?= $mn ?>" <?= $mn === $filter_month ? 'selected' : '' ?>><?= $ml ?></option>
            <?php endforeach; ?>
          </select>
          <select name="year" class="form-control form-control-sm">
            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
              <option <?= $y === $filter_year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
          <select name="status" class="form-control form-control-sm">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $s): ?>
              <option <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="fas fa-filter mr-1"></i> Filter
          </button>
          <a href="attendance_log.php" class="btn btn-sm btn-secondary">Reset</a>
        </form>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="row mb-3">
      <?php
      $cards = array(
        array('Present',         (int)(isset($summary['present'])  ? $summary['present']  : 0), 'success'),
        array('Absent',          (int)(isset($summary['absent'])   ? $summary['absent']   : 0), 'danger'),
        array('Late',            (int)(isset($summary['late'])     ? $summary['late']     : 0), 'warning'),
        array('Tardiness (mins)',(int)(isset($summary['tardiness'])? $summary['tardiness']: 0), 'info'),
        array('Overtime (hrs)',  number_format((float)(isset($summary['overtime']) ? $summary['overtime'] : 0),2), 'primary'),
      );
      foreach ($cards as $card):
      ?>
      <div class="col-6 col-md-2">
        <div class="small-box bg-<?= $card[2] ?>" style="border-radius:8px;">
          <div class="inner" style="padding:10px 12px;">
            <h4 style="margin:0;font-size:20px;"><?= $card[1] ?></h4>
            <p style="font-size:11px;margin:0;"><?= $card[0] ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Log table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <?= $months[$filter_month] ?> <?= $filter_year ?>
          &mdash; <?= count($logs) ?> records
        </h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-bordered table-hover dt-export mb-0" id="logTable">
          <thead class="thead-light">
            <tr>
              <th>Date</th><th>Employee</th><th>Department</th>
              <th>AM In</th><th>AM Out</th>
              <th>PM In</th><th>PM Out</th>
              <th>Status</th><th>Tardiness</th><th>Overtime</th><th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $row):
              $sb  = isset($stat_badge[$row['status']]) ? $stat_badge[$row['status']] : 'secondary';
              $ti  = (!empty($row['time_in'])     && $row['time_in']     !== '00:00:00') ? date('h:i A', strtotime($row['time_in']))     : '—';
              $to  = (!empty($row['time_out'])    && $row['time_out']    !== '00:00:00') ? date('h:i A', strtotime($row['time_out']))    : '—';
              $pmi = (!empty($row['pm_time_in'])  && $row['pm_time_in']  !== '00:00:00') ? date('h:i A', strtotime($row['pm_time_in']))  : '—';
              $pmo = (!empty($row['pm_time_out']) && $row['pm_time_out'] !== '00:00:00') ? date('h:i A', strtotime($row['pm_time_out'])) : '—';
            ?>
            <tr>
              <td><?= date('M d, Y', strtotime($row['attendance_date'])) ?></td>
              <td>
                <a href="view_employee.php?id=<?= $row['employee_id'] ?>">
                  <?= htmlspecialchars($row['full_name']) ?>
                </a><br>
                <small class="text-muted"><?= htmlspecialchars($row['emp_code']) ?></small>
              </td>
              <td><?= htmlspecialchars($row['dept_name'] ? $row['dept_name'] : '—') ?></td>
              <td><?= $ti ?></td>
              <td><?= $to ?></td>
              <td><?= $pmi ?></td>
              <td><?= $pmo ?></td>
              <td><span class="badge badge-<?= $sb ?>"><?= $row['status'] ?></span></td>
              <td><?= $row['tardiness_minutes'] > 0 ? $row['tardiness_minutes'].' mins' : '—' ?></td>
              <td><?= $row['overtime_hours'] > 0 ? $row['overtime_hours'].' hrs' : '—' ?></td>
              <td><?= htmlspecialchars($row['remarks'] ? $row['remarks'] : '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>