<?php
// ============================================
// FILE: pages/my_requests_page.php
// My Requests — view history + New Request modal
// Shows: All requests, status tabs, Edit/Cancel
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$my_uid    = (int)$_SESSION['user_id'];
$my_user   = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array($my_uid));
$my_emp_id = $my_user ? (int)$my_user['employee_id'] : 0;

if (!$my_emp_id) { echo not_linked_error(); exit; }

// CSRF token helper
if (!function_exists('csrf_token_val')) {
    function csrf_token_val() {
        if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token']=bin2hex(random_bytes(16)); }
        return $_SESSION['csrf_token'];
    }
}

// ── Handle GET actions: cancel/retract ───────────────────────
$get_action = isset($_GET['action']) ? clean_string($_GET['action'],'20') : '';
$get_id     = isset($_GET['id'])     ? (int)$_GET['id'] : 0;

if (($get_action==='cancel'||$get_action==='retract') && $get_id) {
    $tok = isset($_GET['_token']) ? $_GET['_token'] : '';
    $sess_tok = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if (!hash_equals($sess_tok,$tok)) { prg_redirect_error('my_requests_page.php','Invalid token.'); }
    $req = db_fetch($pdo,"SELECT * FROM employee_requests WHERE id=? AND employee_id=?",array($get_id,$my_emp_id));
    if ($req) {
        if ($get_action==='cancel' && $req['status']==='Pending') {
            db_run($pdo,"UPDATE employee_requests SET status='Cancelled' WHERE id=?",array($get_id));
            audit_log($pdo,'UPDATE','employee_requests',$get_id,array('action'=>'cancelled_by_requester'));
            prg_redirect('my_requests_page.php','Request cancelled.');
        }
        if ($get_action==='retract' && in_array($req['status'], array('Pending','Pending Clarification'))) {
            $acted = (int)db_value($pdo,"SELECT COUNT(*) FROM request_approvals WHERE request_id=? AND acted_by IS NOT NULL",array($get_id));
            if ($acted===0) {
                db_run($pdo,"UPDATE employee_requests SET status='Cancelled' WHERE id=?",array($get_id));
                db_run($pdo,"UPDATE request_approvals SET status='Cancelled' WHERE request_id=?",array($get_id));
                audit_log($pdo,'UPDATE','employee_requests',$get_id,array('action'=>'retracted_by_requester'));
                prg_redirect('my_requests_page.php','Request cancelled successfully.');
            } else {
                prg_redirect_error('my_requests_page.php','Cannot cancel — an approver has already acted.');
            }
        }
    }
}

// ── Load all requests ─────────────────────────────────────────
$all_requests = db_fetchall($pdo,
    "SELECT er.*, rt.name AS type_name, rt.category
     FROM employee_requests er
     JOIN request_types rt ON er.request_type_id=rt.id
     WHERE er.employee_id=? ORDER BY er.created_at DESC",
    array($my_emp_id)) ?: array();

