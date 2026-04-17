<?php
// ============================================
// FILE: pages/payroll_bulk.php
// Bulk semi-monthly payroll processing
// Processes all active employees at once
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_HR_HEAD);

echo prg_flash();

// ── Payroll computation helpers ───────────────────────────────────
function compute_sss($salary) {
    if ($salary < 4250)  return 180.00;
    if ($salary < 10250) return 180.00 + (floor(($salary-4250)/500) * 22.50);
    if ($salary < 20250) return 450.00 + (floor(($salary-10250)/1000) * 45.00);
    return 900.00;
}
function compute_philhealth($salary) {
    $share = ($salary * 0.05) / 2;
    return min(round($share, 2), 5000);
}
function compute_pagibig($salary) {
    return min(round($salary * 0.02, 2), 100);
}
function compute_tax($taxable) {
    if ($taxable <= 20833)  return 0;
    if ($taxable <= 33332)  return ($taxable - 20833) * 0.20;
    if ($taxable <= 66666)  return 2500  + ($taxable - 33333) * 0.25;
    if ($taxable <= 166666) return 10833 + ($taxable - 66667) * 0.30;
    if ($taxable <= 666666) return 40833 + ($taxable - 166667) * 0.32;
    return 200833 + ($taxable - 666667) * 0.35;
}

// ── Get cutoff period labels ──────────────────────────────────────
function get_periods() {
    $periods = array();
    for ($i = 0; $i <= 2; $i++) {
        $ts = mktime(0,0,0, date('m')-$i, 1, date('Y'));
        $y  = date('Y', $ts);
        $m  = date('m', $ts);
        $last = date('t', $ts);
        $periods[] = array(
            'label' => date('M Y', $ts) . ' — 1st cutoff (1–15)',
            'start' => "$y-$m-01",
            'end'   => "$y-$m-15",
        );
        $periods[] = array(
            'label' => date('M Y', $ts) . ' — 2nd cutoff (16–' . $last . ')',
            'start' => "$y-$m-16",
            'end'   => "$y-$m-$last",
        );
    }
    return $periods;
}

$periods  = get_periods();
$success_count = 0;
$skipped_count = 0;
$results  = array();
$run_done = false;

// ── Load settings ─────────────────────────────────────────────────
$settings_raw = db_fetchall($pdo, "SELECT setting_key,setting_value FROM settings");
$cfg = array();
foreach ($settings_raw as $s) $cfg[$s['setting_key']] = $s['setting_value'];
$working_days = isset($cfg['working_days_month']) ? (float)$cfg['working_days_month'] : 22;
$ot_rate      = isset($cfg['overtime_rate'])       ? (float)$cfg['overtime_rate']       : 1.25;

