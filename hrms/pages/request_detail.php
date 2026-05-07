<?php
// ============================================================
// FILE: pages/request_detail.php
// Full request detail — view, digital sign, approve, reject
// All roles: employee (own), secretary, dept head, clinic,
//            VP, President, HR, exec_secretary
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// ── HRMS timezone ─────────────────────────────────────────────
$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz = 'Asia/Manila'; $_hrms_delta = 0;
foreach ($_tz_row as $_r) {
    if ($_r['setting_key']==='system_timezone' && $_r['setting_value']) $_hrms_tz = $_r['setting_value'];
    if ($_r['setting_key']==='clock_delta_seconds') $_hrms_delta = (int)$_r['setting_value'];
}
try { date_default_timezone_set($_hrms_tz); } catch(Exception $e) { date_default_timezone_set('Asia/Manila'); }
function rd_now() { global $_hrms_delta; return date('Y-m-d H:i:s', time()+$_hrms_delta); }

$my_uid    = (int)$_SESSION['user_id'];
$my_role   = $_SESSION['role'] ?? 'employee';
$my_user   = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array($my_uid));
$my_emp_id = $my_user ? (int)$my_user['employee_id'] : 0;

// ── Load request ──────────────────────────────────────────────
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: employee_requests.php"); exit; }

$req = db_fetch($pdo,
    "SELECT er.*, rt.name AS type_name, rt.category,
            e.full_name AS emp_name, e.employee_id AS emp_code,
            d.name AS dept_name, d.id AS dept_id,
            ec.name AS emp_classification
     FROM employee_requests er
     JOIN request_types rt ON er.request_type_id=rt.id
     JOIN employees e ON er.employee_id=e.id
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN employee_classifications ec ON e.classification_id=ec.id
     WHERE er.id=?",
    array($id)
);
if (!$req) { header("Location: employee_requests.php"); exit; }

// Access control: employee can only see own request
$is_own_request = ($my_emp_id && $my_emp_id == $req['employee_id']);
$is_hr_or_admin = in_array($my_role, array('hr','hr_head','admin','superadmin'));
$can_view = $is_own_request || $is_hr_or_admin || has_role(ROLE_DEPT_SECRETARY) || has_role(ROLE_MANAGER);
if (!$can_view) {
    http_response_code(403); include '../includes/403.php'; exit;
}

// Audit: log this view
audit_log($pdo,'VIEW','employee_requests',$id,array('viewer_role'=>$my_role));

// ── Load approval steps ────────────────────────────────────────
$steps = db_fetchall($pdo,
    "SELECT ra.*,
            u.username AS actor_username,
            e_actor.full_name AS actor_full_name,
            u2.username AS signer_username,
            e_signer.full_name AS signer_full_name
     FROM request_approvals ra
     LEFT JOIN users u ON ra.acted_by=u.id
     LEFT JOIN employees e_actor ON u.employee_id=e_actor.id
     LEFT JOIN users u2 ON ra.signed_by=u2.id
     LEFT JOIN employees e_signer ON u2.employee_id=e_signer.id
     WHERE ra.request_id=?
     ORDER BY ra.step_order",
    array($id)
);

