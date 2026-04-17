<?php
// ============================================
// FILE: pages/thirteenth_month.php
// 13th month pay computation — RA 6686
// Formula: Total basic salary earned in year / 12
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$year    = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$success = ''; $error = '';

// Generate 13th month records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    if ($_POST['action'] === 'generate') {
        // Sum all basic payroll records for the year per employee
        $payroll_totals = db_fetchall($pdo,
            "SELECT employee_id,
                    SUM(basic_salary / 22 * days_worked) AS total_basic_earned,
                    COUNT(*) AS payroll_count
             FROM payroll
             WHERE YEAR(period_start) = ? AND status = 'Released'
             GROUP BY employee_id",
            array($year)
        );

        $generated = 0;
        foreach ($payroll_totals as $pt) {
            $thirteenth = round($pt['total_basic_earned'] / 12, 2);
            $emp_id = (int)$pt['employee_id'];

            // Check if already exists
            $exists = db_value($pdo,
                "SELECT COUNT(*) FROM payroll
                 WHERE employee_id=? AND remarks LIKE ? AND YEAR(period_start)=?",
                array($emp_id, '13th month%', $year)
            );

            if (!$exists) {
                db_run($pdo,
                    "INSERT INTO payroll
                     (employee_id, period_start, period_end, basic_salary, days_worked,
                      gross_pay, net_pay, status, remarks)
                     VALUES (?,?,?,?,?,?,?,'Draft',?)",
                    array(
                        $emp_id,
                        $year . '-12-01',
                        $year . '-12-31',
                        $thirteenth, 0,
                        $thirteenth, $thirteenth,
                        '13th month pay ' . $year
                    )
                );
                $generated++;
            }
        }
        $success = "13th month pay generated for $generated employee(s) for $year.";
    }
}

// Get employees with 13th month computed
$employees = db_fetchall($pdo,
    "SELECT e.id, e.full_name, e.employee_id AS emp_code,
            d.name AS dept,
            e.date_hired,
            e.basic_salary,
            COALESCE(
                (SELECT SUM(p.basic_salary / 22 * p.days_worked)
                 FROM payroll p
                 WHERE p.employee_id = e.id
                   AND YEAR(p.period_start) = ?
                   AND p.status = 'Released'
                   AND p.remarks NOT LIKE '13th month%'),
                0
            ) AS total_basic_earned
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE e.employment_status = 'Active'
     ORDER BY e.full_name",
    array($year)
);

$grand_total = 0;
foreach ($employees as $emp) {
    $grand_total += $emp['total_basic_earned'] / 12;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>13th Month Pay | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    @media print { .no-print { display:none !important; } body{background:#fff;} }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">13th Month Pay</h1></div>
      <div class="col-sm-6 text-right no-print">
        <button onclick="window.print()" class="btn btn-sm btn-secondary">
          <i class="fas fa-print mr-1"></i> Print report
        </button>
      </div>
    </div>
  </div></div>
  <div class="content"><div class="container-fluid">

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible no-print">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <!-- Controls -->
    <div class="card no-print">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-md-4">
            <form method="GET" action="" class="form-inline" style="gap:8px;">
              <label>Year:</label>
              <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
                  <option <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </form>
          </div>
          <div class="col-md-8 text-right">
            <form method="POST" action="" class="d-inline"
                  onsubmit="return confirm('Generate 13th month pay for all active employees with released payroll in <?= $year ?>?')">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="generate">
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-calculator mr-1"></i> Generate 13th month payroll records
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary box -->
    <div class="row mb-3">
      <div class="col-md-4">
        <div class="small-box bg-success" style="border-radius:8px;">
          <div class="inner"><h3>&#8369; <?= number_format($grand_total, 2) ?></h3><p>Total 13th month payout</p></div>
          <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="small-box bg-info" style="border-radius:8px;">
          <div class="inner"><h3><?= count($employees) ?></h3><p>Eligible employees</p></div>
          <div class="icon"><i class="fas fa-users"></i></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="small-box bg-warning" style="border-radius:8px;">
          <div class="inner"><h3><?= $year ?></h3><p>Computation year</p></div>
          <div class="icon"><i class="fas fa-calendar"></i></div>
        </div>
      </div>
    </div>

    <!-- Computation table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">13th month pay computation &mdash; <?= $year ?></h3>
        <p class="text-muted" style="font-size:12px;margin:4px 0 0;">
          Formula: Total basic salary earned in <?= $year ?> &divide; 12 months (per RA 6686)
        </p>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0" id="tmTable">
          <thead class="thead-light">
            <tr>
              <th>Employee ID</th>
              <th>Full name</th>
              <th>Department</th>
              <th>Date hired</th>
              <th class="text-right">Monthly basic</th>
              <th class="text-right">Total basic earned</th>
              <th class="text-right">13th month pay</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $emp):
              $thirteenth = $emp['total_basic_earned'] / 12;
            ?>
            <tr>
              <td><code><?= htmlspecialchars($emp['emp_code']) ?></code></td>
              <td><?= htmlspecialchars($emp['full_name']) ?></td>
              <td><?= htmlspecialchars($emp['dept'] ? $emp['dept'] : '—') ?></td>
              <td><?= $emp['date_hired'] ? date('M d, Y', strtotime($emp['date_hired'])) : '—' ?></td>
              <td class="text-right">&#8369; <?= number_format($emp['basic_salary'], 2) ?></td>
              <td class="text-right">&#8369; <?= number_format($emp['total_basic_earned'], 2) ?></td>
              <td class="text-right"><strong>&#8369; <?= number_format($thirteenth, 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#e9f7ef;font-weight:700;">
              <td colspan="6" class="text-right">Grand total</td>
              <td class="text-right">&#8369; <?= number_format($grand_total, 2) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script src="../assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../assets/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="../assets/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="../assets/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="../assets/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="../assets/plugins/jszip/jszip.min.js"></script>
<script>
$(function(){
    $('#tmTable').DataTable({
        pageLength:25, searching:false,
        dom:'Bfrtip',
        buttons:[
            {extend:'excelHtml5',text:'<i class="fas fa-file-excel mr-1"></i>Excel',className:'btn btn-success btn-sm no-print'},
            {extend:'csvHtml5',  text:'<i class="fas fa-file-csv mr-1"></i>CSV',  className:'btn btn-info btn-sm no-print'}
        ]
    });
});
</script>
</body></html>
