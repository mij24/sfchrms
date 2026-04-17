<?php
// ============================================
// FILE: pages/flex_schedule.php
// Flexible time + compressed work week + remote
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$get_action = isset($_GET['action']) ? $_GET['action']    : '';

if ($get_action === 'delete' && $get_id) {
    db_run($pdo,"DELETE FROM flex_schedules WHERE id=?",array($get_id));
    prg_redirect('flex_schedule.php','Flex schedule removed.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $emp_id   = (int)$_POST['employee_id'];
    $type     = trim($_POST['schedule_type']);
    $c_start  = trim($_POST['core_start']);
    $c_end    = trim($_POST['core_end']);
    $f_early  = trim($_POST['flex_start_earliest']);
    $f_late   = trim($_POST['flex_start_latest']);
    $req_hrs  = (float)$_POST['required_hours'];
    $work_days= trim($_POST['work_days']);
    $eff_date = trim($_POST['effective_date']);
    $end_date = !empty($_POST['end_date']) ? trim($_POST['end_date']) : null;
    $notes    = trim($_POST['notes']);
    $approver = (int)$_SESSION['user_id'];

    if (!$emp_id) { prg_redirect_error('flex_schedule.php','Select an employee.'); }

    // Deactivate any current active flex schedule for this employee
    db_run($pdo,"UPDATE flex_schedules SET is_active=0 WHERE employee_id=? AND is_active=1",
        array($emp_id));

    db_run($pdo,
        "INSERT INTO flex_schedules
         (employee_id,schedule_type,core_start,core_end,flex_start_earliest,
          flex_start_latest,required_hours,work_days,effective_date,end_date,
          approved_by,notes,is_active)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)",
        array($emp_id,$type,$c_start,$c_end,$f_early,$f_late,
              $req_hrs,$work_days,$eff_date,$end_date,$approver,$notes)
    );
    prg_redirect('flex_schedule.php','Flex schedule assigned.');
}