// ── POST: Digital Sign / Approve / Reject / Return ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $post_action = clean_string($_POST['post_action'] ?? '');
    $ra_id       = (int)($_POST['ra_id'] ?? 0);
    $remarks     = clean_string($_POST['remarks'] ?? '', 500);

    // Get the step being acted on
    $step = db_fetch($pdo,
        "SELECT ra.*, at.triggers_logbook, at.logbook_office
         FROM request_approvals ra
         LEFT JOIN approval_templates at
           ON at.request_type_id=(SELECT request_type_id FROM employee_requests WHERE id=ra.request_id)
              AND at.step_order=ra.step_order
         WHERE ra.id=? AND ra.request_id=?",
        array($ra_id, $id)
    );

    if (!$step) {
        prg_redirect('request_detail.php?id='.$id, 'Invalid action.');
    }

    // ── DIGITAL SIGN ──────────────────────────────────────────
    if ($post_action === 'digital_sign') {
        // Sign and simultaneously mark as Approved
        $signer_name = db_value($pdo,
            "SELECT e.full_name FROM users u LEFT JOIN employees e ON u.employee_id=e.id WHERE u.id=?",
            array($my_uid)
        ) ?: $_SESSION['username'];

        db_run($pdo,
            "UPDATE request_approvals SET
             status='Approved', acted_by=?, acted_at=NOW(), remarks=?,
             digital_signed=1, signed_by=?, signed_at=NOW(), signed_name=?
             WHERE id=?",
            array($my_uid, $remarks, $my_uid, $signer_name, $ra_id)
        );

        audit_log($pdo,'DIGITAL_SIGN','request_approvals',$ra_id,
            array('request_id'=>$id,'step'=>$step['step_label'],
                  'action'=>$step['action_label'],'signer'=>$signer_name));

        // Handle can_proceed
        if ($step['can_proceed_after_this']) {
            db_run($pdo,"UPDATE employee_requests SET can_proceed=1, last_action_at=NOW(), last_action_by=? WHERE id=?",
                array($my_uid, $id));
        } else {
            db_run($pdo,"UPDATE employee_requests SET last_action_at=NOW(), last_action_by=? WHERE id=?",
                array($my_uid, $id));
        }

        // Advance to next step
        _advance_request($pdo, $id, $my_uid, $step);

        // Auto-trigger logbook if this step triggers it
        if (!empty($step['triggers_logbook']) && !empty($step['logbook_office'])) {
            _trigger_logbook($pdo, $req, $step, $my_uid);
        }

        prg_redirect('request_detail.php?id='.$id,
            'Digital signature applied. '.$step['step_label'].' — '.$step['action_label'].' recorded.');
    }

    // ── RETURN to previous / employee ─────────────────────────
    if ($post_action === 'return_request') {
        if (empty($remarks)) {
            prg_redirect('request_detail.php?id='.$id, 'Reason is required when returning a request.');
        }
        db_run($pdo,
            "UPDATE request_approvals SET status='Rejected', acted_by=?, acted_at=NOW(), remarks=? WHERE id=?",
            array($my_uid, $remarks, $ra_id)
        );
        db_run($pdo,
            "UPDATE employee_requests SET status='Returned', rejection_reason=?,
             last_action_at=NOW(), last_action_by=? WHERE id=?",
            array($remarks, $my_uid, $id)
        );
        audit_log($pdo,'RETURN','employee_requests',$id,
            array('step'=>$step['step_label'],'reason'=>$remarks));
        prg_redirect('request_detail.php?id='.$id, 'Request returned for correction.');
    }
}

// Helper: advance request to next pending step or mark fully approved
function _advance_request(PDO $pdo, $req_id, $uid, $current_step) {
    $next = db_fetch($pdo,
        "SELECT * FROM request_approvals
         WHERE request_id=? AND status='Pending'
         ORDER BY step_order ASC LIMIT 1",
        array($req_id)
    );
    if ($next) {
        db_run($pdo,"UPDATE employee_requests SET current_step=?, last_action_at=NOW(), last_action_by=? WHERE id=?",
            array($next['step_order'], $uid, $req_id));
    } else {
        // All done
        db_run($pdo,"UPDATE employee_requests SET status='Approved', last_action_at=NOW(), last_action_by=? WHERE id=?",
            array($uid, $req_id));
        // For leave requests, create leave_requests record
        $req_full = db_fetch($pdo,
            "SELECT er.*, rt.name AS type_name, rt.category FROM employee_requests er
             JOIN request_types rt ON er.request_type_id=rt.id WHERE er.id=?",
            array($req_id)
        );
        if ($req_full && $req_full['category'] === 'Leave' && $req_full['date_from']) {
            $leave_map = array(
                'Vacation Leave'=>'Vacation leave','Sick Leave'=>'Sick leave',
                'Emergency Leave'=>'Emergency leave','Maternity Leave'=>'Maternity leave',
                'Paternity Leave'=>'Paternity leave','Solo Parent Leave'=>'Solo parent leave',
            );
            $lt = isset($leave_map[$req_full['type_name']]) ? $leave_map[$req_full['type_name']] : $req_full['type_name'];
            db_run($pdo,
                "INSERT IGNORE INTO leave_requests
                 (employee_id,leave_type,date_from,date_to,days_applied,reason,status,approved_by,approved_at)
                 VALUES (?,?,?,?,?,?,'Approved',?,NOW())",
                array($req_full['employee_id'],$lt,$req_full['date_from'],$req_full['date_to'],
                      $req_full['days_applied'],$req_full['purpose'],$uid)
            );
            // Deduct credits
            $credit_map = array(
                'Vacation leave'=>'vacation_used','Sick leave'=>'sick_used',
                'Emergency leave'=>'emergency_used','Maternity leave'=>'maternity_used',
                'Paternity leave'=>'paternity_used','Solo parent leave'=>'solo_parent_used',
            );
            if (isset($credit_map[$lt])) {
                $uc = $credit_map[$lt];
                db_run($pdo,"UPDATE leave_credits SET $uc=$uc+? WHERE employee_id=? AND year=YEAR(CURDATE())",
                    array($req_full['days_applied'],$req_full['employee_id']));
            }
        }
    }
}

