<?php
// ============================================================
// FILE: pages/exec_secretary_dashboard.php
// Executive Secretary Dashboard
// For secretaries of VP Academic, VP Administration, President
// - Overview of pending logbook entries
// - Quick access to log incoming/outgoing
// - View requests currently at their executive's desk
// - All requests that passed through their office
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// Allow exec_secretary; also allow higher roles to view
if (!function_exists('is_exec_secretary') || 
    (!is_exec_secretary() && !is_manager())) {
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

// ── Get secretary's assigned office ──────────────────────────
$my_uid     = (int)$_SESSION['user_id'];
$my_user    = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array($my_uid));
$my_office  = $my_user['assigned_office'] ?? '';

// Validate office assignment
if (empty($my_office)) {
    ?><!DOCTYPE html><html><head><meta charset="utf-8">
    <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
    </head><body class="hold-transition sidebar-mini"><div class="wrapper">
    <?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
    <div class="content-wrapper"><div class="content"><div class="container-fluid mt-4">
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <strong>No executive office assigned.</strong><br>
        Your account has not been assigned to an executive office.
        Please contact HR to set your <strong>Assigned Executive Office</strong>
        in User Management (VP Academic / VP Administration / President / Board).
      </div>
    </div></div></div>
    <?php include '../includes/footer.php'; ?></div></body></html>
    <?php exit;
}

// Office prefix for control numbers
$prefix_map = array(
    'VP Academic'      => 'VPAA',
    'VP Administration'=> 'VPA',
    'President'        => 'PRES',
    'Board'            => 'BOT',
);
$office_exec_role = array(
    'VP Academic'      => 'vp_academic',
    'VP Administration'=> 'vp_admin',
    'President'        => 'president',
    'Board'            => 'board_director',
);
$my_prefix      = isset($prefix_map[$my_office]) ? $prefix_map[$my_office] : 'PRES';
$my_exec_role   = isset($office_exec_role[$my_office]) ? $office_exec_role[$my_office] : 'president';

// ── Load logbook data for this office ────────────────────────
// Pending incoming (not yet acted on by executive)
$pending_incoming = db_fetchall($pdo,
    "SELECT * FROM document_logbook
     WHERE office=? AND status='Incoming'
     ORDER BY date_received ASC",
    array($my_office)
);

// With executive (signed, not yet released)
$with_executive = db_fetchall($pdo,
    "SELECT * FROM document_logbook
     WHERE office=? AND status='With Executive'
     ORDER BY exec_action_date DESC",
    array($my_office)
);

// Recently released (last 30 days)
$recently_released = db_fetchall($pdo,
    "SELECT * FROM document_logbook
     WHERE office=? AND status='Released'
       AND date_released >= DATE_SUB(NOW(),INTERVAL 30 DAY)
     ORDER BY date_released DESC",
    array($my_office)
);

// All requests currently at this executive's step
$requests_at_exec = db_fetchall($pdo,
    "SELECT er.*, rt.name AS type_name,
            e.full_name AS emp_name, e.employee_id AS emp_code,
            d.name AS dept_name, ra.step_label, ra.action_label
     FROM employee_requests er
     JOIN request_types rt ON er.request_type_id=rt.id
     JOIN employees e ON er.employee_id=e.id
     LEFT JOIN departments d ON e.department_id=d.id
     JOIN request_approvals ra ON ra.request_id=er.id
     WHERE ra.role_required=?
       AND ra.status='Pending'
       AND er.current_step=ra.step_order
       AND er.status IN ('Pending','In Progress')
     ORDER BY er.created_at ASC",
    array($my_exec_role)
);

// Stats this month
$cur_month = (int)date('m');
$cur_year  = (int)date('Y');
$month_stats = db_fetchall($pdo,
    "SELECT status, COUNT(*) AS cnt FROM document_logbook
     WHERE office=? AND MONTH(date_received)=? AND YEAR(date_received)=?
     GROUP BY status",
    array($my_office, $cur_month, $cur_year)
);
$stats = array('Incoming'=>0,'With Executive'=>0,'Released'=>0);
foreach ($month_stats as $ms) $stats[$ms['status']] = (int)$ms['cnt'];

