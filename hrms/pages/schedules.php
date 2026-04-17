<?php
// ============================================
// FILE: pages/schedules.php
// Shift scheduling
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	csrf_verify();

    if ($_POST['action'] === 'add_shift') {
        $name    = $conn->real_escape_string(trim($_POST['name']));
        $time_in = $conn->real_escape_string($_POST['time_in']);
        $time_out= $conn->real_escape_string($_POST['time_out']);
        $brk     = (int)$_POST['break_minutes'];
        $days    = $conn->real_escape_string($_POST['work_days']);
        $conn->query("INSERT INTO shifts (name,time_in,time_out,break_minutes,work_days) VALUES ('$name','$time_in','$time_out',$brk,'$days')");
        //$success = "Shift added.";
		prg_redirect('schedules.php', 'Shift added.');

    } elseif ($_POST['action'] === 'assign') {
        $emp_id   = (int)$_POST['employee_id'];
        $shift_id = (int)$_POST['shift_id'];
        $eff_date = $conn->real_escape_string($_POST['effective_date']);
        $end_date = $conn->real_escape_string($_POST['end_date']);
        $end_val  = $end_date ? "'$end_date'" : 'NULL';
        $conn->query("INSERT INTO employee_shifts (employee_id,shift_id,effective_date,end_date) VALUES ($emp_id,$shift_id,'$eff_date',$end_val)");
        //$success = "Shift assigned.";
		prg_redirect('schedules.php', 'Shift assigned.');
    }
}

$shifts    = $conn->query("SELECT * FROM shifts ORDER BY name");
$employees = $conn->query("SELECT id, full_name, employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name");
$assigned  = $conn->query("
    SELECT es.*, e.full_name, e.employee_id AS emp_code, s.name AS shift_name, s.time_in, s.time_out
    FROM employee_shifts es
    JOIN employees e ON es.employee_id = e.id
    JOIN shifts    s ON es.shift_id    = s.id
    ORDER BY es.effective_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Schedules | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Shift Scheduling</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?=prg_flash()?>
	  <!--<?php //if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $success ?></div><?php //endif; ?>-->
	  
    <div class="row">
      <div class="col-md-4">
        <!-- Add Shift -->
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">Add shift</h3></div>
          <div class="card-body">
            <form method="POST" action="">
				<?php csrf_field(); ?>
              <input type="hidden" name="action" value="add_shift">
              <div class="form-group"><label>Shift name</label><input type="text" name="name" class="form-control" required placeholder="e.g. Morning shift"></div>
              <div class="row">
                <div class="col-6"><div class="form-group"><label>Time in</label><input type="time" name="time_in" class="form-control" value="08:00"></div></div>
                <div class="col-6"><div class="form-group"><label>Time out</label><input type="time" name="time_out" class="form-control" value="17:00"></div></div>
              </div>
              <div class="form-group"><label>Break (minutes)</label><input type="number" name="break_minutes" class="form-control" value="60"></div>
              <div class="form-group"><label>Work days</label><input type="text" name="work_days" class="form-control" value="Mon-Fri"></div>
              <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-plus mr-1"></i> Add shift</button>
            </form>
          </div>
        </div>
        <!-- Assign Shift -->
        <div class="card card-info card-outline">
          <div class="card-header"><h3 class="card-title">Assign shift</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <input type="hidden" name="action" value="assign">
              <div class="form-group">
                <label>Employee</label>
                <select name="employee_id" class="form-control" required>
                  <option value="">Select...</option>
                  <?php while ($e = $employees->fetch_assoc()): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)</option><?php endwhile; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Shift</label>
                <select name="shift_id" class="form-control" required>
                  <option value="">Select...</option>
                  <?php $shifts->data_seek(0); while ($s = $shifts->fetch_assoc()): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= date('h:i A', strtotime($s['time_in'])) ?> - <?= date('h:i A', strtotime($s['time_out'])) ?>)</option><?php endwhile; ?>
                </select>
              </div>
              <div class="row">
                <div class="col-6"><div class="form-group"><label>Effective date</label><input type="date" name="effective_date" class="form-control" value="<?= date('Y-m-d') ?>"></div></div>
                <div class="col-6"><div class="form-group"><label>End date (opt.)</label><input type="date" name="end_date" class="form-control"></div></div>
              </div>
              <button type="submit" class="btn btn-info btn-block"><i class="fas fa-calendar-check mr-1"></i> Assign shift</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-8">
        <!-- Shifts table -->
        <div class="card">
          <div class="card-header"><h3 class="card-title">Defined shifts</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light"><tr><th>Shift</th><th>Time in</th><th>Time out</th><th>Break</th><th>Work days</th></tr></thead>
              <tbody>
                <?php $shifts->data_seek(0); while ($s = $shifts->fetch_assoc()): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                  <td><?= date('h:i A', strtotime($s['time_in'])) ?></td>
                  <td><?= date('h:i A', strtotime($s['time_out'])) ?></td>
                  <td><?= $s['break_minutes'] ?> mins</td>
                  <td><?= htmlspecialchars($s['work_days']) ?></td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
        <!-- Assignments -->
        <div class="card">
          <div class="card-header"><h3 class="card-title">Shift assignments</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0" id="assignTable">
              <thead class="thead-light"><tr><th>Employee</th><th>Shift</th><th>Schedule</th><th>Effective</th><th>End</th></tr></thead>
              <tbody>
                <?php while ($a = $assigned->fetch_assoc()): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($a['full_name']) ?></strong><br><small><?= htmlspecialchars($a['emp_code']) ?></small></td>
                  <td><?= htmlspecialchars($a['shift_name']) ?></td>
                  <td><?= date('h:i A', strtotime($a['time_in'])) ?> - <?= date('h:i A', strtotime($a['time_out'])) ?></td>
                  <td><?= date('M d, Y', strtotime($a['effective_date'])) ?></td>
                  <td><?= $a['end_date'] ? date('M d, Y', strtotime($a['end_date'])) : '<span class="text-muted">Ongoing</span>' ?></td>
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
<script>$(function(){ $('#assignTable').DataTable({ pageLength:15, order:[[3,'desc']] }); });</script>
</body></html>