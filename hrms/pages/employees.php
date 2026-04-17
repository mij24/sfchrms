<?php
// ============================================
// FILE: pages/employees.php — FIXED
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$success = '';
$deleted = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;
if ($deleted) {
    $success = "Employee record deleted successfully.";
}

$sql = "
    SELECT e.id, e.employee_id, e.full_name, e.employment_type,
           e.employment_status, e.date_hired,
           d.name AS department_name,
           p.title AS position_title
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions   p ON e.position_id   = p.id
    ORDER BY e.created_at DESC
";
$result = $conn->query($sql);

$status_colors = array(
    'Active'     => 'success',
    'Inactive'   => 'secondary',
    'Resigned'   => 'warning',
    'Terminated' => 'danger',
    'On leave'   => 'info'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Employees | HRMS</title>
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
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0">Employees</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active">Employees</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="container-fluid">

      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <i class="fas fa-check-circle mr-1"></i> <?= $success ?>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">All employee records</h3>
          <div class="card-tools">
            <a href="add_employee.php" class="btn btn-primary btn-sm">
              <i class="fas fa-plus mr-1"></i> Add employee
            </a>
          </div>
        </div>
        <div class="card-body table-responsive p-0">
          <table id="employeeTable" class="table table-bordered table-striped table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th>Employee ID</th>
                <th>Full name</th>
                <th>Department</th>
                <th>Position</th>
                <th>Type</th>
                <th>Status</th>
                <th>Date hired</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $dept     = ($row['department_name'] !== null && $row['department_name'] !== '') ? $row['department_name'] : '&mdash;';
                  $position = ($row['position_title']  !== null && $row['position_title']  !== '') ? $row['position_title']  : '&mdash;';
                  $badge    = isset($status_colors[$row['employment_status']]) ? $status_colors[$row['employment_status']] : 'secondary';
                  $date_hired = ($row['date_hired'] && $row['date_hired'] !== '0000-00-00')
                                ? date('M d, Y', strtotime($row['date_hired'])) : '&mdash;';
              ?>
              <tr>
                <td><code><?= htmlspecialchars($row['employee_id']) ?></code></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= $dept ?></td>
                <td><?= $position ?></td>
                <td><?= htmlspecialchars($row['employment_type']) ?></td>
                <td>
                  <span class="badge badge-<?= $badge ?>">
                    <?= htmlspecialchars($row['employment_status']) ?>
                  </span>
                </td>
                <td><?= $date_hired ?></td>
                <td class="text-center" style="white-space:nowrap;">
                  <a href="view_employee.php?id=<?= $row['id'] ?>"
                     class="btn btn-info btn-xs" title="View">
                    <i class="fas fa-eye"></i>
                  </a>
                  <a href="edit_employee.php?id=<?= $row['id'] ?>"
                     class="btn btn-warning btn-xs" title="Edit">
                    <i class="fas fa-edit"></i>
                  </a>
                  <a href="delete_employee.php?id=<?= $row['id'] ?>"
                     class="btn btn-danger btn-xs" title="Delete">
                    <i class="fas fa-trash"></i>
                  </a>
                </td>
              </tr>
              <?php
                endwhile;
              else:
              ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-4">
                  No employee records found. <a href="add_employee.php">Add the first employee</a>.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(function() {
    $('#employeeTable').DataTable({
        pageLength: 25,
        order: [[6, 'desc']],
        columnDefs: [{ orderable: false, targets: 7 }]
    });
});
</script>
</body>
</html>