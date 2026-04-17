<?php
// ============================================
// FILE: pages/payroll_release_bulk.php
// Release all Draft payroll for a given period
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_HR_HEAD);

$period_start = isset($_GET['start']) ? clean_date(urldecode($_GET['start'])) : null;
$period_end   = isset($_GET['end'])   ? clean_date(urldecode($_GET['end']))   : null;

if ($period_start && $period_end && isset($_GET['confirm'])) {
    $affected = db_run($pdo,
        "UPDATE payroll SET status='Released'
         WHERE period_start=? AND period_end=? AND status='Draft'",
        array($period_start, $period_end)
    )->rowCount();
    prg_redirect('payroll_bulk.php', "$affected payroll record(s) released for period $period_start to $period_end.");
}

$drafts = $period_start ? db_fetchall($pdo,
    "SELECT p.*, e.full_name, e.employee_id AS emp_code
     FROM payroll p JOIN employees e ON p.employee_id=e.id
     WHERE p.period_start=? AND p.status='Draft'
     ORDER BY e.full_name",
    array($period_start)
) : array();

$total_net = 0;
foreach ($drafts as $d) $total_net += $d['net_pay'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Release Payroll | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Release Payroll</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle mr-1"></i>
      You are about to release payroll for period
      <strong><?= $period_start ?> to <?= $period_end ?></strong>.
      This will mark <strong><?= count($drafts) ?></strong> records as Released.
      Total net payout: <strong>&#8369; <?= number_format($total_net,2) ?></strong>.
      <br>This action cannot be undone.
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Draft payroll records to be released</h3></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="thead-light">
            <tr><th>Employee</th><th class="text-right">Gross</th>
                <th class="text-right">Deductions</th><th class="text-right">Net pay</th></tr>
          </thead>
          <tbody>
            <?php foreach ($drafts as $d): ?>
            <tr>
              <td><?= htmlspecialchars($d['full_name']) ?> <small class="text-muted">(<?= htmlspecialchars($d['emp_code']) ?>)</small></td>
              <td class="text-right">&#8369; <?= number_format($d['gross_pay'],2) ?></td>
              <td class="text-right text-danger">- &#8369; <?= number_format($d['total_deductions'],2) ?></td>
              <td class="text-right"><strong>&#8369; <?= number_format($d['net_pay'],2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:#e9f7ef;font-weight:700;">
              <td>Total</td>
              <td colspan="2" class="text-right">Grand total net pay</td>
              <td class="text-right">&#8369; <?= number_format($total_net,2) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-right">
        <a href="payroll_bulk.php" class="btn btn-secondary mr-2">
          <i class="fas fa-times mr-1"></i> Cancel
        </a>
        <a href="payroll_release_bulk.php?start=<?= urlencode($period_start) ?>&end=<?= urlencode($period_end) ?>&confirm=1"
           class="btn btn-success"
           onclick="return confirm('Release all <?= count($drafts) ?> payroll records? This cannot be undone.')">
          <i class="fas fa-check mr-1"></i> Confirm release (<?= count($drafts) ?> records)
        </a>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>