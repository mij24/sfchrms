<?php
// ============================================
// FILE: pages/appraisals.php
// Performance appraisals
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$error = ''; $success = '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id'] : 0;
$get_action = isset($_GET['action']) ? $_GET['action']   : '';

if ($get_action === 'delete' && $get_id) { $conn->query("DELETE FROM appraisals WHERE id=$get_id"); 
//$success = "Appraisal deleted."; 
prg_redirect('appraisals.php', 'Appraisal deleted.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	csrf_verify();
    $emp_id   = (int)$_POST['employee_id'];
    $rev_id   = (int)$_POST['reviewer_id'];
    $p_start  = $conn->real_escape_string($_POST['period_start']);
    $p_end    = $conn->real_escape_string($_POST['period_end']);
    $kpi      = (float)$_POST['kpi_score'];
    $att      = (float)$_POST['attendance_score'];
    $attitude = (float)$_POST['attitude_score'];
    $perf     = (float)$_POST['performance_score'];
    $overall  = round(($kpi + $att + $attitude + $perf) / 4, 1);
    $strengths= $conn->real_escape_string(trim($_POST['strengths']));
    $improve  = $conn->real_escape_string(trim($_POST['improvements']));
    $comments = $conn->real_escape_string(trim($_POST['comments']));
    $status   = $conn->real_escape_string($_POST['status']);
    $rev_val  = $rev_id > 0 ? $rev_id : 'NULL';

    $conn->query("INSERT INTO appraisals
        (employee_id,reviewer_id,period_start,period_end,overall_rating,
         kpi_score,attendance_score,attitude_score,performance_score,
         strengths,improvements,comments,status)
        VALUES ($emp_id,$rev_val,'$p_start','$p_end',$overall,
                $kpi,$att,$attitude,$perf,
                '$strengths','$improve','$comments','$status')");
    //$success = "Appraisal saved.";
	prg_redirect('appraisals.php', 'Appraisal saved.');
}

$employees  = $conn->query("SELECT id, full_name, employee_id FROM employees WHERE employment_status='Active' ORDER BY full_name");
$appraisals = $conn->query("
    SELECT a.*, e.full_name, e.employee_id AS emp_code,
           r.full_name AS reviewer_name
    FROM appraisals a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN employees r ON a.reviewer_id = r.id
    ORDER BY a.created_at DESC
");
$stat_color = array('Draft'=>'secondary','Submitted'=>'warning','Acknowledged'=>'success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Appraisals | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Performance Appraisal</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?=prg_flash()?>
	  <!--<?php //if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $success ?></div><?php //endif; ?>-->
	  
    <div class="row">
      <div class="col-md-5">
        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">New appraisal</h3></div>
          <div class="card-body">
            <form method="POST" action="">
				<?php csrf_field(); ?>
              <div class="form-group"><label>Employee <span class="text-danger">*</span></label>
                <select name="employee_id" class="form-control" required>
                  <option value="">Select...</option>
                  <?php while ($e = $employees->fetch_assoc()): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['employee_id']) ?>)</option><?php endwhile; ?>
                </select>
              </div>
              <div class="form-group"><label>Reviewer</label>
                <select name="reviewer_id" class="form-control">
                  <option value="0">Select reviewer...</option>
                  <?php $employees->data_seek(0); while ($e = $employees->fetch_assoc()): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?></option><?php endwhile; ?>
                </select>
              </div>
              <div class="row">
                <div class="col-6"><div class="form-group"><label>Period start</label><input type="date" name="period_start" class="form-control" value="<?= date('Y-01-01') ?>"></div></div>
                <div class="col-6"><div class="form-group"><label>Period end</label><input type="date" name="period_end" class="form-control" value="<?= date('Y-12-31') ?>"></div></div>
              </div>
              <p style="font-size:12px;color:#6c757d;margin-bottom:6px;">Scores: 1 (Poor) to 5 (Excellent). Overall rating is auto-computed.</p>
              <div class="row">
                <div class="col-6"><div class="form-group"><label>KPI score</label><input type="number" name="kpi_score" class="form-control" step="0.1" min="1" max="5" value="3"></div></div>
                <div class="col-6"><div class="form-group"><label>Attendance score</label><input type="number" name="attendance_score" class="form-control" step="0.1" min="1" max="5" value="3"></div></div>
                <div class="col-6"><div class="form-group"><label>Attitude score</label><input type="number" name="attitude_score" class="form-control" step="0.1" min="1" max="5" value="3"></div></div>
                <div class="col-6"><div class="form-group"><label>Performance score</label><input type="number" name="performance_score" class="form-control" step="0.1" min="1" max="5" value="3"></div></div>
              </div>
              <div class="form-group"><label>Strengths</label><textarea name="strengths" class="form-control" rows="2"></textarea></div>
              <div class="form-group"><label>Areas for improvement</label><textarea name="improvements" class="form-control" rows="2"></textarea></div>
              <div class="form-group"><label>Comments</label><textarea name="comments" class="form-control" rows="2"></textarea></div>
              <div class="form-group"><label>Status</label>
                <select name="status" class="form-control">
                  <?php foreach (array('Draft','Submitted','Acknowledged') as $s): ?><option><?= $s ?></option><?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i> Save appraisal</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-7">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Appraisal records</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0" id="aprTable">
              <thead class="thead-light"><tr><th>Employee</th><th>Reviewer</th><th>Period</th><th class="text-center">Overall</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php while ($ap = $appraisals->fetch_assoc()):
                  $sc = isset($stat_color[$ap['status']]) ? $stat_color[$ap['status']] : 'secondary';
                  $rating = (float)$ap['overall_rating'];
                  $rating_color = $rating >= 4 ? 'success' : ($rating >= 3 ? 'warning' : 'danger');
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($ap['full_name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($ap['emp_code']) ?></small></td>
                  <td><?= htmlspecialchars($ap['reviewer_name'] ? $ap['reviewer_name'] : '—') ?></td>
                  <td style="font-size:12px;"><?= date('M Y', strtotime($ap['period_start'])) ?> - <?= date('M Y', strtotime($ap['period_end'])) ?></td>
                  <td class="text-center"><span class="badge badge-<?= $rating_color ?>" style="font-size:14px;"><?= number_format($rating, 1) ?></span></td>
                  <td><span class="badge badge-<?= $sc ?>"><?= $ap['status'] ?></span></td>
                  <td><a href="appraisals.php?action=delete&id=<?= $ap['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a></td>
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
<script>$(function(){ $('#aprTable').DataTable({ pageLength:15, order:[[2,'desc']] }); });</script>
</body></html>