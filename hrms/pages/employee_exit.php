<?php
// ============================================
// FILE: pages/employee_exit.php
// Employee Lifecycle — Exit / Termination Process
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$get_action = isset($_GET['action']) ? $_GET['action']    : '';

// Toggle clearance checkbox
if ($get_action === 'toggle_clear' && $get_id) {
    $col = isset($_GET['col']) ? clean_string($_GET['col']) : '';
    $allowed_cols = array('cleared_hr','cleared_finance','cleared_it','cleared_property','cleared_dept_head');
    if (in_array($col, $allowed_cols)) {
        $cur = db_value($pdo, "SELECT $col FROM employee_exits WHERE id=?", array($get_id));
        db_run($pdo, "UPDATE employee_exits SET $col=? WHERE id=?", array($cur ? 0 : 1, $get_id));
        // Update clearance_status
        $rec = db_fetch($pdo, "SELECT * FROM employee_exits WHERE id=?", array($get_id));
        $all_clear = $rec['cleared_hr'] && $rec['cleared_finance'] && $rec['cleared_it']
                  && $rec['cleared_property'] && $rec['cleared_dept_head'];
        $new_cs = $all_clear ? 'Completed' : 'In Progress';
        db_run($pdo, "UPDATE employee_exits SET clearance_status=? WHERE id=?", array($new_cs, $get_id));
    }
    prg_redirect('employee_exit.php', '');
}