// Helper: auto-create logbook incoming entry
function _trigger_logbook(PDO $pdo, $req, $step, $uid) {
    // Check if already logged for this step
    $exists = db_value($pdo,
        "SELECT COUNT(*) FROM document_logbook WHERE request_id=? AND office=?",
        array($req['id'], $step['logbook_office'])
    );
    if ($exists) return;

    // Generate control number
    $prefix_map = array(
        'VP Administration'=>'VPA','VP Academic'=>'VPAA',
        'President'=>'PRES','Board'=>'BOT'
    );
    $prefix = isset($prefix_map[$step['logbook_office']]) ? $prefix_map[$step['logbook_office']] : 'PRES';
    $year   = (int)date('Y');
    $pdo->prepare("INSERT INTO document_control_counter (office,year,counter) VALUES (?,?,1) ON DUPLICATE KEY UPDATE counter=counter+1")
        ->execute(array($prefix,$year));
    $counter    = (int)db_value($pdo,"SELECT counter FROM document_control_counter WHERE office=? AND year=?",array($prefix,$year));
    $control_no = $prefix.'-'.$year.'-'.str_pad($counter,4,'0',STR_PAD_LEFT);

    // Get requestor details
    $emp = db_fetch($pdo,
        "SELECT e.full_name, e.employee_id, d.name AS dept
         FROM employees e LEFT JOIN departments d ON e.department_id=d.id
         WHERE e.id=?",
        array($req['employee_id'])
    );

    db_run($pdo,
        "INSERT INTO document_logbook
         (control_number, request_id, reference_no, office, document_type, document_category,
          requestor_name, requestor_emp_id, requestor_dept, requestor_employee_id,
          date_received, received_from, incoming_remarks,
          incoming_logged_by, incoming_logged_at, exec_action, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),'System — forwarded from workflow',?,?,NOW(),'Pending','Incoming')",
        array(
            $control_no, $req['id'], $req['reference_no'],
            $step['logbook_office'], $req['type_name'], 'Request',
            $emp ? $emp['full_name'] : '',
            $emp ? $emp['employee_id'] : '',
            $emp ? $emp['dept'] : '',
            $req['employee_id'],
            'Auto-routed from approval workflow — step: '.$step['step_label'],
            $uid
        )
    );

    audit_log($pdo,'INSERT','document_logbook',db_lastid($pdo),
        array('control_number'=>$control_no,'triggered_by_step'=>$step['step_label'],'request_id'=>$req['id']));
}

// ── Determine current user's actionable step ──────────────────
// Maps logged-in role → which role_required values in request_approvals they can act on
// Role map: logged-in role → which role_required values they can act on
$role_map_steps = array(
    'dept_secretary'         => array('dept_secretary','timekeeper','dept_timekeeper'),
    'manager'                => array('manager','academic_dept_head','non_academic_dept_head','dept_head'),
    'academic_dept_head'     => array('manager','academic_dept_head','dept_head'),
    'non_academic_dept_head' => array('manager','non_academic_dept_head','dept_head'),
    'vp_academic'            => array('vp_academic'),
    'vp_admin'               => array('vp_admin'),
    'clinic_head'            => array('clinic_head','clinic_staff','clinic'),
    'clinic_staff'           => array('clinic_staff','clinic_head','clinic'),
    'president'              => array('president','school_president'),
    'board_director'         => array('president','board_director'),
    'hr'                     => array('hr','hr_staff'),
    'hr_head'                => array('hr','hr_head','hr_staff'),
    'exec_secretary'         => array(),
    'admin'                  => array('hr','hr_head','manager','academic_dept_head','non_academic_dept_head',
                                      'vp_academic','vp_admin','clinic_head','clinic_staff',
                                      'president','dept_secretary','timekeeper','dept_head'),
    'superadmin'             => array('hr','hr_head','manager','academic_dept_head','non_academic_dept_head',
                                      'vp_academic','vp_admin','clinic_head','clinic_staff',
                                      'president','dept_secretary','timekeeper','dept_head'),
);
$my_actionable_roles = isset($role_map_steps[$my_role]) ? $role_map_steps[$my_role] : array($my_role);

