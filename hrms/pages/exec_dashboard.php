<?php
// ============================================================
// FILE: pages/exec_dashboard.php
// Executive Dashboard for VP for Academic Affairs,
// VP for Administration, President, Board of Directors
// Full scope — no department filter
// Categorized requests: Leave / Teaching Load / Other
// Sections: Pending / Approved / Declined
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// Only VP-level and above, NOT dept head (manager)
$exec_roles = array('vp_admin','vp_academic','president','board_director');
$my_role    = $_SESSION['role'] ?? 'employee';
if (!in_array($my_role, $exec_roles)) {
    http_response_code(403); include '../includes/403.php'; exit;
}

// ── HRMS timezone ─────────────────────────────────────────────
$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz = 'Asia/Manila'; $_hrms_delta = 0;
foreach ($_tz_row as $_r) {
    if ($_r['setting_key']==='system_timezone' && $_r['setting_value']) $_hrms_tz = $_r['setting_value'];
    if ($_r['setting_key']==='clock_delta_seconds') $_hrms_delta = (int)$_r['setting_value'];
}
try { date_default_timezone_set($_hrms_tz); } catch(Exception $e) { date_default_timezone_set('Asia/Manila'); }

$my_uid  = (int)$_SESSION['user_id'];
$my_user = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array($my_uid));
$my_emp_id = $my_user ? (int)$my_user['employee_id'] : 0;

// Role labels for display
global $ROLE_LABELS;
$role_display = isset($ROLE_LABELS[$my_role]) ? $ROLE_LABELS[$my_role] : ucwords(str_replace('_',' ',$my_role));

// ── Role → what steps this executive acts on ──────────────────
$role_to_steps = array(
    'vp_academic'   => array('vp_academic'),
    'vp_admin'      => array('vp_admin'),
    'president'     => array('president'),
    'board_director'=> array('president','board_director'),
);
$my_step_roles = isset($role_to_steps[$my_role]) ? $role_to_steps[$my_role] : array($my_role);
$placeholders  = implode(',', array_fill(0, count($my_step_roles), '?'));

// ── POST: Approve / Reject / Return ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form_action = clean_string($_POST['form_action'] ?? '');
    $ra_id       = (int)($_POST['ra_id'] ?? 0);
    $req_id      = (int)($_POST['req_id'] ?? 0);
    $remarks     = clean_string($_POST['remarks'] ?? '', 500);

    if (in_array($form_action, array('approve','reject'))) {
        // Verify this step belongs to this executive's roles
        $step = db_fetch($pdo,
            "SELECT ra.*, at.triggers_logbook, at.logbook_office, at.can_proceed_after_this
             FROM request_approvals ra
             LEFT JOIN approval_templates at
               ON at.request_type_id=(SELECT request_type_id FROM employee_requests WHERE id=ra.request_id)
                  AND at.step_order=ra.step_order
             WHERE ra.id=? AND ra.request_id=? AND ra.status='Pending'",
            array($ra_id, $req_id)
        );

        if (!$step || !in_array($step['role_required'], $my_step_roles)) {
            prg_redirect('exec_dashboard.php','Access denied or step not available.');
        }

        if ($form_action === 'approve') {
            // Digital sign + approve
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

            // Handle can_proceed
            if (!empty($step['can_proceed_after_this'])) {
                db_run($pdo,"UPDATE employee_requests SET can_proceed=1,last_action_at=NOW(),last_action_by=? WHERE id=?",
                    array($my_uid,$req_id));
            }

            // Advance to next step
            $next = db_fetch($pdo,
                "SELECT * FROM request_approvals WHERE request_id=? AND status='Pending'
                 ORDER BY step_order ASC LIMIT 1",
                array($req_id)
            );
            if ($next) {
                db_run($pdo,"UPDATE employee_requests SET current_step=?,last_action_at=NOW(),last_action_by=? WHERE id=?",
                    array($next['step_order'],$my_uid,$req_id));
            } else {
                db_run($pdo,"UPDATE employee_requests SET status='Approved',last_action_at=NOW(),last_action_by=? WHERE id=?",
                    array($my_uid,$req_id));
                // Deduct leave credits if not yet done
                _exec_deduct_credits($pdo,$req_id,$my_uid);
            }

            // Auto-trigger logbook if needed
            if (!empty($step['triggers_logbook']) && !empty($step['logbook_office'])) {
                $req_full = db_fetch($pdo,"SELECT * FROM employee_requests WHERE id=?",array($req_id));
                _exec_trigger_logbook($pdo,$req_full,$step,$my_uid);
            }

            audit_log($pdo,'DIGITAL_SIGN','request_approvals',$ra_id,
                array('action'=>$step['action_label'],'step'=>$step['step_label'],
                      'request_id'=>$req_id,'signer'=>$signer_name));
            prg_redirect('exec_dashboard.php','Request signed and forwarded.');

        } else {
            // Reject / Return
            if (empty($remarks)) {
                prg_redirect('exec_dashboard.php','Reason is required when declining.');
            }
            db_run($pdo,
                "UPDATE request_approvals SET status='Rejected',acted_by=?,acted_at=NOW(),remarks=? WHERE id=?",
                array($my_uid,$remarks,$ra_id)
            );
            db_run($pdo,
                "UPDATE employee_requests SET status='Returned',rejection_reason=?,last_action_at=NOW(),last_action_by=? WHERE id=?",
                array($remarks,$my_uid,$req_id)
            );
            audit_log($pdo,'REJECT','employee_requests',$req_id,
                array('step'=>$step['step_label'],'reason'=>$remarks));
            prg_redirect('exec_dashboard.php','Request declined and returned.');
        }
    }
}

