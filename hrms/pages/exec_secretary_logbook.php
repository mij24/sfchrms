<?php
// ============================================================
// FILE: pages/exec_secretary_logbook.php
// Executive Secretary Document Logbook
// - Log incoming documents/requests to executive office
// - Record executive decision (auto-linked or manual)
// - Log outgoing — release date, method, recipient
// - Department-scoped to exec secretary's assigned office
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

if (!has_role(ROLE_EXEC_SECRETARY)) {
    http_response_code(403); include '../includes/403.php'; exit;
}

// ── HRMS timezone ─────────────────────────────────────────────
$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz = 'Asia/Manila'; $_hrms_delta = 0;
foreach ($_tz_row as $_r) {
    if ($_r['setting_key']==='system_timezone'     && $_r['setting_value']) $_hrms_tz    = $_r['setting_value'];
    if ($_r['setting_key']==='clock_delta_seconds')                          $_hrms_delta = (int)$_r['setting_value'];
}
try { date_default_timezone_set($_hrms_tz); } catch(Exception $e) { date_default_timezone_set('Asia/Manila'); }
function hrms_now_es() { global $_hrms_delta; return time() + $_hrms_delta; }
function hrms_fmt_es($fmt) { return date($fmt, hrms_now_es()); }

// ── Secretary's assigned office from users table ──────────────
$my_uid  = (int)$_SESSION['user_id'];
$my_user = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array($my_uid));
$my_emp_id = $my_user ? (int)$my_user['employee_id'] : 0;

// Get assigned office — from users.assigned_office (set by HR)
$my_assigned_office = isset($my_user['assigned_office']) ? $my_user['assigned_office'] : '';

// Determine office prefix
function detect_office_prefix($office_label) {
    $map = array(
        'VP Academic'      => 'VPAA',
        'VP Administration'=> 'VPA',
        'President'        => 'PRES',
        'Board'            => 'BOT',
    );
    return isset($map[$office_label]) ? $map[$office_label] : 'PRES';
}

function office_label($prefix) {
    $map = array('VPAA'=>'VP for Academic Affairs','VPA'=>'VP for Administration',
                 'PRES'=>"President's Office",'BOT'=>'Board of Trustees');
    return isset($map[$prefix]) ? $map[$prefix] : $prefix;
}

$my_office_prefix = detect_office_prefix($my_assigned_office);
$my_office_label  = $my_assigned_office ?: "President's Office";

// ── Generate control number ────────────────────────────────────
function generate_control_number(PDO $pdo, $prefix) {
    $year = (int)date('Y');
    // Atomically increment counter
    $pdo->prepare(
        "INSERT INTO document_control_counter (office,year,counter) VALUES (?,?,1)
         ON DUPLICATE KEY UPDATE counter=counter+1"
    )->execute(array($prefix, $year));
    $counter = (int)db_value($pdo,
        "SELECT counter FROM document_control_counter WHERE office=? AND year=?",
        array($prefix, $year)
    );
    return $prefix.'-'.$year.'-'.str_pad($counter, 4, '0', STR_PAD_LEFT);
}

// ── Offices this secretary manages ────────────────────────────
$offices = array(
    'VP Academic'     => 'VPAA',
    'VP Administration' => 'VPA',
    'President'       => 'PRES',
    'Board'           => 'BOT',
);

$doc_categories = array('Request','Memo','Letter','Report','Contract',
                         'Certificate','Resolution','Order','Other');
$release_methods = array('Printed Copy','Email','System Notification',
                          'Courier','Walk-in Pickup','Fax','Other');
$exec_actions = array('Pending','Approved','Noted','Recommended',
                       'Rejected','Returned','Signed','For Compliance','Deferred');

