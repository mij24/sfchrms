<?php
// ============================================
// FILE: pages/employee_requests.php
// Approver action page — Confirm/Endorse/Approve/Reject/Return
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$user_id   = (int)$_SESSION['user_id'];
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';

// ── POST: Handle approval action ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    csrf_verify();
    $action  = clean_string($_POST['form_action']);
    $ra_id   = (int)(isset($_POST['ra_id'])  ? $_POST['ra_id']  : 0);
    $req_id  = (int)(isset($_POST['req_id']) ? $_POST['req_id'] : 0);
    $remarks = clean_string(isset($_POST['remarks']) ? $_POST['remarks'] : '', 500);

    $req = db_fetch($pdo, "SELECT * FROM employee_requests WHERE id=?", array($req_id));
    if (!$req) { prg_redirect_error('employee_requests.php', 'Request not found.'); }

    $prev_status = $req['status'];

    // ── LOG helper ────────────────────────────────────────────
    function log_status_change($pdo, $req_id, $uid, $prev, $new, $note='') {
        try {
            db_run($pdo,
                "INSERT INTO request_status_log
                 (request_id, changed_by, prev_status, new_status, remarks, changed_at)
                 VALUES (?,?,?,?,?,NOW())",
                array($req_id, $uid, $prev, $new, $note));
        } catch(Exception $e) {
            audit_log($pdo,'STATUS_CHANGE','employee_requests',$req_id,
                array('prev'=>$prev,'new'=>$new,'note'=>$note));
        }
    }

    // ── RETURN (send back to employee for correction) ─────────
    if ($action === 'return_with_note') {
        if (empty($remarks)) {
            prg_redirect_error('employee_requests.php', 'A note is required when returning a request.');
        }
        // Mark this step as Returned
        db_run($pdo,
            "UPDATE request_approvals SET status='Returned', acted_by=?, acted_at=NOW(), remarks=?
             WHERE id=?",
            array($user_id, $remarks, $ra_id));
        // Set request to Pending Clarification — holds it, visible to requester under Pending tab
        db_run($pdo,
            "UPDATE employee_requests SET status='Pending Clarification', rejection_reason=?, updated_at=NOW()
             WHERE id=?",
            array($remarks, $req_id));
        log_status_change($pdo, $req_id, $user_id, $prev_status, 'Pending Clarification', $remarks);
        prg_redirect('employee_requests.php',
            'Request returned to employee for correction.');
    }

    // ── REJECT (final denial) ─────────────────────────────────
    if ($action === 'reject') {
        if (empty($remarks)) {
            prg_redirect_error('employee_requests.php', 'A reason is required when rejecting a request.');
        }
        db_run($pdo,
            "UPDATE request_approvals SET status='Rejected', acted_by=?, acted_at=NOW(), remarks=?
             WHERE id=?",
            array($user_id, $remarks, $ra_id));
        db_run($pdo,
            "UPDATE employee_requests SET status='Denied', rejection_reason=?, updated_at=NOW()
             WHERE id=?",
            array($remarks, $req_id));
        log_status_change($pdo, $req_id, $user_id, $prev_status, 'Denied', $remarks);
        prg_redirect('employee_requests.php', 'Request has been denied.');
    }

    // ── RESUBMIT after Pending Clarification ─────────────────
    if ($action === 'resubmit_clarification') {
        if ($req['status'] !== 'Pending Clarification') {
            prg_redirect_error('my_approvals.php', 'This request is not in a state that can be resubmitted.');
        }
        // Reset the returned step to Pending so approver can act again
        db_run($pdo,
            "UPDATE request_approvals SET status='Pending', acted_by=NULL, acted_at=NULL, remarks=NULL
             WHERE request_id=? AND status='Returned'",
            array($req_id));
        $note = clean_string(isset($_POST['resubmit_note']) ? $_POST['resubmit_note'] : '', 500);
        db_run($pdo,
            "UPDATE employee_requests SET status='Pending', rejection_reason=NULL, updated_at=NOW()
             WHERE id=?",
            array($req_id));
        log_status_change($pdo, $req_id, $user_id, 'Pending Clarification', 'Pending',
            'Resubmitted by employee'.($note ? ': '.$note : ''));
        prg_redirect('my_approvals.php', 'Request resubmitted successfully. It is back to Pending.');
    }

    // ── CONFIRM / ENDORSE / APPROVE (step action) ─────────────
    // Map action → step status and label
    $step_status_map = array(
        'confirm'  => 'Confirmed',
        'endorse'  => 'Endorsed',
        'approve'  => 'Approved',
        'noted'    => 'Noted',
        'verified' => 'Verified',
    );
    if (!isset($step_status_map[$action])) {
        prg_redirect_error('employee_requests.php', 'Invalid action.');
    }
    $step_status = $step_status_map[$action];

    // Mark this step
    db_run($pdo,
        "UPDATE request_approvals SET status=?, acted_by=?, acted_at=NOW(), remarks=?
         WHERE id=?",
        array($step_status, $user_id, $remarks, $ra_id));

    // can_proceed_after_this flag
    $step = db_fetch($pdo, "SELECT * FROM request_approvals WHERE id=?", array($ra_id));
    if ($step && !empty($step['can_proceed_after_this'])) {
        db_run($pdo, "UPDATE employee_requests SET can_proceed=1 WHERE id=?", array($req_id));
    }

    // Find next Pending step
    $next = db_fetch($pdo,
        "SELECT * FROM request_approvals
         WHERE request_id=? AND status='Pending'
         ORDER BY step_order ASC LIMIT 1",
        array($req_id));

    if ($next) {
        // Move to next step
        db_run($pdo,
            "UPDATE employee_requests SET current_step=?, updated_at=NOW() WHERE id=?",
            array($next['step_order'], $req_id));
        // Status stays Pending (awaiting next approver)
        log_status_change($pdo, $req_id, $user_id, $prev_status, 'Pending',
            $step_status.' by step '.$step['step_order'].'. Forwarded to next approver.');
    } else {
        // All steps done — fully Approved
        db_run($pdo,
            "UPDATE employee_requests SET status='Approved', updated_at=NOW() WHERE id=?",
            array($req_id));
        log_status_change($pdo, $req_id, $user_id, $prev_status, 'Approved',
            'All approval steps completed.');

        // If leave → create leave_requests record and deduct credits
        $rt = db_fetch($pdo,
            "SELECT rt.category, rt.name FROM employee_requests er
             JOIN request_types rt ON er.request_type_id=rt.id WHERE er.id=?",
            array($req_id));
        if ($rt && $rt['category']==='Leave' && !empty($req['date_from'])) {
            $leave_map = array(
                'Vacation Leave'=>'Vacation leave','Sick Leave'=>'Sick leave',
                'Emergency Leave'=>'Emergency leave','Maternity Leave'=>'Maternity leave',
                'Paternity Leave'=>'Paternity leave','Solo Parent Leave'=>'Solo parent leave',
            );
            $lt = isset($leave_map[$rt['name']]) ? $leave_map[$rt['name']] : $rt['name'];
            db_run($pdo,
                "INSERT IGNORE INTO leave_requests
                 (employee_id,leave_type,date_from,date_to,days_applied,reason,status,approved_by,approved_at)
                 VALUES (?,?,?,?,?,?,'Approved',?,NOW())",
                array($req['employee_id'],$lt,$req['date_from'],$req['date_to'],
                      $req['days_applied'],$req['purpose'],$user_id));
            $credit_map = array(
                'Vacation leave'   =>array('vacation_leave','vacation_used'),
                'Sick leave'       =>array('sick_leave','sick_used'),
                'Emergency leave'  =>array('emergency_leave','emergency_used'),
                'Maternity leave'  =>array('maternity_leave','maternity_used'),
                'Paternity leave'  =>array('paternity_leave','paternity_used'),
                'Solo parent leave'=>array('solo_parent_leave','solo_parent_used'),
            );
            if (isset($credit_map[$lt])) {
                $used_col = $credit_map[$lt][1];
                db_run($pdo,
                    "UPDATE leave_credits SET $used_col=$used_col+?
                     WHERE employee_id=? AND year=YEAR(CURDATE())",
                    array($req['days_applied'],$req['employee_id']));
            }
        }
    }
    prg_redirect('employee_requests.php', 'Action recorded successfully.');
}