function _exec_deduct_credits(PDO $pdo, $req_id, $uid) {
    $already = (int)db_value($pdo,"SELECT credits_deducted FROM employee_requests WHERE id=?",array($req_id));
    if ($already) return;
    $req = db_fetch($pdo,"SELECT er.*,rt.category,rt.name AS type_name FROM employee_requests er JOIN request_types rt ON er.request_type_id=rt.id WHERE er.id=?",array($req_id));
    if (!$req || $req['category']!=='Leave' || !$req['date_from']) return;
    $leave_map = array('Vacation Leave'=>'Vacation leave','Sick Leave'=>'Sick leave','Emergency Leave'=>'Emergency leave','Maternity Leave'=>'Maternity leave','Paternity Leave'=>'Paternity leave','Solo Parent Leave'=>'Solo parent leave');
    $lt = isset($leave_map[$req['type_name']]) ? $leave_map[$req['type_name']] : $req['type_name'];
    db_run($pdo,"INSERT IGNORE INTO leave_requests (employee_id,leave_type,date_from,date_to,days_applied,reason,status,approved_by,approved_at) VALUES (?,?,?,?,?,?,'Approved',?,NOW())",array($req['employee_id'],$lt,$req['date_from'],$req['date_to'],$req['days_applied'],$req['purpose'],$uid));
    $credit_map = array('Vacation leave'=>'vacation_used','Sick leave'=>'sick_used','Emergency leave'=>'emergency_used','Maternity leave'=>'maternity_used','Paternity leave'=>'paternity_used','Solo parent leave'=>'solo_parent_used');
    if (isset($credit_map[$lt])) {
        $uc = $credit_map[$lt];
        db_run($pdo,"UPDATE leave_credits SET $uc=$uc+? WHERE employee_id=? AND year=YEAR(CURDATE())",array($req['days_applied'],$req['employee_id']));
        db_run($pdo,"UPDATE employee_requests SET credits_deducted=1 WHERE id=?",array($req_id));
    }
}

