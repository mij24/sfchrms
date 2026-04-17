<?php
// ============================================
// FILE: pages/analytics.php
// Advanced HR Analytics Dashboard
// Charts: headcount trend, turnover, attendance,
// payroll cost, leave utilization, department breakdown
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
enforce_page_role();

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// ── KPI Cards ─────────────────────────────────────────────────────
$total_active   = (int)db_value($pdo,"SELECT COUNT(*) FROM employees WHERE employment_status='Active'");
$total_regular  = (int)db_value($pdo,"SELECT COUNT(*) FROM employees WHERE employment_type='Regular' AND employment_status='Active'");
$total_prob     = (int)db_value($pdo,"SELECT COUNT(*) FROM employees WHERE employment_type='Probationary' AND employment_status='Active'");
$new_hires_year = (int)db_value($pdo,"SELECT COUNT(*) FROM employees WHERE YEAR(date_hired)=?",array($year));
$resigned_year  = (int)db_value($pdo,"SELECT COUNT(*) FROM employees WHERE employment_status IN ('Resigned','Terminated') AND YEAR(updated_at)=?",array($year));
$turnover_rate  = $total_active > 0 ? round(($resigned_year / max($total_active,1)) * 100, 1) : 0;

// Average tenure
$avg_tenure = db_value($pdo,
    "SELECT AVG(TIMESTAMPDIFF(MONTH,date_hired,CURDATE()))
     FROM employees WHERE employment_status='Active' AND date_hired IS NOT NULL"
);
$avg_tenure_years = $avg_tenure ? round($avg_tenure / 12, 1) : 0;

// Avg salary
$avg_salary = db_value($pdo,
    "SELECT AVG(basic_salary) FROM employees WHERE employment_status='Active' AND basic_salary>0"
);

// ── Headcount by month (new hires trend) ──────────────────────────
$monthly_hires = db_fetchall($pdo,
    "SELECT MONTH(date_hired) AS mo, COUNT(*) AS cnt
     FROM employees WHERE YEAR(date_hired)=?
     GROUP BY MONTH(date_hired) ORDER BY mo",
    array($year)
);
$hire_data = array_fill(1,12,0);
foreach ($monthly_hires as $h) $hire_data[(int)$h['mo']] = (int)$h['cnt'];

// ── Attendance rate by month ───────────────────────────────────────
$monthly_att = db_fetchall($pdo,
    "SELECT MONTH(attendance_date) AS mo,
            SUM(status='Present') AS present,
            COUNT(*) AS total
     FROM attendance WHERE YEAR(attendance_date)=?
     GROUP BY MONTH(attendance_date) ORDER BY mo",
    array($year)
);
$att_rate = array_fill(1,12,0);
foreach ($monthly_att as $a) {
    if ($a['total'] > 0) $att_rate[(int)$a['mo']] = round(($a['present']/$a['total'])*100,1);
}

// ── Payroll cost by month ─────────────────────────────────────────
$monthly_payroll = db_fetchall($pdo,
    "SELECT MONTH(period_start) AS mo, SUM(gross_pay) AS total
     FROM payroll WHERE YEAR(period_start)=? AND status='Released'
       AND remarks NOT LIKE '13th month%'
     GROUP BY MONTH(period_start) ORDER BY mo",
    array($year)
);
$payroll_cost = array_fill(1,12,0);
foreach ($monthly_payroll as $p) $payroll_cost[(int)$p['mo']] = round($p['total'],2);

// ── Department headcount ──────────────────────────────────────────
$dept_hc = db_fetchall($pdo,
    "SELECT d.name, COUNT(e.id) AS cnt
     FROM departments d
     LEFT JOIN employees e ON e.department_id=d.id AND e.employment_status='Active'
     GROUP BY d.id ORDER BY cnt DESC LIMIT 10"
);

// ── Leave utilization ─────────────────────────────────────────────
$leave_util = db_fetchall($pdo,
    "SELECT leave_type, COUNT(*) AS requests, SUM(days_applied) AS total_days
     FROM leave_requests WHERE YEAR(date_from)=?
     GROUP BY leave_type ORDER BY total_days DESC",
    array($year)
);

// ── Attendance status breakdown (current month) ───────────────────
$att_breakdown = db_fetchall($pdo,
    "SELECT status, COUNT(*) AS cnt
     FROM attendance
     WHERE MONTH(attendance_date)=MONTH(CURDATE()) AND YEAR(attendance_date)=YEAR(CURDATE())
     GROUP BY status"
);
$att_bd_labels = array(); $att_bd_data = array();
foreach ($att_breakdown as $ab) { $att_bd_labels[] = $ab['status']; $att_bd_data[] = $att_ab['cnt'] ?? $ab['cnt']; }

// ── Employment type distribution ──────────────────────────────────
$emp_type_dist = db_fetchall($pdo,
    "SELECT employment_type, COUNT(*) AS cnt
     FROM employees WHERE employment_status='Active'
     GROUP BY employment_type"
);