// ── Filter & data ─────────────────────────────────────────────
$user_roles_arr = array($user_role);
$role_placeholders = implode(',', array_fill(0, count($user_roles_arr), '?'));

$filter_status = isset($_GET['status']) ? clean_string($_GET['status'],'30') : '';
$filter_type   = isset($_GET['type'])   ? (int)$_GET['type']                  : 0;

$where  = array("er.status='Pending'");
$params = array();

if ($filter_status) { $where[]='er.status=?'; $params[]=$filter_status; }
if ($filter_type)   { $where[]='er.request_type_id=?'; $params[]=$filter_type; }
$where_sql = implode(' AND ', $where);

// Requests this approver can action (matching their role, current step)
$my_queue = db_fetchall($pdo,
    "SELECT er.*, rt.name AS type_name, rt.category,
            e.full_name AS emp_name, d.name AS dept_name,
            ra.id AS ra_id, ra.step_label, ra.action_label, ra.step_order
     FROM employee_requests er
     JOIN employees e ON er.employee_id=e.id
     LEFT JOIN departments d ON e.department_id=d.id
     JOIN request_types rt ON er.request_type_id=rt.id
     JOIN request_approvals ra ON ra.request_id=er.id
       AND ra.role_required IN ($role_placeholders)
       AND er.current_step=ra.step_order
       AND ra.status='Pending'
     WHERE $where_sql
     ORDER BY er.created_at ASC",
    array_merge($user_roles_arr, $params)) ?: array();