$status_colors = array(
    'Incoming'=>'info','With Executive'=>'warning','Released'=>'success','Cancelled'=>'secondary'
);
$action_colors = array(
    'Pending'=>'secondary','Approved'=>'success','Noted'=>'info',
    'Recommended'=>'primary','Rejected'=>'danger','Returned'=>'warning',
    'Signed'=>'success','For Compliance'=>'warning','Deferred'=>'secondary'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Executive Secretary Dashboard | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .office-banner {
      background:linear-gradient(135deg,#1a1a2e,#16213e);
      color:#fff; border-radius:12px; padding:18px 24px; margin-bottom:20px;
    }
    .office-banner .role-lbl { font-size:11px; opacity:.4; text-transform:uppercase; letter-spacing:.1em; }
    .office-banner .office-name { font-size:22px; font-weight:700; margin:4px 0 2px; }
    .office-banner .office-meta { font-size:12px; opacity:.55; }
    .ctrl-no { font-family:monospace; font-weight:700; font-size:13px; color:#0f3460; letter-spacing:1px; }
    .stat-box { border-radius:10px; padding:16px; text-align:center; background:#fff; border:1px solid #dee2e6; }
    .stat-box .n { font-size:28px; font-weight:700; }
    .stat-box .l { font-size:11px; color:#6c757d; }
    .req-card { border-left:4px solid #dee2e6; padding:14px; margin-bottom:10px;
                background:#fff; border-radius:0 8px 8px 0; box-shadow:0 1px 3px rgba(0,0,0,.06); }
    .req-card.incoming     { border-left-color:#17a2b8; }
    .req-card.with-exec    { border-left-color:#ffc107; }
    .log-stage { font-size:10px; font-weight:700; text-transform:uppercase;
                 letter-spacing:.07em; color:#6c757d; margin-bottom:4px; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0"><i class="fas fa-book mr-2"></i>Executive Secretary Dashboard</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">Exec. Secretary</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- Office banner -->
    <div class="office-banner">
      <div class="row align-items-center">
        <div class="col-md-7">
          <div class="role-lbl">Executive Secretary — Document Logbook</div>
          <div class="office-name">
            <i class="fas fa-building mr-2"></i>
            <?= htmlspecialchars($my_office) ?>
          </div>
          <div class="office-meta">
            Control number prefix: <strong><?= $my_prefix ?>-<?= date('Y') ?>-XXXX</strong>
            &nbsp;|&nbsp; <?= date('F Y') ?>
          </div>
        </div>
        <div class="col-md-5 text-right">
          <a href="exec_secretary_logbook.php" class="btn btn-light btn-sm mr-2">
            <i class="fas fa-book mr-1"></i> Open Full Logbook
          </a>
          <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#modalIncoming">
            <i class="fas fa-inbox mr-1"></i> Log Incoming Document
          </button>
        </div>
      </div>
    </div>

    <!-- Stats row -->
    <div class="row mb-3">
      <div class="col-6 col-md-3">
        <div class="stat-box">
          <div class="n text-info"><?= count($pending_incoming) ?></div>
          <div class="l"><i class="fas fa-inbox mr-1"></i>Awaiting Exec Action</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-box">
          <div class="n text-warning"><?= count($with_executive) ?></div>
          <div class="l"><i class="fas fa-user-tie mr-1"></i>With Executive</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-box">
          <div class="n text-primary"><?= count($requests_at_exec) ?></div>
          <div class="l"><i class="fas fa-file-alt mr-1"></i>Requests at Exec Desk</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-box">
          <div class="n text-success"><?= $stats['Released'] ?></div>
          <div class="l"><i class="fas fa-paper-plane mr-1"></i>Released This Month</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="card">
      <div class="card-header p-0">
        <ul class="nav nav-tabs">
          <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#tab-incoming">
              <i class="fas fa-inbox mr-1 text-info"></i> Needs Action
              <?php if (!empty($pending_incoming)): ?>
                <span class="badge badge-info ml-1"><?= count($pending_incoming) ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-exec">
              <i class="fas fa-user-tie mr-1 text-warning"></i> With Executive
              <?php if (!empty($with_executive)): ?>
                <span class="badge badge-warning ml-1"><?= count($with_executive) ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-requests">
              <i class="fas fa-file-signature mr-1 text-primary"></i> HRMS Requests at Desk
              <?php if (!empty($requests_at_exec)): ?>
                <span class="badge badge-primary ml-1"><?= count($requests_at_exec) ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-released">
              <i class="fas fa-paper-plane mr-1 text-success"></i> Recently Released
            </a>
          </li>
        </ul>
      </div>
      <div class="card-body tab-content" style="padding:20px;">

        <!-- ══ TAB 1: NEEDS ACTION (Incoming, awaiting exec decision) ══ -->
        <div class="tab-pane fade show active" id="tab-incoming">
          <?php if (empty($pending_incoming)): ?>
          <div class="text-center text-muted py-5">
            <i class="fas fa-check-circle fa-3x d-block mb-2" style="opacity:.2;color:#28a745;"></i>
            <strong>No documents awaiting executive action.</strong>
          </div>
          <?php else: ?>
          <p class="text-muted mb-3" style="font-size:13px;">
            These documents have been received and logged as incoming.
            Once the executive acts on them, record the executive decision below.
          </p>
          <?php foreach ($pending_incoming as $doc): ?>
          <div class="req-card incoming">
            <div class="row align-items-start">
              <div class="col-md-7">
                <div class="log-stage">Incoming — Awaiting Executive Decision</div>
                <div style="font-size:15px;font-weight:700;">
                  <span class="ctrl-no"><?= htmlspecialchars($doc['control_number']) ?></span>
                </div>
                <div style="font-size:13px;margin-top:4px;">
                  <strong><?= htmlspecialchars($doc['requestor_name']) ?></strong>
                  <?php if ($doc['requestor_dept']): ?>
                    <span class="text-muted"> — <?= htmlspecialchars($doc['requestor_dept']) ?></span>
                  <?php endif; ?>
                </div>
                <div style="font-size:12px;color:#6c757d;margin-top:2px;">
                  <?= htmlspecialchars($doc['document_type']) ?>
                  &nbsp;|&nbsp;
                  <?= date('M d, Y h:i A', strtotime($doc['date_received'])) ?>
                  <?php if ($doc['reference_no']): ?>
                    &nbsp;|&nbsp; Ref: <code><?= htmlspecialchars($doc['reference_no']) ?></code>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col-md-5">
                <div class="d-flex gap-2" style="gap:8px;">
                  <a href="exec_secretary_logbook.php?view=<?= $doc['id'] ?>"
                     class="btn btn-info btn-sm flex-fill">
                    <i class="fas fa-eye mr-1"></i> View &amp; Record Decision
                  </a>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- ══ TAB 2: WITH EXECUTIVE (decision recorded, not yet released) ══ -->
        <div class="tab-pane fade" id="tab-exec">
          <?php if (empty($with_executive)): ?>
          <div class="text-center text-muted py-5">
            <i class="fas fa-inbox fa-3x d-block mb-2" style="opacity:.2;"></i>
            <strong>No documents pending release.</strong>
          </div>
          <?php else: ?>
          <p class="text-muted mb-3" style="font-size:13px;">
            The executive has acted on these documents. Log the outgoing release
            once the document has been transmitted to the next party.
          </p>
          <?php foreach ($with_executive as $doc): ?>
          <div class="req-card with-exec">
            <div class="row align-items-center">
              <div class="col-md-7">
                <div class="log-stage">Executive Decision Recorded — Pending Release</div>
                <div style="font-size:15px;font-weight:700;">
                  <span class="ctrl-no"><?= htmlspecialchars($doc['control_number']) ?></span>
                  <span class="badge badge-<?= isset($action_colors[$doc['exec_action']]) ? $action_colors[$doc['exec_action']] : 'secondary' ?> ml-2"
                        style="font-size:11px;">
                    <?= htmlspecialchars($doc['exec_action']) ?>
                  </span>
                </div>
                <div style="font-size:13px;margin-top:4px;">
                  <strong><?= htmlspecialchars($doc['requestor_name']) ?></strong>
                  — <?= htmlspecialchars($doc['document_type']) ?>
                </div>
                <div style="font-size:12px;color:#6c757d;margin-top:2px;">
                  Decided by: <?= htmlspecialchars($doc['exec_action_by'] ?: '—') ?>
                  <?php if ($doc['exec_action_date']): ?>
                    &nbsp;·&nbsp; <?= date('M d, Y h:i A', strtotime($doc['exec_action_date'])) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col-md-5 text-right">
                <a href="exec_secretary_logbook.php?view=<?= $doc['id'] ?>"
                   class="btn btn-warning btn-sm">
                  <i class="fas fa-paper-plane mr-1"></i> Log Outgoing Release
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- ══ TAB 3: HRMS REQUESTS CURRENTLY AT THIS EXEC'S DESK ══ -->
        <div class="tab-pane fade" id="tab-requests">
          <p class="text-muted mb-3" style="font-size:13px;">
            These HRMS requests are currently waiting for <strong><?= htmlspecialchars($my_office) ?></strong> action.
            The logbook entry is auto-created when the request reaches this step.
          </p>
          <?php if (empty($requests_at_exec)): ?>
          <div class="text-center text-muted py-4">
            <i class="fas fa-check-circle fa-2x d-block mb-2" style="opacity:.2;"></i>
            No HRMS requests currently at this executive's desk.
          </div>
          <?php else: ?>
          <table class="table table-sm table-bordered table-hover" id="reqTable">
            <thead class="thead-light">
              <tr>
                <th>Reference</th><th>Employee</th><th>Department</th>
                <th>Request Type</th><th>Filed</th><th>Status</th><th>Logbook</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($requests_at_exec as $r):
                // Check if logbook entry exists
                $log_entry = db_fetch($pdo,
                    "SELECT id, control_number, status FROM document_logbook
                     WHERE request_id=? AND office=? LIMIT 1",
                    array($r['id'], $my_office)
                );
              ?>
              <tr>
                <td><code style="font-size:11px;"><?= htmlspecialchars($r['reference_no']) ?></code></td>
                <td style="font-size:12px;">
                  <strong><?= htmlspecialchars($r['emp_name']) ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($r['emp_code']) ?></small>
                </td>
                <td style="font-size:12px;"><?= htmlspecialchars($r['dept_name'] ?: '—') ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($r['type_name']) ?></td>
                <td style="font-size:12px;"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                <td>
                  <span class="badge badge-warning" style="font-size:10px;">
                    <?= htmlspecialchars($r['step_label']) ?>
                  </span>
                </td>
                <td>
                  <?php if ($log_entry): ?>
                    <a href="exec_secretary_logbook.php?view=<?= $log_entry['id'] ?>"
                       class="btn btn-xs btn-info">
                      <i class="fas fa-book mr-1"></i>
                      <span class="ctrl-no" style="font-size:10px;"><?= htmlspecialchars($log_entry['control_number']) ?></span>
                    </a>
                  <?php else: ?>
                    <span class="text-muted" style="font-size:11px;">
                      <i class="fas fa-clock mr-1"></i>Auto-pending
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>

        <!-- ══ TAB 4: RECENTLY RELEASED ══ -->
        <div class="tab-pane fade" id="tab-released">
          <h6 class="mb-3 text-muted">
            Documents released from <?= htmlspecialchars($my_office) ?> in the past 30 days
          </h6>
          <?php if (empty($recently_released)): ?>
          <div class="text-center text-muted py-4">No documents released in the past 30 days.</div>
          <?php else: ?>
          <table class="table table-sm table-bordered table-hover" id="releasedTable">
            <thead class="thead-light">
              <tr>
                <th>Control No.</th><th>Requestor</th><th>Document Type</th>
                <th>Exec. Decision</th><th>Date Released</th><th>Method</th><th>Received By</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recently_released as $doc): ?>
              <tr>
                <td>
                  <a href="exec_secretary_logbook.php?view=<?= $doc['id'] ?>"
                     class="ctrl-no"><?= htmlspecialchars($doc['control_number']) ?></a>
                </td>
                <td style="font-size:12px;">
                  <strong><?= htmlspecialchars($doc['requestor_name']) ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($doc['requestor_dept'] ?: '—') ?></small>
                </td>
                <td style="font-size:12px;"><?= htmlspecialchars($doc['document_type']) ?></td>
                <td>
                  <span class="badge badge-<?= isset($action_colors[$doc['exec_action']]) ? $action_colors[$doc['exec_action']] : 'secondary' ?>"
                        style="font-size:10px;">
                    <?= htmlspecialchars($doc['exec_action']) ?>
                  </span>
                </td>
                <td style="font-size:12px;">
                  <?= date('M d, Y', strtotime($doc['date_released'])) ?><br>
                  <small><?= date('h:i A', strtotime($doc['date_released'])) ?></small>
                </td>
                <td style="font-size:12px;">
                  <span class="badge badge-secondary" style="font-size:10px;">
                    <?= htmlspecialchars($doc['release_method'] ?: '—') ?>
                  </span>
                </td>
                <td style="font-size:12px;"><?= htmlspecialchars($doc['received_by'] ?: '—') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>

      </div><!-- /.tab-content -->
    </div><!-- /.card -->

  </div></div>
</div>

<!-- Quick Log Incoming Modal -->
<div class="modal fade" id="modalIncoming" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#0f3460;color:#fff;">
        <h5 class="modal-title">
          <i class="fas fa-inbox mr-2"></i>Log Incoming —
          <?= htmlspecialchars($my_office) ?>
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3" style="font-size:13px;">
          <i class="fas fa-info-circle mr-1"></i>
          For detailed logging with HRMS request linking, use the
          <a href="exec_secretary_logbook.php">full logbook page</a>.
          This quick form logs a new incoming document.
        </p>
        <form method="POST" action="exec_secretary_logbook.php">
          <?php csrf_field(); ?>
          <input type="hidden" name="post_action" value="log_incoming">
          <input type="hidden" name="office" value="<?= htmlspecialchars($my_office) ?>">
          <div class="row">
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Document Category <span class="text-danger">*</span></label>
                <select name="document_category" class="form-control form-control-sm" required>
                  <?php foreach (array('Request','Memo','Letter','Report','Certificate','Resolution','Order','Other') as $cat): ?>
                    <option <?= $cat==='Request'?'selected':'' ?>><?= $cat ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Document Type / Title <span class="text-danger">*</span></label>
                <input type="text" name="document_type" class="form-control form-control-sm"
                       placeholder="e.g. Leave Request, Teaching Load" required>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Requestor / Sender Name <span class="text-danger">*</span></label>
                <input type="text" name="requestor_name" class="form-control form-control-sm"
                       placeholder="Full name" required>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Department</label>
                <input type="text" name="requestor_dept" class="form-control form-control-sm"
                       placeholder="Department or office">
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">HRMS Reference No. <small class="text-muted">(if applicable)</small></label>
                <input type="text" name="reference_no" class="form-control form-control-sm"
                       placeholder="REQ-20260428-XXXX">
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Received From</label>
                <input type="text" name="received_from" class="form-control form-control-sm"
                       placeholder="Who delivered it (Dept Secretary, HR, Walk-in...)">
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Date &amp; Time Received <span class="text-danger">*</span></label>
                <input type="datetime-local" name="date_received" class="form-control form-control-sm"
                       value="<?= date('Y-m-d\TH:i', time()+$_hrms_delta) ?>" required>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label style="font-size:12px;">Remarks</label>
                <textarea name="incoming_remarks" class="form-control form-control-sm" rows="2"
                          placeholder="Optional notes..."></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer px-0 pb-0">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save mr-1"></i> Log Incoming Document
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    if ($.fn.DataTable) {
        $('#reqTable').length && $('#reqTable').DataTable({ pageLength:25, order:[[4,'asc']], dom:'lfrtip' });
        $('#releasedTable').length && $('#releasedTable').DataTable({ pageLength:25, order:[[4,'desc']], dom:'lfrtip' });
    }
});
</script>
</body>
</html>