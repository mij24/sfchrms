<?php
// ============================================
// FILE: pages/probationary.php
// Probationary period tracker
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// Regularization action
$get_action = isset($_GET['action']) ? $_GET['action']  : '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id'] : 0;

if ($get_action === 'regularize' && $get_id) {
    db_run($pdo,
        "UPDATE employees SET employment_type='Regular', employment_status='Active' WHERE id=?",
        array($get_id)
    );
    audit_log($pdo,'UPDATE','employees',$get_id,array('employment_type'=>['Probationary','Regular']));
    prg_redirect('probationary.php','Employee regularized successfully.');
}

if ($get_action === 'extend' && $get_id) {
    // Extend probation by adding 3 months to date_hired (pushes the 6-month mark)
    db_run($pdo,
        "UPDATE employees SET date_hired=DATE_SUB(date_hired, INTERVAL 3 MONTH) WHERE id=?",
        array($get_id)
    );
    prg_redirect('probationary.php','Probationary period extended by 3 months.');
}

// Probationary employees
$probationary = db_fetchall($pdo,
    "SELECT e.*, d.name AS dept, p.title AS position,
            DATE_ADD(e.date_hired, INTERVAL 6 MONTH) AS regularization_date,
            DATEDIFF(DATE_ADD(e.date_hired, INTERVAL 6 MONTH), CURDATE()) AS days_remaining,
            TIMESTAMPDIFF(MONTH, e.date_hired, CURDATE()) AS months_served
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN positions   p ON e.position_id=p.id
     WHERE e.employment_type='Probationary'
       AND e.employment_status='Active'
     ORDER BY regularization_date ASC"
);

// Due this month
$due_this_month = array_filter($probationary, function($e) {
    return $e['days_remaining'] >= 0 && $e['days_remaining'] <= 30;
});

// Overdue (past 6 months, not yet regularized)
$overdue = array_filter($probationary, function($e) {
    return $e['days_remaining'] < 0;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Probationary Tracker | HRMS</title>
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
    <div class="container-fluid"><h1 class="m-0">Probationary Period Tracker</h1></div>
  </div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- Summary -->
    <div class="row mb-3">
      <div class="col-md-3 col-6">
        <div class="small-box bg-warning" style="border-radius:8px;">
          <div class="inner"><h3><?= count($probationary) ?></h3><p>Total probationary</p></div>
          <div class="icon"><i class="fas fa-user-clock"></i></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="small-box bg-info" style="border-radius:8px;">
          <div class="inner"><h3><?= count($due_this_month) ?></h3><p>Due this month</p></div>
          <div class="icon"><i class="fas fa-calendar-check"></i></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="small-box bg-danger" style="border-radius:8px;">
          <div class="inner"><h3><?= count($overdue) ?></h3><p>Overdue for regularization</p></div>
          <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
        </div>
      </div>
    </div>

    <?php if (!empty($due_this_month) || !empty($overdue)): ?>
    <div class="alert alert-warning">
      <i class="fas fa-bell mr-1"></i>
      <strong><?= count($due_this_month) ?></strong> employee(s) are due for regularization this month.
      <strong><?= count($overdue) ?></strong> employee(s) are past their 6-month probation and need immediate action.
    </div>
    <?php endif; ?>

    <!-- Full table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">All probationary employees</h3>
      </div>
      <div class="card-body" style="padding:16px 20px 0;">
        <table class="table table-sm table-bordered table-hover dt-export" id="probTable">
          <thead class="thead-light">
            <tr>
              <th>Employee</th><th>Department</th><th>Position</th>
              <th>Date hired</th><th>Regularization date</th>
              <th class="text-center">Months served</th>
              <th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($probationary as $emp):
              $days_rem = (int)$emp['days_remaining'];
              $months   = (int)$emp['months_served'];
              $pct      = min(100, round(($months / 6) * 100));

              if ($days_rem < 0) {
                  $urgency = 'danger'; $label = 'Overdue';
              } elseif ($days_rem <= 30) {
                  $urgency = 'warning'; $label = 'Due in '.$days_rem.' days';
              } else {
                  $urgency = 'success'; $label = $days_rem.' days left';
              }
            ?>
            <tr class="<?= $days_rem < 0 ? 'table-danger' : ($days_rem <= 30 ? 'table-warning' : '') ?>">
              <td>
                <a href="view_employee.php?id=<?= $emp['id'] ?>">
                  <strong><?= htmlspecialchars($emp['full_name']) ?></strong>
                </a><br>
                <small class="text-muted"><?= htmlspecialchars($emp['employee_id']) ?></small>
              </td>
              <td><?= htmlspecialchars($emp['dept']?:'—') ?></td>
              <td><?= htmlspecialchars($emp['position']?:'—') ?></td>
              <td><?= $emp['date_hired'] ? date('M d, Y',strtotime($emp['date_hired'])) : '—' ?></td>
              <td><?= date('M d, Y',strtotime($emp['regularization_date'])) ?></td>
              <td class="text-center">
                <div style="margin-bottom:3px;"><?= $months ?>/6</div>
                <div class="progress" style="height:5px;">
                  <div class="progress-bar bg-<?= $urgency ?>" style="width:<?= $pct ?>%"></div>
                </div>
              </td>
              <td><span class="badge badge-<?= $urgency ?>"><?= $label ?></span></td>
              <td style="white-space:nowrap;">
                <a href="probationary.php?action=regularize&id=<?= $emp['id'] ?>"
                   class="btn btn-success btn-xs"
                   onclick="return confirm('Regularize <?= htmlspecialchars($emp['full_name']) ?>? This will change their employment type to Regular.')">
                  <i class="fas fa-user-check mr-1"></i> Regularize
                </a>
                <a href="probationary.php?action=extend&id=<?= $emp['id'] ?>"
                   class="btn btn-warning btn-xs"
                   onclick="return confirm('Extend probation by 3 months?')">
                  <i class="fas fa-clock mr-1"></i> Extend
                </a>
                <a href="coe.php?id=<?= $emp['id'] ?>&purpose=Regularization%20review"
                   class="btn btn-info btn-xs" target="_blank" title="Generate COE">
                  <i class="fas fa-file-alt"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($probationary)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">No probationary employees found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>