// History: all requests this approver has acted on
$my_history = db_fetchall($pdo,
    "SELECT er.*, rt.name AS type_name, e.full_name AS emp_name, d.name AS dept_name,
            ra.step_label, ra.action_label, ra.status AS step_status,
            ra.remarks AS step_remarks, ra.acted_at, u.username AS acted_by_name
     FROM employee_requests er
     JOIN employees e ON er.employee_id=e.id
     LEFT JOIN departments d ON e.department_id=d.id
     JOIN request_types rt ON er.request_type_id=rt.id
     JOIN request_approvals ra ON ra.request_id=er.id
       AND ra.role_required IN ($role_placeholders)
       AND ra.acted_by IS NOT NULL
     ORDER BY ra.acted_at DESC LIMIT 50",
    $user_roles_arr) ?: array();

// Request types for filter
$req_types = db_fetchall($pdo,
    "SELECT DISTINCT rt.id, rt.name FROM request_types rt
     JOIN approval_templates at2 ON at2.request_type_id=rt.id
       AND at2.role_required=? WHERE rt.is_active=1 ORDER BY rt.name",
    array($user_role)) ?: array();

$status_color = array(
    'Pending'    =>'warning', 'Returned'   =>'warning',
    'Approved'   =>'success', 'Confirmed'  =>'success',
    'Endorsed'   =>'info',    'Verified'   =>'info',    'Noted'=>'info',
    'Denied'     =>'danger',  'Rejected'   =>'danger',  'Cancelled'=>'secondary',
);

