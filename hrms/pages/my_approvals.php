<?php
// ============================================
// FILE: pages/my_approvals.php
// Tabs: Pending | Approved | Rejected | Cancelled
// Flow hidden by default, shown in View modal
// Edit + Cancel buttons per card (context-aware)
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$my_uid  = (int)$_SESSION['user_id'];
$my_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';

// CSRF token helper for GET links
if (!function_exists('csrf_token_val')) {
    function csrf_token_val() {
        if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
        return $_SESSION['csrf_token'];
    }
}

// ── Handle cancel/retract from this page ─────────────────────
$get_action = isset($_GET['action']) ? clean_string($_GET['action'], '20') : '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id']                    : 0;
if (($get_action === 'cancel' || $get_action === 'retract') && $get_id) {
    $tok = isset($_GET['_token']) ? $_GET['_token'] : '';
    if (!hash_equals(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '', $tok)) {
        prg_redirect_error('my_approvals.php', 'Invalid token.');
    }
    $req = db_fetch($pdo, "SELECT * FROM employee_requests WHERE id=?", array($get_id));
    if ($req) {
        $acted = (int)db_value($pdo,
            "SELECT COUNT(*) FROM request_approvals WHERE request_id=? AND acted_by IS NOT NULL",
            array($get_id));
        if ($acted === 0) {
            db_run($pdo, "UPDATE employee_requests SET status='Cancelled' WHERE id=?", array($get_id));
            db_run($pdo, "UPDATE request_approvals SET status='Cancelled' WHERE request_id=?", array($get_id));
            audit_log($pdo, 'UPDATE', 'employee_requests', $get_id, array('action' => 'cancelled_via_approvals'));
            prg_redirect('my_approvals.php', 'Request cancelled successfully.');
        } else {
            prg_redirect_error('my_approvals.php', 'Cannot cancel — an approver has already acted on this request.');
        }
    }
}

// ── Handle POST: edit/update request ─────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['post_action']) && $_POST['post_action']==='edit_request') {
    csrf_verify();
    $edit_id = (int)(isset($_POST['edit_id']) ? $_POST['edit_id'] : 0);
    if ($edit_id) {
        // Verify still editable (no approver acted)
        $acted_chk = (int)db_value($pdo,
            "SELECT COUNT(*) FROM request_approvals WHERE request_id=? AND acted_by IS NOT NULL",
            array($edit_id));
        if ($acted_chk > 0) {
            prg_redirect_error('my_approvals.php','Cannot edit — an approver has already acted on this request.');
        }
        $e_type_id   = (int)(isset($_POST['request_type_id']) ? $_POST['request_type_id'] : 0);
        $e_date_from = !empty($_POST['date_from']) ? clean_date($_POST['date_from']) : null;
        $e_date_to   = !empty($_POST['date_to'])   ? clean_date($_POST['date_to'])   : null;
        $e_days      = !empty($_POST['days_applied']) ? (float)$_POST['days_applied'] : null;
        $e_time_from = !empty($_POST['time_from']) ? clean_string($_POST['time_from'],10) : null;
        $e_time_to   = !empty($_POST['time_to'])   ? clean_string($_POST['time_to'],10)   : null;
        $e_purpose   = clean_string(isset($_POST['purpose']) ? $_POST['purpose'] : '', 1000);
        if (empty($e_purpose)) {
            prg_redirect_error('my_approvals.php','Purpose / Reason is required.');
        }
        db_run($pdo,
            "UPDATE employee_requests
             SET request_type_id=?, date_from=?, date_to=?, days_applied=?,
                 time_from=?, time_to=?, purpose=?, updated_at=NOW()
             WHERE id=?",
            array($e_type_id,$e_date_from,$e_date_to,$e_days,$e_time_from,$e_time_to,$e_purpose,$edit_id));
        audit_log($pdo,'UPDATE','employee_requests',$edit_id,array('action'=>'edited_by_requester'));
        prg_redirect('my_approvals.php','Request updated successfully.');
    }
}

// ── Load all request data ─────────────────────────────────────
$base_select = "SELECT er.*, rt.name AS type_name, rt.category,
        e.full_name AS requester_name, d.name AS dept_name
        FROM employee_requests er
        JOIN request_types rt ON er.request_type_id=rt.id
        JOIN employees e ON er.employee_id=e.id
        LEFT JOIN departments d ON e.department_id=d.id";

