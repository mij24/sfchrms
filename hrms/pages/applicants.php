<?php
// ============================================
// FILE: pages/applicants.php
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$job_id     = isset($_GET['job_id'])  ? (int)$_GET['job_id']  : 0;
$get_id     = isset($_GET['id'])      ? (int)$_GET['id']       : 0;
$get_action = isset($_GET['action'])  ? $_GET['action']         : '';
$error = ''; $success = '';

// Stage advance / reject
if ($get_action === 'advance' && $get_id) {
    $stages = array('Initial screening','HR interview','Department interview','Exam / Assessment','Job offer','Hired');
    $app = $conn->query("SELECT stage FROM applicants WHERE id=$get_id")->fetch_assoc();
    if ($app) {
        $idx = array_search($app['stage'], $stages);
        if ($idx !== false && $idx < count($stages) - 1) {
            $next = $conn->real_escape_string($stages[$idx + 1]);
            $conn->query("UPDATE applicants SET stage='$next' WHERE id=$get_id");

            // If hired — prompt to convert to employee (just a notice here)
            if ($next === 'Hired') $success = "Applicant marked as Hired! You may now add them as an employee.";
            else $success = "Applicant advanced to: $next";
        }
    }
}
if ($get_action === 'reject' && $get_id) {
    $conn->query("UPDATE applicants SET stage='Rejected' WHERE id=$get_id");
    //$success = "Applicant marked as rejected.";
	prg_redirect('applicants.php?job_id=' . $job_id, 'Applicant rejected.');
	
}
if ($get_action === 'delete' && $get_id) {
    $conn->query("DELETE FROM applicants WHERE id=$get_id");
    //$success = "Applicant deleted.";
	prg_redirect('applicants.php?job_id=' . $job_id, 'Applicant deleted.');
}

// Add applicant
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	csrf_verify();
    $jo_id    = (int)$_POST['job_opening_id'];
    $name     = $conn->real_escape_string(trim($_POST['full_name']));
    $email    = $conn->real_escape_string(trim($_POST['email']));
    $contact  = $conn->real_escape_string(trim($_POST['contact_number']));
    $source   = $conn->real_escape_string($_POST['source']);
    $app_date = $conn->real_escape_string($_POST['application_date']);
    $notes    = $conn->real_escape_string(trim($_POST['notes']));

    $conn->query("INSERT INTO applicants
        (job_opening_id, full_name, email, contact_number, source, application_date, notes)
        VALUES ($jo_id,'$name','$email','$contact','$source','$app_date','$notes')");
    //$success = "Applicant added.";
	prg_redirect('applicants.php?job_id=' . $job_id, 'Applicant added.');
    $job_id  = $jo_id;
}

$openings = $conn->query("SELECT * FROM job_openings WHERE status='Open' ORDER BY title");

// Build applicant query
$where = $job_id ? "WHERE a.job_opening_id = $job_id" : '';
$applicants = $conn->query("
    SELECT a.*, jo.title AS job_title
    FROM applicants a
    JOIN job_openings jo ON a.job_opening_id = jo.id
    $where
    ORDER BY a.created_at DESC
");

$stage_colors = array(
    'Initial screening'    => 'secondary',
    'HR interview'         => 'info',
    'Department interview' => 'primary',
    'Exam / Assessment'    => 'warning',
    'Job offer'            => 'success',
    'Hired'                => 'success',
    'Rejected'             => 'danger'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Applicants | HRMS</title>
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
      <div class="row">
        <div class="col-sm-6"><h1 class="m-0">Applicants</h1></div>
        <div class="col-sm-6">
          <a href="job_openings.php" class="btn btn-secondary btn-sm float-right">
            <i class="fas fa-arrow-left mr-1"></i> Job openings
          </a>
        </div>
      </div>
    </div>
  </div>
  <div class="content"><div class="container-fluid">
<?= prg_flash() ?>
    <!--<?php //if ($success): ?>
      <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <?//= $success ?>
      </div>
    <?php //endif; ?>-->

    <div class="row">
      <!-- Add form -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">Add applicant</h3></div>
          <div class="card-body">
            <form method="POST" action="">
				<?php csrf_field(); ?>
              <div class="form-group">
                <label>Job opening <span class="text-danger">*</span></label>
                <select name="job_opening_id" class="form-control" required>
                  <option value="">Select...</option>
                  <?php
                  $openings->data_seek(0);
                  while ($jo = $openings->fetch_assoc()):
                  ?>
                    <option value="<?= $jo['id'] ?>" <?= ($job_id == $jo['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($jo['title']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Full name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control" required>
              </div>
              <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control">
              </div>
              <div class="form-group">
                <label>Contact number</label>
                <input type="text" name="contact_number" class="form-control">
              </div>
              <div class="form-group">
                <label>Source</label>
                <select name="source" class="form-control">
                  <?php foreach (array('Referral','JobStreet','LinkedIn','Walk-in','Indeed','Company website','Other') as $src): ?>
                    <option><?= $src ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Application date</label>
                <input type="date" name="application_date" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-plus mr-1"></i> Add applicant
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Applicants table -->
      <div class="col-md-8">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <?= $job_id ? 'Applicants for selected job opening' : 'All applicants' ?>
            </h3>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0" id="appTable">
              <thead class="thead-light">
                <tr><th>Name</th><th>Job</th><th>Source</th><th>Stage</th><th>Date</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php
                $app_count = 0;
                while ($app = $applicants->fetch_assoc()):
                  $app_count++;
                  $sc = isset($stage_colors[$app['stage']]) ? $stage_colors[$app['stage']] : 'secondary';
                  $is_final = in_array($app['stage'], array('Hired','Rejected'));
                ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($app['full_name']) ?></strong><br>
                    <small class="text-muted"><?= htmlspecialchars($app['email'] ? $app['email'] : '') ?></small>
                  </td>
                  <td style="font-size:12px;"><?= htmlspecialchars($app['job_title']) ?></td>
                  <td><span class="badge badge-light"><?= htmlspecialchars($app['source']) ?></span></td>
                  <td><span class="badge badge-<?= $sc ?>"><?= htmlspecialchars($app['stage']) ?></span></td>
                  <td style="font-size:12px;">
                    <?= $app['application_date'] ? date('M d, Y', strtotime($app['application_date'])) : '—' ?>
                  </td>
                  <td style="white-space:nowrap;">
                    <?php if (!$is_final): ?>
                    <a href="applicants.php?action=advance&id=<?= $app['id'] ?>&job_id=<?= $job_id ?>"
                       class="btn btn-success btn-xs" title="Advance stage"
                       onclick="return confirm('Advance to next stage?')">
                      <i class="fas fa-arrow-right"></i>
                    </a>
                    <a href="applicants.php?action=reject&id=<?= $app['id'] ?>&job_id=<?= $job_id ?>"
                       class="btn btn-danger btn-xs" title="Reject"
                       onclick="return confirm('Mark as rejected?')">
                      <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($app['stage'] === 'Hired'): ?>
                    <a href="add_employee.php" class="btn btn-primary btn-xs" title="Convert to employee">
                      <i class="fas fa-user-plus"></i>
                    </a>
                    <?php endif; ?>
                    <a href="applicants.php?action=delete&id=<?= $app['id'] ?>&job_id=<?= $job_id ?>"
                       class="btn btn-secondary btn-xs" title="Delete"
                       onclick="return confirm('Delete this applicant?')">
                      <i class="fas fa-trash"></i>
                    </a>
                  </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($app_count === 0): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">No applicants found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>$(function(){ $('#appTable').DataTable({ pageLength:15, order:[[4,'desc']] }); });</script>
</body>
</html>