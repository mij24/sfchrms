<?php
// ============================================
// FILE: pages/remittances.php
// Government remittance reports
// SSS R3 / PhilHealth RF-1 / Pag-IBIG MCRF
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$month  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year   = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$report = isset($_GET['report'])? $_GET['report']      : 'sss';

$months = array(1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
                7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December');

// Fetch released payroll for the month
$payroll_data = db_fetchall($pdo,
    "SELECT p.*, e.full_name, e.employee_id AS emp_code,
            e.sss_number, e.philhealth_number, e.pagibig_number, e.tin_number,
            e.basic_salary
     FROM payroll p
     JOIN employees e ON p.employee_id = e.id
     WHERE MONTH(p.period_start) = ?
       AND YEAR(p.period_start)  = ?
       AND p.status = 'Released'
       AND p.remarks NOT LIKE '13th month%'
     ORDER BY e.full_name",
    array($month, $year)
);

// Totals
$totals = array(
    'sss'        => 0, 'philhealth' => 0, 'pagibig' => 0,
    'tax'        => 0, 'gross'      => 0, 'net'     => 0,
    'sss_er'     => 0, 'ph_er'      => 0, 'pi_er'   => 0,
);

// Employer share rates (2024)
foreach ($payroll_data as $p) {
    $totals['sss']       += $p['sss_contribution'];
    $totals['philhealth']+= $p['philhealth_contribution'];
    $totals['pagibig']   += $p['pagibig_contribution'];
    $totals['tax']       += $p['withholding_tax'];
    $totals['gross']     += $p['gross_pay'];
    $totals['net']       += $p['net_pay'];
    // Employer shares (approx)
    $totals['sss_er']    += $p['sss_contribution'] * 2;       // roughly 2x employee share
    $totals['ph_er']     += $p['philhealth_contribution'];     // same as employee
    $totals['pi_er']     += min($p['pagibig_contribution'] * 2, 200); // max ₱200
}

$company_name = db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key='company_name'");
if (!$company_name) $company_name = 'Your Company';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Remittances | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    @media print { .no-print{display:none!important;} body{background:#fff;} .content-wrapper{margin-left:0!important;} }
    .rem-header { background:#f8f9fa; padding:12px 16px; border-radius:8px; margin-bottom:16px; }
    .rem-header .company { font-size:16px; font-weight:700; }
    .rem-header .period  { font-size:13px; color:#6c757d; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">Government Remittances</h1></div>
      <div class="col-sm-6 text-right no-print">
        <button onclick="window.print()" class="btn btn-sm btn-secondary">
          <i class="fas fa-print mr-1"></i> Print
        </button>
      </div>
    </div>
  </div></div>
  <div class="content"><div class="container-fluid">

    <!-- Filter bar -->
    <div class="card no-print">
      <div class="card-body">
        <form method="GET" action="" class="form-inline flex-wrap" style="gap:10px;">
          <select name="report" class="form-control form-control-sm">
            <option value="sss"       <?= $report==='sss'        ? 'selected':'' ?>>SSS R3</option>
            <option value="philhealth"<?= $report==='philhealth' ? 'selected':'' ?>>PhilHealth RF-1</option>
            <option value="pagibig"   <?= $report==='pagibig'    ? 'selected':'' ?>>Pag-IBIG MCRF</option>
            <option value="bir"       <?= $report==='bir'        ? 'selected':'' ?>>BIR Withholding Summary</option>
            <option value="summary"   <?= $report==='summary'    ? 'selected':'' ?>>All agencies summary</option>
          </select>
          <select name="month" class="form-control form-control-sm">
            <?php foreach ($months as $mn => $ml): ?>
              <option value="<?= $mn ?>" <?= $mn===$month ? 'selected':'' ?>><?= $ml ?></option>
            <?php endforeach; ?>
          </select>
          <select name="year" class="form-control form-control-sm">
            <?php for ($y=(int)date('Y'); $y>=(int)date('Y')-3; $y--): ?>
              <option <?= $y===$year ? 'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="fas fa-filter mr-1"></i> Generate
          </button>
        </form>
      </div>
    </div>

    <!-- Report header -->
    <div class="rem-header">
      <div class="company"><?= htmlspecialchars($company_name) ?></div>
      <div class="period">
        <?php
        $report_labels = array(
            'sss'=>'SSS Form R3 — Monthly Remittance Return',
            'philhealth'=>'PhilHealth RF-1 — Premium Remittance',
            'pagibig'=>'Pag-IBIG MCRF — Monthly Contribution Remittance',
            'bir'=>'BIR Form 1601-C — Monthly Remittance of Income Taxes Withheld',
            'summary'=>'Government Contributions Summary'
        );
        echo isset($report_labels[$report]) ? $report_labels[$report] : '';
        ?>
        &mdash; <?= $months[$month] ?> <?= $year ?>
      </div>
    </div>

    <!-- Summary boxes -->
    <div class="row mb-3">
      <?php
      $boxes = array(
        array('SSS (employee)',    $totals['sss'],        'primary'),
        array('PhilHealth (emp)', $totals['philhealth'], 'success'),
        array('Pag-IBIG (emp)',   $totals['pagibig'],    'info'),
        array('Withholding tax',  $totals['tax'],        'warning'),
        array('SSS (employer)',   $totals['sss_er'],     'secondary'),
      );
      foreach ($boxes as $box):
      ?>
      <div class="col-6 col-md-2 mb-2">
        <div class="card mb-0">
          <div class="card-body p-2 text-center">
            <div style="font-size:11px;color:#6c757d;"><?= $box[0] ?></div>
            <div style="font-size:14px;font-weight:700;color:#343a40;">&#8369; <?= number_format($box[1],2) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php
    // ── Column definitions per report ──
    if ($report === 'sss' || $report === 'summary'):
    ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title">SSS Contributions &mdash; <?= $months[$month] ?> <?= $year ?></h3></div>
      <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0" id="sssTable">
          <thead class="thead-light">
            <tr>
              <th>No.</th><th>SSS number</th><th>Employee name</th>
              <th class="text-right">Monthly salary</th>
              <th class="text-right">EE share</th>
              <th class="text-right">ER share (est.)</th>
              <th class="text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $i = 1;
            $sss_total_ee = 0; $sss_total_er = 0;
            foreach ($payroll_data as $p):
              $ee = $p['sss_contribution'];
              $er = $ee * 2;
              $sss_total_ee += $ee; $sss_total_er += $er;
            ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($p['sss_number'] ? $p['sss_number'] : '—') ?></td>
              <td><?= htmlspecialchars($p['full_name']) ?></td>
              <td class="text-right">&#8369; <?= number_format($p['basic_salary'],2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ee,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($er,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ee+$er,2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#e9f7ef;font-weight:700;">
              <td colspan="4" class="text-right">Total</td>
              <td class="text-right">&#8369; <?= number_format($sss_total_ee,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($sss_total_er,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($sss_total_ee+$sss_total_er,2) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($report === 'philhealth' || $report === 'summary'): ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title">PhilHealth Contributions &mdash; <?= $months[$month] ?> <?= $year ?></h3></div>
      <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0" id="phTable">
          <thead class="thead-light">
            <tr>
              <th>No.</th><th>PhilHealth no.</th><th>Employee name</th>
              <th class="text-right">Basic salary</th>
              <th class="text-right">EE share (2.5%)</th>
              <th class="text-right">ER share (2.5%)</th>
              <th class="text-right">Total premium</th>
            </tr>
          </thead>
          <tbody>
            <?php $j=1; $ph_ee_total=0; $ph_er_total=0; foreach ($payroll_data as $p):
              $ee = $p['philhealth_contribution'];
              $er = $ee;
              $ph_ee_total += $ee; $ph_er_total += $er;
            ?>
            <tr>
              <td><?= $j++ ?></td>
              <td><?= htmlspecialchars($p['philhealth_number'] ? $p['philhealth_number'] : '—') ?></td>
              <td><?= htmlspecialchars($p['full_name']) ?></td>
              <td class="text-right">&#8369; <?= number_format($p['basic_salary'],2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ee,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($er,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ee+$er,2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#e9f7ef;font-weight:700;">
              <td colspan="4" class="text-right">Total</td>
              <td class="text-right">&#8369; <?= number_format($ph_ee_total,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ph_er_total,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ph_ee_total+$ph_er_total,2) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($report === 'pagibig' || $report === 'summary'): ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Pag-IBIG Contributions &mdash; <?= $months[$month] ?> <?= $year ?></h3></div>
      <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0" id="piTable">
          <thead class="thead-light">
            <tr>
              <th>No.</th><th>Pag-IBIG no.</th><th>Employee name</th>
              <th class="text-right">EE share</th>
              <th class="text-right">ER share (est.)</th>
              <th class="text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php $k=1; $pi_ee=0; $pi_er=0; foreach ($payroll_data as $p):
              $ee = $p['pagibig_contribution'];
              $er = min($ee * 2, 200);
              $pi_ee += $ee; $pi_er += $er;
            ?>
            <tr>
              <td><?= $k++ ?></td>
              <td><?= htmlspecialchars($p['pagibig_number'] ? $p['pagibig_number'] : '—') ?></td>
              <td><?= htmlspecialchars($p['full_name']) ?></td>
              <td class="text-right">&#8369; <?= number_format($ee,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($er,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($ee+$er,2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#e9f7ef;font-weight:700;">
              <td colspan="3" class="text-right">Total</td>
              <td class="text-right">&#8369; <?= number_format($pi_ee,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($pi_er,2) ?></td>
              <td class="text-right">&#8369; <?= number_format($pi_ee+$pi_er,2) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($report === 'bir' || $report === 'summary'): ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title">BIR Withholding Tax &mdash; <?= $months[$month] ?> <?= $year ?></h3></div>
      <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0" id="birTable">
          <thead class="thead-light">
            <tr>
              <th>No.</th><th>TIN</th><th>Employee name</th>
              <th class="text-right">Gross compensation</th>
              <th class="text-right">Tax withheld</th>
            </tr>
          </thead>
          <tbody>
            <?php $l=1; $bir_total=0; foreach ($payroll_data as $p):
              $bir_total += $p['withholding_tax'];
            ?>
            <tr>
              <td><?= $l++ ?></td>
              <td><?= htmlspecialchars($p['tin_number'] ? $p['tin_number'] : '—') ?></td>
              <td><?= htmlspecialchars($p['full_name']) ?></td>
              <td class="text-right">&#8369; <?= number_format($p['gross_pay'],2) ?></td>
              <td class="text-right">&#8369; <?= number_format($p['withholding_tax'],2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#fff5f5;font-weight:700;">
              <td colspan="3" class="text-right">Total withheld</td>
              <td class="text-right">&#8369; <?= number_format($totals['gross'],2) ?></td>
              <td class="text-right">&#8369; <?= number_format($bir_total,2) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script src="../assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../assets/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="../assets/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="../assets/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="../assets/plugins/jszip/jszip.min.js"></script>
<script>
$(function(){
    var opts = { pageLength:25, dom:'Bfrtip', buttons:[
        {extend:'excelHtml5',text:'Excel',className:'btn btn-success btn-sm no-print'},
        {extend:'csvHtml5',  text:'CSV',  className:'btn btn-info btn-sm no-print'}
    ]};
    $('#sssTable, #phTable, #piTable, #birTable').each(function(){ $(this).DataTable(opts); });
});
</script>
</body></html>