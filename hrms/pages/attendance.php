<?php
// ============================================
// FILE: pages/attendance.php
// Daily attendance logging
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$error = ''; $success = '';
$today = date('Y-m-d');
$selected_date = isset($_GET['date']) ? $_GET['date'] : $today;

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	csrf_verify();
    $emp_id  = (int)$_POST['employee_id'];
    $a_date  = $conn->real_escape_string($_POST['attendance_date']);
    $time_in = $conn->real_escape_string($_POST['time_in']);
    $time_out= $conn->real_escape_string($_POST['time_out']);
    $status  = $conn->real_escape_string($_POST['status']);
    $tardy   = (int)$_POST['tardiness_minutes'];
    $ot      = (float)$_POST['overtime_hours'];
    $remarks = $conn->real_escape_string($_POST['remarks']);

    // Upsert
    $existing = $conn->query("SELECT id FROM attendance WHERE employee_id=$emp_id AND attendance_date='$a_date'")->fetch_assoc();
    if ($existing) {
        $conn->query("UPDATE attendance SET time_in='$time_in', time_out='$time_out', status='$status',
            tardiness_minutes=$tardy, overtime_hours=$ot, remarks='$remarks'
            WHERE employee_id=$emp_id AND attendance_date='$a_date'");
        $success = "Attendance updated.";
    } else {
        $conn->query("INSERT INTO attendance (employee_id, attendance_date, time_in, time_out, status, tardiness_minutes, overtime_hours, remarks)
            VALUES ($emp_id, '$a_date', '$time_in', '$time_out', '$status', $tardy, $ot, '$remarks')");
        //$success = "Attendance logged.";
		prg_redirect('attendance.php?date=' . $a_date, 'Attendance logged.');
    }
    $selected_date = $a_date;
}

$employees = $conn->query("SELECT id, full_name, employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name");

// Attendance for selected date
$att_list = $conn->query("
    SELECT a.*, e.full_name, e.employee_id AS emp_code
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.attendance_date = '$selected_date'
    ORDER BY e.full_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attendance | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6"><h1 class="m-0">Daily Attendance</h1></div>
        <div class="col-sm-6">
          <!-- Date picker filter -->
          <form method="GET" action="" class="float-right d-flex" style="gap:8px;">
            <input type="date" name="date" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($selected_date) ?>">
            <button type="submit" class="btn btn-sm btn-primary">
              <i class="fas fa-search"></i>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="container-fluid">

      <?php csrf_field(); ?>

      <div class="row">
        <!-- Log form -->
        <div class="col-md-4">
          <div class="card card-primary card-outline">
            <div class="card-header"><h3 class="card-title">Log attendance</h3></div>
            <div class="card-body">
              <form method="POST" action="">
				  <?php csrf_field(); ?>
                <div class="form-group">
                  <label>Employee <span class="text-danger">*</span></label>
                  <select name="employee_id" class="form-control" required>
                    <option value="">Select...</option>
                    <?php while ($e = $employees->fetch_assoc()): ?>
                      <option value="<?= $e['id'] ?>">
                        <?= htmlspecialchars($e['full_name']) ?> (<?= $e['employee_id'] ?>)
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Date</label>
                  <input type="date" name="attendance_date" class="form-control"
                         value="<?= htmlspecialchars($selected_date) ?>">
                </div>
                <div class="row">
                  <div class="col-6">
                    <div class="form-group">
                      <label>Time in</label>
                      <input type="time" name="time_in" class="form-control" value="08:00">
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="form-group">
                      <label>Time out</label>
                      <input type="time" name="time_out" class="form-control" value="17:00">
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label>Status</label>
                  <select name="status" class="form-control">
                    <?php
                    $statuses = array('Present','Absent','Late','Half-day','On leave','Holiday','Rest day');
                    foreach ($statuses as $s): ?>
                      <option><?= $s ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="row">
                  <div class="col-6">
                    <div class="form-group">
                      <label>Tardiness (mins)</label>
                      <input type="number" name="tardiness_minutes" class="form-control" min="0" value="0">
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="form-group">
                      <label>Overtime (hrs)</label>
                      <input type="number" name="overtime_hours" class="form-control" step="0.5" min="0" value="0">
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label>Remarks</label>
                  <input type="text" name="remarks" class="form-control" placeholder="Optional...">
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                  <i class="fas fa-save mr-1"></i> Save attendance
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Attendance list for selected date -->
        <div class="col-md-8">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-calendar-day mr-1"></i>
                <?= date('l, F d, Y', strtotime($selected_date)) ?>
              </h3>
            </div>
            <div class="card-body p-0">
              <table class="table table-sm table-hover mb-0" id="attDailyTable">
                <thead class="thead-light">
                  <tr>
                    <th>Employee</th><th>Time in</th><th>Time out</th>
                    <th>Status</th><th>Tardiness</th><th>Overtime</th><th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $att_count = 0;
                  while ($a = $att_list->fetch_assoc()):
                    $att_count++;
                    $sc = array('Present'=>'success','Absent'=>'danger','Late'=>'warning',
                        'Half-day'=>'info','On leave'=>'primary','Holiday'=>'secondary','Rest day'=>'dark');
                    $sb = isset($sc[$a['status']]) ? $sc[$a['status']] : 'secondary';
                  ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($a['full_name']) ?></strong><br>
                      <small class="text-muted"><?= htmlspecialchars($a['emp_code']) ?></small>
                    </td>
                    <td><?= $a['time_in'] ? date('h:i A', strtotime($a['time_in'])) : '—' ?></td>
                    <td><?= $a['time_out'] ? date('h:i A', strtotime($a['time_out'])) : '—' ?></td>
                    <td><span class="badge badge-<?= $sb ?>"><?= $a['status'] ?></span></td>
                    <td><?= $a['tardiness_minutes'] > 0 ? $a['tardiness_minutes'].' mins' : '—' ?></td>
                    <td><?= $a['overtime_hours'] > 0 ? $a['overtime_hours'].' hrs' : '—' ?></td>
                    <td><?= htmlspecialchars($a['remarks'] ? $a['remarks'] : '—') ?></td>
                  </tr>
                  <?php endwhile; ?>
                  <?php if ($att_count === 0): ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted py-3">
                      No attendance records for this date yet.
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
$(function(){ $('#attDailyTable').DataTable({ paging: false, searching: true, info: false }); });
</script>
</body>
</html>