// ── POST handling ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $post_action = trim(isset($_POST['post_action']) ? $_POST['post_action'] : '');

    // ── LOG INCOMING ──────────────────────────────────────────
    if ($post_action === 'log_incoming') {
        $office        = clean_string($_POST['office']);
        $doc_type      = clean_string($_POST['document_type']);
        $doc_cat       = clean_string($_POST['document_category']);
        $req_name      = clean_string($_POST['requestor_name']);
        $req_emp_code  = clean_string($_POST['requestor_emp_id']);
        $req_dept      = clean_string($_POST['requestor_dept']);
        $date_received = clean_string($_POST['date_received']); // Y-m-d H:i
        $received_from = clean_string($_POST['received_from']);
        $inc_remarks   = clean_string($_POST['incoming_remarks'], 1000);
        $request_id    = !empty($_POST['request_id']) ? (int)$_POST['request_id'] : null;
        $reference_no  = clean_string($_POST['reference_no']);

        // Get office prefix
        $office_prefix = isset($offices[$office]) ? $offices[$office] : $my_office_prefix;
        $control_no    = generate_control_number($pdo, $office_prefix);

        // Look up requestor employee if emp code given
        $req_emp_db_id = null;
        if ($req_emp_code) {
            $req_emp_row = db_fetch($pdo,
                "SELECT id, full_name, employee_id FROM employees WHERE employee_id=? LIMIT 1",
                array($req_emp_code)
            );
            if ($req_emp_row) {
                $req_emp_db_id = $req_emp_row['id'];
                if (!$req_name) $req_name = $req_emp_row['full_name'];
            }
        }

        // Auto-fill from request if linked
        if ($request_id && !$req_name) {
            $linked_req = db_fetch($pdo,
                "SELECT er.reference_no, e.full_name, e.employee_id, d.name AS dept,
                        rt.name AS type_name
                 FROM employee_requests er
                 JOIN employees e ON er.employee_id=e.id
                 LEFT JOIN departments d ON e.department_id=d.id
                 JOIN request_types rt ON er.request_type_id=rt.id
                 WHERE er.id=?", array($request_id)
            );
            if ($linked_req) {
                $req_name     = $linked_req['full_name'];
                $req_emp_code = $linked_req['employee_id'];
                $req_dept     = $linked_req['dept'];
                $reference_no = $linked_req['reference_no'];
                if (!$doc_type) $doc_type = $linked_req['type_name'];
            }
        }

        db_run($pdo,
            "INSERT INTO document_logbook
             (control_number, request_id, reference_no, office, document_type, document_category,
              requestor_name, requestor_emp_id, requestor_dept, requestor_employee_id,
              date_received, received_from, incoming_remarks,
              incoming_logged_by, incoming_logged_at,
              exec_action, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),'Pending','Incoming')",
            array($control_no, $request_id, $reference_no, $office, $doc_type, $doc_cat,
                  $req_name, $req_emp_code, $req_dept, $req_emp_db_id,
                  $date_received, $received_from, $inc_remarks, $my_uid)
        );

        audit_log($pdo,'INSERT','document_logbook',db_lastid($pdo),
            array('control_number'=>$control_no,'office'=>$office,'action'=>'incoming_logged'));
        prg_redirect('exec_secretary_logbook.php','Incoming document logged. Control No: '.$control_no);
    }

    // ── RECORD EXECUTIVE ACTION ──────────────────────────────
    if ($post_action === 'record_exec_action') {
        $log_id      = (int)$_POST['log_id'];
        $action      = clean_string($_POST['exec_action']);
        $action_by   = clean_string($_POST['exec_action_by']);
        $action_date = clean_string($_POST['exec_action_date']); // Y-m-d H:i
        $remarks     = clean_string($_POST['exec_remarks'], 1000);

        db_run($pdo,
            "UPDATE document_logbook SET
             exec_action=?, exec_action_by=?, exec_action_date=?,
             exec_remarks=?, status='With Executive', updated_at=NOW()
             WHERE id=?",
            array($action, $action_by, $action_date, $remarks, $log_id)
        );
        prg_redirect('exec_secretary_logbook.php?view='.$log_id,'Executive action recorded.');
    }

    // ── LOG OUTGOING ──────────────────────────────────────────
    if ($post_action === 'log_outgoing') {
        $log_id          = (int)$_POST['log_id'];
        $date_released   = clean_string($_POST['date_released']); // Y-m-d H:i
        $release_method  = clean_string($_POST['release_method']);
        $received_by     = clean_string($_POST['received_by']);
        $received_by_pos = clean_string($_POST['received_by_position']);
        $out_remarks     = clean_string($_POST['outgoing_remarks'], 1000);

        db_run($pdo,
            "UPDATE document_logbook SET
             is_released=1, date_released=?, release_method=?,
             received_by=?, received_by_position=?,
             outgoing_remarks=?, outgoing_logged_by=?, outgoing_logged_at=NOW(),
             status='Released', updated_at=NOW()
             WHERE id=?",
            array($date_released, $release_method, $received_by,
                  $received_by_pos, $out_remarks, $my_uid, $log_id)
        );
        prg_redirect('exec_secretary_logbook.php?view='.$log_id,'Outgoing release logged. Document marked as Released.');
    }
}

// ── View single log entry ─────────────────────────────────────
$view_id  = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_log = null;
if ($view_id) {
    $view_log = db_fetch($pdo,
        "SELECT dl.*, er.status AS req_status,
                u_in.username AS logged_by_name,
                u_out.username AS released_by_name
         FROM document_logbook dl
         LEFT JOIN employee_requests er ON dl.request_id=er.id
         LEFT JOIN users u_in  ON dl.incoming_logged_by=u_in.id
         LEFT JOIN users u_out ON dl.outgoing_logged_by=u_out.id
         WHERE dl.id=?",
        array($view_id)
    );
}

