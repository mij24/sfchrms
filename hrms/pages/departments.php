<?php
// ============================================
// FILE: pages/departments.php — FIXED
// PRG pattern + duplicate name check
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$get_action = isset($_GET['action']) ? $_GET['action']    : '';

if ($get_action === 'delete' && $get_id) {
    $in_use = db_value($pdo,"SELECT COUNT(*) FROM employees WHERE department_id=?",array($get_id));
    if ($in_use) {
        prg_redirect_error('departments.php',"Cannot delete — $in_use employee(s) are assigned to this department.");
    }
    db_run($pdo,"DELETE FROM departments WHERE id=?",array($get_id));
    prg_redirect('departments.php','Department deleted.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name    = trim($_POST['name']);
    $code    = trim($_POST['code']);
    $cost    = trim($_POST['cost_center']);
    $post_id = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;

    if (empty($name)) {
        prg_redirect_error('departments.php','Department name is required.');
    }

    // Duplicate check
    $dup_sql = $post_id
        ? "SELECT COUNT(*) FROM departments WHERE name=? AND id!=?"
        : "SELECT COUNT(*) FROM departments WHERE name=?";
    $dup_params = $post_id ? array($name,$post_id) : array($name);
    if (db_value($pdo,$dup_sql,$dup_params)) {
        prg_redirect_error('departments.php',"Department '$name' already exists.");
    }

    if ($post_id) {
        db_run($pdo,"UPDATE departments SET name=?,code=?,cost_center=? WHERE id=?",
            array($name,$code,$cost,$post_id));
        prg_redirect('departments.php','Department updated.');
    } else {
        db_run($pdo,"INSERT INTO departments (name,code,cost_center) VALUES (?,?,?)",
            array($name,$code,$cost));
        prg_redirect('departments.php','Department added.');
    }
}

$edit  = ($get_action==='edit' && $get_id)
    ? db_fetch($pdo,"SELECT * FROM departments WHERE id=?",array($get_id))
    : null;

$depts = db_fetchall($pdo,
    "SELECT d.*, e.full_name AS head_name,
            (SELECT COUNT(*) FROM employees WHERE department_id=d.id) AS headcount
     FROM departments d
     LEFT JOIN employees e ON d.head_employee_id=e.id
     ORDER BY d.name"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Departments | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Departments</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>
    <div class="row">
      <!-- Add/Edit Form -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title"><?= $edit ? 'Edit department' : 'Add department' ?></h3>
            <?php if ($edit): ?>
              <div class="card-tools">
                <a href="departments.php" class="btn btn-xs btn-secondary">Cancel edit</a>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <?php if ($edit): ?>
                <input type="hidden" name="dept_id" value="<?= $edit['id'] ?>">
              <?php endif; ?>
              <div class="form-group">
                <label>Department name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required
                       value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>">
              </div>
              <div class="form-group">
                <label>Department code</label>
                <input type="text" name="code" class="form-control"
                       value="<?= $edit ? htmlspecialchars($edit['code']) : '' ?>"
                       placeholder="e.g. HR, IT, FIN">
              </div>
              <div class="form-group">
                <label>Cost center</label>
                <input type="text" name="cost_center" class="form-control"
                       value="<?= $edit ? htmlspecialchars($edit['cost_center']) : '' ?>">
              </div>
              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-save mr-1"></i>
                <?= $edit ? 'Update department' : 'Add department' ?>
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">All departments (<?= count($depts) ?>)</h3></div>
          <div class="card-body" style="padding:16px 20px 0;">
            <table class="table table-sm table-bordered table-hover dt-export" id="deptTable">
              <thead class="thead-light">
                <tr><th>Name</th><th>Code</th><th>Cost center</th><th>Head</th>
                    <th class="text-center">Headcount</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($depts as $d): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
                  <td><code><?= htmlspecialchars($d['code'] ?: '—') ?></code></td>
                  <td><?= htmlspecialchars($d['cost_center'] ?: '—') ?></td>
                  <td><?= htmlspecialchars($d['head_name'] ?: '—') ?></td>
                  <td class="text-center"><span class="badge badge-primary"><?= $d['headcount'] ?></span></td>
                  <td style="white-space:nowrap;">
                    <a href="departments.php?action=edit&id=<?= $d['id'] ?>"
                       class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <a href="departments.php?action=delete&id=<?= $d['id'] ?>"
                       class="btn btn-danger btn-xs"
                       onclick="return confirm('Delete <?= htmlspecialchars($d['name']) ?>?')">
                      <i class="fas fa-trash"></i>
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
</body></html>