$active_tab = isset($_GET['tab']) ? clean_string($_GET['tab'],'20') : 'queue';
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
    .req-card{border-radius:8px;border:1px solid #e9ecef;padding:14px 16px;margin-bottom:10px;
      background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.05);}
    .req-card:hover{box-shadow:0 3px 10px rgba(0,0,0,.1);}
    .req-ref{font-family:monospace;font-size:11px;background:#f8f9fa;padding:2px 6px;border-radius:4px;}
    .tab-count{font-size:9px;padding:2px 5px;border-radius:8px;margin-left:4px;}
    .act-btn{border-radius:6px;font-size:12px;padding:5px 12px;}
    .hist-row{font-size:12px;border-bottom:1px solid #f5f5f5;padding:8px 0;}
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
<div class="content"><div class="container-fluid" style="padding-top:20px;">
  <?= prg_flash() ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Approval Queue</h5>
    <div class="d-flex" style="gap:8px;align-items:center;">
      <span class="badge badge-secondary" style="font-size:11px;">
        Role: <?= ucwords(str_replace('_',' ',$user_role)) ?>
      </span>
    </div>
  </div>

  <div class="card">
    <div class="card-header p-0">
      <ul class="nav nav-tabs">
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='queue'?'active':'' ?>" data-toggle="tab" href="#tab-queue">
            <i class="fas fa-tasks text-warning mr-1"></i>My Queue
            <?php if (count($my_queue)): ?>
            <span class="badge badge-warning tab-count"><?= count($my_queue) ?></span>
            <?php endif; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='history'?'active':'' ?>" data-toggle="tab" href="#tab-history">
            <i class="fas fa-history text-secondary mr-1"></i>History
            <span class="badge badge-light tab-count"><?= count($my_history) ?></span>
          </a>
        </li>
      </ul>
    </div>
    <div class="card-body tab-content p-3">

      <!-- ── Queue tab ── -->
      <div class="tab-pane fade <?= $active_tab==='queue'?'show active':'' ?>" id="tab-queue">
        <?php if (empty($my_queue)): ?>
        <div class="text-center text-muted py-5">
          <i class="fas fa-check-circle fa-3x d-block mb-3 text-success" style="opacity:.4;"></i>
          <div style="font-size:14px;">Queue is empty — nothing to act on.</div>
        </div>
        <?php else: ?>
        <?php foreach ($my_queue as $r):
          $sc = isset($status_color[$r['status']]) ? $status_color[$r['status']] : 'secondary';
        ?>
        <div class="req-card">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($r['emp_name']) ?></div>
              <div style="font-size:12px;color:#6c757d;">
                <?= htmlspecialchars($r['dept_name'] ?: '—') ?>
                &middot; <strong><?= htmlspecialchars($r['type_name']) ?></strong>
                &middot; <span class="badge badge-<?= $sc ?>" style="font-size:10px;"><?= $r['status'] ?></span>
              </div>
              <div style="margin-top:5px;">
                <span class="req-ref"><?= htmlspecialchars($r['reference_no']) ?></span>
                <span class="text-muted ml-2" style="font-size:11px;"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                <?php if ($r['date_from']): ?>
                <span class="text-muted ml-2" style="font-size:11px;">
                  <i class="fas fa-calendar mr-1"></i><?= date('M d', strtotime($r['date_from'])) ?>
                  <?php if ($r['date_to'] && $r['date_to']!=$r['date_from']): ?>
                  – <?= date('M d, Y', strtotime($r['date_to'])) ?>
                  <?php endif; ?>
                  <?php if ($r['days_applied']): ?>(<?= $r['days_applied'] ?>d)<?php endif; ?>
                </span>
                <?php endif; ?>
              </div>
              <?php if ($r['purpose']): ?>
              <div style="font-size:12px;color:#6c757d;margin-top:5px;">
                <i class="fas fa-comment mr-1"></i><?= htmlspecialchars(substr($r['purpose'],0,100)) ?>
              </div>
              <?php endif; ?>
              <div class="mt-2" style="font-size:11px;color:#6c757d;">
                <i class="fas fa-arrow-right mr-1 text-warning"></i>
                Your action: <strong><?= htmlspecialchars($r['action_label']) ?></strong>
                (Step <?= $r['step_order'] ?>: <?= htmlspecialchars($r['step_label']) ?>)
              </div>
            </div>
            <div class="d-flex flex-column" style="gap:5px;align-items:flex-end;">
              <button type="button" class="btn btn-success act-btn action-btn"
                      data-ra-id="<?= $r['ra_id'] ?>"
                      data-req-id="<?= $r['id'] ?>"
                      data-action="<?= $r['action_label']==='Approve' ? 'approve'
                                     : ($r['action_label']==='Endorse' ? 'endorse'
                                     : ($r['action_label']==='Verify'  ? 'verified'
                                     : ($r['action_label']==='Note'    ? 'noted'
                                     : 'confirm'))) ?>"
                      data-label="<?= htmlspecialchars($r['action_label']) ?>"
                      data-emp="<?= htmlspecialchars($r['emp_name']) ?>"
                      data-type="<?= htmlspecialchars($r['type_name']) ?>"
                      data-toggle="modal" data-target="#actionModal">
                <i class="fas fa-check mr-1"></i><?= htmlspecialchars($r['action_label']) ?>
              </button>
              <button type="button" class="btn btn-warning act-btn action-btn"
                      data-ra-id="<?= $r['ra_id'] ?>"
                      data-req-id="<?= $r['id'] ?>"
                      data-action="return_with_note"
                      data-label="Return"
                      data-emp="<?= htmlspecialchars($r['emp_name']) ?>"
                      data-type="<?= htmlspecialchars($r['type_name']) ?>"
                      data-toggle="modal" data-target="#actionModal">
                <i class="fas fa-undo mr-1"></i>Return
              </button>
              <button type="button" class="btn btn-danger act-btn action-btn"
                      data-ra-id="<?= $r['ra_id'] ?>"
                      data-req-id="<?= $r['id'] ?>"
                      data-action="reject"
                      data-label="Deny"
                      data-emp="<?= htmlspecialchars($r['emp_name']) ?>"
                      data-type="<?= htmlspecialchars($r['type_name']) ?>"
                      data-toggle="modal" data-target="#actionModal">
                <i class="fas fa-times mr-1"></i>Deny
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div><!-- /#tab-queue -->

      <!-- ── History tab ── -->
      <div class="tab-pane fade <?= $active_tab==='history'?'show active':'' ?>" id="tab-history">
        <?php if (empty($my_history)): ?>
        <div class="text-center text-muted py-5">
          <i class="fas fa-history fa-3x d-block mb-3" style="opacity:.2;"></i>
          <div style="font-size:13px;">No history yet.</div>
        </div>
        <?php else: ?>
        <table class="table table-sm" style="font-size:12px;">
          <thead class="thead-light">
            <tr>
              <th>Ref #</th><th>Employee</th><th>Type</th>
              <th>Your Action</th><th>Date Acted</th><th>Note</th><th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($my_history as $h):
            $hsc = isset($status_color[$h['step_status']]) ? $status_color[$h['step_status']] : 'secondary';
          ?>
          <tr>
            <td><code><?= htmlspecialchars($h['reference_no']) ?></code></td>
            <td><?= htmlspecialchars($h['emp_name']) ?></td>
            <td><?= htmlspecialchars($h['type_name']) ?></td>
            <td><span class="badge badge-<?= $hsc ?>"><?= htmlspecialchars($h['step_status']) ?></span></td>
            <td><?= $h['acted_at'] ? date('M d, Y g:i A', strtotime($h['acted_at'])) : '—' ?></td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= $h['step_remarks'] ? htmlspecialchars(substr($h['step_remarks'],0,60)) : '' ?>
            </td>
            <td>
              <a href="request_detail.php?id=<?= $h['id'] ?>" class="btn btn-xs btn-info">
                <i class="fas fa-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div><!-- /#tab-history -->

    </div>
  </div>
</div></div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header py-2" id="modalHeader" style="background:#28a745;">
        <h6 class="modal-title text-white mb-0" id="modalTitle">Confirm Action</h6>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="ra_id"       id="modal_ra_id"   value="">
        <input type="hidden" name="req_id"      id="modal_req_id"  value="">
        <input type="hidden" name="form_action" id="modal_action"  value="">
        <div class="modal-body">
          <p id="modal_desc" style="font-size:13px;margin-bottom:12px;"></p>
          <div class="form-group mb-0">
            <label style="font-size:12px;font-weight:600;">
              Remarks <span id="modal_remarks_req" style="color:#dc3545;"></span>
              <small id="modal_remarks_hint" class="text-muted"></small>
            </label>
            <textarea id="modal_remarks" name="remarks" class="form-control form-control-sm"
                      rows="3" placeholder="Add a note (optional)..."></textarea>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm" id="modal_submit_btn">Confirm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
document.querySelectorAll('.action-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        var action = this.dataset.action;
        var label  = this.dataset.label;
        var emp    = this.dataset.emp;
        var type   = this.dataset.type;
        document.getElementById('modal_ra_id').value  = this.dataset.raId;
        document.getElementById('modal_req_id').value = this.dataset.reqId;
        document.getElementById('modal_action').value = action;

        var hdr = document.getElementById('modalHeader');
        var sub = document.getElementById('modal_submit_btn');
        var rem = document.getElementById('modal_remarks');
        var req_star = document.getElementById('modal_remarks_req');
        var hint = document.getElementById('modal_remarks_hint');

        if (action === 'reject') {
            document.getElementById('modalTitle').textContent = 'Deny Request';
            document.getElementById('modal_desc').textContent =
                'You are about to DENY the request "' + type + '" by ' + emp +
                '. Reason is required — the employee will be notified.';
            hdr.style.background = '#dc3545';
            sub.className = 'btn btn-sm btn-danger';
            sub.textContent = 'Deny Request';
            rem.required = true;
            req_star.textContent = '*';
            hint.textContent = '(required — employee will be notified)';
        } else if (action === 'return_with_note') {
            document.getElementById('modalTitle').textContent = 'Return for Correction';
            document.getElementById('modal_desc').textContent =
                'Return the request "' + type + '" by ' + emp +
                ' for correction. Your note is required and will be visible to the employee.';
            hdr.style.background = '#ffc107';
            sub.className = 'btn btn-sm btn-warning';
            sub.textContent = 'Return for Correction';
            rem.required = true;
            req_star.textContent = '*';
            hint.textContent = '(required — employee must correct and resubmit)';
        } else {
            document.getElementById('modalTitle').textContent = label + ' Request';
            document.getElementById('modal_desc').textContent =
                'You are about to ' + label.toLowerCase() + ' the request "' + type +
                '" by ' + emp + '.';
            hdr.style.background = '#28a745';
            sub.className = 'btn btn-sm btn-success';
            sub.textContent = 'Confirm ' + label;
            rem.required = false;
            req_star.textContent = '';
            hint.textContent = '(optional)';
        }
    });
});
</script>
</body>
</html>