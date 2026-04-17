<?php
// ============================================
// FILE: pages/separation_pay.php
// Final pay & separation pay computation
// Philippine Labor Code basis
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $emp_id      = clean_int($_POST['employee_id']);
    $sep_type    = clean_string($_POST['separation_type']);
    $last_day    = clean_date($_POST['last_day_worked']);
    $unpaid      = clean_float($_POST['unpaid_salary']);
    $unused_days = clean_float($_POST['unused_leave_days']);
    $leave_conv  = clean_float($_POST['leave_conversion']);
    $thirteenth  = clean_float($_POST['thirteenth_prorated']);
    $sep_pay     = clean_float($_POST['separation_pay']);
    $other       = clean_float($_POST['other_pay']);
    $total       = $unpaid + $leave_conv + $thirteenth + $sep_pay + $other;
    $notes       = clean_string($_POST['notes'], 1000);
    $by          = (int)$_SESSION['user_id'];

    db_run($pdo,
        "INSERT INTO separation_records
         (employee_id,separation_type,last_day_worked,unpaid_salary,unused_leave_days,
          leave_conversion,thirteenth_prorated,separation_pay,other_pay,total_final_pay,notes,processed_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
        array($emp_id,$sep_type,$last_day,$unpaid,$unused_days,$leave_conv,$thirteenth,$sep_pay,$other,$total,$notes,$by)
    );

    // Update employee status
    db_run($pdo,
        "UPDATE employees SET employment_status=? WHERE id=?",
        array($sep_type === 'Termination' ? 'Terminated' : 'Resigned', $emp_id)
    );

    $success = "Separation record saved. Total final pay: &#8369; " . number_format($total,2);
    audit_log($pdo,'INSERT','separation_records',0,array('employee_id'=>$emp_id,'total'=>$total));
}