$employees = db_fetchall($pdo,
    "SELECT id,full_name,employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name"
);
$schedules = db_fetchall($pdo,
    "SELECT fs.*,e.full_name,e.employee_id AS emp_code, u.username AS approver_name
     FROM flex_schedules fs
     JOIN employees e ON fs.employee_id=e.id
     LEFT JOIN users u ON fs.approved_by=u.id
     ORDER BY fs.is_active DESC, fs.effective_date DESC"
);
$type_badges = array('Fixed'=>'secondary','Flexible'=>'success','Compressed'=>'info','Remote'=>'primary');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Flex Schedules | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Flexible Time Schedules</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>
    <div class="row">
      <div class="col-md-4">
        <div class="card card-success card-outline">
          <div class="card-header"><h3 class="card-title">Assign flex schedule</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <div class="form-group">
                <label>Employee <span class="text-danger">*</span></label>
                <select name="employee_id" class="form-control" required>
                  <option value="">Select...</option>
                  <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>">
                      <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Schedule type</label>
                <select name="schedule_type" class="form-control" id="schedType" onchange="toggleFlexFields(this.value)">
                  <option>Fixed</option>
                  <option>Flexible</option>
                  <option>Compressed</option>
                  <option>Remote</option>
                </select>
              </div>

              <div id="flexFields">
                <div class="alert alert-info" style="font-size:12px;">
                  <strong>Flexible time:</strong> Employee can start between the earliest and latest time,
                  as long as they complete the required daily hours.
                  Core hours are the mandatory period everyone must be present.
                </div>
                <div class="row">
                  <div class="col-6"><div class="form-group"><label>Core start</label>
                    <input type="time" name="core_start" class="form-control" value="09:00"></div></div>
                  <div class="col-6"><div class="form-group"><label>Core end</label>
                    <input type="time" name="core_end" class="form-control" value="15:00"></div></div>
                  <div class="col-6"><div class="form-group"><label>Earliest start</label>
                    <input type="time" name="flex_start_earliest" class="form-control" value="07:00"></div></div>
                  <div class="col-6"><div class="form-group"><label>Latest start</label>
                    <input type="time" name="flex_start_latest" class="form-control" value="10:00"></div></div>
                </div>
              </div>

              <div id="fixedFields">
                <div class="row">
                  <div class="col-6"><div class="form-group"><label>Start time</label>
                    <input type="time" name="core_start" class="form-control" value="08:00"></div></div>
                  <div class="col-6"><div class="form-group"><label>End time</label>
                    <input type="time" name="core_end" class="form-control" value="17:00"></div></div>
                </div>
              </div>

              <div class="form-group">
                <label>Required hours per day</label>
                <input type="number" name="required_hours" class="form-control" step="0.5" value="8">
              </div>
              <div class="form-group">
                <label>Work days</label>
                <input type="text" name="work_days" class="form-control"
                       value="Mon-Fri" placeholder="e.g. Mon-Fri or Mon,Tue,Wed,Thu,Fri">
              </div>
              <div class="row">
                <div class="col-6"><div class="form-group"><label>Effective date</label>
                  <input type="date" name="effective_date" class="form-control" value="<?= date('Y-m-d') ?>"></div></div>
                <div class="col-6"><div class="form-group"><label>End date (opt.)</label>
                  <input type="date" name="end_date" class="form-control"></div></div>
              </div>
              <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
              </div>
              <button type="submit" class="btn btn-success btn-block">
                <i class="fas fa-calendar-check mr-1"></i> Assign schedule
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Flex schedule assignments</h3></div>
          <div class="card-body" style="padding:16px 20px 0;">
            <table class="table table-sm table-hover dt-export" id="fsTable">
              <thead class="thead-light">
                <tr><th>Employee</th><th>Type</th><th>Hours/day</th>
                    <th>Schedule</th><th>Work days</th><th>Effective</th>
                    <th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($schedules as $fs):
                  $tb = isset($type_badges[$fs['schedule_type']]) ? $type_badges[$fs['schedule_type']] : 'secondary';
                ?>
                <tr class="<?= !$fs['is_active'] ? 'text-muted' : '' ?>">
                  <td><strong><?= htmlspecialchars($fs['full_name']) ?></strong><br>
                      <small><?= htmlspecialchars($fs['emp_code']) ?></small></td>
                  <td><span class="badge badge-<?= $tb ?>"><?= $fs['schedule_type'] ?></span></td>
                  <td class="text-center"><?= $fs['required_hours'] ?>h</td>
                  <td style="font-size:12px;">
                    <?php if ($fs['schedule_type']==='Flexible'): ?>
                      Core: <?= date('h:i A',strtotime($fs['core_start'])) ?>–<?= date('h:i A',strtotime($fs['core_end'])) ?><br>
                      Flex: <?= date('h:i A',strtotime($fs['flex_start_earliest'])) ?>–<?= date('h:i A',strtotime($fs['flex_start_latest'])) ?>
                    <?php else: ?>
                      <?= date('h:i A',strtotime($fs['core_start'])) ?>–<?= date('h:i A',strtotime($fs['core_end'])) ?>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($fs['work_days']) ?></td>
                  <td style="font-size:12px;"><?= date('M d, Y',strtotime($fs['effective_date'])) ?></td>
                  <td><span class="badge badge-<?= $fs['is_active']?'success':'secondary' ?>"><?= $fs['is_active']?'Active':'Inactive' ?></span></td>
                  <td>
                    <a href="flex_schedule.php?action=delete&id=<?= $fs['id'] ?>"
                       class="btn btn-danger btn-xs"
                       onclick="return confirm('Remove this schedule?')">
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
<script>
$(function(){
    var type = document.getElementById('schedType');
    toggleFlexFields(type.value);
});
function toggleFlexFields(val) {
    if (val === 'Flexible') {
        document.getElementById('flexFields').style.display = 'block';
        document.getElementById('fixedFields').style.display = 'none';
    } else {
        document.getElementById('flexFields').style.display = 'none';
        document.getElementById('fixedFields').style.display = 'block';
    }
}
</script>
</body></html>
