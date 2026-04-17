<?php
// ============================================
// FILE: pages/leave_requests.php
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// Add leave_requests table if not yet created
$conn->query("CREATE TABLE IF NOT EXISTS leave_requests (
    id INT(11) NOT NULL AUTO_INCREMENT,
    employee_id INT(11) NOT NULL,
    leave_type ENUM('Vacation leave','Sick leave','Emergency leave','Maternity leave','Paternity leave','Solo parent leave') NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    days_applied DECIMAL(4,1) NOT NULL DEFAULT 1,
    reason TEXT,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    approved_by INT(11) DEFAULT NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY fk_lr_emp (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$error = ''; $success = '';

// Approve / Reject
$get_id     = isset($_GET['id'])     ? (int)$_GET['id']     : 0;
$get_action = isset($_GET['action']) ? $_GET['action']       : '';

if ($get_action === 'approve' && $get_id) {
    $user_id = (int)$_SESSION['user_id'];
    $conn->query("UPDATE leave_requests SET status='Approved', approved_by=$user_id, approved_at=NOW() WHERE id=$get_id");
    //$success = "Leave request approved.";
	prg_redirect('leave_requests.php', 'Leave request approved.');
}
if ($get_action === 'reject' && $get_id) {
    $conn->query("UPDATE leave_requests SET status='Rejected' WHERE id=$get_id");
    //$success = "Leave request rejected.";
	prg_redirect('leave_requests.php', 'Leave request rejected.');
}

// New request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	csrf_verify();
    $emp_id    = (int)$_POST['employee_id'];
    $ltype     = $conn->real_escape_string($_POST['leave_type']);
    $from      = $conn->real_escape_string($_POST['date_from']);
    $to        = $conn->real_escape_string($_POST['date_to']);
    $days      = (float)$_POST['days_applied'];
    $reason    = $conn->real_escape_string($_POST['reason']);

    $conn->query("INSERT INTO leave_requests (employee_id, leave_type, date_from, date_to, days_applied, reason)
                  VALUES ($emp_id, '$ltype', '$from', '$to', $days, '$reason')");
    //$success = "Leave request filed.";
	prg_redirect('leave_requests.php', 'Leave request filed.');
}

$employees = $conn->query("SELECT id, full_name, employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name");
$requests  = $conn->query("
    SELECT lr.*, e.full_name, e.employee_id AS emp_code
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    ORDER BY lr.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Leave Requests | HRMS</title>
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
    <div class="container-fluid"><h1 class="m-0">Leave Requests</h1></div>
  </div>
  <div class="content">
    <div class="container-fluid">

		<?= prg_flash() ?>
      <!--<?//php if ($success): ?>
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <?//= $success ?>
        </div>
      <?php //endif; ?>-->

      <div class="row">
        <!-- File request form -->
        <div class="col-md-4">
          <div class="card card-primary card-outline">
            <div class="card-header"><h3 class="card-title">File leave request</h3></div>
            <div class="card-body">
              <form method="POST" action="">
				  <?php csrf_field(); ?>
                <div class="form-group">
                  <label>Employee <span class="text-danger">*</span></label>
                  <select name="employee_id" class="form-control" required>
                    <option value="">Select employee...</option>
                    <?php while ($e = $employees->fetch_assoc()): ?>
                      <option value="<?= $e['id'] ?>">
                        <?= htmlspecialchars($e['full_name']) ?> (<?= $e['employee_id'] ?>)
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Leave type <span class="text-danger">*</span></label>
                  <select name="leave_type" class="form-control" required>
                    <?php
                    $ltypes = array('Vacation leave','Sick leave','Emergency leave','Maternity leave','Paternity leave','Solo parent leave');
                    foreach ($ltypes as $lt): ?>
                      <option><?= $lt ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="row">
                  <div class="col-6">
                    <div class="form-group">
                      <label>From <span class="text-danger">*</span></label>
                      <input type="date" name="date_from" class="form-control" required>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="form-group">
                      <label>To <span class="text-danger">*</span></label>
                      <input type="date" name="date_to" class="form-control" required>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label>Days applied <span class="text-danger">*</span></label>
                  <input type="number" name="days_applied" class="form-control" step="0.5" min="0.5" required value="1">
                </div>
                <div class="form-group">
                  <label>Reason</label>
                  <textarea name="reason" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                  <i class="fas fa-paper-plane mr-1"></i> Submit request
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Requests table -->
        <div class="col-md-8">
          <div class="card">
            <div class="card-header"><h3 class="card-title">All leave requests</h3></div>
            <div class="card-body p-0">
              <table class="table table-sm table-hover mb-0" id="leaveTable">
                <thead class="thead-light">
                  <tr>
                    <th>Employee</th><th>Type</th><th>From</th><th>To</th>
                    <th class="text-center">Days</th><th>Status</th><th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($lr = $requests->fetch_assoc()):
                    $sc = array('Pending'=>'warning','Approved'=>'success','Rejected'=>'danger');
                    $sb = isset($sc[$lr['status']]) ? $sc[$lr['status']] : 'secondary';
                  ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($lr['full_name']) ?></strong><br>
                      <small class="text-muted"><?= htmlspecialchars($lr['emp_code']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($lr['leave_type']) ?></td>
                    <td><?= date('M d, Y', strtotime($lr['date_from'])) ?></td>
                    <td><?= date('M d, Y', strtotime($lr['date_to'])) ?></td>
                    <td class="text-center"><?= $lr['days_applied'] ?></td>
                    <td><span class="badge badge-<?= $sb ?>"><?= $lr['status'] ?></span></td>
                    <td>
                      <?php if ($lr['status'] === 'Pending'): ?>
                        <a href="leave_requests.php?action=approve&id=<?= $lr['id'] ?>"
                           class="btn btn-success btn-xs"
                           onclick="return confirm('Approve this leave?')">
                          <i class="fas fa-check"></i>
                        </a>
                        <a href="leave_requests.php?action=reject&id=<?= $lr['id'] ?>"
                           class="btn btn-danger btn-xs"
                           onclick="return confirm('Reject this leave?')">
                          <i class="fas fa-times"></i>
                        </a>
                      <?php else: ?>
                        <span class="text-muted" style="font-size:12px;">—</span>
                      <?php endif; ?>
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
$(function(){ $('#leaveTable').DataTable({ order: [[0,'asc']], pageLength: 15 }); });
</script>
</body>
</html>