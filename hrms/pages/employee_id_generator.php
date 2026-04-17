<?php
// ============================================
// FILE: pages/employee_id_generator.php
// Auto-generate Employee IDs based on rules
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_HR);

// Get settings for ID format
$id_prefix  = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='emp_id_prefix'") ?: 'EMP';
$id_digits  = (int)(db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='emp_id_digits'") ?: 4);
$id_by_dept = (int)(db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='emp_id_by_dept'") ?: 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean_string($_POST['action']);

    if ($action === 'save_format') {
        $prefix   = strtoupper(trim($_POST['prefix']));
        $digits   = max(3,min(8,(int)$_POST['digits']));
        $by_dept  = isset($_POST['by_dept']) ? 1 : 0;
        foreach (array(
            array('emp_id_prefix',$prefix),
            array('emp_id_digits',$digits),
            array('emp_id_by_dept',$by_dept),
        ) as $s) {
            $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)
                           ON DUPLICATE KEY UPDATE setting_value=?")->execute(array($s[0],$s[1],$s[1]));
        }
        prg_redirect('employee_id_generator.php','ID format saved.');
    }

    if ($action === 'generate') {
        $dept_id = clean_int($_POST['department_id']);
        // Get next number
        if ($id_by_dept && $dept_id) {
            $dept_code = db_value($pdo,"SELECT code FROM departments WHERE id=?",array($dept_id)) ?: 'GEN';
            $pfx = $id_prefix.'-'.$dept_code.'-';
            $last = db_value($pdo,
                "SELECT MAX(CAST(SUBSTRING(employee_id, ?) AS UNSIGNED)) FROM employees WHERE employee_id LIKE ?",
                array(strlen($pfx)+1, $pfx.'%')
            ) ?: 0;
            $next_id = $pfx . str_pad($last+1, $id_digits, '0', STR_PAD_LEFT);
        } else {
            $pfx = $id_prefix.'-';
            $last = db_value($pdo,
                "SELECT MAX(CAST(SUBSTRING(employee_id, ?) AS UNSIGNED)) FROM employees WHERE employee_id LIKE ?",
                array(strlen($pfx)+1, $pfx.'%')
            ) ?: 0;
            $next_id = $pfx . str_pad($last+1, $id_digits, '0', STR_PAD_LEFT);
        }
        $_SESSION['generated_id'] = $next_id;
        prg_redirect('employee_id_generator.php','ID generated: '.$next_id);
    }
}

$generated_id = isset($_SESSION['generated_id']) ? $_SESSION['generated_id'] : null;
if ($generated_id) unset($_SESSION['generated_id']);
$departments = db_fetchall($pdo,"SELECT * FROM departments ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Employee ID Generator | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Employee ID Generator</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>
    <div class="row justify-content-center">
      <div class="col-md-6">

        <?php if ($generated_id): ?>
        <div class="card card-success mb-3">
          <div class="card-body text-center">
            <div style="font-size:13px;color:#6c757d;margin-bottom:4px;">Generated Employee ID</div>
            <div style="font-size:36px;font-weight:700;color:#155724;letter-spacing:3px;"><?= htmlspecialchars($generated_id) ?></div>
            <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($generated_id) ?>')"
                    class="btn btn-sm btn-outline-success mt-2">
              <i class="fas fa-copy mr-1"></i> Copy to clipboard
            </button>
            <a href="add_employee.php" class="btn btn-sm btn-success mt-2 ml-1">
              <i class="fas fa-user-plus mr-1"></i> Use in Add Employee
            </a>
          </div>
        </div>
        <?php endif; ?>

        <!-- Format settings -->
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">ID format configuration</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="save_format">
              <div class="form-group">
                <label>ID Prefix (letters only)</label>
                <input type="text" name="prefix" class="form-control" maxlength="10"
                       value="<?= htmlspecialchars($id_prefix) ?>"
                       placeholder="e.g. EMP, MNHS, STF">
                <small class="text-muted">A dash will be added automatically after prefix</small>
              </div>
              <div class="form-group">
                <label>Number of digits</label>
                <select name="digits" class="form-control">
                  <?php for ($i=3;$i<=8;$i++): ?>
                    <option value="<?= $i ?>" <?= $id_digits===$i?'selected':'' ?>><?= $i ?> digits (e.g. <?= str_pad(1,$i,'0',STR_PAD_LEFT) ?>)</option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="custom-control custom-checkbox mb-3">
                <input type="checkbox" name="by_dept" class="custom-control-input" id="byDept"
                       <?= $id_by_dept?'checked':'' ?>>
                <label class="custom-control-label" for="byDept">
                  Include department code in ID (e.g. EMP-HR-0001)
                </label>
              </div>
              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-save mr-1"></i> Save format
              </button>
            </form>
          </div>
        </div>

        <!-- Generate -->
        <div class="card card-success card-outline">
          <div class="card-header"><h3 class="card-title">Generate next Employee ID</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="generate">
              <?php if ($id_by_dept): ?>
              <div class="form-group">
                <label>Department</label>
                <select name="department_id" class="form-control">
                  <option value="0">-- No department --</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['code']?:'—') ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php else: ?>
                <input type="hidden" name="department_id" value="0">
              <?php endif; ?>
              <div class="alert alert-light" style="font-size:12px;">
                Current format: <strong><?= htmlspecialchars($id_prefix) ?>-<?= $id_by_dept?'[DEPT]-':'' ?><?= str_pad('N',$id_digits,'0',STR_PAD_LEFT) ?></strong>
              </div>
              <button type="submit" class="btn btn-success btn-block">
                <i class="fas fa-magic mr-1"></i> Generate next ID
              </button>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>