// ── Process payroll ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $period_start = clean_date($_POST['period_start']);
    $period_end   = clean_date($_POST['period_end']);
    $release_now  = isset($_POST['release_now']) ? 1 : 0;

    if (!$period_start || !$period_end) {
        prg_redirect_error('payroll_bulk.php', 'Invalid period dates.');
    }

    $employees = db_fetchall($pdo,
        "SELECT e.*, d.name AS dept
         FROM employees e
         LEFT JOIN departments d ON e.department_id=d.id
         WHERE e.employment_status='Active' AND e.basic_salary > 0
         ORDER BY e.full_name"
    );

    foreach ($employees as $emp) {
        // Skip if payroll already exists for this period
        $exists = db_value($pdo,
            "SELECT COUNT(*) FROM payroll
             WHERE employee_id=? AND period_start=? AND remarks NOT LIKE '13th month%'",
            array($emp['id'], $period_start)
        );
        if ($exists) {
            $skipped_count++;
            $results[] = array(
                'name'   => $emp['full_name'],
                'status' => 'skipped',
                'reason' => 'Already processed',
                'net'    => 0,
            );
            continue;
        }

        // Get attendance for period
        $att = db_fetch($pdo,
            "SELECT
                COUNT(CASE WHEN status='Present' THEN 1 END) AS days_present,
                COUNT(CASE WHEN status='Late'    THEN 1 END) AS days_late,
                COALESCE(SUM(tardiness_minutes),0)           AS total_tardiness,
                COALESCE(SUM(overtime_hours),0)              AS total_overtime
             FROM attendance
             WHERE employee_id=?
               AND attendance_date BETWEEN ? AND ?",
            array($emp['id'], $period_start, $period_end)
        );

        // Days worked — use attendance if available, else assume full period
        $period_days  = 11; // half month = ~11 working days
        $days_worked  = $att && $att['days_present'] > 0 ? (float)$att['days_present'] : $period_days;
        $overtime_hrs = $att ? (float)$att['total_overtime'] : 0;

        $monthly      = (float)$emp['basic_salary'];
        $daily_rate   = $monthly / $working_days;
        $hourly_rate  = $daily_rate / 8;
        $basic_earned = round($daily_rate * $days_worked, 2);
        $ot_pay       = round($hourly_rate * $overtime_hrs * $ot_rate, 2);

        // Recurring allowances
        $allowances_total = (float)db_value($pdo,
            "SELECT COALESCE(SUM(ea.amount),0)
             FROM employee_allowances ea
             JOIN allowance_types at ON ea.allowance_type_id=at.id
             WHERE ea.employee_id=? AND ea.is_active=1 AND at.type='Allowance'",
            array($emp['id'])
        );

        // Recurring deductions (loans amortization etc)
        $deductions_extra = (float)db_value($pdo,
            "SELECT COALESCE(SUM(ea.amount),0)
             FROM employee_allowances ea
             JOIN allowance_types at ON ea.allowance_type_id=at.id
             WHERE ea.employee_id=? AND ea.is_active=1 AND at.type='Deduction'",
            array($emp['id'])
        );

        $gross    = $basic_earned + $ot_pay + $allowances_total;
        $sss      = compute_sss($monthly);
        $ph       = compute_philhealth($monthly);
        $pi       = compute_pagibig($monthly);
        $taxable  = $gross - $sss - $ph - $pi;
        $tax      = compute_tax($taxable);
        $total_ded= $sss + $ph + $pi + $tax + $deductions_extra;
        $net      = $gross - $total_ded;
        $status   = $release_now ? 'Released' : 'Draft';

        db_run($pdo,
            "INSERT INTO payroll
             (employee_id,period_start,period_end,basic_salary,days_worked,overtime_pay,
              allowances,gross_pay,sss_contribution,philhealth_contribution,pagibig_contribution,
              withholding_tax,other_deductions,total_deductions,net_pay,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            array(
                $emp['id'],$period_start,$period_end,$monthly,$days_worked,$ot_pay,
                $allowances_total,$gross,$sss,$ph,$pi,$tax,$deductions_extra,$total_ded,$net,$status
            )
        );
        $new_id = db_lastid($pdo);

        // Auto-deduct active loans
        $active_loans = db_fetchall($pdo,
            "SELECT * FROM loans WHERE employee_id=? AND status='Active' AND balance>0",
            array($emp['id'])
        );
        foreach ($active_loans as $loan) {
            $amort = min((float)$loan['monthly_amortization'], (float)$loan['balance']);
            db_run($pdo,
                "INSERT INTO loan_payments (loan_id,payroll_id,amount,payment_date)
                 VALUES (?,?,?,?)",
                array($loan['id'],$new_id,$amort,$period_end)
            );
            db_run($pdo,
                "UPDATE loans SET amount_paid=amount_paid+?, balance=balance-?,
                 status=IF(balance-?<=0,'Fully paid',status) WHERE id=?",
                array($amort,$amort,$amort,$loan['id'])
            );
        }

        $success_count++;
        $results[] = array(
            'name'   => $emp['full_name'],
            'dept'   => $emp['dept'],
            'status' => 'processed',
            'gross'  => $gross,
            'ded'    => $total_ded,
            'net'    => $net,
        );
    }
    $run_done = true;
}

