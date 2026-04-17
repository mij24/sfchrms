<?php
// ============================================
// FILE: pages/org_chart.php
// Visual org chart using D3.js tree
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// Build hierarchy data for D3
$departments = db_fetchall($pdo,
    "SELECT d.*, e.full_name AS head_name, e.id AS head_emp_id,
            (SELECT COUNT(*) FROM employees WHERE department_id=d.id AND employment_status='Active') AS headcount
     FROM departments d
     LEFT JOIN employees e ON d.head_employee_id = e.id
     ORDER BY d.name"
);

$dept_data = array();
foreach ($departments as $d) {
    $members = db_fetchall($pdo,
        "SELECT e.id, e.full_name, e.employee_id AS emp_code, p.title AS position
         FROM employees e
         LEFT JOIN positions p ON e.position_id = p.id
         WHERE e.department_id = ? AND e.employment_status = 'Active'
         ORDER BY e.full_name",
        array($d['id'])
    );
    $dept_data[] = array(
        'id'        => $d['id'],
        'name'      => $d['name'],
        'head'      => $d['head_name'],
        'headcount' => $d['headcount'],
        'members'   => $members
    );
}

$json_data = json_encode($dept_data, JSON_UNESCAPED_UNICODE);

// Company totals
$total_active = db_value($pdo, "SELECT COUNT(*) FROM employees WHERE employment_status='Active'");
$company_name = db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key='company_name'");
if (!$company_name) $company_name = 'Company';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Org Chart | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    #org-wrapper { background:#f8f9fa; border-radius:10px; padding:20px; min-height:500px; overflow-x:auto; }
    .dept-card { background:#fff; border:1px solid #dee2e6; border-radius:10px; padding:14px 18px;
                 display:inline-block; min-width:180px; text-align:center; cursor:pointer;
                 transition:box-shadow .2s; vertical-align:top; margin:6px; }
    .dept-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.1); }
    .dept-card .dept-name { font-weight:600; font-size:14px; color:#343a40; }
    .dept-card .dept-head { font-size:12px; color:#6c757d; margin-top:3px; }
    .dept-card .dept-count { display:inline-block; background:#e9f7ef; color:#27500A;
                              font-size:11px; font-weight:600; padding:2px 8px;
                              border-radius:99px; margin-top:4px; }
    .company-box { background:#007bff; color:#fff; border-radius:10px; padding:16px 24px;
                   text-align:center; display:inline-block; margin-bottom:20px; }
    .members-panel { margin-top:6px; font-size:11px; color:#6c757d; display:none; border-top:1px solid #f0f0f0; padding-top:6px; }
    .member-row { padding:2px 0; text-align:left; }
    .connector { text-align:center; color:#adb5bd; font-size:20px; line-height:1; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">Org Chart</h1></div>
      <div class="col-sm-6">
        <button onclick="window.print()" class="btn btn-sm btn-secondary float-right">
          <i class="fas fa-print mr-1"></i> Print
        </button>
      </div>
    </div>
  </div></div>
  <div class="content"><div class="container-fluid">

    <div id="org-wrapper">
      <!-- Company top node -->
      <div class="text-center mb-3">
        <div class="company-box">
          <div style="font-size:18px;font-weight:700;"><?= htmlspecialchars($company_name) ?></div>
          <div style="font-size:12px;opacity:.85;"><?= $total_active ?> active employees &mdash; <?= count($departments) ?> departments</div>
        </div>
      </div>

      <div class="connector">|</div>
      <div class="connector" style="border-top:2px solid #adb5bd;width:60%;margin:0 auto 8px;"></div>

      <!-- Department cards -->
      <div class="text-center" id="deptGrid">
        <?php foreach ($dept_data as $dept): ?>
        <div class="dept-card" onclick="toggleMembers(<?= $dept['id'] ?>)">
          <div class="dept-name"><?= htmlspecialchars($dept['name']) ?></div>
          <?php if ($dept['head']): ?>
            <div class="dept-head"><i class="fas fa-user-tie mr-1"></i><?= htmlspecialchars($dept['head']) ?></div>
          <?php endif; ?>
          <div class="dept-count"><?= $dept['headcount'] ?> employees</div>
          <div class="members-panel" id="members_<?= $dept['id'] ?>">
            <?php foreach ($dept['members'] as $m): ?>
              <div class="member-row">
                <i class="fas fa-user mr-1" style="font-size:10px;"></i>
                <?= htmlspecialchars($m['full_name']) ?>
                <?php if ($m['position']): ?>
                  <span style="color:#adb5bd;"> &mdash; <?= htmlspecialchars($m['position']) ?></span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <?php if (empty($dept['members'])): ?>
              <div class="member-row text-muted">No active members</div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
function toggleMembers(id) {
    var panel = document.getElementById('members_' + id);
    if (panel.style.display === 'block') {
        panel.style.display = 'none';
    } else {
        panel.style.display = 'block';
    }
}
</script>
</body></html>