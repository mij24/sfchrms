<?php
// ============================================
// FILE: pages/delete_employee.php
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: employees.php"); exit; }

$emp = $conn->query("SELECT * FROM employees WHERE id = $id")->fetch_assoc();
if (!$emp) { header("Location: employees.php"); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
	csrf_verify();
    // Delete related records first
    $conn->query("DELETE FROM leave_credits      WHERE employee_id = $id");
    $conn->query("DELETE FROM attendance         WHERE employee_id = $id");
    $conn->query("DELETE FROM employee_documents WHERE employee_id = $id");
    $conn->query("UPDATE employees SET supervisor_id = NULL WHERE supervisor_id = $id");

    if ($conn->query("DELETE FROM employees WHERE id = $id")) {
        header("Location: employees.php?deleted=1");
        exit;
    } else {
        $error = "Delete failed: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Delete Employee | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
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
      <h1 class="m-0">Delete Employee</h1>
    </div>
  </div>
  <div class="content">
    <div class="container-fluid">
      <div class="row justify-content-center">
        <div class="col-md-6">

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
          <?php endif; ?>

          <div class="card card-danger card-outline">
            <div class="card-header">
              <h3 class="card-title text-danger">
                <i class="fas fa-exclamation-triangle mr-1"></i> Confirm deletion
              </h3>
            </div>
            <div class="card-body">
              <p>You are about to permanently delete the following employee record. This action <strong>cannot be undone</strong>.</p>

              <table class="table table-bordered table-sm mb-3">
                <tr><th style="width:40%">Employee ID</th><td><?= htmlspecialchars($emp['employee_id']) ?></td></tr>
                <tr><th>Full name</th><td><?= htmlspecialchars($emp['full_name']) ?></td></tr>
                <tr><th>Status</th><td><?= htmlspecialchars($emp['employment_status']) ?></td></tr>
                <tr><th>Date hired</th><td><?= $emp['date_hired'] ? date('F d, Y', strtotime($emp['date_hired'])) : '—' ?></td></tr>
              </table>

              <div class="alert alert-warning">
                <i class="fas fa-info-circle mr-1"></i>
                All related records (attendance, leave credits, documents) will also be deleted.
              </div>

              <form method="POST" action="">
				  <?php csrf_field(); ?>
                <div class="form-group">
                  <label>Type <strong>DELETE</strong> to confirm:</label>
                  <input type="text" id="confirmText" class="form-control"
                         placeholder="Type DELETE here" autocomplete="off">
                </div>
                <div class="d-flex justify-content-between">
                  <a href="employees.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Cancel
                  </a>
                  <button type="submit" name="confirm_delete" id="deleteBtn"
                          class="btn btn-danger" disabled>
                    <i class="fas fa-trash mr-1"></i> Delete permanently
                  </button>
                </div>
              </form>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
document.getElementById('confirmText').addEventListener('input', function() {
    document.getElementById('deleteBtn').disabled = (this.value !== 'DELETE');
});
</script>
</body>
</html>