function _exec_trigger_logbook(PDO $pdo, $req, $step, $uid) {
    if (!$req) return;
    $exists = db_value($pdo,"SELECT COUNT(*) FROM document_logbook WHERE request_id=? AND office=?",array($req['id'],$step['logbook_office']));
    if ($exists) return;
    $prefix_map = array('VP Administration'=>'VPA','VP Academic'=>'VPAA','President'=>'PRES','Board'=>'BOT');
    $prefix = isset($prefix_map[$step['logbook_office']]) ? $prefix_map[$step['logbook_office']] : 'PRES';
    $year = (int)date('Y');
    $pdo->prepare("INSERT INTO document_control_counter (office,year,counter) VALUES (?,?,1) ON DUPLICATE KEY UPDATE counter=counter+1")->execute(array($prefix,$year));
    $counter = (int)db_value($pdo,"SELECT counter FROM document_control_counter WHERE office=? AND year=?",array($prefix,$year));
    $ctrl = $prefix.'-'.$year.'-'.str_pad($counter,4,'0',STR_PAD_LEFT);
    $emp = db_fetch($pdo,"SELECT e.full_name,e.employee_id,d.name AS dept FROM employees e LEFT JOIN departments d ON e.department_id=d.id WHERE e.id=?",array($req['employee_id']));
    db_run($pdo,"INSERT INTO document_logbook (control_number,request_id,reference_no,office,document_type,document_category,requestor_name,requestor_emp_id,requestor_dept,requestor_employee_id,date_received,received_from,incoming_remarks,incoming_logged_by,incoming_logged_at,exec_action,status) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),'System - approval workflow',?,?,NOW(),'Pending','Incoming')",array($ctrl,$req['id'],$req['reference_no'],$step['logbook_office'],$req['type_name'],'Request',$emp?$emp['full_name']:'',$emp?$emp['employee_id']:'',$emp?$emp['dept']:'',$req['employee_id'],'Auto-logged on workflow step: '.$step['step_label'],$uid));
}

// ── Filters ───────────────────────────────────────────────────
$f_category = isset($_GET['category']) ? trim($_GET['category']) : '';
$f_status   = isset($_GET['status'])   ? trim($_GET['status'])   : '';
$f_search   = isset($_GET['search'])   ? trim($_GET['search'])   : '';

// ── Load data ─────────────────────────────────────────────────
// 1. Pending (my action required)
$pending = db_fetchall($pdo,
    "SELECT er.*, rt.name AS type_name, rt.category,
            e.full_name AS emp_name, e.employee_id AS emp_code,
            d.name AS dept_name,
            ra.id AS ra_id, ra.step_label, ra.action_label, ra.step_order
     FROM request_approvals ra
     JOIN employee_requests er ON ra.request_id=er.id
     JOIN request_types rt ON er.request_type_id=rt.id
     JOIN employees e ON er.employee_id=e.id
     LEFT JOIN departments d ON e.department_id=d.id
     WHERE ra.status='Pending'
       AND ra.role_required IN ($placeholders)
       AND er.current_step=ra.step_order
       AND er.status IN ('Pending','In Progress')
     ORDER BY er.created_at ASC",
    $my_step_roles
);

// 2. All requests this exec has visibility on (acted on OR pending)
$where_all  = array("ra.role_required IN ($placeholders)");
$params_all = $my_step_roles;
if ($f_category) { $where_all[] = "rt.category=?"; $params_all[] = $f_category; }
if ($f_status)   { $where_all[] = "er.status=?";   $params_all[] = $f_status; }
if ($f_search)   {
    $where_all[] = "(er.reference_no LIKE ? OR e.full_name LIKE ? OR rt.name LIKE ?)";
    $like = '%'.$f_search.'%';
    $params_all[] = $like; $params_all[] = $like; $params_all[] = $like;
}