$months_short = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HR Analytics | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .kpi-card { border-radius:10px;padding:18px 20px;color:#fff;position:relative;overflow:hidden; }
    .kpi-card .kpi-num { font-size:32px;font-weight:700;line-height:1; }
    .kpi-card .kpi-lbl { font-size:12px;opacity:.85;margin-top:4px; }
    .kpi-card .kpi-icon { position:absolute;right:14px;top:14px;font-size:32px;opacity:.25; }
    .chart-card { background:#fff;border:0.5px solid #dee2e6;border-radius:10px;padding:16px 18px;margin-bottom:16px; }
    .chart-title { font-size:13px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">HR Analytics</h1></div>
        <div class="col-sm-6">
          <form method="GET" action="" class="float-right form-inline" style="gap:8px;">
            <label class="mr-1">Year:</label>
            <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
              <?php for ($y=(int)date('Y'); $y>=(int)date('Y')-3; $y--): ?>
                <option <?= $y===$year?'selected':'' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
            <button onclick="window.print()" type="button" class="btn btn-sm btn-secondary no-print">
              <i class="fas fa-print mr-1"></i>Print
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="content"><div class="container-fluid">

    <!-- ── KPI Row ── -->
    <div class="row mb-3">
      <div class="col-md-2 col-6 mb-2">
        <div class="kpi-card" style="background:#007bff;">
          <div class="kpi-num"><?= $total_active ?></div>
          <div class="kpi-lbl">Active employees</div>
          <i class="fas fa-users kpi-icon"></i>
        </div>
      </div>
      <div class="col-md-2 col-6 mb-2">
        <div class="kpi-card" style="background:#28a745;">
          <div class="kpi-num"><?= $new_hires_year ?></div>
          <div class="kpi-lbl">New hires <?= $year ?></div>
          <i class="fas fa-user-plus kpi-icon"></i>
        </div>
      </div>
      <div class="col-md-2 col-6 mb-2">
        <div class="kpi-card" style="background:#dc3545;">
          <div class="kpi-num"><?= $turnover_rate ?>%</div>
          <div class="kpi-lbl">Turnover rate</div>
          <i class="fas fa-sign-out-alt kpi-icon"></i>
        </div>
      </div>
      <div class="col-md-2 col-6 mb-2">
        <div class="kpi-card" style="background:#fd7e14;">
          <div class="kpi-num"><?= $total_prob ?></div>
          <div class="kpi-lbl">On probation</div>
          <i class="fas fa-user-clock kpi-icon"></i>
        </div>
      </div>
      <div class="col-md-2 col-6 mb-2">
        <div class="kpi-card" style="background:#6f42c1;">
          <div class="kpi-num"><?= $avg_tenure_years ?>y</div>
          <div class="kpi-lbl">Avg tenure</div>
          <i class="fas fa-calendar-alt kpi-icon"></i>
        </div>
      </div>
      <div class="col-md-2 col-6 mb-2">
        <div class="kpi-card" style="background:#17a2b8;">
          <div class="kpi-num">&#8369;<?= number_format($avg_salary/1000,1) ?>k</div>
          <div class="kpi-lbl">Avg basic salary</div>
          <i class="fas fa-money-bill kpi-icon"></i>
        </div>
      </div>
    </div>

    <!-- ── Charts Row 1 ── -->
    <div class="row">
      <div class="col-md-8">
        <div class="chart-card">
          <div class="chart-title">Monthly new hires &amp; payroll cost — <?= $year ?></div>
          <canvas id="chartHirePayroll" height="100"></canvas>
        </div>
      </div>
      <div class="col-md-4">
        <div class="chart-card">
          <div class="chart-title">Employment type distribution</div>
          <canvas id="chartEmpType" height="200"></canvas>
        </div>
      </div>
    </div>

    <!-- ── Charts Row 2 ── -->
    <div class="row">
      <div class="col-md-6">
        <div class="chart-card">
          <div class="chart-title">Monthly attendance rate (%) — <?= $year ?></div>
          <canvas id="chartAttRate" height="120"></canvas>
        </div>
      </div>
      <div class="col-md-6">
        <div class="chart-card">
          <div class="chart-title">Headcount by department</div>
          <canvas id="chartDeptHC" height="120"></canvas>
        </div>
      </div>
    </div>

    <!-- ── Charts Row 3 ── -->
    <div class="row">
      <div class="col-md-6">
        <div class="chart-card">
          <div class="chart-title">Leave utilization by type — <?= $year ?></div>
          <canvas id="chartLeave" height="140"></canvas>
        </div>
      </div>
      <div class="col-md-6">
        <div class="chart-card">
          <div class="chart-title">Attendance status breakdown (current month)</div>
          <canvas id="chartAttBD" height="140"></canvas>
        </div>
      </div>
    </div>

    <!-- ── Data tables ── -->
    <div class="row">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Department headcount details</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light"><tr><th>Department</th><th class="text-center">Headcount</th><th>% of total</th></tr></thead>
              <tbody>
                <?php foreach ($dept_hc as $d):
                  $pct = $total_active > 0 ? round(($d['cnt']/$total_active)*100,1) : 0;
                ?>
                <tr>
                  <td><?= htmlspecialchars($d['name']) ?></td>
                  <td class="text-center"><strong><?= $d['cnt'] ?></strong></td>
                  <td>
                    <div class="progress" style="height:5px;margin-top:6px;">
                      <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                    </div>
                    <small><?= $pct ?>%</small>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Leave requests summary — <?= $year ?></h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light"><tr><th>Leave type</th><th class="text-center">Requests</th><th class="text-right">Total days</th></tr></thead>
              <tbody>
                <?php foreach ($leave_util as $l): ?>
                <tr>
                  <td><?= htmlspecialchars($l['leave_type']) ?></td>
                  <td class="text-center"><?= $l['requests'] ?></td>
                  <td class="text-right"><?= number_format($l['total_days'],1) ?> days</td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($leave_util)): ?>
                  <tr><td colspan="3" class="text-center text-muted py-3">No leave data.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div></div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
var months = <?= json_encode(array_values($months_short)) ?>;
var hireData    = <?= json_encode(array_values($hire_data)) ?>;
var attRateData = <?= json_encode(array_values($att_rate)) ?>;
var payrollData = <?= json_encode(array_values($payroll_cost)) ?>;

var deptLabels  = <?= json_encode(array_column($dept_hc,'name')) ?>;
var deptCounts  = <?= json_encode(array_map('intval',array_column($dept_hc,'cnt'))) ?>;

var empTypeLabels = <?= json_encode(array_column($emp_type_dist,'employment_type')) ?>;
var empTypeCounts = <?= json_encode(array_map('intval',array_column($emp_type_dist,'cnt'))) ?>;

var leaveLabels = <?= json_encode(array_column($leave_util,'leave_type')) ?>;
var leaveDays   = <?= json_encode(array_map('floatval',array_column($leave_util,'total_days'))) ?>;

var attBDLabels = <?= json_encode($att_bd_labels) ?>;
var attBDData   = <?= json_encode($att_bd_data) ?>;

var palette = ['#007bff','#28a745','#dc3545','#fd7e14','#6f42c1','#17a2b8','#ffc107','#343a40','#20c997','#e83e8c'];

// Chart 1 — Hires + Payroll
new Chart(document.getElementById('chartHirePayroll'), {
  type: 'bar',
  data: {
    labels: months,
    datasets: [
      { label:'New hires', data: hireData, backgroundColor:'rgba(0,123,255,.7)', yAxisID:'y' },
      { label:'Payroll cost (₱)', data: payrollData, type:'line', borderColor:'#28a745',
        backgroundColor:'rgba(40,167,69,.1)', fill:true, tension:.3, yAxisID:'y1' }
    ]
  },
  options: {
    responsive:true, interaction:{mode:'index'},
    scales: {
      y:  {position:'left',  title:{display:true,text:'New hires'}},
      y1: {position:'right', title:{display:true,text:'Payroll cost (₱)'},
           ticks:{callback:function(v){return '₱'+v.toLocaleString();}},grid:{drawOnChartArea:false}}
    }
  }
});

// Chart 2 — Employment type donut
new Chart(document.getElementById('chartEmpType'), {
  type:'doughnut',
  data:{ labels:empTypeLabels, datasets:[{data:empTypeCounts,backgroundColor:palette}] },
  options:{ responsive:true, plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}}} }
});