// Complete exit — update employee status
if ($get_action === 'complete' && $get_id) {
    $rec = db_fetch($pdo, "SELECT * FROM employee_exits WHERE id=?", array($get_id));
    if ($rec) {
        $new_emp_status = in_array($rec['exit_type'], array('Retirement')) ? 'Resigned' : 'Terminated';
        if ($rec['exit_type'] === 'Resignation' || $rec['exit_type'] === 'Mutual Agreement') $new_emp_status = 'Resigned';
        db_run($pdo, "UPDATE employees SET employment_status=? WHERE id=?", array($new_emp_status, $rec['employee_id']));
        db_run($pdo, "UPDATE employee_exits SET status='Completed', processed_by=? WHERE id=?",
            array((int)$_SESSION['user_id'], $get_id));
        prg_redirect('employee_exit.php', 'Exit process completed. Employee status updated.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $emp_id      = clean_int($_POST['employee_id']);
    $exit_type   = clean_string($_POST['exit_type']);
    $last_day    = clean_date($_POST['last_day']);
    $date_filed  = clean_date($_POST['date_filed']);
    $reason      = clean_string($_POST['reason'], 1000);
    $unpaid      = clean_float($_POST['unpaid_salary']);
    $unused_days = clean_float($_POST['unused_leave_days']);
    $leave_conv  = clean_float($_POST['leave_conversion']);
    $thirteenth  = clean_float($_POST['thirteenth_prorated']);
    $sep_pay     = clean_float($_POST['separation_pay']);
    $other_pay   = clean_float($_POST['other_pay']);
    $total       = $unpaid + $leave_conv + $thirteenth + $sep_pay + $other_pay;

    db_run($pdo,
        "INSERT INTO employee_exits
         (employee_id, exit_type, last_day, date_filed, reason,
          unpaid_salary, unused_leave_days, leave_conversion,
          thirteenth_prorated, separation_pay, other_pay, total_final_pay,
          status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'Filed')",
        array($emp_id,$exit_type,$last_day,$date_filed,$reason,
              $unpaid,$unused_days,$leave_conv,$thirteenth,$sep_pay,$other_pay,$total)
    );
    prg_redirect('employee_exit.php', 'Exit record filed. Please proceed with clearance checklist.');
}

$employees = db_fetchall($pdo,
    "SELECT e.id, e.full_name, e.employee_id, e.basic_salary, e.date_hired
     FROM employees e
     WHERE e.employment_status='Active' ORDER BY e.full_name"
);
$records = db_fetchall($pdo,
    "SELECT ee.*, e.full_name, e.employee_id AS emp_code
     FROM employee_exits ee
     JOIN employees e ON ee.employee_id=e.id
     ORDER BY ee.created_at DESC"
);

$exit_types  = array('Resignation','Retirement','End of Contract','Termination','Death','Redundancy','Mutual Agreement');
$stat_colors = array('Draft'=>'secondary','Filed'=>'warning','Processing'=>'info','Completed'=>'success');
$clearance_items = array(
    'cleared_hr'        => 'HR Department',
    'cleared_finance'   => 'Finance / Accounting',
    'cleared_it'        => 'IT Department',
    'cleared_property'  => 'Property / Custodian',
    'cleared_dept_head' => 'Department Head',
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Exit / Termination | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">Exit / Termination Process</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item">Employee Lifecycle</li>
          <li class="breadcrumb-item active">Exit / Termination</li>
        </ol>
      </div>
    </div>
  </div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <div class="row">
      <!-- File exit form -->
      <div class="col-md-4">
        <div class="card card-danger card-outline">
          <div class="card-header"><h3 class="card-title">File Exit Record</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <div class="form-group"><label>Employee <span class="text-danger">*</span></label>
                <select name="employee_id" id="empExitSel" class="form-control" required
                        onchange="prefillExit(this)">
                  <option value="">Select...</option>
                  <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>"
                            data-salary="<?= $e['basic_salary'] ?>"
                            data-hired="<?= $e['date_hired'] ?>">
                      <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Exit type</label>
                <select name="exit_type" id="exitType" class="form-control" onchange="updateSepPay()">
                  <?php foreach ($exit_types as $et): ?><option><?= $et ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Date filed</label>
                <input type="date" name="date_filed" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="form-group"><label>Last day of work</label>
                <input type="date" name="last_day" class="form-control">
              </div>
              <div class="form-group"><label>Reason</label>
                <textarea name="reason" class="form-control" rows="3"></textarea>
              </div>
              <hr>
              <p style="font-size:11px;font-weight:600;color:#6c757d;text-transform:uppercase;">Final Pay Computation</p>
              <div class="form-group"><label>Unpaid salary (&#8369;)</label>
                <input type="number" name="unpaid_salary" class="form-control" step="0.01" value="0" oninput="calcTotal()">
              </div>
              <div class="form-group"><label>Unused leave days</label>
                <input type="number" name="unused_leave_days" id="unusedDays" class="form-control" step="0.5" value="0" oninput="calcLeaveConv()">
              </div>
              <div class="form-group"><label>Leave conversion (&#8369;)</label>
                <input type="number" name="leave_conversion" id="leaveConv" class="form-control" step="0.01" value="0" oninput="calcTotal()">
              </div>
              <div class="form-group"><label>Pro-rated 13th month (&#8369;)</label>
                <input type="number" name="thirteenth_prorated" id="thirteenth" class="form-control" step="0.01" value="0" oninput="calcTotal()">
              </div>
              <div class="form-group"><label>Separation pay (&#8369;)</label>
                <input type="number" name="separation_pay" id="sepPay" class="form-control" step="0.01" value="0" oninput="calcTotal()">
              </div>
              <div class="form-group"><label>Other pay (&#8369;)</label>
                <input type="number" name="other_pay" class="form-control" step="0.01" value="0" oninput="calcTotal()">
              </div>
              <div style="background:#e9f7ef;padding:10px;border-radius:8px;margin-bottom:14px;">
                <div style="font-size:11px;color:#6c757d;">Estimated total final pay</div>
                <div style="font-size:22px;font-weight:700;color:#155724;">&#8369; <span id="totalFP">0.00</span></div>
              </div>
              <button type="submit" class="btn btn-danger btn-block">
                <i class="fas fa-save mr-1"></i> File Exit Record
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Records -->
      <div class="col-md-8">
        <?php foreach ($records as $rec):
          $sc = isset($stat_colors[$rec['status']]) ? $stat_colors[$rec['status']] : 'secondary';
          $all_clear = $rec['cleared_hr'] && $rec['cleared_finance'] && $rec['cleared_it']
                    && $rec['cleared_property'] && $rec['cleared_dept_head'];
        ?>
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <strong><?= htmlspecialchars($rec['full_name']) ?></strong>
              <small class="text-muted ml-2"><?= htmlspecialchars($rec['emp_code']) ?></small>
              <span class="badge badge-warning ml-2"><?= htmlspecialchars($rec['exit_type']) ?></span>
              <span class="badge badge-<?= $sc ?> ml-1"><?= $rec['status'] ?></span>
            </div>
            <div style="font-size:12px;color:#6c757d;">
              Last day: <?= $rec['last_day'] ? date('M d, Y', strtotime($rec['last_day'])) : '—' ?>
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <!-- Final pay -->
              <div class="col-md-5">
                <p style="font-size:11px;font-weight:600;color:#6c757d;text-transform:uppercase;">Final Pay</p>
                <table class="table table-sm table-borderless mb-0" style="font-size:12px;">
                  <tr><td class="text-muted">Unpaid salary</td><td class="text-right">&#8369; <?= number_format($rec['unpaid_salary'],2) ?></td></tr>
                  <tr><td class="text-muted">Leave conversion (<?= $rec['unused_leave_days'] ?> days)</td><td class="text-right">&#8369; <?= number_format($rec['leave_conversion'],2) ?></td></tr>
                  <tr><td class="text-muted">13th month (prorated)</td><td class="text-right">&#8369; <?= number_format($rec['thirteenth_prorated'],2) ?></td></tr>
                  <tr><td class="text-muted">Separation pay</td><td class="text-right">&#8369; <?= number_format($rec['separation_pay'],2) ?></td></tr>
                  <tr><td class="text-muted">Other</td><td class="text-right">&#8369; <?= number_format($rec['other_pay'],2) ?></td></tr>
                  <tr style="border-top:2px solid #dee2e6;">
                    <td><strong>Total final pay</strong></td>
                    <td class="text-right"><strong>&#8369; <?= number_format($rec['total_final_pay'],2) ?></strong></td>
                  </tr>
                </table>
              </div>
              <!-- Clearance checklist -->
              <div class="col-md-7">
                <p style="font-size:11px;font-weight:600;color:#6c757d;text-transform:uppercase;">
                  Clearance Checklist
                  <span class="badge badge-<?= $rec['clearance_status']==='Completed'?'success':($rec['clearance_status']==='In Progress'?'warning':'secondary') ?> ml-1">
                    <?= $rec['clearance_status'] ?>
                  </span>
                </p>
                <?php foreach ($clearance_items as $col => $label):
                  $checked = (bool)$rec[$col];
                ?>
                <div class="d-flex align-items-center mb-1">
                  <a href="employee_exit.php?action=toggle_clear&id=<?= $rec['id'] ?>&col=<?= $col ?>"
                     class="btn btn-xs btn-<?= $checked?'success':'secondary' ?> mr-2">
                    <i class="fas fa-<?= $checked?'check-square':'square' ?>"></i>
                  </a>
                  <span style="font-size:13px;<?= $checked?'text-decoration:line-through;color:#aaa;':'' ?>">
                    <?= $label ?>
                  </span>
                </div>
                <?php endforeach; ?>

                <?php if ($all_clear && $rec['status'] !== 'Completed' && is_hr_head()): ?>
                <a href="employee_exit.php?action=complete&id=<?= $rec['id'] ?>"
                   class="btn btn-success btn-sm mt-2"
                   onclick="return confirm('Complete this exit process? Employee status will be updated.')">
                  <i class="fas fa-flag-checkered mr-1"></i> Complete Exit Process
                </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($records)): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">
          <i class="fas fa-door-open fa-3x mb-3 d-block" style="opacity:.2;"></i>
          No exit records yet.
        </div></div>
        <?php endif; ?>
      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
var basicSalary = 0;
function prefillExit(sel) {
    var opt = sel.options[sel.selectedIndex];
    basicSalary = parseFloat(opt.getAttribute('data-salary')) || 0;
    var hired   = opt.getAttribute('data-hired');
    // Auto-compute prorated 13th month (months served this year / 12)
    if (hired) {
        var now = new Date(), hireDate = new Date(hired);
        var months = now.getMonth() + 1;
        document.getElementById('thirteenth').value = ((basicSalary * months) / 12).toFixed(2);
    }
    updateSepPay();
    calcTotal();
}
function updateSepPay() {
    var type = document.getElementById('exitType').value;
    if (type === 'Redundancy') {
        document.getElementById('sepPay').value = basicSalary.toFixed(2);
    } else if (type === 'Retirement') {
        document.getElementById('sepPay').value = (basicSalary * 0.5).toFixed(2);
    } else {
        document.getElementById('sepPay').value = '0.00';
    }
    calcTotal();
}
function calcLeaveConv() {
    var days  = parseFloat(document.getElementById('unusedDays').value) || 0;
    var daily = basicSalary / 22;
    document.getElementById('leaveConv').value = (daily * days).toFixed(2);
    calcTotal();
}
function calcTotal() {
    var total = 0;
    document.querySelectorAll('input[name="unpaid_salary"], input[name="leave_conversion"], input[name="thirteenth_prorated"], input[name="separation_pay"], input[name="other_pay"]').forEach(function(inp) {
        total += parseFloat(inp.value) || 0;
    });
    document.getElementById('totalFP').textContent = total.toLocaleString('en-PH', {minimumFractionDigits:2});
}
$(function(){ });
</script>
</body></html>