// Load approval steps for all requests
$all_steps = array();
if (!empty($all_requests)) {
    $ids = implode(',', array_map(function($r){ return (int)$r['id']; }, $all_requests));
    $steps_raw = db_fetchall($pdo,
        "SELECT ra.*, u.username AS actor_name FROM request_approvals ra
         LEFT JOIN users u ON ra.acted_by=u.id
         WHERE ra.request_id IN ($ids) ORDER BY ra.request_id, ra.step_order") ?: array();
    foreach ($steps_raw as $s) {
        $all_steps[$s['request_id']][] = $s;
    }
}

// Bucket by status
$req_pending  = array_filter($all_requests, function($r){ return in_array($r['status'],array('Pending','Pending Clarification')); });
$req_approved = array_filter($all_requests, function($r){ return $r['status']==='Approved'; });
$req_rejected = array_filter($all_requests, function($r){ return in_array($r['status'],array('Denied','Rejected','Returned')); });
$req_cancelled= array_filter($all_requests, function($r){ return $r['status']==='Cancelled'; });

$status_color = array('Pending'=>'warning','Approved'=>'success',
    'Denied'=>'danger','Rejected'=>'danger','Returned'=>'warning','Cancelled'=>'secondary');

// Load request types for New Request modal
$req_types_grouped = array();
try {
    $rt_rows = db_fetchall($pdo,
        "SELECT MIN(id) AS id, name, category, MAX(requires_days) AS requires_days,
                MAX(requires_time) AS requires_time
         FROM request_types WHERE is_active=1
         GROUP BY name, category ORDER BY category, name") ?: array();
    foreach ($rt_rows as $rt) {
        $cat = trim($rt['category'] ?: '');
        $req_types_grouped[$cat][] = $rt;
    }
} catch(Exception $e) {}

// Employee leave credits
$leave_credits = db_fetch($pdo,"SELECT * FROM leave_credits WHERE employee_id=? AND year=YEAR(CURDATE())",array($my_emp_id));

// Active tab
$active_tab = isset($_GET['tab']) ? clean_string($_GET['tab'],'20') : 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Requests | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .req-card{border-radius:8px;border:1px solid #dee2e6;padding:14px 16px;margin-bottom:12px;
      background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.06);}
    .req-card:hover{box-shadow:0 3px 10px rgba(0,0,0,.1);}
    .req-ref{font-family:monospace;font-size:11px;background:#f8f9fa;padding:2px 6px;border-radius:4px;}
    .flow-step{display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;}
    .flow-icon{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;
      justify-content:center;font-size:11px;flex-shrink:0;}
    .flow-icon.done{background:#d4edda;color:#155724;}
    .flow-icon.active{background:#fff3cd;color:#856404;border:2px solid #ffc107;}
    .flow-icon.pending{background:#f8f9fa;color:#6c757d;}
    .flow-icon.rejected{background:#f8d7da;color:#721c24;}
    .tab-count{font-size:9px;padding:2px 5px;border-radius:8px;margin-left:4px;}
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
    <h5 class="mb-0"><i class="fas fa-file-alt mr-2 text-primary"></i>My Requests</h5>
    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalNewRequest">
      <i class="fas fa-plus mr-1"></i>New Request
    </button>
  </div>

  <!-- Request tabs -->
  <div class="card">
    <div class="card-header p-0">
      <ul class="nav nav-tabs">
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='pending'?'active':'' ?>" data-toggle="tab" href="#rp-pending">
            <i class="fas fa-hourglass-half text-warning mr-1"></i>Pending
            <?php if (count($req_pending)>0): ?><span class="badge badge-warning tab-count"><?= count($req_pending) ?></span><?php endif; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='approved'?'active':'' ?>" data-toggle="tab" href="#rp-approved">
            <i class="fas fa-check-circle text-success mr-1"></i>Approved
            <?php if (count($req_approved)>0): ?><span class="badge badge-success tab-count"><?= count($req_approved) ?></span><?php endif; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='rejected'?'active':'' ?>" data-toggle="tab" href="#rp-rejected">
            <i class="fas fa-times-circle text-danger mr-1"></i>Rejected
            <?php if (count($req_rejected)>0): ?><span class="badge badge-danger tab-count"><?= count($req_rejected) ?></span><?php endif; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='cancelled'?'active':'' ?>" data-toggle="tab" href="#rp-cancelled">
            <i class="fas fa-ban text-secondary mr-1"></i>Cancelled
            <?php if (count($req_cancelled)>0): ?><span class="badge badge-secondary tab-count"><?= count($req_cancelled) ?></span><?php endif; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='all'?'active':'' ?>" data-toggle="tab" href="#rp-all">
            All <span class="badge badge-light tab-count"><?= count($all_requests) ?></span>
          </a>
        </li>
      </ul>
    </div>
    <div class="card-body tab-content p-3">

      <?php
      // Request card renderer
      function render_request_card($req, $steps, $status_color, $my_emp_id) {
          $sc  = isset($status_color[$req['status']]) ? $status_color[$req['status']] : 'secondary';
          // Can edit/cancel?
          $acted = 0;
          if (in_array($req['status'],array('Pending','Pending Clarification'))) {
              global $pdo;
              try { $acted=(int)db_value($pdo,"SELECT COUNT(*) FROM request_approvals WHERE request_id=? AND acted_by IS NOT NULL",array($req['id'])); } catch(Exception $e){}
          }
          $can_act = ($acted===0 && in_array($req['status'],array('Pending','Pending Clarification')));
          $tok = csrf_token_val();
          ?>
          <div class="req-card">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <span style="font-weight:700;font-size:14px;"><?= htmlspecialchars($req['type_name']) ?></span>
                <span class="badge badge-<?= $sc ?> ml-2" style="font-size:10px;"><?= $req['status'] ?></span>
                <?php if ($req['can_proceed']): ?>
                <span class="badge badge-success ml-1" style="font-size:10px;"><i class="fas fa-walking mr-1"></i>You may proceed</span>
                <?php endif; ?>
                <div style="margin-top:4px;">
                  <span class="req-ref"><?= htmlspecialchars($req['reference_no']) ?></span>
                  <span class="text-muted ml-2" style="font-size:12px;"><?= date('M d, Y g:i A',strtotime($req['created_at'])) ?></span>
                  <?php if ($req['category']): ?>
                  <span class="badge badge-light ml-1" style="font-size:10px;"><?= htmlspecialchars($req['category']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="d-flex" style="gap:6px;align-items:flex-start;">
                <button type="button" class="btn btn-info btn-sm"
                        onclick="viewRequest(<?= $req['id'] ?>)">
                  <i class="fas fa-eye mr-1"></i>View
                </button>
                <?php if ($can_act): ?>
                <a href="my_requests_page.php?action=<?= $req['status']==='Pending'?'cancel':'retract' ?>&id=<?= $req['id'] ?>&_token=<?= $tok ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Cancel this request?')">
                  <i class="fas fa-times mr-1"></i>Cancel
                </a>
                <?php endif; ?>
              </div>
            </div>

            <!-- Request details row -->
            <div class="row mt-2" style="font-size:12px;">
              <?php if ($req['date_from']): ?>
              <div class="col-auto text-muted">
                <i class="fas fa-calendar mr-1"></i><?= date('M d',strtotime($req['date_from'])) ?>
                <?php if ($req['date_to'] && $req['date_to']!=$req['date_from']): ?> – <?= date('M d, Y',strtotime($req['date_to'])) ?><?php endif; ?>
                <?php if ($req['days_applied']): ?> (<?= $req['days_applied'] ?> day<?= $req['days_applied']!=1?'s':'' ?>)<?php endif; ?>
              </div>
              <?php endif; ?>
              <?php if ($req['purpose']): ?>
              <div class="col-auto text-muted"><i class="fas fa-comment mr-1"></i><?= htmlspecialchars(substr($req['purpose'],0,80)) ?><?= strlen($req['purpose'])>80?'...':'' ?></div>
              <?php endif; ?>
            </div>

            <?php if (in_array($req['status'],array('Denied','Rejected','Returned')) && $req['rejection_reason']): ?>
            <div class="mt-2" style="font-size:12px;color:#721c24;background:#f8d7da;padding:6px 10px;border-radius:4px;">
              <i class="fas fa-comment-alt mr-1"></i><em>"<?= htmlspecialchars($req['rejection_reason']) ?>"</em>
            </div>
            <?php endif; ?>

            <!-- Approval flow — hidden by default, shown in View modal -->
            <?php if (!empty($steps)): ?>
            <div class="flow-collapse mt-2" id="flow-<?= $req['id'] ?>" style="display:none;border-top:1px solid #f0f0f0;padding-top:10px;margin-top:10px;">
              <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6c757d;margin-bottom:8px;">Approval Flow</div>
              <?php foreach ($steps as $s):
                $is_cur = ((int)$req['current_step']===(int)$s['step_order'] && in_array($req['status'],array('Pending','Pending Clarification')));
                $icon_cls = $s['status']==='Approved'?'done':($s['status']==='Denied'||$s['status']==='Rejected'?'rejected':($s['status']==='Returned'?'rejected':($is_cur?'active':'pending')));
                $fa = $s['status']==='Approved'?'fa-check':($s['status']==='Denied'||$s['status']==='Rejected'?'fa-times':($s['status']==='Returned'?'fa-undo':($is_cur?'fa-hourglass-half':'fa-circle')));
              ?>
              <div class="flow-step">
                <div class="flow-icon <?= $icon_cls ?>"><i class="fas <?= $fa ?>"></i></div>
                <div>
                  <div style="font-size:12px;font-weight:600;"><?= htmlspecialchars($s['step_label']) ?>
                    <span style="font-size:11px;font-weight:400;color:#6c757d;"> — <?= htmlspecialchars($s['action_label']) ?></span>
                    <?php if ($is_cur): ?><span class="badge badge-warning ml-1" style="font-size:9px;">Awaiting</span><?php endif; ?>
                    <?php if ($s['status']==='Returned'): ?><span class="badge badge-warning ml-1" style="font-size:9px;">Returned</span><?php endif; ?>
                  </div>
                  <?php if ($s['acted_by']): ?>
                  <div style="font-size:11px;color:#6c757d;">
                    <?= htmlspecialchars($s['actor_name']) ?> &middot;
                    <?= date('M d, Y g:i A',strtotime($s['acted_at'])) ?>
                    <?php if ($s['remarks']): ?>&middot; <em>"<?= htmlspecialchars($s['remarks']) ?>"</em><?php endif; ?>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php
      }

      // Helper: render a tab pane with a set of requests
      function render_tab($pane_id, $active, $requests, $all_steps, $status_color, $my_emp_id, $empty_msg) {
          ?>
          <div class="tab-pane fade <?= $active?'show active':'' ?>" id="<?= $pane_id ?>">
            <?php if (empty($requests)): ?>
            <div class="text-center text-muted py-5">
              <i class="fas fa-folder-open fa-2x d-block mb-2" style="opacity:.3;"></i>
              <div style="font-size:13px;"><?= $empty_msg ?></div>
            </div>
            <?php else: ?>
            <?php foreach ($requests as $req):
              $steps = isset($all_steps[$req['id']]) ? $all_steps[$req['id']] : array();
              render_request_card($req,$steps,$status_color,$my_emp_id);
            endforeach; ?>
            <?php endif; ?>
          </div>
          <?php
      }
      ?>

      <?php render_tab('rp-pending',  $active_tab==='pending',   $req_pending,  $all_steps,$status_color,$my_emp_id,'No pending requests.'); ?>
      <?php render_tab('rp-approved', $active_tab==='approved',  $req_approved, $all_steps,$status_color,$my_emp_id,'No approved requests yet.'); ?>
      <?php render_tab('rp-rejected', $active_tab==='rejected',  $req_rejected, $all_steps,$status_color,$my_emp_id,'No rejected requests.'); ?>
      <?php render_tab('rp-cancelled',$active_tab==='cancelled', $req_cancelled,$all_steps,$status_color,$my_emp_id,'No cancelled requests.'); ?>
      <?php render_tab('rp-all',      $active_tab==='all',       $all_requests, $all_steps,$status_color,$my_emp_id,'No requests filed yet.'); ?>

    </div><!-- /.tab-content -->
  </div><!-- /.card -->

</div></div>
</div>

<!-- ══ New Request Modal ══ -->
<div class="modal fade" id="modalNewRequest" tabindex="-1" data-backdrop="static">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white py-2">
        <h5 class="modal-title mb-0"><i class="fas fa-file-plus mr-2"></i>New Request</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="my_requests.php" enctype="multipart/form-data">
        <?php csrf_field(); ?>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Request Type <span class="text-danger">*</span></label>
                <select name="request_type_id" class="form-control form-control-sm" required id="nrTypeSelect">
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
            <div class="col-md-6" id="nrDatesSection" style="display:none;">
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm">
              </div>
              <div class="form-group">
                <label style="font-size:12px;font-weight:600;">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm">
              </div>
            </div>
          </div>
          <div id="nrTimeSection" style="display:none;" class="row">
            <div class="col-md-3"><div class="form-group">
              <label style="font-size:12px;font-weight:600;">Time From</label>
              <input type="time" name="time_from" class="form-control form-control-sm">
            </div></div>
            <div class="col-md-3"><div class="form-group">
              <label style="font-size:12px;font-weight:600;">Time To</label>
              <input type="time" name="time_to" class="form-control form-control-sm">
            </div></div>
          </div>
          <div class="form-group">
            <label style="font-size:12px;font-weight:600;">Purpose / Reason <span class="text-danger">*</span></label>
            <textarea name="purpose" class="form-control form-control-sm" rows="3"
                      placeholder="State the purpose or reason for this request..." required></textarea>
          </div>
          <!-- Leave credits info -->
          <?php if ($leave_credits): ?>
          <div class="alert alert-light p-2 mb-0" style="font-size:12px;">
            <i class="fas fa-info-circle text-info mr-1"></i>
            Leave credits — Vacation: <strong><?= max(0,(isset($leave_credits['vacation_leave'])?(float)$leave_credits['vacation_leave']:0)-(isset($leave_credits['vacation_used'])?(float)$leave_credits['vacation_used']:0)) ?></strong> days &nbsp;|&nbsp;
            Sick: <strong><?= max(0,(isset($leave_credits['sick_leave'])?(float)$leave_credits['sick_leave']:0)-(isset($leave_credits['sick_used'])?(float)$leave_credits['sick_used']:0)) ?></strong> days
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

<!-- ══ View Request Detail Modal ══ -->
<div class="modal fade" id="modalViewRequest" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white py-2">
        <h6 class="modal-title mb-0"><i class="fas fa-file-alt mr-2"></i>Request Details</h6>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body" id="viewRequestBody">
        <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
// Show/hide date and time fields based on request type selection
document.getElementById('nrTypeSelect').addEventListener('change', function(){
    var opt = this.options[this.selectedIndex];
    var needsDays = opt.dataset.days === '1';
    var needsTime = opt.dataset.time === '1';
    document.getElementById('nrDatesSection').style.display = needsDays ? '' : 'none';
    document.getElementById('nrTimeSection').style.display  = needsTime ? '' : 'none';
});

// View request modal + toggle inline flow
function viewRequest(id) {
    // Toggle the inline approval flow on this card
    var flowEl = document.getElementById('flow-' + id);
    if (flowEl) {
        var isVisible = flowEl.style.display !== 'none';
        // Close all other flows first
        document.querySelectorAll('.flow-collapse').forEach(function(el){
            el.style.display = 'none';
        });
        // Toggle this one
        flowEl.style.display = isVisible ? 'none' : 'block';
        if (isVisible) return; // just toggling off — don't open modal
    }
    // Open the view modal
    document.getElementById('viewRequestBody').innerHTML =
        '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
    $('#modalViewRequest').modal('show');
    $.get('request_detail.php', {id: id, modal: 1}, function(html){
        document.getElementById('viewRequestBody').innerHTML = html;
    }).fail(function(){
        document.getElementById('viewRequestBody').innerHTML =
            '<div class="text-center text-muted py-3">Failed to load. <a href="request_detail.php?id='+id+'" target="_blank">Open in new tab</a></div>';
    });
}

// Auto-open correct tab from URL
$(document).ready(function(){
    var tab = '<?= addslashes($active_tab) ?>';
    var map = {pending:'rp-pending',approved:'rp-approved',rejected:'rp-rejected',cancelled:'rp-cancelled',all:'rp-all'};
    if (tab && map[tab] && tab !== 'pending') {
        $('a[href="#'+map[tab]+'"]').tab('show');
    }
});
</script>
</body>
</html>