// Chart 3 — Attendance rate line
new Chart(document.getElementById('chartAttRate'), {
  type:'line',
  data:{
    labels:months,
    datasets:[{label:'Attendance rate (%)',data:attRateData,borderColor:'#007bff',
      backgroundColor:'rgba(0,123,255,.1)',fill:true,tension:.3,
      pointRadius:4,pointHoverRadius:6}]
  },
  options:{responsive:true,scales:{y:{min:0,max:100,ticks:{callback:function(v){return v+'%';}}}}}
});

// Chart 4 — Dept headcount horizontal bar
new Chart(document.getElementById('chartDeptHC'), {
  type:'bar',
  data:{
    labels:deptLabels,
    datasets:[{data:deptCounts,backgroundColor:palette,borderRadius:4}]
  },
  options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true}}}
});

// Chart 5 — Leave days bar
new Chart(document.getElementById('chartLeave'), {
  type:'bar',
  data:{
    labels:leaveLabels,
    datasets:[{label:'Days taken',data:leaveDays,backgroundColor:palette,borderRadius:4}]
  },
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
});

// Chart 6 — Attendance breakdown donut
new Chart(document.getElementById('chartAttBD'), {
  type:'pie',
  data:{
    labels:attBDLabels,
    datasets:[{data:attBDData,
      backgroundColor:['#28a745','#dc3545','#ffc107','#17a2b8','#007bff','#6c757d','#343a40']}]
  },
  options:{responsive:true,plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}}}}
});
</script>
</body></html>