$employees = db_fetchall($pdo,
    "SELECT e.id, e.full_name, e.employee_id AS emp_code, e.basic_salary, e.date_hired,
            TIMESTAMPDIFF(YEAR, e.date_hired, CURDATE()) AS years_of_service
     FROM employees e
     WHERE e.employment_status = 'Active'
     ORDER BY e.full_name"
);
$records = db_fetchall($pdo,
    "SELECT sr.*, e.full_name, e.employee_id AS emp_code
     FROM separation_records sr
     JOIN employees e ON sr.employee_id=e.id
     ORDER BY sr.created_at DESC LIMIT 30"
);
$sep_types = array('Resignation','Termination','End of contract','Retirement','Death','Redundancy');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Separation Pay | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Separation Pay &amp; Final Pay</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $success ?></div><?php endif; ?>

    <div class="alert alert-info">
      <strong>PH Labor Code guide:</strong>
      Resignation = no separation pay (unless company policy).
      Authorized causes (redundancy, retrenchment) = 1 month salary per year of service.
      Illegal dismissal = full backwages + reinstatement or separation pay.
      Final pay must be released within 30 days from last day of work.
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="card card-danger card-outline">
          <div class="card-header"><h3 class="card-title">Compute final pay</h3></div>
          <div class="card-body">
            <form method="POST" action="" id="sepForm">
              <?php csrf_field(); ?>
              <div class="form-group"><label>Employee <span class="text-danger">*</span></label>
                <select name="employee_id" id="empSel" class="form-control" required>
                  <option value="">Select...</option>
                  <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>"
                            data-salary="<?= $e['basic_salary'] ?>"
                            data-hired="<?= $e['date_hired'] ?>"
                            data-years="<?= $e['years_of_service'] ?>">
                      <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['emp_code']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Separation type</label>
                <select name="separation_type" id="sepType" class="form-control">
                  <?php foreach ($sep_types as $st): ?><option><?= $st ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Last day worked</label>
                <input type="date" name="last_day_worked" class="form-control" value="<?= date('Y-m-d') ?>"></div>
              <hr>
              <h6>Final pay components</h6>
              <div class="form-group"><label>Unpaid salary (&#8369;)</label>
                <input type="number" name="unpaid_salary" id="unpaidSal" class="form-control" step="0.01" value="0"></div>
              <div class="form-group"><label>Unused leave days</label>
                <input type="number" name="unused_leave_days" id="unusedDays" class="form-control" step="0.5" value="0" oninput="computeLeaveConv()"></div>
              <div class="form-group"><label>Leave conversion (&#8369;)</label>
                <input type="number" name="leave_conversion" id="leaveConv" class="form-control" step="0.01" value="0"></div>
              <div class="form-group"><label>Pro-rated 13th month (&#8369;)</label>
                <input type="number" name="thirteenth_prorated" id="thirteenth" class="form-control" step="0.01" value="0"></div>
              <div class="form-group"><label>Separation pay (&#8369;) <small class="text-muted">(if applicable)</small></label>
                <input type="number" name="separation_pay" id="sepPay" class="form-control" step="0.01" value="0"></div>
              <div class="form-group"><label>Other pay (&#8369;)</label>
                <input type="number" name="other_pay" class="form-control" step="0.01" value="0"></div>
              <div class="form-group"><label>Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea></div>
              <div style="background:#e9f7ef;padding:10px 14px;border-radius:8px;margin-bottom:14px;">
                <div style="font-size:12px;color:#6c757d;">Estimated total final pay</div>
                <div style="font-size:22px;font-weight:700;color:#155724;">&#8369; <span id="totalFP">0.00</span></div>
              </div>
              <button type="submit" class="btn btn-danger btn-block"><i class="fas fa-save mr-1"></i> Save separation record</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-7">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Separation records</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0" id="sepTable">
              <thead class="thead-light">
                <tr><th>Employee</th><th>Type</th><th>Last day</th><th class="text-right">Total final pay</th><th>Status</th></tr>
              </thead>
              <tbody>
                <?php foreach ($records as $r):
                  $sc = $r['status']==='Draft' ? 'warning' : 'success';
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($r['full_name']) ?></strong><br><small><?= htmlspecialchars($r['emp_code']) ?></small></td>
                  <td><?= htmlspecialchars($r['separation_type']) ?></td>
                  <td><?= $r['last_day_worked'] ? date('M d, Y', strtotime($r['last_day_worked'])) : '—' ?></td>
                  <td class="text-right"><strong>&#8369; <?= number_format($r['total_final_pay'],2) ?></strong></td>
                  <td><span class="badge badge-<?= $sc ?>"><?= $r['status'] ?></span></td>
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
<script>
$(function(){
    $('#sepTable').DataTable({ pageLength:15, order:[[2,'desc']] });

    $('#empSel').on('change', function(){
        var opt    = $(this).find(':selected');
        var salary = parseFloat(opt.data('salary')) || 0;
        var years  = parseInt(opt.data('years'))    || 0;
        var daily  = salary / 22;

        // Auto-compute: pro-rated 13th month (current month / 12)
        var curMonth = new Date().getMonth() + 1;
        var thirteenth = (salary * curMonth) / 12;
        $('#thirteenth').val(thirteenth.toFixed(2));

        // Auto-compute: separation pay for authorized causes (1 month per year, min 1 month)
        var type = $('#sepType').val();
        if (type === 'Redundancy' || type === 'Retrenchment') {
            $('#sepPay').val((salary * Math.max(1, years)).toFixed(2));
        } else if (type === 'Retirement') {
            $('#sepPay').val((salary * 0.5 * Math.max(1, years)).toFixed(2));
        } else {
            $('#sepPay').val('0.00');
        }

        computeLeaveConv();
        computeTotal();
    });

    $('#sepType').on('change', function(){
        $('#empSel').trigger('change');
    });

    function computeLeaveConv() {
        var opt   = $('#empSel').find(':selected');
        var daily = (parseFloat(opt.data('salary')) || 0) / 22;
        var days  = parseFloat($('#unusedDays').val()) || 0;
        $('#leaveConv').val((daily * days).toFixed(2));
        computeTotal();
    }

    function computeTotal() {
        var total = 0;
        $('#sepForm input[type=number]').each(function(){
            total += parseFloat($(this).val()) || 0;
        });
        // Subtract unused_leave_days (it's not money)
        total -= parseFloat($('#unusedDays').val()) || 0;
        $('#totalFP').text(total.toLocaleString('en-PH', {minimumFractionDigits:2}));
    }

    $('#sepForm input[type=number]').on('input', computeTotal);
});
</script>
</body></html>