// ── Load recent payroll history ───────────────────────────────────
$history = db_fetchall($pdo,
    "SELECT period_start, period_end, status,
            COUNT(*) AS emp_count,
            SUM(gross_pay) AS total_gross,
            SUM(total_deductions) AS total_ded,
            SUM(net_pay) AS total_net
     FROM payroll
     WHERE remarks NOT LIKE '13th month%'
     GROUP BY period_start, period_end, status
     ORDER BY period_start DESC
     LIMIT 12"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bulk Payroll | HRMS</title>
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
        <div class="col-sm-6"><h1 class="m-0">Bulk Payroll Processing</h1></div>
        <div class="col-sm-6">
          <a href="payroll.php" class="btn btn-sm btn-secondary float-right">
            <i class="fas fa-user mr-1"></i> Single employee payroll
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <div class="row">
      <!-- Form -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">Run payroll for all employees</h3></div>
          <div class="card-body">
            <form method="POST" action=""
                  onsubmit="return confirm('Process payroll for ALL active employees for this period? This cannot be undone.');">
              <?php csrf_field(); ?>
              <div class="form-group">
                <label>Pay period <span class="text-danger">*</span></label>
                <select name="period_key" class="form-control" id="periodSel"
                        onchange="setPeriod(this)">
                  <option value="">Select period...</option>
                  <?php foreach ($periods as $i => $p): ?>
                    <option value="<?= $p['start'].'|'.$p['end'] ?>"><?= $p['label'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <input type="hidden" name="period_start" id="ps">
              <input type="hidden" name="period_end"   id="pe">

              <div class="form-group">
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" name="release_now" class="custom-control-input" id="releaseNow">
                  <label class="custom-control-label" for="releaseNow">
                    Release immediately (skip Draft status)
                  </label>
                </div>
              </div>

              <div class="alert alert-info" style="font-size:12px;">
                <i class="fas fa-info-circle mr-1"></i>
                Employees with existing payroll for the selected period will be <strong>skipped</strong>.
                Attendance records will be used automatically if available.
                Active loan amortizations will be auto-deducted.
              </div>

              <button type="submit" class="btn btn-primary btn-block" id="runBtn" disabled>
                <i class="fas fa-play mr-1"></i> Run bulk payroll
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Results or history -->
      <div class="col-md-8">
        <?php if ($run_done): ?>
        <div class="card">
          <div class="card-header bg-success text-white">
            <h3 class="card-title">
              <i class="fas fa-check-circle mr-1"></i>
              Payroll processed: <?= $success_count ?> employees, <?= $skipped_count ?> skipped
            </h3>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
              <thead class="thead-light">
                <tr><th>Employee</th><th>Dept</th><th class="text-right">Gross</th><th class="text-right">Deductions</th><th class="text-right">Net pay</th><th>Result</th></tr>
              </thead>
              <tbody>
                <?php foreach ($results as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['name']) ?></td>
                  <td><?= htmlspecialchars($r['dept'] ?? '—') ?></td>
                  <?php if ($r['status'] === 'processed'): ?>
                  <td class="text-right">&#8369; <?= number_format($r['gross'],2) ?></td>
                  <td class="text-right text-danger">- &#8369; <?= number_format($r['ded'],2) ?></td>
                  <td class="text-right"><strong>&#8369; <?= number_format($r['net'],2) ?></strong></td>
                  <td><span class="badge badge-success">Done</span></td>
                  <?php else: ?>
                  <td colspan="3" class="text-muted"><?= htmlspecialchars($r['reason']) ?></td>
                  <td><span class="badge badge-secondary">Skipped</span></td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <!-- Payroll history -->
        <div class="card">
          <div class="card-header"><h3 class="card-title">Payroll run history</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
              <thead class="thead-light">
                <tr><th>Period</th><th class="text-center">Employees</th><th class="text-right">Gross total</th><th class="text-right">Net total</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($history as $h):
                  $sc = $h['status']==='Released' ? 'success' : 'warning';
                ?>
                <tr>
                  <td>
                    <?= date('M d', strtotime($h['period_start'])) ?>
                    &ndash; <?= date('M d, Y', strtotime($h['period_end'])) ?>
                  </td>
                  <td class="text-center"><?= $h['emp_count'] ?></td>
                  <td class="text-right">&#8369; <?= number_format($h['total_gross'],2) ?></td>
                  <td class="text-right"><strong>&#8369; <?= number_format($h['total_net'],2) ?></strong></td>
                  <td><span class="badge badge-<?= $sc ?>"><?= $h['status'] ?></span></td>
                  <td>
                    <?php if ($h['status']==='Draft'): ?>
                    <a href="payroll_release_bulk.php?start=<?= urlencode($h['period_start']) ?>&end=<?= urlencode($h['period_end']) ?>"
                       class="btn btn-success btn-xs"
                       onclick="return confirm('Release all payroll for this period?')">
                      <i class="fas fa-check mr-1"></i> Release all
                    </a>
                    <?php endif; ?>
                    <a href="remittances.php?month=<?= date('n',strtotime($h['period_start'])) ?>&year=<?= date('Y',strtotime($h['period_start'])) ?>&report=summary"
                       class="btn btn-info btn-xs">
                      <i class="fas fa-file-alt"></i>
                    </a>
                  </td>
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
function setPeriod(sel) {
    var val = sel.value;
    if (!val) { document.getElementById('runBtn').disabled=true; return; }
    var parts = val.split('|');
    document.getElementById('ps').value = parts[0];
    document.getElementById('pe').value = parts[1];
    document.getElementById('runBtn').disabled = false;
}
</script>
</body></html>