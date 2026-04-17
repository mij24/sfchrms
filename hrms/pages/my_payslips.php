<?php
// ============================================
// FILE: pages/my_payslips.php
// Employee self-service — view own payslips
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$user   = db_fetch($pdo, "SELECT * FROM users WHERE id=?", array((int)$_SESSION['user_id']));
$emp_id = $user ? (int)$user['employee_id'] : 0;

if (!$emp_id) { ?>
  <!DOCTYPE html><html><body>
  <div class="alert alert-warning m-4">
    Your account is not linked to an employee record. Please contact HR.
  </div></body></html>
<?php exit; }

$emp = db_fetch($pdo,
    "SELECT e.*, d.name AS dept, p.title AS position
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN positions p   ON e.position_id=p.id
     WHERE e.id=?",
    array($emp_id)
);

$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$payslips = db_fetchall($pdo,
    "SELECT * FROM payroll
     WHERE employee_id=? AND status='Released' AND YEAR(period_start)=?
     ORDER BY period_start DESC",
    array($emp_id, $year)
);

$ytd_gross  = 0;
$ytd_ded    = 0;
$ytd_net    = 0;
foreach ($payslips as $p) {
    $ytd_gross += $p['gross_pay'];
    $ytd_ded   += $p['total_deductions'];
    $ytd_net   += $p['net_pay'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Payslips | HRMS</title>
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
        <div class="col-sm-6"><h1 class="m-0">My Payslips</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active">My payslips</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="content"><div class="container-fluid">

    <!-- Year selector -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="" class="form-inline" style="gap:10px;">
          <label class="mr-2">Year:</label>
          <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
            <?php for ($y=(int)date('Y'); $y>=(int)date('Y')-3; $y--): ?>
              <option <?= $y===$year?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </form>
      </div>
    </div>

    <!-- YTD summary cards -->
    <div class="row mb-3">
      <div class="col-md-4">
        <div class="small-box bg-success" style="border-radius:8px;">
          <div class="inner"><h4>&#8369; <?= number_format($ytd_gross,2) ?></h4><p>Year-to-date gross</p></div>
          <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="small-box bg-danger" style="border-radius:8px;">
          <div class="inner"><h4>&#8369; <?= number_format($ytd_ded,2) ?></h4><p>Year-to-date deductions</p></div>
          <div class="icon"><i class="fas fa-minus-circle"></i></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="small-box bg-primary" style="border-radius:8px;">
          <div class="inner"><h4>&#8369; <?= number_format($ytd_net,2) ?></h4><p>Year-to-date net pay</p></div>
          <div class="icon"><i class="fas fa-wallet"></i></div>
        </div>
      </div>
    </div>

    <!-- Payslips list -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Payslips &mdash; <?= $year ?></h3></div>
      <div class="card-body" style="padding:16px 20px 0;">
        <?php if (empty($payslips)): ?>
          <p class="text-muted text-center py-4">No released payslips for <?= $year ?>.</p>
        <?php else: ?>
        <table class="table table-sm table-bordered table-hover dt-export" id="payslipTable">
          <thead class="thead-light">
            <tr>
              <th>Pay period</th>
              <th class="text-right">Gross pay</th>
              <th class="text-right">SSS</th>
              <th class="text-right">PhilHealth</th>
              <th class="text-right">Pag-IBIG</th>
              <th class="text-right">Tax withheld</th>
              <th class="text-right">Total deductions</th>
              <th class="text-right">Net pay</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payslips as $ps): ?>
            <tr>
              <td>
                <?= date('M d', strtotime($ps['period_start'])) ?>
                &ndash; <?= date('M d, Y', strtotime($ps['period_end'])) ?>
                <?php if (strpos($ps['remarks'],'13th month') !== false): ?>
                  <span class="badge badge-success ml-1">13th month</span>
                <?php endif; ?>
              </td>
              <td class="text-right">&#8369; <?= number_format($ps['gross_pay'],2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ps['sss_contribution'],2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ps['philhealth_contribution'],2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ps['pagibig_contribution'],2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ps['withholding_tax'],2) ?></td>
              <td class="text-right text-danger">&#8369; <?= number_format($ps['total_deductions'],2) ?></td>
              <td class="text-right"><strong>&#8369; <?= number_format($ps['net_pay'],2) ?></strong></td>
              <td class="text-center">
                <a href="payslips.php?payroll_id=<?= $ps['id'] ?>" target="_blank"
                   class="btn btn-info btn-xs">
                  <i class="fas fa-print mr-1"></i> View
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#e9f7ef; font-weight:700;">
              <td>Total for <?= $year ?></td>
              <td class="text-right">&#8369; <?= number_format($ytd_gross,2) ?></td>
              <td colspan="5" class="text-right text-danger">- &#8369; <?= number_format($ytd_ded,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ytd_net,2) ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>