$all_requests = db_fetchall($pdo,
    "SELECT DISTINCT er.*, rt.name AS type_name, rt.category,
            e.full_name AS emp_name, e.employee_id AS emp_code,
            d.name AS dept_name,
            ra.step_label, ra.action_label, ra.status AS step_status,
            ra.digital_signed, ra.signed_name, ra.signed_at, ra.acted_at, ra.remarks AS step_remarks
     FROM request_approvals ra
     JOIN employee_requests er ON ra.request_id=er.id
     JOIN request_types rt ON er.request_type_id=rt.id
     JOIN employees e ON er.employee_id=e.id
     LEFT JOIN departments d ON e.department_id=d.id
     WHERE ".implode(' AND ',$where_all)."
     ORDER BY er.created_at DESC",
    $params_all
);

// Split into categories for tab counts
$by_category = array('Leave'=>array(),'Teaching Load'=>array(),'Other'=>array());
$by_status   = array('Pending'=>array(),'Approved'=>array(),'Returned'=>array(),'Rejected'=>array());
foreach ($all_requests as $r) {
    $cat = $r['category'] ?? 'Other';
    if (!isset($by_category[$cat])) $by_category[$cat] = array();
    $by_category[$cat][] = $r;
    $st = $r['status'];
    if (!isset($by_status[$st])) $by_status[$st] = array();
    $by_status[$st][] = $r;
}

// Stats
$total_pending  = count($pending);
$total_approved = count($by_status['Approved'] ?? array());
$total_declined = count(array_merge($by_status['Returned'] ?? array(), $by_status['Rejected'] ?? array()));

$status_color = array(
    'Pending'=>'warning','In Progress'=>'info','Approved'=>'success',
    'Rejected'=>'danger','Returned'=>'warning','Cancelled'=>'secondary'
);