// Find the step this user can act on right now
$my_action_step = null;
foreach ($steps as $s) {
    if ($s['status'] !== 'Pending') continue;
    if ((int)$s['step_order'] !== (int)$req['current_step']) continue;
    // Check if this user's role can act on this step
    $role_matches = in_array($s['role_required'], $my_actionable_roles)
                 || $s['role_required'] === $my_role  // direct match
                 || ($my_role === 'dept_secretary' && in_array($s['role_required'],
                     array('dept_secretary','timekeeper','dept_timekeeper','manager')))
                 || (in_array($my_role, array('manager','academic_dept_head','non_academic_dept_head'))
                     && in_array($s['role_required'], array('manager','academic_dept_head','non_academic_dept_head','dept_head')));
    if ($role_matches) {
        $my_action_step = $s;
        break;
    }
}

$status_color = array(
    'Pending'=>'warning','Approved'=>'success',
    'Rejected'=>'danger','Returned'=>'warning','Noted'=>'info','Cancelled'=>'secondary'
);
$back_url = $is_own_request && !$is_hr_or_admin && !has_role(ROLE_MANAGER)
    ? 'my_requests_history.php' : 'employee_requests.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Request Detail | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .flow-step { border-left:3px solid #dee2e6; padding:0 0 24px 20px; margin-left:14px; position:relative; }
    .flow-step:last-child { border-left-color:transparent; padding-bottom:0; }
    .flow-step::before {
      content:''; width:16px; height:16px; border-radius:50%;
      background:#dee2e6; border:2px solid #fff; box-shadow:0 0 0 2px #dee2e6;
      position:absolute; left:-10px; top:0;
    }
    .flow-step.done::before    { background:#28a745; box-shadow:0 0 0 2px #28a745; }
    .flow-step.current::before { background:#ffc107; box-shadow:0 0 0 2px #ffc107; }
    .flow-step.rejected::before{ background:#dc3545; box-shadow:0 0 0 2px #dc3545; }
    .flow-step.mine::before    { background:#007bff; box-shadow:0 0 0 2px #007bff; animation: pulse 1.5s infinite; }
    @keyframes pulse { 0%,100%{box-shadow:0 0 0 2px #007bff} 50%{box-shadow:0 0 0 5px rgba(0,123,255,.3)} }
    .step-title { font-weight:600; font-size:14px; margin-bottom:4px; }
    .step-meta  { font-size:12px; color:#6c757d; }
    .digital-stamp {
      display:inline-block; border:2px solid #28a745; border-radius:8px;
      padding:6px 14px; font-size:11px; color:#28a745; font-weight:700;
      text-transform:uppercase; letter-spacing:.05em; margin-top:6px;
      background:rgba(40,167,69,.06);
    }
    .digital-stamp i { margin-right:4px; }
    .can-proceed-banner {
      background:#d4edda; border:1px solid #c3e6cb; border-radius:8px;
      padding:10px 14px; font-size:13px; color:#155724; margin-top:6px;
    }
    .action-card { border:2px solid #007bff; border-radius:12px; padding:20px; margin-top:20px; }
    .logbook-badge {
      display:inline-block; background:#0f3460; color:#74c0fc;
      border-radius:6px; padding:3px 10px; font-size:10px;
      font-weight:700; letter-spacing:.05em; margin-left:6px;
    }
    /* ── Print styles ── */
    @media print {
      .wrapper > .main-header,
      .main-sidebar,
      .action-card,
      .breadcrumb,
      .btn:not(.no-print-hide),
      .content-header { display:none !important; }
      .content-wrapper { margin-left:0 !important; padding:0 !important; }
      .content { padding:0 !important; }
      .container-fluid { padding:0 !important; }
      .card { box-shadow:none !important; border:1px solid #dee2e6 !important; }
      .print-header { display:block !important; }
      .flow-step::before { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
      .digital-stamp { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
      .badge { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
      body { font-size:12px; }
      .no-print { display:none !important; }
    }
    @media screen {
      .print-header { display:none; }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">
          <i class="fas fa-file-alt mr-2"></i>Request Detail
        </h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item"><a href="<?= $back_url ?>">Requests</a></li>
          <li class="breadcrumb-item active">Detail</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <!-- Print header (hidden on screen, shown on print) -->
    <div class="print-header" style="text-align:center;padding:16px 0 10px;border-bottom:2px solid #343a40;margin-bottom:16px;">
      <h4 style="margin:0;font-size:16px;font-weight:700;">SFC|HRMS — Official Request Document</h4>
      <p style="margin:4px 0 0;font-size:12px;color:#666;">
        Printed by: <?= htmlspecialchars($_SESSION['username']) ?>
        &nbsp;|&nbsp; <?= date('F d, Y g:i A') ?>
        &nbsp;|&nbsp; This document reflects the current approval status at time of printing.
      </p>
    </div>

    <?= prg_flash() ?>

    <div class="mb-3 d-flex justify-content-between align-items-center no-print">
      <a href="<?= $back_url ?>" class="btn btn-sm btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back
      </a>
      <button onclick="printRequest()" class="btn btn-sm btn-outline-dark">
        <i class="fas fa-print mr-1"></i> Print / Save as PDF
      </button>
    </div>

    <!-- Can Proceed Banner -->
    <?php if ($req['can_proceed'] && $is_own_request): ?>
    <div class="alert alert-success d-flex align-items-center mb-3">
      <i class="fas fa-walking fa-2x mr-3"></i>
      <div>
        <strong>You may proceed with your scheduled leave.</strong><br>
        <small>The Department Head has signed. The request is continuing through the approval chain for final approval.</small>
      </div>
    </div>
    <?php endif; ?>

    <!-- Retract option for filing employee -->
    <?php if ($is_own_request && in_array($req['status'], array('Pending','Pending Clarification','Returned'))): ?>
    <div class="alert alert-light border mb-3 d-flex justify-content-between align-items-center no-print">
      <div style="font-size:13px;">
        <i class="fas fa-info-circle text-muted mr-1"></i>
        Changed your mind? You may retract this request at any time before final approval.
      </div>
      <button type="button" class="btn btn-sm btn-outline-danger"
              onclick="$('#retractModalDetail').modal('show')">
        <i class="fas fa-undo-alt mr-1"></i> Retract Request
      </button>
    </div>
    <?php endif; ?>

    <div class="row">
      <!-- Left: Request info -->
      <div class="col-md-5">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
              <?= htmlspecialchars($req['type_name']) ?>
            </h3>
            <?php $osc = isset($status_color[$req['status']]) ? $status_color[$req['status']] : 'secondary'; ?>
            <span class="badge badge-<?= $osc ?>" style="font-size:12px;">
              <?= $req['status'] ?>
              <?php if ($req['can_proceed']): ?>
                <i class="fas fa-walking ml-1"></i>
              <?php endif; ?>
            </span>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm table-borderless mb-0" style="font-size:13px;">
              <tr class="border-bottom">
                <td class="text-muted pl-3" width="130">Reference</td>
                <td><code class="font-weight-bold"><?= htmlspecialchars($req['reference_no']) ?></code></td>
              </tr>
              <tr class="border-bottom">
                <td class="text-muted pl-3">Employee</td>
                <td>
                  <strong><?= htmlspecialchars($req['emp_name']) ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($req['emp_code']) ?> — <?= htmlspecialchars($req['dept_name'] ?: '—') ?></small>
                </td>
              </tr>
              <tr class="border-bottom">
                <td class="text-muted pl-3">Classification</td>
                <td>
                  <span class="badge badge-<?= strpos(strtolower($req['emp_classification'] ?: ''),'teach')!==false?'primary':'success' ?>">
                    <?= htmlspecialchars($req['emp_classification'] ?: '—') ?>
                  </span>
                </td>
              </tr>
              <?php if ($req['date_from']): ?>
              <tr class="border-bottom">
                <td class="text-muted pl-3">Date</td>
                <td>
                  <?= date('M d, Y', strtotime($req['date_from'])) ?> –
                  <?= date('M d, Y', strtotime($req['date_to'])) ?><br>
                  <strong><?= $req['days_applied'] ?> day(s)</strong>
                </td>
              </tr>
              <?php endif; ?>
              <?php if ($req['time_from']): ?>
              <tr class="border-bottom">
                <td class="text-muted pl-3">Time</td>
                <td><?= date('h:i A', strtotime($req['time_from'])) ?> – <?= date('h:i A', strtotime($req['time_to'])) ?></td>
              </tr>
              <?php endif; ?>
              <?php if ($req['purpose']): ?>
              <tr class="border-bottom">
                <td class="text-muted pl-3">Purpose</td>
                <td><?= nl2br(htmlspecialchars($req['purpose'])) ?></td>
              </tr>
              <?php endif; ?>
              <?php if ($req['attachment_path']): ?>
              <tr class="border-bottom">
                <td class="text-muted pl-3">Attachment</td>
                <td>
                  <a href="../uploads/requests/<?= htmlspecialchars($req['attachment_path']) ?>" target="_blank">
                    <i class="fas fa-paperclip mr-1"></i> View
                  </a>
                </td>
              </tr>
              <?php endif; ?>
              <tr>
                <td class="text-muted pl-3">Filed</td>
                <td><?= date('M d, Y g:i A', strtotime($req['created_at'])) ?></td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Leave credits info (for own request) -->
        <?php if ($is_own_request && $req['category'] === 'Leave'): ?>
        <?php
        $credits = db_fetch($pdo,
            "SELECT * FROM leave_credits WHERE employee_id=? AND year=YEAR(CURDATE())",
            array($req['employee_id'])
        );
        ?>
        <?php if ($credits): ?>
        <div class="card">
          <div class="card-header"><h3 class="card-title">Leave Credits (<?= date('Y') ?>)</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0" style="font-size:12px;">
              <thead class="thead-light"><tr><th>Type</th><th>Granted</th><th>Used</th><th>Balance</th></tr></thead>
              <tbody>
                <?php
                $types = array(
                    'Vacation leave'  =>array('vacation_leave','vacation_used'),
                    'Sick leave'      =>array('sick_leave','sick_used'),
                    'Emergency leave' =>array('emergency_leave','emergency_used'),
                );
                foreach ($types as $tname => $cols):
                    $granted = $credits[$cols[0]] ?? 0;
                    $used    = $credits[$cols[1]] ?? 0;
                    $bal     = $granted - $used;
                ?>
                <tr>
                  <td><?= $tname ?></td>
                  <td><?= $granted ?></td>
                  <td><?= $used ?></td>
                  <td><strong class="<?= $bal<2?'text-danger':'' ?>"><?= $bal ?></strong></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- Right: Approval flow + action -->
      <div class="col-md-7">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-stream mr-2"></i>Approval Flow</h3>
          </div>
          <div class="card-body pb-0">

            <!-- Step 0: Employee filed -->
            <div class="flow-step done">
              <div class="step-title">
                <i class="fas fa-user mr-1 text-success"></i>
                <?= htmlspecialchars($req['emp_name']) ?> — Request Filed
              </div>
              <div class="step-meta"><?= date('M d, Y g:i A', strtotime($req['created_at'])) ?></div>
            </div>

            <?php foreach ($steps as $step):
              $is_current = ((int)$req['current_step'] === (int)$step['step_order']
                             && in_array($req['status'], array('Pending','Pending Clarification')));
              $is_mine    = ($my_action_step && $my_action_step['id'] === $step['id']);

              if ($step['status'] === 'Approved')  $css = 'done';
              elseif ($step['status'] === 'Rejected') $css = 'rejected';
              elseif ($is_mine)  $css = 'mine';
              elseif ($is_current) $css = 'current';
              else $css = '';
            ?>
            <div class="flow-step <?= $css ?>">
              <div class="step-title">
                <?= htmlspecialchars($step['step_label']) ?>
                <span class="text-muted" style="font-size:12px;font-weight:400;">
                  — <?= htmlspecialchars($step['action_label']) ?>
                </span>
                <?php if ($is_mine): ?>
                  <span class="badge badge-primary ml-1" style="font-size:10px;">Your action required</span>
                <?php elseif ($is_current): ?>
                  <span class="badge badge-warning ml-1" style="font-size:10px;">Awaiting</span>
                <?php endif; ?>
                <?php if ($step['triggers_logbook']): ?>
                  <span class="logbook-badge"><i class="fas fa-book mr-1"></i>Logbook</span>
                <?php endif; ?>
              </div>

              <?php if ($step['status'] === 'Approved' || $step['status'] === 'Noted'): ?>
              <div class="step-meta">
                <strong><?= htmlspecialchars($step['actor_full_name'] ?: $step['actor_username'] ?: '—') ?></strong>
                <span class="badge badge-success ml-1" style="font-size:10px;"><?= $step['status'] ?></span>
                &nbsp;·&nbsp;
                <?= $step['acted_at'] ? date('M d, Y g:i A', strtotime($step['acted_at'])) : '' ?>
              </div>
              <?php if ($step['digital_signed']): ?>
              <div class="digital-stamp">
                <i class="fas fa-signature"></i>
                Digitally signed by <?= htmlspecialchars($step['signed_name'] ?: $step['actor_full_name'] ?: '—') ?>
                <?= $step['signed_at'] ? '· '.date('M d, Y g:i A', strtotime($step['signed_at'])) : '' ?>
              </div>
              <?php endif; ?>
              <?php if ($step['remarks']): ?>
              <div class="mt-2 p-2" style="background:#f8f9fa;border-radius:6px;font-size:12px;">
                <i class="fas fa-comment mr-1 text-muted"></i>
                <em><?= htmlspecialchars($step['remarks']) ?></em>
              </div>
              <?php endif; ?>

              <?php elseif ($step['status'] === 'Rejected'): ?>
              <div class="step-meta text-danger">
                <strong><?= htmlspecialchars($step['actor_full_name'] ?: $step['actor_username'] ?: '—') ?></strong>
                returned/rejected · <?= $step['acted_at'] ? date('M d, Y g:i A', strtotime($step['acted_at'])) : '' ?>
              </div>
              <?php if ($step['remarks']): ?>
              <div class="mt-1 p-2" style="background:#f8d7da;border-radius:6px;font-size:12px;color:#721c24;">
                <i class="fas fa-exclamation-circle mr-1"></i>
                <em><?= htmlspecialchars($step['remarks']) ?></em>
              </div>
              <?php endif; ?>

              <?php elseif ($is_current && !$is_mine): ?>
              <div class="step-meta text-warning">Waiting for action from this approver.</div>

              <?php else: ?>
              <div class="step-meta text-muted">Not yet reached.</div>
              <?php endif; ?>

              <?php if ($step['can_proceed_after_this'] && $step['status']==='Approved'): ?>
              <div class="can-proceed-banner">
                <i class="fas fa-walking mr-1"></i>
                Employee may proceed with leave from this point onwards.
              </div>
              <?php endif; ?>

            </div>
            <?php endforeach; ?>

            <?php if ($req['status'] === 'Approved'): ?>
            <div class="flow-step done">
              <div class="step-title text-success">
                <i class="fas fa-check-double mr-1"></i> Request Fully Approved
              </div>
            </div>
            <?php elseif ($req['status'] === 'Returned'): ?>
            <div class="flow-step rejected">
              <div class="step-title text-warning">
                <i class="fas fa-undo mr-1"></i> Request Returned for Correction
              </div>
              <?php if ($req['rejection_reason']): ?>
              <div class="step-meta"><?= htmlspecialchars($req['rejection_reason']) ?></div>
              <?php endif; ?>
            </div>
            <?php elseif ($req['status'] === 'Rejected'): ?>
            <div class="flow-step rejected">
              <div class="step-title text-danger">
                <i class="fas fa-times-circle mr-1"></i> Request Rejected
              </div>
            </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- ── ACTION CARD (only shown to the person whose turn it is) ── -->
        <?php if ($my_action_step && in_array($req['status'], array('Pending','Pending Clarification'))): ?>
        <div class="action-card">
          <h5 class="mb-1">
            <i class="fas fa-pen-nib mr-2 text-primary"></i>
            Your Action: <?= htmlspecialchars($my_action_step['step_label']) ?>
          </h5>
          <p class="text-muted mb-3" style="font-size:13px;">
            Action required: <strong><?= htmlspecialchars($my_action_step['action_label']) ?></strong>
            <?php if ($my_action_step['can_proceed_after_this']): ?>
              <span class="badge badge-success ml-1">Signing this allows employee to proceed</span>
            <?php endif; ?>
          </p>

          <!-- Digital Sign form -->
          <form method="POST" action="" id="signForm">
            <?php csrf_field(); ?>
            <input type="hidden" name="post_action" value="digital_sign">
            <input type="hidden" name="ra_id" value="<?= $my_action_step['id'] ?>">
            <div class="form-group">
              <label style="font-size:13px;">Remarks / Notes <small class="text-muted">(optional)</small></label>
              <textarea name="remarks" class="form-control form-control-sm" rows="3"
                        placeholder="Add any notes, conditions, or comments..."></textarea>
            </div>
            <!-- Digital signature confirmation -->
            <div class="custom-control custom-checkbox mb-3">
              <input type="checkbox" class="custom-control-input" id="confirmSign" required>
              <label class="custom-control-label" for="confirmSign" style="font-size:13px;">
                I confirm that I have reviewed this request and I am affixing my
                <strong>digital signature</strong> as
                <strong><?= htmlspecialchars($my_action_step['step_label']) ?></strong>.
              </label>
            </div>
            <button type="submit" class="btn btn-primary" id="signBtn" disabled>
              <i class="fas fa-signature mr-1"></i>
              Digital Sign — <?= htmlspecialchars($my_action_step['action_label']) ?>
            </button>
          </form>

          <hr style="margin:16px 0;">

          <!-- Return form -->
          <form method="POST" action="">
            <?php csrf_field(); ?>
            <input type="hidden" name="post_action" value="return_request">
            <input type="hidden" name="ra_id" value="<?= $my_action_step['id'] ?>">
            <div class="form-group mb-2">
              <label style="font-size:13px;">Return / Decline Reason <span class="text-danger">*</span></label>
              <textarea name="remarks" class="form-control form-control-sm" rows="2"
                        placeholder="State the reason for returning or declining..." required></textarea>
            </div>
            <button type="submit" class="btn btn-outline-warning btn-sm"
                    onclick="return confirm('Return this request? The employee will be notified.')">
              <i class="fas fa-undo mr-1"></i> Return for Correction
            </button>
          </form>
        </div>
        <?php endif; ?>

      </div><!-- /.col-md-7 -->
    </div><!-- /.row -->

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
// Enable sign button only when checkbox is checked
document.getElementById('confirmSign') && document.getElementById('confirmSign').addEventListener('change', function(){
    document.getElementById('signBtn').disabled = !this.checked;
});

function printRequest() {
    window.print();
}
</script>

<!-- Retract Modal (shown on request_detail for filing employee) -->
<?php if ($is_own_request && in_array($req['status'], array('Pending','Pending Clarification','Returned'))): ?>
<div class="modal fade" id="retractModalDetail" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-undo-alt mr-2"></i>Retract Request</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="employee_requests.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="form_action" value="retract">
        <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
        <div class="modal-body">
          <p style="font-size:13px;">
            You are retracting <strong><?= htmlspecialchars($req['reference_no']) ?></strong>.
            This will cancel the request at its current stage.
          </p>
          <div class="alert alert-warning" style="font-size:12px;">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <strong>Note:</strong> You can retract at any stage — even after the Department Head
            has signed — as long as the President has not yet approved it.
          </div>
          <div class="form-group mb-0">
            <label style="font-size:13px;font-weight:600;">
              Reason for retracting <span class="text-danger">*</span>
            </label>
            <textarea name="retract_reason" class="form-control" rows="3"
                      placeholder="State your reason..." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"
                  onclick="return confirm('Retract this request? This cannot be undone.')">
            <i class="fas fa-undo-alt mr-1"></i> Confirm Retract
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
</script>
</body>
</html>