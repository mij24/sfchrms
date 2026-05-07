<?php
// ============================================
// FILE: pages/my_requests_history.php
// Employee sees all their requests + approval flow
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
// Helper to get token value for use in URLs
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf_token'];
    }
}

$user   = db_fetch($pdo, "SELECT * FROM users WHERE id=?", array((int)$_SESSION['user_id']));
$emp_id = $user ? (int)$user['employee_id'] : 0;
if (!$emp_id) { echo not_linked_error(); exit; }

// Cancel (Pending) or Retract (Pending - not yet acted on by approver)
$get_action = isset($_GET['action']) ? $_GET['action'] : '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id'] : 0;
if (($get_action === 'cancel' || $get_action === 'retract' || $get_action === 'edit') && $get_id) {
    // Token check for GET-based actions
    if (!isset($_GET["_token"]) || !hash_equals(isset($_SESSION["csrf_token"]) ? $_SESSION["csrf_token"] : "", $_GET["_token"])) {
        prg_redirect_error("my_requests_history.php","Invalid request token.");
    }
    $req = db_fetch($pdo, "SELECT * FROM employee_requests WHERE id=? AND employee_id=?", array($get_id, $emp_id));
    if ($req) {
        // Edit: redirect to my_requests.php with pre-filled data (Pending only)
        if ($get_action === 'edit') {
            $req_check = db_fetch($pdo, "SELECT * FROM employee_requests WHERE id=? AND employee_id=?", array($get_id, $emp_id));
            if ($req_check && in_array($req_check['status'], array('Pending','Pending Clarification'))) {
                // Check no approver acted yet
                $acted = ($req_check['status'] === 'Pending')
                    ? (int)db_value($pdo, "SELECT COUNT(*) FROM request_approvals WHERE request_id=? AND acted_by IS NOT NULL", array($get_id))
                    : 0;
                if ($acted === 0) {
                    // Store edit ID in session, redirect to request form
                    $_SESSION['edit_request_id'] = $get_id;
                    prg_redirect('my_requests.php?edit=' . $get_id, '');
                } else {
                    prg_redirect_error('my_requests_history.php', 'Cannot edit — an approver has already acted on this request.');
                }
            }
        }
    // Cancel: status is Pending (not yet routed)
        if ($get_action === 'cancel' && $req['status'] === 'Pending') {
            db_run($pdo, "UPDATE employee_requests SET status='Cancelled' WHERE id=?", array($get_id));
            audit_log($pdo,'UPDATE','employee_requests',$get_id,array('action'=>'cancelled_by_requester'));
            prg_redirect('my_requests_history.php', 'Request cancelled successfully.');
        }
        // Retract: status is Pending AND no approver has acted yet
        if ($get_action === 'retract' && in_array($req['status'], array('Pending','Pending Clarification'))) {
            // Check if any step has been acted on (acted_by is not null)
            $acted = db_value($pdo,
                "SELECT COUNT(*) FROM request_approvals WHERE request_id=? AND acted_by IS NOT NULL",
                array($get_id));
            if ((int)$acted === 0) {
                db_run($pdo, "UPDATE employee_requests SET status='Cancelled' WHERE id=?", array($get_id));
                db_run($pdo, "UPDATE request_approvals SET status='Cancelled' WHERE request_id=?", array($get_id));
                audit_log($pdo,'UPDATE','employee_requests',$get_id,array('action'=>'retracted_by_requester'));
                prg_redirect('my_requests_history.php', 'Request retracted successfully. No approver had acted on it yet.');
            } else {
                prg_redirect_error('my_requests_history.php', 'Cannot retract — an approver has already acted on this request.');
            }
        }
    }
}

$requests = db_fetchall($pdo,
    "SELECT er.*, rt.name AS type_name, rt.category
     FROM employee_requests er
     JOIN request_types rt ON er.request_type_id = rt.id
     WHERE er.employee_id = ?
     ORDER BY er.created_at DESC",
    array($emp_id)
);

