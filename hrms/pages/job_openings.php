<?php
// ============================================
// FILE: pages/job_openings.php
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$error = ''; $success = '';
$edit  = null;

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']     : 0;
$get_action = isset($_GET['action']) ? $_GET['action']       : '';

if ($get_action === 'delete' && $get_id) {
    $conn->query("DELETE FROM job_openings WHERE id = $get_id");
    //$success = "Job opening deleted.";
	prg_redirect('job_openings.php', 'Job opening deleted.');
}
if ($get_action === 'close' && $get_id) {
    $conn->query("UPDATE job_openings SET status='Closed', date_closed=CURDATE() WHERE id=$get_id");
    //$success = "Job opening closed.";
	prg_redirect('job_openings.php', 'Job opening closed.');
}
if ($get_action === 'edit' && $get_id) {
    $edit = $conn->query("SELECT * FROM job_openings WHERE id=$get_id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	csrf_verify();
    $title       = $conn->real_escape_string(trim($_POST['title']));
    $dept_id     = (int)$_POST['department_id'];
    $pos_id      = (int)$_POST['position_id'];
    $slots       = (int)$_POST['slots'];
    $description = $conn->real_escape_string(trim($_POST['description']));
    $requirements= $conn->real_escape_string(trim($_POST['requirements']));
    $status      = $conn->real_escape_string($_POST['status']);
    $date_opened = $conn->real_escape_string($_POST['date_opened']);
    $user_id     = (int)$_SESSION['user_id'];
    $post_id     = isset($_POST['jo_id']) ? (int)$_POST['jo_id'] : 0;

    if ($post_id) {
        $conn->query("UPDATE job_openings SET title='$title', department_id=$dept_id,
            position_id=$pos_id, slots=$slots, description='$description',
            requirements='$requirements', status='$status', date_opened='$date_opened'
            WHERE id=$post_id");
        //$success = "Job opening updated.";
		prg_redirect('job_openings.php', 'Job opening updated.');
        $edit = null;
    } else {
        $conn->query("INSERT INTO job_openings
            (title, department_id, position_id, slots, description, requirements, status, date_opened, created_by)
            VALUES ('$title',$dept_id,$pos_id,$slots,'$description','$requirements','$status','$date_opened',$user_id)");
        //$success = "Job opening created.";
		prg_redirect('job_openings.php', 'Job opening created.');
    }
}

$depts     = $conn->query("SELECT * FROM departments ORDER BY name");
$positions = $conn->query("SELECT * FROM positions ORDER BY title");
$openings  = $conn->query("
    SELECT jo.*, d.name AS dept_name, p.title AS pos_title,
           (SELECT COUNT(*) FROM applicants WHERE job_opening_id = jo.id) AS applicant_count
    FROM job_openings jo
    LEFT JOIN departments d ON jo.department_id = d.id
    LEFT JOIN positions   p ON jo.position_id   = p.id
    ORDER BY jo.created_at DESC
");

$status_colors = array('Open'=>'success','Closed'=>'secondary','On hold'=>'warning');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Job Openings | HRMS</title>
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
    <div class="container-fluid"><h1 class="m-0">Job Openings</h1></div>
  </div>
  <div class="content"><div class="container-fluid">

	  <? prg_flash() ?>
    <!--<?php //if ($success): ?>
      <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <?//= $success ?>
      </div>
    <?php //endif; ?>-->

    <div class="row">
      <!-- Form -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title"><?= $edit ? 'Edit job opening' : 'New job opening' ?></h3>
          </div>
          <div class="card-body">
            <form method="POST" action="">
				<?php csrf_field(); ?>
              <?php if ($edit): ?>
                <input type="hidden" name="jo_id" value="<?= $edit['id'] ?>">
              <?php endif; ?>
              <div class="form-group">
                <label>Job title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" required
                       value="<?= $edit ? htmlspecialchars($edit['title']) : '' ?>">
              </div>
              <div class="form-group">
                <label>Department</label>
                <select name="department_id" class="form-control">
                  <option value="0">Select...</option>
                  <?php while ($d = $depts->fetch_assoc()): ?>
                    <option value="<?= $d['id'] ?>"
                      <?= ($edit && $edit['department_id'] == $d['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($d['name']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Position</label>
                <select name="position_id" class="form-control">
                  <option value="0">Select...</option>
                  <?php while ($p = $positions->fetch_assoc()): ?>
                    <option value="<?= $p['id'] ?>"
                      <?= ($edit && $edit['position_id'] == $p['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($p['title']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="row">
                <div class="col-6">
                  <div class="form-group">
                    <label>Slots</label>
                    <input type="number" name="slots" class="form-control" min="1"
                           value="<?= $edit ? $edit['slots'] : 1 ?>">
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                      <?php foreach (array('Open','Closed','On hold') as $s): ?>
                        <option <?= ($edit && $edit['status'] === $s) ? 'selected' : '' ?>><?= $s ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label>Date opened</label>
                <input type="date" name="date_opened" class="form-control"
                       value="<?= $edit ? $edit['date_opened'] : date('Y-m-d') ?>">
              </div>
              <div class="form-group">
                <label>Job description</label>
                <textarea name="description" class="form-control" rows="3"><?= $edit ? htmlspecialchars($edit['description']) : '' ?></textarea>
              </div>
              <div class="form-group">
                <label>Requirements</label>
                <textarea name="requirements" class="form-control" rows="3"><?= $edit ? htmlspecialchars($edit['requirements']) : '' ?></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-save mr-1"></i> <?= $edit ? 'Update' : 'Create job opening' ?>
              </button>
              <?php if ($edit): ?>
                <a href="job_openings.php" class="btn btn-secondary btn-block mt-1">Cancel</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>

      <!-- List -->
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">All job openings</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0" id="joTable">
              <thead class="thead-light">
                <tr><th>Title</th><th>Dept</th><th>Slots</th><th>Applicants</th><th>Status</th><th>Date opened</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php while ($jo = $openings->fetch_assoc()):
                  $sc = isset($status_colors[$jo['status']]) ? $status_colors[$jo['status']] : 'secondary';
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($jo['title']) ?></strong></td>
                  <td><?= htmlspecialchars($jo['dept_name'] ? $jo['dept_name'] : '—') ?></td>
                  <td class="text-center"><?= $jo['slots'] ?></td>
                  <td class="text-center">
                    <a href="applicants.php?job_id=<?= $jo['id'] ?>" class="badge badge-info">
                      <?= $jo['applicant_count'] ?> applicants
                    </a>
                  </td>
                  <td><span class="badge badge-<?= $sc ?>"><?= $jo['status'] ?></span></td>
                  <td><?= $jo['date_opened'] ? date('M d, Y', strtotime($jo['date_opened'])) : '—' ?></td>
                  <td style="white-space:nowrap;">
                    <a href="applicants.php?job_id=<?= $jo['id'] ?>" class="btn btn-info btn-xs" title="View applicants">
                      <i class="fas fa-users"></i>
                    </a>
                    <a href="job_openings.php?action=edit&id=<?= $jo['id'] ?>" class="btn btn-warning btn-xs">
                      <i class="fas fa-edit"></i>
                    </a>
                    <?php if ($jo['status'] === 'Open'): ?>
                    <a href="job_openings.php?action=close&id=<?= $jo['id'] ?>"
                       class="btn btn-secondary btn-xs"
                       onclick="return confirm('Close this job opening?')">
                      <i class="fas fa-times-circle"></i>
                    </a>
                    <?php endif; ?>
                    <a href="job_openings.php?action=delete&id=<?= $jo['id'] ?>"
                       class="btn btn-danger btn-xs"
                       onclick="return confirm('Delete this job opening?')">
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

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>$(function(){ $('#joTable').DataTable({ pageLength:15 }); });</script>
</body>
</html>
