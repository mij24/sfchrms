<?php
// ============================================
// FILE: pages/departments.php
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$error   = '';
$success = '';
$edit    = null;

// Get id safely for PHP 5.6+ / 7.x
$get_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
echo prg_flash();
// DELETE
if ($action === 'delete' && $get_id) {
    $conn->query("UPDATE departments SET head_employee_id = NULL WHERE id = $get_id");
    if ($conn->query("DELETE FROM departments WHERE id = $get_id")) {
        prg_redirect('departments.php', 'Department deleted.');
    } else {
        $error = "Cannot delete — employees may still be assigned to this department.";
    }
}

// EDIT — load record
if ($action === 'edit' && $get_id) {
    $edit = $conn->query("SELECT * FROM departments WHERE id = $get_id")->fetch_assoc();
}

// SAVE (add or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	csrf_verify();
    $name   = $conn->real_escape_string(trim($_POST['name']));
    $code   = $conn->real_escape_string(trim($_POST['code']));
    $cost   = $conn->real_escape_string(trim($_POST['cost_center']));
    $post_id = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;

    if ($post_id) {
        $conn->query("UPDATE departments SET name='$name', code='$code', cost_center='$cost' WHERE id=$post_id");
        prg_redirect('departments.php', 'Department updated.');
        $edit = null;
    } else {
        $conn->query("INSERT INTO departments (name, code, cost_center) VALUES ('$name','$code','$cost')");
        prg_redirect('departments.php', 'Department added.');
    }
}

$depts = $conn->query("
    SELECT d.*, e.full_name AS head_name,
           (SELECT COUNT(*) FROM employees WHERE department_id = d.id) AS headcount
    FROM departments d
    LEFT JOIN employees e ON d.head_employee_id = e.id
    ORDER BY d.name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Departments | SFCHRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <h1 class="m-0">Departments</h1>
    </div>
  </div>
  <div class="content">
    <div class="container-fluid">

      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <?= $success ?>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <?= $error ?>
        </div>
      <?php endif; ?>

      <div class="row">
        <!-- Form -->
        <div class="col-md-4">
          <div class="card card-primary card-outline">
            <div class="card-header">
              <h3 class="card-title"><?= $edit ? 'Edit department' : 'Add department' ?></h3>
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
                         value="<?= $edit ? htmlspecialchars($edit['code']) : '' ?>">
                </div>
                <div class="form-group">
                  <label>Cost center</label>
                  <input type="text" name="cost_center" class="form-control"
                         value="<?= $edit ? htmlspecialchars($edit['cost_center']) : '' ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                  <i class="fas fa-save mr-1"></i> <?= $edit ? 'Update department' : 'Add department' ?>
                </button>
                <?php if ($edit): ?>
                  <a href="departments.php" class="btn btn-secondary btn-block mt-1">Cancel</a>
                <?php endif; ?>
              </form>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="col-md-8">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">All departments</h3>
				
				<button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#deptModal">
					<i class="fas fa-plus"></i> Add Department
				  </button>
            </div>
            <div class="card-body p-0">
              <table class="table table-striped table-hover mb-0" id="deptTable">
                <thead class="thead-light">
                  <tr>
                    <th>Name</th><th>Code</th><th>Cost center</th>
                    <th>Head</th><th class="text-center">Headcount</th><th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($d = $depts->fetch_assoc()): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($d['code'] ? $d['code'] : '—') ?></code></td>
                    <td><?= htmlspecialchars($d['cost_center'] ? $d['cost_center'] : '—') ?></td>
                    <td><?= htmlspecialchars($d['head_name'] ? $d['head_name'] : '—') ?></td>
                    <td class="text-center">
                      <span class="badge badge-primary"><?= $d['headcount'] ?></span>
                    </td>
                    <td>
                      <a href="departments.php?action=edit&id=<?= $d['id'] ?>"
                         class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                      <a href="departments.php?action=delete&id=<?= $d['id'] ?>"
                         class="btn btn-danger btn-xs"
                         onclick="return confirm('Delete this department?')">
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
$(function(){ $('#deptTable').DataTable({ pageLength: 15 }); });
</script>
</body>
</html>