$status_color = array(
    'Pending'     => 'warning',
    'Approved'    => 'success',
    'Rejected'    => 'danger',
    'Cancelled'   => 'secondary',
    'Returned'    => 'warning',
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Request History | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .step-row { display:flex; align-items:flex-start; gap:10px; margin-bottom:10px; }
    .step-num { width:28px; height:28px; border-radius:50%; display:flex; align-items:center;
                justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
    .step-content { flex:1; }
    .step-label { font-size:13px; font-weight:600; }
    .step-meta  { font-size:11px; color:#6c757d; }
    .step-pending  { background:#e9ecef; color:#6c757d; }
    .step-approved { background:#d4edda; color:#155724; }
    .step-rejected { background:#f8d7da; color:#721c24; }
    .step-noted    { background:#d1ecf1; color:#0c5460; }
    .step-current  { background:#fff3cd; color:#856404; border:2px solid #ffc107; }
    .connector-line { width:2px; height:12px; background:#dee2e6; margin-left:13px; }
    .can-proceed-badge { background:#28a745; color:#fff; font-size:10px; padding:2px 7px;
                         border-radius:99px; margin-left:6px; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
<div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <?php if (empty($requests)): ?>
      <div class="card"><div class="card-body text-center text-muted py-5">
        <i class="fas fa-file-alt fa-3x mb-3 d-block" style="opacity:.2;"></i>
        No requests yet. <a href="my_requests.php">Submit your first request.</a>
      </div></div>
    <?php endif; ?>

    <?php foreach ($requests as $req):
      $sc = isset($status_color[$req['status']]) ? $status_color[$req['status']] : 'secondary';

      // Load approval steps for this request
      $steps = db_fetchall($pdo,
          "SELECT ra.*, u.username AS actor_name
           FROM request_approvals ra
           LEFT JOIN users u ON ra.acted_by = u.id
           WHERE ra.request_id = ?
           ORDER BY ra.step_order",
          array($req['id'])
      );
    ?>
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <strong><?= htmlspecialchars($req['type_name']) ?></strong>
          <span class="text-muted ml-2" style="font-size:12px;"><?= htmlspecialchars($req['reference_no']) ?></span>
          <span class="badge badge-<?= $sc ?> ml-2"><?= $req['status'] ?></span>
          <?php if ($req['can_proceed']): ?>
            <span class="can-proceed-badge"><i class="fas fa-check mr-1"></i>You may proceed</span>
          <?php endif; ?>
        </div>
        <div style="font-size:12px; color:#6c757d; text-align:right;">
          Filed: <?= date('M d, Y g:i A', strtotime($req['created_at'])) ?>
          <?php if ($req['status'] === 'Pending'): ?>
            <a href="my_requests_history.php?action=edit&id=<?= $req['id'] ?>"
               class="btn btn-primary btn-xs ml-2"
               title="Edit before cancelling">
              <i class="fas fa-pencil-alt mr-1"></i>Edit
            </a>
            <a href="my_requests_history.php?action=cancel&id=<?= $req['id'] ?>&_token=<?= csrf_token() ?>"
               class="btn btn-danger btn-xs ml-2"
               onclick="return confirm('Cancel this request? This cannot be undone.')">
              <i class="fas fa-times mr-1"></i>Cancel
            </a>
          <?php elseif (in_array($req['status'], array('Pending','Pending Clarification'))): ?>
            <?php
            // Show Retract only if NO approver has acted yet
            $can_retract = ((int)db_value($pdo,
                "SELECT COUNT(*) FROM request_approvals WHERE request_id=? AND acted_by IS NOT NULL",
                array($req['id'])) === 0);
            ?>
            <?php if ($can_retract): ?>
            <a href="my_requests_history.php?action=edit&id=<?= $req['id'] ?>"
               class="btn btn-primary btn-xs ml-2"
               title="Edit this request before it is approved">
              <i class="fas fa-pencil-alt mr-1"></i>Edit
            </a>
            <a href="my_requests_history.php?action=retract&id=<?= $req['id'] ?>&_token=<?= csrf_token() ?>"
               class="btn btn-danger btn-xs ml-2"
               onclick="return confirm('Cancel this request?\n\nThis will withdraw the request before any approver acts on it.')">
              <i class="fas fa-times mr-1"></i>Cancel
            </a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body">
        <div class="row">
          <!-- Request details -->
          <div class="col-md-4">
            <table class="table table-sm table-borderless mb-0" style="font-size:13px;">
              <?php if ($req['date_from']): ?>
              <tr><td class="text-muted" style="width:90px;">Date from</td>
                  <td><strong><?= date('M d, Y', strtotime($req['date_from'])) ?></strong></td></tr>
              <?php endif; ?>
              <?php if ($req['date_to']): ?>
              <tr><td class="text-muted">Date to</td>
                  <td><strong><?= date('M d, Y', strtotime($req['date_to'])) ?></strong></td></tr>
              <?php endif; ?>
              <?php if ($req['days_applied']): ?>
              <tr><td class="text-muted">Days</td>
                  <td><strong><?= $req['days_applied'] ?> day(s)</strong></td></tr>
              <?php endif; ?>
              <?php if ($req['time_from']): ?>
              <tr><td class="text-muted">Time</td>
                  <td><strong><?= date('h:i A', strtotime($req['time_from'])) ?> – <?= date('h:i A', strtotime($req['time_to'])) ?></strong></td></tr>
              <?php endif; ?>
              <tr><td class="text-muted">Purpose</td>
                  <td><?= nl2br(htmlspecialchars($req['purpose'])) ?></td></tr>
            </table>
          </div>

          <!-- Approval flow -->
          <div class="col-md-8">
            <div style="font-size:11px;font-weight:600;color:#6c757d;text-transform:uppercase;margin-bottom:10px;">
              Approval Flow
            </div>
            <?php if (empty($steps)): ?>
              <p class="text-muted" style="font-size:13px;">No approval steps configured for this request.</p>
            <?php endif; ?>

            <?php foreach ($steps as $idx => $step):
              $is_current = ($req['current_step'] == $step['step_order'] && in_array($req['status'], array('Pending','Pending Clarification')));
              $step_class = 'step-pending';
              $icon       = 'fas fa-clock';
              if ($step['status'] === 'Approved') { $step_class = 'step-approved'; $icon = 'fas fa-check'; }
              elseif ($step['status'] === 'Rejected') { $step_class = 'step-rejected'; $icon = 'fas fa-times'; }
              elseif ($step['status'] === 'Noted')    { $step_class = 'step-noted';    $icon = 'fas fa-eye'; }
              elseif ($is_current)                    { $step_class = 'step-current';  $icon = 'fas fa-hourglass-half'; }
            ?>
            <?php if ($idx > 0): ?><div class="connector-line"></div><?php endif; ?>
            <div class="step-row">
              <div class="step-num <?= $step_class ?>">
                <i class="<?= $icon ?>" style="font-size:10px;"></i>
              </div>
              <div class="step-content">
                <div class="step-label">
                  <?= htmlspecialchars($step['step_label']) ?>
                  <span style="font-size:10px;color:#888;font-weight:400;"> — <?= htmlspecialchars($step['action_label']) ?></span>
                  <?php if ($step['can_proceed_after_this'] && $step['status'] !== 'Pending'): ?>
                    <span class="can-proceed-badge"><i class="fas fa-walking mr-1"></i>Can proceed</span>
                  <?php endif; ?>
                  <?php if ($is_current): ?>
                    <span class="badge badge-warning ml-1" style="font-size:10px;">Awaiting</span>
                  <?php endif; ?>
                </div>
                <?php if ($step['acted_by']): ?>
                <div class="step-meta">
                  <i class="fas fa-user mr-1"></i><?= htmlspecialchars($step['actor_name']) ?>
                  &nbsp;·&nbsp;
                  <i class="fas fa-clock mr-1"></i><?= date('M d, Y g:i A', strtotime($step['acted_at'])) ?>
                  <?php if ($step['remarks']): ?>
                    &nbsp;·&nbsp;
                    <i class="fas fa-comment mr-1"></i>
                    <em><?= htmlspecialchars($step['remarks']) ?></em>
                  <?php endif; ?>
                </div>
                <?php elseif ($is_current): ?>
                <div class="step-meta">Waiting for action from this office.</div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>