// ── Filter / list ─────────────────────────────────────────────
$f_office  = isset($_GET['office'])  ? trim($_GET['office'])  : '';
$f_status  = isset($_GET['status'])  ? trim($_GET['status'])  : '';
$f_month   = isset($_GET['month'])   ? (int)$_GET['month']    : (int)hrms_fmt_es('m');
$f_year    = isset($_GET['year'])    ? (int)$_GET['year']     : (int)hrms_fmt_es('Y');
$f_search  = isset($_GET['search'])  ? trim($_GET['search'])  : '';

$where  = array("MONTH(dl.date_received)=?", "YEAR(dl.date_received)=?");
$params = array($f_month, $f_year);
if ($f_office)  { $where[] = "dl.office=?";             $params[] = $f_office; }
if ($f_status)  { $where[] = "dl.status=?";             $params[] = $f_status; }
if ($f_search)  {
    $where[]  = "(dl.control_number LIKE ? OR dl.requestor_name LIKE ? OR dl.reference_no LIKE ?)";
    $like     = '%'.$f_search.'%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$logs = db_fetchall($pdo,
    "SELECT dl.* FROM document_logbook dl
     WHERE ".implode(" AND ",$where)."
     ORDER BY dl.date_received DESC",
    $params
);

// Pending requests available to link (not yet logged)
$pending_reqs = db_fetchall($pdo,
    "SELECT er.id, er.reference_no, er.status, er.current_step,
            e.full_name, e.employee_id AS emp_code,
            rt.name AS type_name
     FROM employee_requests er
     JOIN employees e ON er.employee_id=e.id
     JOIN request_types rt ON er.request_type_id=rt.id
     LEFT JOIN document_logbook dl ON dl.request_id=er.id
     WHERE er.status IN ('In Progress','Pending')
       AND dl.id IS NULL
     ORDER BY er.created_at DESC
     LIMIT 50"
);