$categories = array('Leave','Teaching Load','Other');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .exec-banner {
      background:linear-gradient(135deg,#0d1b2a,#1b3a5c);
      color:#fff; border-radius:12px; padding:18px 24px; margin-bottom:20px;
    }
    .exec-banner .role-lbl { font-size:11px; opacity:.4; text-transform:uppercase; letter-spacing:.1em; }
    .exec-banner .role-name { font-size:22px; font-weight:700; margin:4px 0 2px; }
    .stat-box { border-radius:10px; padding:16px; text-align:center;
                background:#fff; border:1px solid #dee2e6; margin-bottom:12px; }
    .stat-box .n { font-size:28px; font-weight:700; line-height:1; }
    .stat-box .l { font-size:11px; color:#6c757d; margin-top:4px; }
    .digital-stamp {
      display:inline-block; border:1px solid #28a745; border-radius:6px;
      padding:2px 10px; font-size:10px; color:#28a745; font-weight:700;
      text-transform:uppercase; letter-spacing:.05em; margin-top:4px;
    }
    .req-row-pending { background:#fffde7; }
    @media print {
      .wrapper > .main-header,.main-sidebar,.no-print,.breadcrumb { display:none !important; }
      .content-wrapper { margin-left:0 !important; }
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
        <h1 class="m-0"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">Dashboard</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- Executive banner -->
    <div class="exec-banner">
      <div class="row align-items-center">
        <div class="col">
          <div class="role-lbl">Signed in as</div>
          <div class="role-name"><?= htmlspecialchars($role_display) ?></div>
          <div style="font-size:12px;opacity:.5;"><?= date('l, F d, Y', time()+$_hrms_delta) ?></div>
        </div>
        <div class="col-auto">
          <div class="text-right">
            <div style="font-size:28px;font-weight:700;"><?= $total_pending ?></div>
            <div style="font-size:11px;opacity:.6;">Pending your action</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="row mb-2">
      <div class="col-4">
        <div class="stat-box">
          <div class="n text-warning"><?= $total_pending ?></div>
          <div class="l"><i class="fas fa-clock mr-1"></i>Pending</div>
        </div>
      </div>
      <div class="col-4">
        <div class="stat-box">
          <div class="n text-success"><?= $total_approved ?></div>
          <div class="l"><i class="fas fa-check mr-1"></i>Approved</div>
        </div>
      </div>
      <div class="col-4">
        <div class="stat-box">
          <div class="n text-danger"><?= $total_declined ?></div>
          <div class="l"><i class="fas fa-times mr-1"></i>Declined</div>
        </div>
      </div>
    </div>

    <!-- ══ PENDING: Action Required ══ -->
    <?php if (!empty($pending)): ?>
    <div class="card card-outline card-warning mb-3">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-bell text-warning mr-1"></i>
          Requests Awaiting Your Action
          <span class="badge badge-warning ml-1"><?= count($pending) ?></span>
        </h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead class="thead-light">
            <tr>
              <th>Reference</th><th>Employee</th><th>Department</th>
              <th>Type</th><th>Details</th><th>Your Action</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pending as $r): ?>
            <tr class="req-row-pending">
              <td>
                <code style="font-size:11px;"><?= htmlspecialchars($r['reference_no']) ?></code><br>
                <small class="text-muted"><?= date('M d, Y', strtotime($r['created_at'])) ?></small>
              </td>
              <td style="font-size:13px;">
                <strong><?= htmlspecialchars($r['emp_name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($r['emp_code']) ?></small>
              </td>
              <td style="font-size:12px;"><?= htmlspecialchars($r['dept_name'] ?: '—') ?></td>
              <td>
                <span class="badge badge-secondary" style="font-size:10px;">
                  <?= htmlspecialchars($r['category'] ?? '') ?>
                </span><br>
                <small><?= htmlspecialchars($r['type_name']) ?></small>
              </td>
              <td style="font-size:12px;">
                <?php if ($r['date_from']): ?>
                  <?= date('M d', strtotime($r['date_from'])) ?> –
                  <?= date('M d, Y', strtotime($r['date_to'])) ?>
                  (<?= $r['days_applied'] ?> day(s))<br>
                <?php endif; ?>
                <?php if ($r['purpose']): ?>
                  <em style="color:#6c757d;"><?= htmlspecialchars(substr($r['purpose'],0,60)) ?><?= strlen($r['purpose'])>60?'...':'' ?></em>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge-primary" style="font-size:10px;"><?= htmlspecialchars($r['step_label']) ?></span><br>
                <small class="text-muted"><?= htmlspecialchars($r['action_label']) ?></small>
              </td>
              <td style="white-space:nowrap;">
                <a href="request_detail.php?id=<?= $r['id'] ?>"
                   class="btn btn-primary btn-sm mb-1">
                  <i class="fas fa-signature mr-1"></i> Review &amp; Sign
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ══ ALL REQUESTS — Categorized ══ -->
    <div class="card">
      <div class="card-header p-0 pt-1">
        <!-- Category tabs -->
        <ul class="nav nav-tabs px-3" id="catTabs">
          <li class="nav-item">
            <a class="nav-link <?= !$f_category?'active':'' ?>"
               href="exec_dashboard.php?status=<?= urlencode($f_status) ?>&search=<?= urlencode($f_search) ?>">
              All <span class="badge badge-secondary ml-1"><?= count($all_requests) ?></span>
            </a>
          </li>
          <?php foreach ($categories as $cat): ?>
          <li class="nav-item">
            <a class="nav-link <?= $f_category===$cat?'active':'' ?>"
               href="exec_dashboard.php?category=<?= urlencode($cat) ?>&status=<?= urlencode($f_status) ?>&search=<?= urlencode($f_search) ?>">
              <?= htmlspecialchars($cat) ?>
              <span class="badge badge-secondary ml-1"><?= count($by_category[$cat] ?? array()) ?></span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="card-body">
        <!-- Status filter + search -->
        <form method="GET" action="" class="row mb-3">
          <div class="col-auto">
            <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
              <option value="">All Statuses</option>
              <?php foreach (array('Pending','In Progress','Approved','Rejected','Returned','Cancelled') as $st): ?>
                <option value="<?= $st ?>" <?= $f_status===$st?'selected':'' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="category" value="<?= htmlspecialchars($f_category) ?>">
          </div>
          <div class="col-auto">
            <div class="input-group input-group-sm">
              <input type="text" name="search" class="form-control"
                     placeholder="Search name, reference, type..."
                     value="<?= htmlspecialchars($f_search) ?>">
              <div class="input-group-append">
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="fas fa-search"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="col-auto">
            <a href="exec_dashboard.php" class="btn btn-sm btn-secondary">Reset</a>
          </div>
        </form>

        <!-- Requests table -->
        <table class="table table-sm table-bordered table-hover" id="execReqTable">
          <thead class="thead-dark">
            <tr>
              <th>Reference</th><th>Employee</th><th>Dept</th>
              <th>Category</th><th>Type</th><th>Details</th>
              <th>My Step</th><th>Status</th><th>Signed</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($all_requests as $r):
              $sc = isset($status_color[$r['status']]) ? $status_color[$r['status']] : 'secondary';
            ?>
            <tr>
              <td>
                <code style="font-size:11px;"><?= htmlspecialchars($r['reference_no']) ?></code><br>
                <small class="text-muted"><?= date('M d, Y', strtotime($r['created_at'])) ?></small>
              </td>
              <td style="font-size:12px;">
                <strong><?= htmlspecialchars($r['emp_name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($r['emp_code']) ?></small>
              </td>
              <td style="font-size:11px;"><?= htmlspecialchars($r['dept_name'] ?: '—') ?></td>
              <td>
                <span class="badge badge-secondary" style="font-size:10px;">
                  <?= htmlspecialchars($r['category'] ?? '—') ?>
                </span>
              </td>
              <td style="font-size:12px;"><?= htmlspecialchars($r['type_name']) ?></td>
              <td style="font-size:12px;">
                <?php if ($r['date_from']): ?>
                  <?= date('M d', strtotime($r['date_from'])) ?> –
                  <?= date('M d, Y', strtotime($r['date_to'])) ?>
                  (<?= $r['days_applied'] ?> day(s))
                <?php endif; ?>
              </td>
              <td style="font-size:11px;">
                <span class="badge badge-<?= $r['step_status']==='Approved'?'success':($r['step_status']==='Rejected'?'danger':'warning') ?>"
                      style="font-size:10px;">
                  <?= htmlspecialchars($r['step_label']) ?>
                </span>
              </td>
              <td>
                <span class="badge badge-<?= $sc ?>" style="font-size:10px;"><?= $r['status'] ?></span>
              </td>
              <td style="font-size:11px;">
                <?php if ($r['digital_signed']): ?>
                  <div class="digital-stamp">
                    <i class="fas fa-signature mr-1"></i>Signed
                  </div>
                  <?php if ($r['signed_at']): ?>
                    <div style="font-size:10px;color:#6c757d;">
                      <?= date('M d h:i A', strtotime($r['signed_at'])) ?>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted" style="font-size:11px;">—</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap;">
                <a href="request_detail.php?id=<?= $r['id'] ?>"
                   class="btn btn-xs <?= $r['step_status']==='Pending' && in_array($r['status'],array('Pending','In Progress'))?'btn-primary':'btn-info' ?>">
                  <i class="fas fa-<?= $r['step_status']==='Pending' && in_array($r['status'],array('Pending','In Progress'))?'signature':'eye' ?> mr-1"></i>
                  <?= $r['step_status']==='Pending' && in_array($r['status'],array('Pending','In Progress'))?'Sign':'View' ?>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($all_requests)): ?>
            <tr>
              <td colspan="10" class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-2x d-block mb-2" style="opacity:.2;"></i>
                No requests found for the selected filters.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    if ($.fn.DataTable && $('#execReqTable').length) {
        $('#execReqTable').DataTable({
            pageLength: 25,
            order: [[0,'desc']],
            dom: 'lfrtip'
        });
    }
});
</script>
</body>
</html>