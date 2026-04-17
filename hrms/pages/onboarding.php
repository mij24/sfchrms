<?php
// ============================================
// FILE: pages/onboarding.php
// Employee onboarding checklist
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$success = '';
$view_emp = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean_string($_POST['action']);

    if ($action === 'assign') {
        $emp_id  = clean_int($_POST['employee_id']);
        $tmpl_id = clean_int($_POST['template_id']);
        $tasks   = db_fetchall($pdo, "SELECT id FROM onboarding_tasks WHERE template_id=?", array($tmpl_id));
        $inserted = 0;
        foreach ($tasks as $t) {
            $exists = db_value($pdo,
                "SELECT COUNT(*) FROM employee_onboarding WHERE employee_id=? AND task_id=?",
                array($emp_id,$t['id'])
            );
            if (!$exists) {
                db_run($pdo,
                    "INSERT INTO employee_onboarding (employee_id,template_id,task_id) VALUES (?,?,?)",
                    array($emp_id,$tmpl_id,$t['id'])
                );
                $inserted++;
            }
        }
        $success = "Assigned $inserted task(s). Redirecting to checklist...";
        $view_emp = $emp_id;
    }

    if ($action === 'update_task') {
        $eo_id  = clean_int($_POST['eo_id']);
        $status = clean_string($_POST['status']);
        $notes  = clean_string($_POST['notes'],500);
        $done   = $status === 'Done' ? date('Y-m-d H:i:s') : null;
        db_run($pdo,
            "UPDATE employee_onboarding SET status=?, notes=?, completed_at=? WHERE id=?",
            array($status,$notes,$done,$eo_id)
        );
        $success = "Task updated.";
    }
}

$employees  = db_fetchall($pdo, "SELECT id,full_name,employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name");
$templates  = db_fetchall($pdo, "SELECT * FROM onboarding_templates WHERE is_active=1");

// Checklist for selected employee
$checklist  = array();
$emp_detail = null;
if ($view_emp) {
    $emp_detail = db_fetch($pdo, "SELECT id,full_name,employee_id,date_hired FROM employees WHERE id=?", array($view_emp));
    $checklist  = db_fetchall($pdo,
        "SELECT eo.*, ot.task_name, ot.task_description, ot.responsible, ot.due_days, ot.sort_order
         FROM employee_onboarding eo
         JOIN onboarding_tasks ot ON eo.task_id=ot.id
         WHERE eo.employee_id=?
         ORDER BY ot.sort_order",
        array($view_emp)
    );
}

$done_count    = 0;
foreach ($checklist as $c) if ($c['status']==='Done') $done_count++;
$total_tasks   = count($checklist);
$pct_done      = $total_tasks > 0 ? round(($done_count / $total_tasks) * 100) : 0;

$status_color  = array('Pending'=>'secondary','In progress'=>'warning','Done'=>'success');
$resp_color    = array('HR'=>'primary','Employee'=>'info','IT'=>'warning','Finance'=>'success','Manager'=>'danger');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Onboarding | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Onboarding Checklist</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="row">
      <!-- Assign panel -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">Assign checklist</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="assign">
              <div class="form-group"><label>Employee</label>
                <select name="employee_id" class="form-control" required>
                  <option value="">Select...</option>
                  <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $view_emp==$e['id'] ? 'selected':'' ?>><?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)</option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Template</label>
                <select name="template_id" class="form-control">
                  <?php foreach ($templates as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-tasks mr-1"></i> Assign checklist</button>
            </form>
          </div>
        </div>

        <!-- View employee selector -->
        <div class="card">
          <div class="card-header"><h3 class="card-title">View checklist</h3></div>
          <div class="card-body">
            <form method="GET" action="">
              <div class="form-group">
                <select name="employee_id" class="form-control">
                  <option value="">Select employee...</option>
                  <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $view_emp==$e['id'] ? 'selected':'' ?>><?= htmlspecialchars($e['full_name']) ?></option><?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-info btn-block"><i class="fas fa-eye mr-1"></i> View checklist</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Checklist -->
      <div class="col-md-8">
        <?php if ($emp_detail && !empty($checklist)): ?>
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <?= htmlspecialchars($emp_detail['full_name']) ?>
              <small class="text-muted ml-2"><?= htmlspecialchars($emp_detail['employee_id']) ?></small>
            </h3>
          </div>
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <small class="text-muted"><?= $done_count ?> of <?= $total_tasks ?> tasks completed</small>
              <strong><?= $pct_done ?>%</strong>
            </div>
            <div class="progress mb-3" style="height:8px;">
              <div class="progress-bar bg-success" style="width:<?= $pct_done ?>%"></div>
            </div>

            <?php foreach ($checklist as $task):
              $sc = isset($status_color[$task['status']]) ? $status_color[$task['status']] : 'secondary';
              $rc = isset($resp_color[$task['responsible']]) ? $resp_color[$task['responsible']] : 'secondary';
            ?>
            <div class="card mb-2" style="border-left:4px solid <?= $task['status']==='Done' ? '#28a745' : ($task['status']==='In progress' ? '#ffc107' : '#dee2e6') ?>;">
              <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-start">
                  <div style="flex:1;">
                    <strong style="font-size:13px;"><?= htmlspecialchars($task['task_name']) ?></strong>
                    <span class="badge badge-<?= $rc ?> ml-1" style="font-size:10px;"><?= $task['responsible'] ?></span><br>
                    <?php if ($task['task_description']): ?>
                      <small class="text-muted"><?= htmlspecialchars($task['task_description']) ?></small>
                    <?php endif; ?>
                    <?php if ($task['completed_at']): ?>
                      <small class="text-success d-block"><i class="fas fa-check mr-1"></i>Done <?= date('M d, Y', strtotime($task['completed_at'])) ?></small>
                    <?php endif; ?>
                  </div>
                  <div style="flex-shrink:0;margin-left:10px;">
                    <span class="badge badge-<?= $sc ?>"><?= $task['status'] ?></span>
                  </div>
                </div>
                <!-- Update form -->
                <form method="POST" action="" class="mt-2 d-flex" style="gap:6px;">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="action" value="update_task">
                  <input type="hidden" name="eo_id" value="<?= $task['id'] ?>">
                  <select name="status" class="form-control form-control-sm" style="width:auto;">
                    <?php foreach (array('Pending','In progress','Done') as $st): ?>
                      <option <?= $task['status']===$st ? 'selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes..." value="<?= htmlspecialchars($task['notes'] ? $task['notes'] : '') ?>">
                  <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i></button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php elseif ($view_emp): ?>
          <div class="card"><div class="card-body text-center text-muted py-5">
            No checklist assigned yet. Use the form on the left to assign one.
          </div></div>
        <?php else: ?>
          <div class="card"><div class="card-body text-center text-muted py-5">
            <i class="fas fa-tasks fa-3x mb-3 d-block" style="opacity:.2;"></i>
            Select an employee to view their onboarding checklist.
          </div></div>
        <?php endif; ?>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>