$months = array(1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
                7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December');

$status_colors = array(
    'Incoming'=>'info','With Executive'=>'warning',
    'Released'=>'success','Cancelled'=>'secondary'
);
$action_colors = array(
    'Pending'=>'secondary','Approved'=>'success','Noted'=>'info',
    'Recommended'=>'primary','Rejected'=>'danger','Returned'=>'warning',
    'Signed'=>'success','For Compliance'=>'warning','Deferred'=>'secondary'
);

// Summary counts
$counts = db_fetchall($pdo,
    "SELECT status, COUNT(*) AS cnt FROM document_logbook
     WHERE MONTH(date_received)=? AND YEAR(date_received)=?
     GROUP BY status",
    array($f_month, $f_year)
);
$cnt_map = array();
foreach ($counts as $cc) $cnt_map[$cc['status']] = (int)$cc['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Executive Logbook | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .ctrl-no { font-family:monospace; font-size:15px; font-weight:700; color:#0f3460; letter-spacing:1px; }
    .office-tag { background:#0f3460; color:#fff; border-radius:6px; padding:2px 10px;
                  font-size:11px; font-weight:700; letter-spacing:.05em; }
    .log-card { border-radius:10px; border:1px solid #dee2e6; padding:16px;
                margin-bottom:12px; background:#fff; }
    .log-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.08); }
    .section-label { font-size:10px; font-weight:700; text-transform:uppercase;
                     color:#6c757d; letter-spacing:.08em; margin-bottom:6px; }
    .flow-line { display:flex; gap:0; margin:20px 0; }
    .flow-box { flex:1; text-align:center; padding:10px 6px; font-size:11px;
                border-top:3px solid #dee2e6; color:#adb5bd; }
    .flow-box.done   { border-color:#28a745; color:#28a745; font-weight:600; }
    .flow-box.active { border-color:#ffc107; color:#856404; font-weight:700; }
    .flow-box.out    { border-color:#007bff; color:#007bff; font-weight:700; }
    .stat-box { border-radius:10px; padding:14px; text-align:center; border:1px solid #dee2e6; background:#fff; }
    .stat-box .n { font-size:26px; font-weight:700; }
    .stat-box .l { font-size:11px; color:#6c757d; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-7">
        <h1 class="m-0">
          <i class="fas fa-book mr-2"></i>
          <?= htmlspecialchars($my_office_label) ?> — Document Logbook
        </h1>
      </div>
      <div class="col-sm-5 text-right">
        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalIncoming">
          <i class="fas fa-inbox mr-1"></i> Log Incoming
        </button>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <?php if ($view_log): ?>
    <!-- ══ DETAIL VIEW ══ -->
    <div class="mb-3">
      <a href="exec_secretary_logbook.php" class="btn btn-sm btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to logbook
      </a>
    </div>

    <!-- Document status flow -->
    <div class="flow-line">
      <div class="flow-box done">
        <i class="fas fa-inbox d-block mb-1"></i> Received
        <div style="font-size:10px;"><?= date('M d, Y h:i A',strtotime($view_log['date_received'])) ?></div>
      </div>
      <div class="flow-box <?= $view_log['exec_action'] !== 'Pending' ? 'done' : 'active' ?>">
        <i class="fas fa-user-tie d-block mb-1"></i> Executive Action
        <?php if ($view_log['exec_action'] !== 'Pending'): ?>
        <div style="font-size:10px;"><?= htmlspecialchars($view_log['exec_action']) ?></div>
        <?php else: ?>
        <div style="font-size:10px;">Awaiting</div>
        <?php endif; ?>
      </div>
      <div class="flow-box <?= $view_log['is_released'] ? 'out' : '' ?>">
        <i class="fas fa-paper-plane d-block mb-1"></i> Released
        <?php if ($view_log['is_released']): ?>
        <div style="font-size:10px;"><?= date('M d, Y h:i A',strtotime($view_log['date_released'])) ?></div>
        <?php else: ?>
        <div style="font-size:10px;">Not yet</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="row">
      <div class="col-md-7">

        <!-- Document details -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <span class="ctrl-no"><?= htmlspecialchars($view_log['control_number']) ?></span>
              <span class="office-tag ml-2"><?= htmlspecialchars($view_log['office']) ?></span>
            </div>
            <span class="badge badge-<?= isset($status_colors[$view_log['status']]) ? $status_colors[$view_log['status']] : 'secondary' ?>">
              <?= $view_log['status'] ?>
            </span>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-6">
                <div class="section-label">Document Type</div>
                <div style="font-size:14px;font-weight:600;"><?= htmlspecialchars($view_log['document_type']) ?></div>
                <div style="font-size:12px;color:#6c757d;"><?= htmlspecialchars($view_log['document_category']) ?></div>
              </div>
              <div class="col-6">
                <?php if ($view_log['reference_no']): ?>
                <div class="section-label">HRMS Reference</div>
                <div style="font-size:13px;"><code><?= htmlspecialchars($view_log['reference_no']) ?></code></div>
                <?php endif; ?>
              </div>
            </div>
            <hr style="margin:12px 0;">
            <div class="section-label">Requestor / Sender</div>
            <div style="font-size:15px;font-weight:600;"><?= htmlspecialchars($view_log['requestor_name']) ?></div>
            <div style="font-size:12px;color:#6c757d;">
              <?php if ($view_log['requestor_emp_id']): ?>
                <?= htmlspecialchars($view_log['requestor_emp_id']) ?> &mdash;
              <?php endif; ?>
              <?= htmlspecialchars($view_log['requestor_dept'] ?: '—') ?>
            </div>
          </div>
        </div>

        <!-- Incoming log -->
        <div class="card">
          <div class="card-header" style="background:#e8f4f8;">
            <h3 class="card-title"><i class="fas fa-inbox mr-1 text-info"></i> Incoming Record</h3>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-6">
                <div class="section-label">Date &amp; Time Received</div>
                <div><?= date('F d, Y h:i A',strtotime($view_log['date_received'])) ?></div>
              </div>
              <div class="col-6">
                <div class="section-label">Received From</div>
                <div><?= htmlspecialchars($view_log['received_from'] ?: '—') ?></div>
              </div>
            </div>
            <?php if ($view_log['incoming_remarks']): ?>
            <div class="mt-2">
              <div class="section-label">Remarks</div>
              <div style="font-size:13px;"><?= htmlspecialchars($view_log['incoming_remarks']) ?></div>
            </div>
            <?php endif; ?>
            <div class="mt-2" style="font-size:11px;color:#adb5bd;">
              Logged by: <?= htmlspecialchars($view_log['logged_by_name'] ?: '—') ?>
              <?php if ($view_log['incoming_logged_at']): ?>
                at <?= date('M d, Y h:i A',strtotime($view_log['incoming_logged_at'])) ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Executive action -->
        <div class="card">
          <div class="card-header" style="background:#fff8e1;">
            <h3 class="card-title"><i class="fas fa-user-tie mr-1 text-warning"></i> Executive Action</h3>
          </div>
          <div class="card-body">
            <?php if ($view_log['exec_action'] === 'Pending'): ?>
            <!-- Form to record exec action -->
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="post_action" value="record_exec_action">
              <input type="hidden" name="log_id" value="<?= $view_log['id'] ?>">
              <div class="row">
                <div class="col-6">
                  <div class="form-group">
                    <label style="font-size:12px;">Executive Decision <span class="text-danger">*</span></label>
                    <select name="exec_action" class="form-control form-control-sm" required>
                      <?php foreach ($exec_actions as $a): if ($a==='Pending') continue; ?>
                        <option><?= $a ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label style="font-size:12px;">Acted By <span class="text-danger">*</span></label>
                    <input type="text" name="exec_action_by" class="form-control form-control-sm"
                           placeholder="Name of executive" required>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label style="font-size:12px;">Date &amp; Time of Action <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="exec_action_date" class="form-control form-control-sm"
                           value="<?= hrms_fmt_es('Y-m-d\TH:i') ?>" required>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label style="font-size:12px;">Executive Remarks</label>
                    <textarea name="exec_remarks" class="form-control form-control-sm" rows="2"
                              placeholder="Notes or conditions..."></textarea>
                  </div>
                </div>
              </div>
              <button type="submit" class="btn btn-warning btn-sm">
                <i class="fas fa-save mr-1"></i> Record Executive Action
              </button>
            </form>
            <?php else: ?>
            <div class="row">
              <div class="col-4">
                <div class="section-label">Decision</div>
                <span class="badge badge-<?= isset($action_colors[$view_log['exec_action']]) ? $action_colors[$view_log['exec_action']] : 'secondary' ?>"
                      style="font-size:13px;padding:6px 12px;">
                  <?= htmlspecialchars($view_log['exec_action']) ?>
                </span>
              </div>
              <div class="col-4">
                <div class="section-label">Acted By</div>
                <div><?= htmlspecialchars($view_log['exec_action_by'] ?: '—') ?></div>
              </div>
              <div class="col-4">
                <div class="section-label">Date &amp; Time</div>
                <div><?= $view_log['exec_action_date'] ? date('M d, Y h:i A',strtotime($view_log['exec_action_date'])) : '—' ?></div>
              </div>
            </div>
            <?php if ($view_log['exec_remarks']): ?>
            <div class="mt-2">
              <div class="section-label">Executive Remarks</div>
              <div style="font-size:13px;font-style:italic;">"<?= htmlspecialchars($view_log['exec_remarks']) ?>"</div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Outgoing log -->
        <div class="card">
          <div class="card-header" style="background:#e8f5e9;">
            <h3 class="card-title"><i class="fas fa-paper-plane mr-1 text-success"></i> Outgoing / Release Record</h3>
          </div>
          <div class="card-body">
            <?php if (!$view_log['is_released']): ?>
            <?php if ($view_log['exec_action'] !== 'Pending'): ?>
            <!-- Form to log outgoing -->
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="post_action" value="log_outgoing">
              <input type="hidden" name="log_id" value="<?= $view_log['id'] ?>">
              <div class="row">
                <div class="col-6">
                  <div class="form-group">
                    <label style="font-size:12px;">Date Released <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="date_released" class="form-control form-control-sm"
                           value="<?= hrms_fmt_es('Y-m-d\TH:i') ?>" required>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label style="font-size:12px;">Release Method <span class="text-danger">*</span></label>
                    <select name="release_method" class="form-control form-control-sm" required>
                      <?php foreach ($release_methods as $m): ?>
                        <option><?= $m ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label style="font-size:12px;">Received By <span class="text-danger">*</span></label>
                    <input type="text" name="received_by" class="form-control form-control-sm"
                           placeholder="Full name of recipient" required>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label style="font-size:12px;">Recipient's Position</label>
                    <input type="text" name="received_by_position" class="form-control form-control-sm"
                           placeholder="e.g. Dept Secretary, HR Staff">
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group">
                    <label style="font-size:12px;">Outgoing Remarks</label>
                    <textarea name="outgoing_remarks" class="form-control form-control-sm" rows="2"
                              placeholder="Additional notes on release..."></textarea>
                  </div>
                </div>
              </div>
              <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-paper-plane mr-1"></i> Log Release / Outgoing
              </button>
            </form>
            <?php else: ?>
            <div class="alert alert-warning mb-0" style="font-size:13px;">
              <i class="fas fa-clock mr-1"></i>
              Record the executive decision first before logging the outgoing release.
            </div>
            <?php endif; ?>
            <?php else: ?>
            <!-- Outgoing already logged -->
            <div class="row">
              <div class="col-4">
                <div class="section-label">Date Released</div>
                <div><?= date('F d, Y h:i A',strtotime($view_log['date_released'])) ?></div>
              </div>
              <div class="col-4">
                <div class="section-label">Release Method</div>
                <span class="badge badge-info" style="font-size:11px;"><?= htmlspecialchars($view_log['release_method']) ?></span>
              </div>
              <div class="col-4">
                <div class="section-label">Received By</div>
                <div><?= htmlspecialchars($view_log['received_by'] ?: '—') ?></div>
                <?php if ($view_log['received_by_position']): ?>
                <div style="font-size:12px;color:#6c757d;"><?= htmlspecialchars($view_log['received_by_position']) ?></div>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($view_log['outgoing_remarks']): ?>
            <div class="mt-2">
              <div class="section-label">Remarks</div>
              <div style="font-size:13px;"><?= htmlspecialchars($view_log['outgoing_remarks']) ?></div>
            </div>
            <?php endif; ?>
            <div class="mt-2" style="font-size:11px;color:#adb5bd;">
              Released by: <?= htmlspecialchars($view_log['released_by_name'] ?: '—') ?>
              <?php if ($view_log['outgoing_logged_at']): ?>
                at <?= date('M d, Y h:i A',strtotime($view_log['outgoing_logged_at'])) ?>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
      <div class="col-md-5">
        <!-- Print-ready summary card -->
        <div class="card" id="printCard">
          <div class="card-header text-center" style="background:#0f3460;color:#fff;">
            <strong style="font-size:13px;">DOCUMENT LOGBOOK RECORD</strong><br>
            <span style="font-size:11px;opacity:.7;"><?= htmlspecialchars($my_office_label) ?></span>
          </div>
          <div class="card-body" style="font-size:12px;">
            <table class="table table-sm table-borderless mb-0">
              <tr><td class="text-muted" width="140">Control No.</td>
                  <td><strong class="ctrl-no" style="font-size:13px;"><?= htmlspecialchars($view_log['control_number']) ?></strong></td></tr>
              <tr><td class="text-muted">Office</td>
                  <td><?= htmlspecialchars($view_log['office']) ?></td></tr>
              <tr><td class="text-muted">Document Type</td>
                  <td><?= htmlspecialchars($view_log['document_type']) ?></td></tr>
              <tr><td class="text-muted">Category</td>
                  <td><?= htmlspecialchars($view_log['document_category']) ?></td></tr>
              <?php if ($view_log['reference_no']): ?>
              <tr><td class="text-muted">HRMS Ref.</td>
                  <td><code><?= htmlspecialchars($view_log['reference_no']) ?></code></td></tr>
              <?php endif; ?>
              <tr><td class="text-muted">Requestor</td>
                  <td><?= htmlspecialchars($view_log['requestor_name']) ?></td></tr>
              <tr><td class="text-muted">Department</td>
                  <td><?= htmlspecialchars($view_log['requestor_dept'] ?: '—') ?></td></tr>
              <tr><td colspan="2"><hr style="margin:6px 0;"></td></tr>
              <tr><td class="text-muted">Date Received</td>
                  <td><?= date('M d, Y h:i A',strtotime($view_log['date_received'])) ?></td></tr>
              <tr><td class="text-muted">Received From</td>
                  <td><?= htmlspecialchars($view_log['received_from'] ?: '—') ?></td></tr>
              <tr><td colspan="2"><hr style="margin:6px 0;"></td></tr>
              <tr><td class="text-muted">Exec. Decision</td>
                  <td><span class="badge badge-<?= isset($action_colors[$view_log['exec_action']]) ? $action_colors[$view_log['exec_action']] : 'secondary' ?>">
                    <?= htmlspecialchars($view_log['exec_action']) ?>
                  </span></td></tr>
              <tr><td class="text-muted">Decided By</td>
                  <td><?= htmlspecialchars($view_log['exec_action_by'] ?: '—') ?></td></tr>
              <tr><td class="text-muted">Decision Date</td>
                  <td><?= $view_log['exec_action_date'] ? date('M d, Y h:i A',strtotime($view_log['exec_action_date'])) : '—' ?></td></tr>
              <?php if ($view_log['exec_remarks']): ?>
              <tr><td class="text-muted">Exec. Remarks</td>
                  <td><em><?= htmlspecialchars($view_log['exec_remarks']) ?></em></td></tr>
              <?php endif; ?>
              <tr><td colspan="2"><hr style="margin:6px 0;"></td></tr>
              <tr><td class="text-muted">Status</td>
                  <td><span class="badge badge-<?= isset($status_colors[$view_log['status']]) ? $status_colors[$view_log['status']] : 'secondary' ?>">
                    <?= $view_log['status'] ?>
                  </span></td></tr>
              <?php if ($view_log['is_released']): ?>
              <tr><td class="text-muted">Date Released</td>
                  <td><?= date('M d, Y h:i A',strtotime($view_log['date_released'])) ?></td></tr>
              <tr><td class="text-muted">Release Method</td>
                  <td><?= htmlspecialchars($view_log['release_method'] ?: '—') ?></td></tr>
              <tr><td class="text-muted">Received By</td>
                  <td><?= htmlspecialchars($view_log['received_by'] ?: '—') ?></td></tr>
              <?php endif; ?>
            </table>
          </div>
          <div class="card-footer text-center">
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
              <i class="fas fa-print mr-1"></i> Print This Record
            </button>
          </div>
        </div>
      </div>
    </div>

    <?php else: ?>
    <!-- ══ LIST VIEW ══ -->

    <!-- Summary stats -->
    <div class="row mb-3">
      <?php
      $stat_defs = array(
        'Incoming'       => array('info',   'fas fa-inbox'),
        'With Executive' => array('warning','fas fa-user-tie'),
        'Released'       => array('success','fas fa-paper-plane'),
      );
      foreach ($stat_defs as $sl => $sd):
      ?>
      <div class="col-4 col-md-4 mb-2">
        <div class="stat-box">
          <div class="n text-<?= $sd[0] ?>"><?= isset($cnt_map[$sl]) ? $cnt_map[$sl] : 0 ?></div>
          <div class="l"><i class="<?= $sd[1] ?> mr-1"></i><?= $sl ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="">
          <div class="row">
            <div class="col-6 col-md-2 mb-2">
              <select name="month" class="form-control form-control-sm">
                <?php foreach ($months as $mn=>$ml): ?>
                  <option value="<?= $mn ?>" <?= $mn===$f_month?'selected':'' ?>><?= $ml ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-1 mb-2">
              <select name="year" class="form-control form-control-sm">
                <?php for ($y=(int)hrms_fmt_es('Y');$y>=(int)hrms_fmt_es('Y')-3;$y--): ?>
                  <option <?= $y===$f_year?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-6 col-md-2 mb-2">
              <select name="office" class="form-control form-control-sm">
                <option value="">All Offices</option>
                <?php foreach (array_keys($offices) as $o): ?>
                  <option <?= $f_office===$o?'selected':'' ?>><?= $o ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-2 mb-2">
              <select name="status" class="form-control form-control-sm">
                <option value="">All Statuses</option>
                <?php foreach (array_keys($status_colors) as $s): ?>
                  <option <?= $f_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-8 col-md-3 mb-2">
              <input type="text" name="search" class="form-control form-control-sm"
                     placeholder="Control No., Name, Reference..." value="<?= htmlspecialchars($f_search) ?>">
            </div>
            <div class="col-4 col-md-2 mb-2">
              <button type="submit" class="btn btn-sm btn-primary mr-1">
                <i class="fas fa-filter mr-1"></i> Filter
              </button>
              <a href="exec_secretary_logbook.php" class="btn btn-sm btn-secondary">Reset</a>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Logbook table -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
          Document Logbook — <?= $months[$f_month] ?> <?= $f_year ?>
          <span class="badge badge-primary ml-1"><?= count($logs) ?></span>
        </h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-bordered table-hover mb-0" id="logbookTable">
          <thead class="thead-dark">
            <tr>
              <th>Control No.</th><th>Office</th><th>Requestor</th>
              <th>Document Type</th><th>Date Received</th>
              <th>Exec. Action</th><th>Status</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $l):
              $sc  = isset($status_colors[$l['status']]) ? $status_colors[$l['status']] : 'secondary';
              $ac  = isset($action_colors[$l['exec_action']]) ? $action_colors[$l['exec_action']] : 'secondary';
            ?>
            <tr>
              <td><span class="ctrl-no" style="font-size:12px;"><?= htmlspecialchars($l['control_number']) ?></span></td>
              <td><span class="office-tag" style="font-size:10px;"><?= htmlspecialchars($l['office']) ?></span></td>
              <td style="font-size:12px;">
                <strong><?= htmlspecialchars($l['requestor_name']) ?></strong>
                <?php if ($l['requestor_dept']): ?>
                  <br><small class="text-muted"><?= htmlspecialchars($l['requestor_dept']) ?></small>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;">
                <?= htmlspecialchars($l['document_type']) ?>
                <br><small class="text-muted"><?= htmlspecialchars($l['document_category']) ?></small>
              </td>
              <td style="font-size:12px;"><?= date('M d, Y', strtotime($l['date_received'])) ?><br>
                <small><?= date('h:i A', strtotime($l['date_received'])) ?></small>
              </td>
              <td>
                <span class="badge badge-<?= $ac ?>" style="font-size:10px;">
                  <?= htmlspecialchars($l['exec_action']) ?>
                </span>
                <?php if ($l['exec_action_date']): ?>
                  <br><small style="font-size:10px;color:#6c757d;">
                    <?= date('M d h:i A',strtotime($l['exec_action_date'])) ?>
                  </small>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge-<?= $sc ?>" style="font-size:10px;"><?= $l['status'] ?></span>
                <?php if ($l['is_released']): ?>
                  <br><small style="font-size:10px;color:#28a745;">
                    <?= date('M d',strtotime($l['date_released'])) ?>
                    via <?= htmlspecialchars($l['release_method']) ?>
                  </small>
                <?php endif; ?>
              </td>
              <td>
                <a href="exec_secretary_logbook.php?view=<?= $l['id'] ?>" class="btn btn-xs btn-info">
                  <i class="fas fa-eye"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-4">
                <i class="fas fa-book fa-2x d-block mb-2" style="opacity:.2;"></i>
                No documents logged for this period.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php endif; // view_log ?>

  </div></div>
</div>

<!-- ══ MODAL: Log Incoming ══ -->
<div class="modal fade" id="modalIncoming" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#0f3460;color:#fff;">
        <h5 class="modal-title"><i class="fas fa-inbox mr-2"></i>Log Incoming Document</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="post_action" value="log_incoming">
        <div class="modal-body">
          <div class="row">
            <!-- Link to HRMS request (optional) -->
            <div class="col-12 mb-3">
              <label style="font-size:12px;">Link to HRMS Request (optional)</label>
              <select name="request_id" class="form-control form-control-sm" id="linkedRequest"
                      onchange="fillFromRequest(this)">
                <option value="">-- Not linked to a system request --</option>
                <?php foreach ($pending_reqs as $pr): ?>
                <option value="<?= $pr['id'] ?>"
                        data-name="<?= htmlspecialchars($pr['full_name']) ?>"
                        data-code="<?= htmlspecialchars($pr['emp_code']) ?>"
                        data-type="<?= htmlspecialchars($pr['type_name']) ?>"
                        data-ref="<?= htmlspecialchars($pr['reference_no']) ?>">
                  <?= htmlspecialchars($pr['reference_no']) ?> —
                  <?= htmlspecialchars($pr['type_name']) ?> —
                  <?= htmlspecialchars($pr['full_name']) ?>
                  (<?= $pr['status'] ?>)
                </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Selecting a request auto-fills requestor details.</small>
            </div>

            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Executive Office <span class="text-danger">*</span></label>
                <select name="office" class="form-control form-control-sm" required>
                  <?php foreach (array_keys($offices) as $o): ?>
                    <option <?= strpos($my_office_label,$o)!==false?'selected':'' ?>><?= $o ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Document Category <span class="text-danger">*</span></label>
                <select name="document_category" class="form-control form-control-sm" required>
                  <?php foreach ($doc_categories as $dc): ?>
                    <option <?= $dc==='Request'?'selected':'' ?>><?= $dc ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Document Type <span class="text-danger">*</span></label>
                <input type="text" name="document_type" id="docType" class="form-control form-control-sm"
                       placeholder="e.g. Leave Request, Teaching Load Approval" required>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">HRMS Reference No.</label>
                <input type="text" name="reference_no" id="refNo" class="form-control form-control-sm"
                       placeholder="e.g. REQ-20260427-0001">
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Requestor / Sender Name <span class="text-danger">*</span></label>
                <input type="text" name="requestor_name" id="reqName" class="form-control form-control-sm"
                       placeholder="Full name" required>
              </div>
            </div>
            <div class="col-3">
              <div class="form-group">
                <label style="font-size:12px;">Employee ID</label>
                <input type="text" name="requestor_emp_id" id="reqCode" class="form-control form-control-sm"
                       placeholder="EMP-0001">
              </div>
            </div>
            <div class="col-3">
              <div class="form-group">
                <label style="font-size:12px;">Department</label>
                <input type="text" name="requestor_dept" id="reqDept" class="form-control form-control-sm"
                       placeholder="Department">
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Date &amp; Time Received <span class="text-danger">*</span></label>
                <input type="datetime-local" name="date_received" class="form-control form-control-sm"
                       value="<?= hrms_fmt_es('Y-m-d\TH:i') ?>" required>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Received From</label>
                <input type="text" name="received_from" class="form-control form-control-sm"
                       placeholder="e.g. Dept Secretary, HR Office, Walk-in">
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label style="font-size:12px;">Incoming Remarks</label>
                <textarea name="incoming_remarks" class="form-control form-control-sm" rows="2"
                          placeholder="Any notes about the document on receipt..."></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i> Log Incoming Document
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    if ($.fn.DataTable && $('#logbookTable').length) {
        $('#logbookTable').DataTable({
            pageLength: 25, order:[[4,'desc']],
            dom:'lfrtip'
        });
    }
});

function fillFromRequest(sel) {
    var opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    document.getElementById('reqName').value = opt.getAttribute('data-name') || '';
    document.getElementById('reqCode').value  = opt.getAttribute('data-code') || '';
    document.getElementById('docType').value  = opt.getAttribute('data-type') || '';
    document.getElementById('refNo').value    = opt.getAttribute('data-ref')  || '';
}
</script>
</body>
</html>