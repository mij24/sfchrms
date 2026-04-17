<?php
// ============================================
// FILE: pages/training.php
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$error = ''; $success = '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id'] : 0;
$get_action = isset($_GET['action']) ? $_GET['action']   : '';

if ($get_action === 'delete' && $get_id) { $conn->query("DELETE FROM trainings WHERE id=$get_id"); $success = "Training deleted."; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	csrf_verify();
    if ($_POST['action'] === 'save_training') {
        $title    = $conn->real_escape_string(trim($_POST['title']));
        $desc     = $conn->real_escape_string(trim($_POST['description']));
        $trainer  = $conn->real_escape_string(trim($_POST['trainer']));
        $t_date   = $conn->real_escape_string($_POST['training_date']);
        $hours    = (float)$_POST['duration_hours'];
        $loc      = $conn->real_escape_string(trim($_POST['location']));
        $status   = $conn->real_escape_string($_POST['status']);
        $conn->query("INSERT INTO trainings (title,description,trainer,training_date,duration_hours,location,status)
            VALUES ('$title','$desc','$trainer','$t_date',$hours,'$loc','$status')");
        //$success = "Training created.";
		prg_redirect('training.php', 'Training created.');
    }
    if ($_POST['action'] === 'enroll') {
        $tr_id  = (int)$_POST['training_id'];
        $emp_id = (int)$_POST['employee_id'];
        $res = $conn->query("INSERT IGNORE INTO training_participants (training_id,employee_id) VALUES ($tr_id,$emp_id)");
        //$success = $res ? "Participant enrolled." : "Already enrolled.";
		prg_redirect('training.php', 'Participant enrolled.');
    }
    if ($_POST['action'] === 'complete') {
        $tp_id = (int)$_POST['tp_id'];
        $conn->query("UPDATE training_participants SET status='Completed' WHERE id=$tp_id");
        $success = "Marked as completed.";
    }
}

$trainings = $conn->query("SELECT * FROM trainings ORDER BY training_date DESC");
$employees = $conn->query("SELECT id, full_name, employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name");
$stat_color= array('Scheduled'=>'info','Ongoing'=>'warning','Completed'=>'success','Cancelled'=>'secondary');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Training | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Training &amp; Development</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>
	  <!--<?php //if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $success ?></div><?php //endif; ?>-->
    <div class="row">
      <div class="col-md-4">
        <div class="card card-success card-outline">
          <div class="card-header"><h3 class="card-title">Create training</h3></div>
          <div class="card-body">
            <form method="POST" action="">
				<?php csrf_field(); ?>
              <input type="hidden" name="action" value="save_training">
              <div class="form-group"><label>Title <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required></div>
              <div class="form-group"><label>Trainer / Facilitator</label><input type="text" name="trainer" class="form-control"></div>
              <div class="row">
                <div class="col-6"><div class="form-group"><label>Date</label><input type="date" name="training_date" class="form-control" value="<?= date('Y-m-d') ?>"></div></div>
                <div class="col-6"><div class="form-group"><label>Hours</label><input type="number" name="duration_hours" class="form-control" step="0.5" value="8"></div></div>
              </div>
              <div class="form-group"><label>Location</label><input type="text" name="location" class="form-control"></div>
              <div class="form-group"><label>Status</label>
                <select name="status" class="form-control">
                  <?php foreach (array('Scheduled','Ongoing','Completed','Cancelled') as $s): ?><option><?= $s ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
              <button type="submit" class="btn btn-success btn-block"><i class="fas fa-plus mr-1"></i> Create training</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">All trainings</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0" id="trTable">
              <thead class="thead-light"><tr><th>Title</th><th>Trainer</th><th>Date</th><th>Hours</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php $trainings->data_seek(0); while ($tr = $trainings->fetch_assoc()):
                  $sc = isset($stat_color[$tr['status']]) ? $stat_color[$tr['status']] : 'secondary';
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($tr['title']) ?></strong></td>
                  <td><?= htmlspecialchars($tr['trainer'] ? $tr['trainer'] : '—') ?></td>
                  <td><?= $tr['training_date'] ? date('M d, Y', strtotime($tr['training_date'])) : '—' ?></td>
                  <td><?= $tr['duration_hours'] ?> hrs</td>
                  <td><span class="badge badge-<?= $sc ?>"><?= $tr['status'] ?></span></td>
                  <td style="white-space:nowrap;">
                    <!-- Enroll modal trigger -->
                    <button type="button" class="btn btn-info btn-xs" data-toggle="modal"
                            data-target="#enrollModal" data-tid="<?= $tr['id'] ?>">
                      <i class="fas fa-user-plus"></i> Enroll
                    </button>
                    <a href="training.php?action=delete&id=<?= $tr['id'] ?>" class="btn btn-danger btn-xs"
                       onclick="return confirm('Delete this training?')"><i class="fas fa-trash"></i></a>
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

<!-- Enroll Modal -->
<div class="modal fade" id="enrollModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Enroll participant</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <input type="hidden" name="action" value="enroll">
        <input type="hidden" name="training_id" id="modal_tid">
        <div class="modal-body">
          <div class="form-group">
            <label>Select employee</label>
            <select name="employee_id" class="form-control" required>
              <option value="">Select...</option>
              <?php $employees->data_seek(0); while ($e = $employees->fetch_assoc()): ?>
                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)</option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Enroll</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    $('#trTable').DataTable({ pageLength:15, order:[[2,'desc']] });
    $('#enrollModal').on('show.bs.modal', function(e){
        $('#modal_tid').val($(e.relatedTarget).data('tid'));
    });
});
</script>
</body></html>