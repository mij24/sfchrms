<?php
// ============================================
// FILE: pages/profile_requests.php
// HR reviews employee self-update requests
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
require_role(ROLE_HR);

$get_id     = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$get_action = isset($_GET['action']) ? $_GET['action']    : '';

if ($get_action === 'approve' && $get_id) {
    csrf_verify();
    $req = db_fetch($pdo, "SELECT * FROM employee_self_updates WHERE id=?", array($get_id));
    if ($req) {
        $data = json_decode($req['submitted_data'], true);
        if ($data) {
            foreach ($data as $field => $vals) {
                $new = clean_string($vals['new'], 500);
                db_run($pdo, "UPDATE employees SET `$field`=? WHERE id=?",
                    array($new, $req['employee_id']));
            }
        }
        db_run($pdo,
            "UPDATE employee_self_updates SET status='Approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?",
            array((int)$_SESSION['user_id'], $get_id)
        );
        prg_redirect('profile_requests.php','Profile update approved and applied.');
    }
}

if ($get_action === 'reject' && $get_id) {
	$notes = clean_string(isset($_GET['notes']) ? $_GET['notes'] : '', 500);
    //$notes = clean_string($_GET['notes']??'', 500);
    db_run($pdo,
        "UPDATE employee_self_updates SET status='Rejected', reviewed_by=?, reviewed_at=NOW(), reviewer_notes=? WHERE id=?",
        array((int)$_SESSION['user_id'], $notes, $get_id)
    );
    prg_redirect('profile_requests.php','Profile update rejected.');
}

$requests = db_fetchall($pdo,
    "SELECT esu.*, e.full_name, e.employee_id AS emp_code
     FROM employee_self_updates esu
     JOIN employees e ON esu.employee_id=e.id
     ORDER BY esu.created_at DESC"
);
$stat_color = array('Pending'=>'warning','Approved'=>'success','Rejected'=>'danger');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profile Requests | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Profile Edit Requests</h1></div></div>
  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Employee profile update requests</h3></div>
      <div class="card-body" style="padding:16px 16px 0;">
        <table class="table table-sm table-hover dt-export" id="reqTable">
          <thead class="thead-light">
            <tr><th>Employee</th><th>Section</th><th>Changes requested</th><th>Status</th><th>Submitted</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $r):
              $sc = isset($stat_color[$r['status']]) ? $stat_color[$r['status']] : 'secondary';
              $data = json_decode($r['submitted_data'], true);
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['full_name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($r['emp_code']) ?></small></td>
              <td><?= htmlspecialchars($r['section']) ?></td>
              <td style="font-size:12px;">
                <?php if ($data): foreach ($data as $field => $vals): ?>
                  <div><strong><?= htmlspecialchars($field) ?>:</strong>
                    <span class="text-danger" style="text-decoration:line-through;"><?= htmlspecialchars($vals['old']?:'—') ?></span>
                    &rarr; <span class="text-success"><?= htmlspecialchars($vals['new']) ?></span>
                  </div>
                <?php endforeach; endif; ?>
              </td>
              <td><span class="badge badge-<?= $sc ?>"><?= $r['status'] ?></span></td>
              <td><?= date('M d, Y',strtotime($r['created_at'])) ?></td>
              <td>
                <?php if ($r['status'] === 'Pending'): ?>
                <a href="profile_requests.php?action=approve&id=<?= $r['id'] ?>"
                   class="btn btn-success btn-xs"
                   onclick="return confirm('Apply these changes to the employee record?')">
                  <i class="fas fa-check"></i> Approve
                </a>
                <a href="profile_requests.php?action=reject&id=<?= $r['id'] ?>"
                   class="btn btn-danger btn-xs"
                   onclick="return confirm('Reject this update request?')">
                  <i class="fas fa-times"></i> Reject
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>