$inprogress = array(); $approved = array(); $rejected = array(); $cancelled = array();
try {
    $inprogress = db_fetchall($pdo, "$base_select WHERE er.status IN ('Pending','Pending Clarification') ORDER BY er.created_at DESC") ?: array();
    $approved   = db_fetchall($pdo, "$base_select WHERE er.status='Approved' ORDER BY er.updated_at DESC LIMIT 50") ?: array();
    $rejected   = db_fetchall($pdo, "$base_select WHERE er.status IN ('Denied','Rejected','Returned') ORDER BY er.updated_at DESC LIMIT 50") ?: array();
    $cancelled  = db_fetchall($pdo, "$base_select WHERE er.status IN ('Cancelled') ORDER BY er.updated_at DESC LIMIT 50") ?: array();
} catch(Exception $e) {}

// ── Load approval steps for ALL requests ─────────────────────
function load_all_steps(PDO $pdo, array ...$groups) {
    $all_reqs = array_merge(...$groups);
    if (empty($all_reqs)) return array();
    $ids = implode(',', array_unique(array_map(function($r){ return (int)$r['id']; }, $all_reqs)));
    $rows = db_fetchall($pdo,
        "SELECT ra.*, u.username AS actor_uname, e.full_name AS actor_name_full
         FROM request_approvals ra
         LEFT JOIN users u ON ra.acted_by=u.id
         LEFT JOIN employees e ON u.employee_id=e.id
         WHERE ra.request_id IN ($ids) ORDER BY ra.request_id, ra.step_order") ?: array();
    $out = array();
    foreach ($rows as $r) {
        $r['actor_name'] = $r['actor_name_full'] ?: $r['actor_uname'] ?: '—';
        $out[$r['request_id']][] = $r;
    }
    return $out;
}
$all_steps = load_all_steps($pdo, $inprogress, $approved, $rejected, $cancelled);

// ── acted_by check per request ────────────────────────────────
// Pre-load which requests have been acted on
$acted_map = array();
$all_ids_flat = array_merge(
    array_column($inprogress,'id'), array_column($approved,'id'),
    array_column($rejected,'id'),   array_column($cancelled,'id')
);
if (!empty($all_ids_flat)) {
    $ids_str = implode(',', array_map('intval', $all_ids_flat));
    try {
        $acted_rows = db_fetchall($pdo,
            "SELECT request_id, COUNT(*) AS cnt FROM request_approvals
             WHERE request_id IN ($ids_str) AND acted_by IS NOT NULL
             GROUP BY request_id") ?: array();
        foreach ($acted_rows as $ar) {
            $acted_map[(int)$ar['request_id']] = (int)$ar['cnt'];
        }
    } catch(Exception $e) {}
}

$status_color = array(
    'Pending'   => 'warning',
    'Pending Clarification' => 'orange',
    'Returned'  => 'secondary',
    'Approved'  => 'success',
    'Confirmed' => 'success',
    'Endorsed'  => 'info',
    'Verified'  => 'info',
    'Noted'     => 'info',
    'Denied'    => 'danger',
    'Rejected'  => 'danger',
    'Cancelled' => 'secondary',
    'Cancelled'   => 'secondary',
);

$active_tab = isset($_GET['tab']) ? clean_string($_GET['tab'], '20') : 'inprogress';

// ── Request types for New Request modal ──────────────────────
$req_types_grouped = array();
try {
    $rt_rows = db_fetchall($pdo,
        "SELECT MIN(id) AS id, name, category,
                MAX(requires_days) AS requires_days,
                MAX(requires_time) AS requires_time
         FROM request_types WHERE is_active=1
         GROUP BY name, category ORDER BY category, name") ?: array();
    foreach ($rt_rows as $rt) {
        $cat = trim(isset($rt['category']) ? $rt['category'] : '');
        $req_types_grouped[$cat][] = $rt;
    }
} catch(Exception $e) {}

$my_user_data  = db_fetch($pdo, "SELECT * FROM users WHERE id=?", array($my_uid));
$my_emp_id     = $my_user_data ? (int)$my_user_data['employee_id'] : 0;
$leave_credits = $my_emp_id ? db_fetch($pdo,
    "SELECT * FROM leave_credits WHERE employee_id=? AND year=YEAR(CURDATE())",
    array($my_emp_id)) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Approvals | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .appr-card {
      border-radius:8px;border:1px solid #e9ecef;padding:14px 16px;
      margin-bottom:10px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.05);
      transition:.15s;
    }
    .appr-card:hover { box-shadow:0 3px 12px rgba(0,0,0,.1); }
    .tab-count { font-size:9px;padding:2px 5px;border-radius:8px;margin-left:4px; }
    .req-ref { font-family:monospace;font-size:11px;background:#f8f9fa;padding:2px 6px;border-radius:4px; }
    /* Flow (inside modal only) */
    .flow-step { display:flex;align-items:flex-start;gap:10px;margin-bottom:10px; }
    .flow-icon { width:28px;height:28px;border-radius:50%;display:flex;align-items:center;
      justify-content:center;font-size:11px;flex-shrink:0; }
    .flow-icon.done     { background:#d4edda;color:#155724; }
    .flow-icon.active   { background:#fff3cd;color:#856404;border:2px solid #ffc107; }
    .flow-icon.pending  { background:#f8f9fa;color:#6c757d; }
    .flow-icon.rejected { background:#f8d7da;color:#721c24; }
    /* Buttons */
    .btn-xs { padding:2px 8px;font-size:11px;border-radius:4px; }
    .badge-orange { background-color:#fd7e14;color:#fff; }
    .btn-row { display:flex;gap:5px;align-items:center;flex-wrap:wrap; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
<div class="content"><div class="container-fluid" style="padding-top:20px;">
  <?= prg_flash() ?>

  <!-- Page header -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Approvals</h5>
    <button type="button" class="btn btn-primary btn-sm"
            data-toggle="modal" data-target="#modalNewRequestAppr">
      <i class="fas fa-plus mr-1"></i>New Request
    </button>
  </div>

  <div class="card">
    <div class="card-header p-0">
      <ul class="nav nav-tabs">
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='inprogress'?'active':'' ?>"
             data-toggle="tab" href="#apv-inprogress">
            <i class="fas fa-hourglass-half text-warning mr-1"></i>Pending
            <?php if (count($inprogress)): ?>
            <span class="badge badge-warning tab-count"><?= count($inprogress) ?></span>
            <?php endif; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='approved'?'active':'' ?>"
             data-toggle="tab" href="#apv-approved">
            <i class="fas fa-check-circle text-success mr-1"></i>Approved
            <span class="badge badge-success tab-count"><?= count($approved) ?></span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='rejected'?'active':'' ?>"
             data-toggle="tab" href="#apv-denied">
            <i class="fas fa-times-circle text-danger mr-1"></i>Denied
            <?php if (count($rejected)): ?>
            <span class="badge badge-danger tab-count"><?= count($rejected) ?></span>
            <?php endif; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='cancelled'?'active':'' ?>"
             data-toggle="tab" href="#apv-cancelled">
            <i class="fas fa-ban text-secondary mr-1"></i>Cancelled
            <span class="badge badge-secondary tab-count"><?= count($cancelled) ?></span>
          </a>
        </li>
      </ul>
    </div><!-- /.card-header -->

    <div class="card-body tab-content p-3">
      <?php
      // ─────────────────────────────────────────────────────────
      // Card renderer — no flow visible, Edit+Cancel context-aware
      // ─────────────────────────────────────────────────────────
      function appr_card($req, $steps, $sc, $acted_map) {
          $rid    = (int)$req['id'];
          $acted  = isset($acted_map[$rid]) ? $acted_map[$rid] : 0;
          // can_act: employee can Edit/Cancel if:
          // - No approver has acted yet (Pending, step 1) OR
          // - Request was returned (Pending Clarification) — employee must be able to edit/cancel
          $can_act = (
              ($acted === 0 && $req['status'] === 'Pending') ||
              ($req['status'] === 'Pending Clarification' && $req['employee_id'] == $my_emp_id)
          );
          $tok    = csrf_token_val();

          // Build JS-safe steps JSON for the modal
          $steps_js = array();
          foreach ($steps as $s) {
              $steps_js[] = array(
                  'step_label' => $s['step_label'],
                  'action_label'=> isset($s['action_label']) ? $s['action_label'] : '',
                  'status'     => $s['status'],
                  'step_order' => (int)$s['step_order'],
                  'actor_name' => $s['actor_name'],
                  'acted_at'   => $s['acted_at'],
                  'remarks'    => isset($s['remarks']) ? $s['remarks'] : '',
              );
          }
          $modal_data = json_encode(array(
              'id'              => $rid,
              'requester_name'  => $req['requester_name'],
              'dept_name'       => $req['dept_name'] ?: '—',
              'type_name'       => $req['type_name'],
              'type_id'         => (int)$req['request_type_id'],
              'category'        => isset($req['category']) ? $req['category'] : '',
              'reference_no'    => $req['reference_no'],
              'status'          => $req['status'],
              'status_color'    => $sc,
              'created_at'      => date('M d, Y g:i A', strtotime($req['created_at'])),
              // Display dates (formatted)
              'date_from_disp'  => $req['date_from'] ? date('M d, Y', strtotime($req['date_from'])) : '',
              'date_to_disp'    => ($req['date_to'] && $req['date_to'] != $req['date_from']) ? date('M d, Y', strtotime($req['date_to'])) : '',
              // Raw dates for form pre-fill
              'date_from_raw'   => $req['date_from'] ?: '',
              'date_to_raw'     => $req['date_to'] ?: '',
              'days_applied'    => $req['days_applied'] ?: '',
              'time_from'       => $req['time_from'] ?: '',
              'time_to'         => $req['time_to'] ?: '',
              'purpose'         => $req['purpose'] ?: '',
              'rejection_reason'=> $req['rejection_reason'] ?: '',
              'current_step'    => (int)$req['current_step'],
              'can_act'         => $can_act,
              'cancel_url'      => "my_approvals.php?action=cancel&id={$rid}&_token={$tok}",
              'steps'           => $steps_js,
          ));
          $modal_data_esc = htmlspecialchars($modal_data, ENT_QUOTES, 'UTF-8');
          ?>
          <div class="appr-card">
            <div class="d-flex justify-content-between align-items-start">
              <div style="flex:1;min-width:0;margin-right:12px;">
                <div class="d-flex align-items-center flex-wrap" style="gap:6px;">
                  <span style="font-weight:700;font-size:14px;"><?= htmlspecialchars($req['requester_name']) ?></span>
                  <span class="badge badge-<?= $sc ?>" style="font-size:10px;"><?= $req['status'] ?></span>
                </div>
                <div style="font-size:12px;color:#6c757d;margin-top:3px;">
                  <?= htmlspecialchars($req['dept_name'] ?: '—') ?>
                  &middot;
                  <strong><?= htmlspecialchars($req['type_name']) ?></strong>
                </div>
                <div style="margin-top:5px;">
                  <span class="req-ref"><?= htmlspecialchars($req['reference_no']) ?></span>
                  <span class="text-muted ml-2" style="font-size:11px;"><?= date('M d, Y', strtotime($req['created_at'])) ?></span>
                  <?php if ($req['date_from']): ?>
                  <span class="text-muted ml-2" style="font-size:11px;">
                    <i class="fas fa-calendar mr-1"></i><?= date('M d', strtotime($req['date_from'])) ?>
                    <?php if ($req['date_to'] && $req['date_to'] != $req['date_from']): ?>
                    – <?= date('M d, Y', strtotime($req['date_to'])) ?>
                    <?php endif; ?>
                    <?php if ($req['days_applied']): ?>(<?= $req['days_applied'] ?>d)<?php endif; ?>
                  </span>
                  <?php endif; ?>
                </div>
                <?php if ($req['purpose']): ?>
                <div style="font-size:12px;color:#6c757d;margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:500px;">
                  <i class="fas fa-comment mr-1"></i><?= htmlspecialchars($req['purpose']) ?>
                </div>
                <?php endif; ?>
                <?php if (in_array($req['status'], array('Rejected','Returned')) && $req['rejection_reason']): ?>
                <div style="font-size:11px;color:#721c24;background:#f8d7da;padding:4px 8px;border-radius:4px;margin-top:6px;">
                  <i class="fas fa-exclamation-circle mr-1"></i>
                  <em>"<?= htmlspecialchars(substr($req['rejection_reason'], 0, 120)) ?>"</em>
                </div>
                <?php endif; ?>

                <!-- Pending Clarification: show note + resubmit form -->
                <?php if ($req['status'] === 'Pending Clarification'): ?>
                <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:10px 12px;margin-top:8px;">
                  <?php $_clarif_label = 'Pending Clarification — Approver Note'; ?>
                  <div style="font-size:12px;font-weight:700;color:#856404;margin-bottom:4px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i><?= $_clarif_label ?>
                  </div>
                  <div style="font-size:12px;color:#856404;margin-bottom:8px;">
                    <?= htmlspecialchars($req['rejection_reason'] ?: 'An approver has returned this request with a note.') ?>
                  </div>
                  <!-- Resubmit form -->
                  <?php
                  $is_own = ($req['employee_id'] == $my_emp_id);
                  if ($is_own):
                  ?>
                  <form method="POST" action="employee_requests.php" style="margin-top:6px;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="form_action" value="resubmit_clarification">
                    <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                    <input type="hidden" name="ra_id" value="0">
                    <div class="form-group mb-2">
                      <textarea name="resubmit_note" class="form-control form-control-sm" rows="2"
                                placeholder="Add a note explaining the clarification (optional)..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm"
                            onclick="return confirm('Resubmit this request for review?')">
                      <i class="fas fa-paper-plane mr-1"></i>Resubmit for Review
                    </button>
                  </form>
                  <?php else: ?>
                  <div style="font-size:11px;color:#856404;font-style:italic;">
                    Waiting for employee to address the clarification and resubmit.
                  </div>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </div>

              <!-- Action buttons -->
              <div class="btn-row" style="flex-shrink:0;">
                <!-- View (always) -->
                <button type="button" class="btn btn-info btn-sm"
                        onclick="openViewModal(<?= $modal_data_esc ?>)">
                  <i class="fas fa-eye mr-1"></i>View
                </button>
                <!-- Edit (only if no approver acted yet) -->
                <?php if ($can_act): ?>
                <button type="button" class="btn btn-primary btn-sm"
                        onclick="openEditModal(<?= $modal_data_esc ?>)"
                        title="Edit — no approver has acted yet">
                  <i class="fas fa-pencil-alt mr-1"></i>Edit
                </button>
                <a href="my_approvals.php?action=cancel&id=<?= $rid ?>&_token=<?= $tok ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Cancel this request? This cannot be undone.')"
                   title="Cancel — no approver has acted yet">
                  <i class="fas fa-times mr-1"></i>Cancel
                </a>
                <?php endif; // $can_act — disabled buttons are hidden, not shown ?>
              </div>
            </div>
          </div>
          <?php
      }

      // ── Pane renderer ──────────────────────────────────────
      function appr_pane($pane_id, $active, $requests, $all_steps, $status_color, $acted_map) {
          ?>
          <div class="tab-pane fade <?= $active ? 'show active' : '' ?>" id="<?= $pane_id ?>">
          <?php if (empty($requests)): ?>
          <div class="text-center text-muted py-5">
            <i class="fas fa-folder-open fa-2x d-block mb-2" style="opacity:.3;"></i>
            <div style="font-size:13px;">Nothing here.</div>
          </div>
          <?php else: ?>
          <?php foreach ($requests as $req):
            $sc    = isset($status_color[$req['status']]) ? $status_color[$req['status']] : 'secondary';
            $steps = isset($all_steps[$req['id']]) ? $all_steps[$req['id']] : array();
            appr_card($req, $steps, $sc, $acted_map);
          endforeach; ?>
          <?php endif; ?>
          </div>
          <?php
      }
      ?>

      <?php appr_pane('apv-inprogress', $active_tab==='inprogress', $inprogress, $all_steps, $status_color, $acted_map); ?>
      <?php appr_pane('apv-approved',   $active_tab==='approved',   $approved,   $all_steps, $status_color, $acted_map); ?>
      <?php appr_pane('apv-denied',   $active_tab==='rejected',   $rejected,   $all_steps, $status_color, $acted_map); ?>
      <?php appr_pane('apv-cancelled',  $active_tab==='cancelled',  $cancelled,  $all_steps, $status_color, $acted_map); ?>

    </div><!-- /.tab-content -->
  </div><!-- /.card -->

</div></div>
</div><!-- /.content-wrapper -->

<!-- ══════════════════════════════════════════════════════════ -->
<!-- VIEW REQUEST MODAL — Full details + flow + Edit/Cancel    -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalViewRequest" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2" id="viewModalHeader" style="background:#17a2b8;">
        <h6 class="modal-title text-white mb-0">
          <i class="fas fa-file-alt mr-2"></i>
          <span id="viewModalTitle">Request Details</span>
        </h6>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body" id="viewModalBody">
        <!-- Filled by JS -->
      </div>
      <div class="modal-footer py-2 d-flex justify-content-between align-items-center">
        <div id="viewModalActions" class="btn-row">
          <!-- Edit/Cancel filled by JS -->
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ New Request Modal ══ -->
<div class="modal fade" id="modalNewRequestAppr" tabindex="-1" data-backdrop="static">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white py-2">
        <h5 class="modal-title mb-0"><i class="fas fa-plus mr-2"></i>New Request</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="my_requests.php" enctype="multipart/form-data">
        <?php csrf_field(); ?>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Request Type <span class="text-danger">*</span></label>
                <select name="request_type_id" class="form-control form-control-sm" required id="apprTypeSelect">
                  <option value="">— Select type —</option>
                  <?php foreach ($req_types_grouped as $cat => $items): ?>
                  <?php if ($cat): ?><optgroup label="── <?= htmlspecialchars($cat) ?> ──"><?php endif; ?>
                  <?php foreach ($items as $rt): ?>
                  <option value="<?= $rt['id'] ?>"
                          data-days="<?= $rt['requires_days'] ?>"
                          data-time="<?= $rt['requires_time'] ?>">
                    <?= htmlspecialchars($rt['name']) ?>
                  </option>
                  <?php endforeach; ?>
                  <?php if ($cat): ?></optgroup><?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6" id="apprDatesSection" style="display:none;">
              <div class="form-group mb-1">
                <label style="font-size:12px;font-weight:600;">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm">
              </div>
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm">
              </div>
            </div>
          </div>
          <div id="apprTimeSection" class="row" style="display:none;">
            <div class="col-md-3">
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Time From</label>
                <input type="time" name="time_from" class="form-control form-control-sm">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Time To</label>
                <input type="time" name="time_to" class="form-control form-control-sm">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label style="font-size:12px;font-weight:600;">Purpose / Reason <span class="text-danger">*</span></label>
            <textarea name="purpose" class="form-control form-control-sm" rows="3"
                      placeholder="State the purpose or reason for this request..." required></textarea>
          </div>
          <?php if ($leave_credits): ?>
          <div class="alert alert-light p-2 mb-0" style="font-size:12px;">
            <i class="fas fa-info-circle text-info mr-1"></i>
            Leave credits — Vacation:
            <strong><?= max(0, (isset($leave_credits['vacation_leave'])?(float)$leave_credits['vacation_leave']:0) - (isset($leave_credits['vacation_used'])?(float)$leave_credits['vacation_used']:0)) ?></strong> days
            &nbsp;|&nbsp; Sick:
            <strong><?= max(0, (isset($leave_credits['sick_leave'])?(float)$leave_credits['sick_leave']:0) - (isset($leave_credits['sick_used'])?(float)$leave_credits['sick_used']:0)) ?></strong> days
          </div>

          <?php endif; ?>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="fas fa-paper-plane mr-1"></i>Submit Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- EDIT REQUEST MODAL — pre-filled, POSTs update             -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditRequest" tabindex="-1" data-backdrop="static">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white py-2">
        <h6 class="modal-title mb-0">
          <i class="fas fa-pencil-alt mr-2"></i>Edit Request
          <small class="ml-2 opacity-75" id="editModalRef" style="font-size:11px;opacity:.7;"></small>
        </h6>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="my_approvals.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="post_action" value="edit_request">
        <input type="hidden" name="edit_id" id="editReqId" value="">
        <div class="modal-body">

          <div class="row">
            <!-- Request Type -->
            <div class="col-md-6">
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">
                  Request Type <span class="text-danger">*</span>
                </label>
                <select name="request_type_id" id="editTypeSelect"
                        class="form-control form-control-sm" required>
                  <option value="">— Select type —</option>
                  <?php foreach ($req_types_grouped as $cat => $items): ?>
                  <?php if ($cat): ?>
                  <optgroup label="── <?= htmlspecialchars($cat) ?> ──">
                  <?php endif; ?>
                  <?php foreach ($items as $rt): ?>
                  <option value="<?= $rt['id'] ?>"
                          data-days="<?= $rt['requires_days'] ?>"
                          data-time="<?= $rt['requires_time'] ?>">
                    <?= htmlspecialchars($rt['name']) ?>
                  </option>
                  <?php endforeach; ?>
                  <?php if ($cat): ?></optgroup><?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Days applied -->
            <div class="col-md-3" id="editDaysCol">
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Days Applied</label>
                <input type="number" name="days_applied" id="editDays"
                       class="form-control form-control-sm" min="0.5" step="0.5">
              </div>
            </div>
          </div>

          <!-- Date range -->
          <div class="row" id="editDatesRow">
            <div class="col-md-4">
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Date From</label>
                <input type="date" name="date_from" id="editDateFrom"
                       class="form-control form-control-sm">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Date To</label>
                <input type="date" name="date_to" id="editDateTo"
                       class="form-control form-control-sm">
              </div>
            </div>
          </div>

          <!-- Time range -->
          <div class="row" id="editTimeRow" style="display:none;">
            <div class="col-md-3">
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Time From</label>
                <input type="time" name="time_from" id="editTimeFrom"
                       class="form-control form-control-sm">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Time To</label>
                <input type="time" name="time_to" id="editTimeTo"
                       class="form-control form-control-sm">
              </div>
            </div>
          </div>

          <!-- Purpose -->
          <div class="form-group">
            <label style="font-size:12px;font-weight:600;">
              Purpose / Reason <span class="text-danger">*</span>
            </label>
            <textarea name="purpose" id="editPurpose"
                      class="form-control form-control-sm" rows="4"
                      placeholder="State the purpose or reason for this request..."
                      required></textarea>
          </div>

          <!-- Warning notice -->
          <div class="alert alert-warning py-2 mb-0" style="font-size:12px;">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Changes will only be saved if no approver has acted on this request yet.
            The request will remain in its current status.
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">
            Discard Changes
          </button>
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="fas fa-save mr-1"></i>Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
var currentData = null;

// ── View modal ────────────────────────────────────────────────
function openViewModal(data) {
    currentData = data;
    var statusColors = {
        'Pending':'#ffc107','Pending Clarification':'#fd7e14','Approved':'#28a745',
        'Rejected':'#dc3545','Returned':'#ffc107','Cancelled':'#6c757d'
    };
    var headerBg = statusColors[data.status] || '#17a2b8';
    document.getElementById('viewModalHeader').style.background = headerBg;
    document.getElementById('viewModalTitle').textContent = data.type_name + ' — ' + data.requester_name;

    // Build body HTML
    var html = '';

    // Info block
    html += '<div class="row mb-3">';
    html += '<div class="col-md-6">';
    html += '<table class="table table-sm table-borderless" style="font-size:13px;">';
    html += '<tr><td class="text-muted" style="width:110px;">Requester</td><td><strong>' + esc(data.requester_name) + '</strong></td></tr>';
    html += '<tr><td class="text-muted">Department</td><td>' + esc(data.dept_name) + '</td></tr>';
    html += '<tr><td class="text-muted">Type</td><td>' + esc(data.type_name) + '</td></tr>';
    html += '<tr><td class="text-muted">Reference</td><td><code>' + esc(data.reference_no) + '</code></td></tr>';
    html += '<tr><td class="text-muted">Status</td><td><span class="badge badge-' + data.status_color + '">' + esc(data.status) + '</span></td></tr>';
    html += '</table>';
    html += '</div>';
    html += '<div class="col-md-6">';
    html += '<table class="table table-sm table-borderless" style="font-size:13px;">';
    html += '<tr><td class="text-muted" style="width:90px;">Filed</td><td>' + esc(data.created_at) + '</td></tr>';
    if (data.date_from_disp) {
        html += '<tr><td class="text-muted">Date</td><td>' + esc(data.date_from_disp);
        if (data.date_to_disp) html += ' – ' + esc(data.date_to_disp);
        if (data.days_applied) html += ' <span class="text-muted">(' + esc(data.days_applied) + ' day(s))</span>';
        html += '</td></tr>';
    }
    if (data.purpose) {
        html += '<tr><td class="text-muted">Purpose</td><td style="white-space:pre-wrap;">' + esc(data.purpose) + '</td></tr>';
    }
    html += '</table>';
    html += '</div>';
    html += '</div>';

    // Rejection reason
    if (data.rejection_reason) {
        html += '<div class="alert alert-danger py-2 mb-3" style="font-size:12px;">';
        html += '<i class="fas fa-exclamation-circle mr-1"></i><strong>Reason:</strong> ' + esc(data.rejection_reason);
        html += '</div>';
    }

    // Approval flow
    if (data.steps && data.steps.length > 0) {
        html += '<div style="background:#f8f9fa;border-radius:8px;padding:14px 16px;">';
        html += '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6c757d;margin-bottom:12px;">Approval Flow</div>';
        for (var i = 0; i < data.steps.length; i++) {
            var s = data.steps[i];
            var isCur = (data.current_step === s.step_order
                         && (data.status === 'Pending' || data.status === 'Pending Clarification'));
            var iconCls, faIcon, borderLeft;
            if (s.status === 'Approved') {
                iconCls = 'done'; faIcon = 'fa-check'; borderLeft = '#28a745';
            } else if (s.status === 'Rejected') {
                iconCls = 'rejected'; faIcon = 'fa-times'; borderLeft = '#dc3545';
            } else if (s.status === 'Returned') {
                iconCls = 'rejected'; faIcon = 'fa-undo'; borderLeft = '#ffc107';
            } else if (isCur) {
                iconCls = 'active'; faIcon = 'fa-hourglass-half'; borderLeft = '#ffc107';
            } else if (s.step_order < data.current_step) {
                iconCls = 'done'; faIcon = 'fa-check'; borderLeft = '#28a745';
            } else {
                iconCls = 'pending'; faIcon = 'fa-circle'; borderLeft = '#dee2e6';
            }
            html += '<div class="flow-step" style="border-left:3px solid ' + borderLeft + ';padding-left:12px;margin-left:12px;">';
            html += '<div class="flow-icon ' + iconCls + '"><i class="fas ' + faIcon + '"></i></div>';
            html += '<div style="flex:1;">';
            html += '<div style="font-size:13px;font-weight:600;">' + esc(s.step_label);
            if (s.action_label) html += ' <span style="font-weight:400;color:#6c757d;font-size:12px;">— ' + esc(s.action_label) + '</span>';
            if (isCur) html += ' <span class="badge badge-warning ml-1" style="font-size:9px;">Awaiting</span>';
            html += '</div>';
            if (s.acted_at) {
                html += '<div style="font-size:11px;color:#6c757d;margin-top:2px;">';
                html += esc(s.actor_name) + ' &middot; ' + esc(s.acted_at);
                if (s.remarks) html += ' &middot; <em>"' + esc(s.remarks) + '"</em>';
                html += '</div>';
            } else if (isCur) {
                html += '<div style="font-size:11px;color:#6c757d;margin-top:2px;">Waiting for action...</div>';
            }
            html += '</div></div>';
        }
        html += '</div>';
    }

    document.getElementById('viewModalBody').innerHTML = html;

    // Action buttons in footer — only shown when can_act, hidden otherwise
    var actHtml = '';
    if (data.can_act) {
        actHtml += '<button type="button" class="btn btn-primary btn-sm mr-1" onclick="openEditModal(currentData)"><i class="fas fa-pencil-alt mr-1"></i>Edit</button>';
        actHtml += '<a href="' + data.cancel_url + '" class="btn btn-danger btn-sm" onclick="return confirm(\'Cancel this request? This cannot be undone.\')"><i class="fas fa-times mr-1"></i>Cancel</a>';
    }
    document.getElementById('viewModalActions').innerHTML = actHtml;

    $('#modalViewRequest').modal('show');
}

function esc(str) {
    if (!str && str !== 0) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                      .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// ── Edit modal ─────────────────────────────────────────────
function openEditModal(data) {
    if (!data) return;

    // Close view modal first, then open edit modal
    $('#modalViewRequest').modal('hide');
    setTimeout(function(){

        // Set hidden fields
        document.getElementById('editReqId').value    = data.id;
        document.getElementById('editModalRef').textContent = data.reference_no;

        // Pre-select request type
        var sel = document.getElementById('editTypeSelect');
        for (var i = 0; i < sel.options.length; i++) {
            if (parseInt(sel.options[i].value) === parseInt(data.type_id)) {
                sel.selectedIndex = i;
                break;
            }
        }
        toggleEditFields(sel);

        // Pre-fill date/time fields
        document.getElementById('editDateFrom').value = data.date_from_raw || '';
        document.getElementById('editDateTo').value   = data.date_to_raw   || '';
        document.getElementById('editDays').value     = data.days_applied   || '';
        document.getElementById('editTimeFrom').value = data.time_from      || '';
        document.getElementById('editTimeTo').value   = data.time_to        || '';

        // Pre-fill purpose
        document.getElementById('editPurpose').value  = data.purpose        || '';

        $('#modalEditRequest').modal('show');
    }, 350);
}

function toggleEditFields(sel) {
    var opt = sel.options[sel.selectedIndex];
    var needsDays = opt && opt.dataset.days === '1';
    var needsTime = opt && opt.dataset.time === '1';
    document.getElementById('editDatesRow').style.display = needsDays ? '' : 'none';
    document.getElementById('editDaysCol').style.display  = needsDays ? '' : 'none';
    document.getElementById('editTimeRow').style.display  = needsTime ? '' : 'none';
}

document.getElementById('editTypeSelect').addEventListener('change', function(){
    toggleEditFields(this);
});

// New Request form — show/hide date and time fields
document.getElementById('apprTypeSelect').addEventListener('change', function(){
    var opt = this.options[this.selectedIndex];
    document.getElementById('apprDatesSection').style.display = opt.dataset.days==='1' ? '' : 'none';
    document.getElementById('apprTimeSection').style.display  = opt.dataset.time==='1' ? '' : 'none';
});
</script>
</body>
</html>