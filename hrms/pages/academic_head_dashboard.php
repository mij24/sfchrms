<?php
// ============================================================
// FILE: pages/academic_head_dashboard.php
// Academic Head Dashboard — Dean / Principal / Dept Head
// - Department-scoped request approvals
// - Teaching load review and approval
// - Department employee overview
// - Forwarding to VP for recommendation
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

if (!has_role(ROLE_ACADEMIC_HEAD)) {
    http_response_code(403);
    include '../includes/403.php';
    exit;
}

// ── Get head's own department via linked employee ─────────────
$my_uid    = (int)$_SESSION['user_id'];
$my_user   = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array($my_uid));
$my_emp_id = $my_user ? (int)$my_user['employee_id'] : 0;

// Academic heads may oversee multiple departments (Dean over a college)
// Primary department from their employee record
$my_emp = null;
$my_dept_id   = 0;
$my_dept_name = 'All Departments';

if ($my_emp_id) {
    $my_emp = db_fetch($pdo,
        "SELECT e.*, d.name AS dept_name, d.id AS dept_id
         FROM employees e
         LEFT JOIN departments d ON e.department_id=d.id
         WHERE e.id=?",
        array($my_emp_id)
    );
    if ($my_emp) {
        $my_dept_id   = (int)$my_emp['dept_id'];
        $my_dept_name = $my_emp['dept_name'] ?: 'All Departments';
    }
}

// ── HRMS timezone ─────────────────────────────────────────────
$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz = 'Asia/Manila'; $_hrms_delta = 0;
foreach ($_tz_row as $_r) {
    if ($_r['setting_key']==='system_timezone'     && $_r['setting_value']) $_hrms_tz    = $_r['setting_value'];
    if ($_r['setting_key']==='clock_delta_seconds')                          $_hrms_delta = (int)$_r['setting_value'];
}
try { date_default_timezone_set($_hrms_tz); } catch(Exception $e) { date_default_timezone_set('Asia/Manila'); }

$cur_sy  = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='current_school_year'") ?: '2025-2026';
$cur_sem = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='current_semester'")    ?: '1st Semester';

// ── POST: Approve / Return requests ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $post_action = trim(isset($_POST['post_action']) ? $_POST['post_action'] : '');

    // ── Approve (note and forward) a request step ─────────────
    if ($post_action === 'approve_step') {
        $ra_id   = (int)$_POST['ra_id'];
        $req_id  = (int)$_POST['req_id'];
        $remarks = clean_string(isset($_POST['remarks']) ? $_POST['remarks'] : '', 500);

        // Verify this step belongs to manager/academic_head role
        $step = db_fetch($pdo,
            "SELECT ra.*, er.employee_id AS req_emp_id
             FROM request_approvals ra
             JOIN employee_requests er ON ra.request_id=er.id
             WHERE ra.id=? AND ra.role_required IN ('manager','academic_head') AND ra.status='Pending'",
            array($ra_id)
        );

        if (!$step) {
            prg_redirect('academic_head_dashboard.php','Access denied or invalid request.');
        }

        // Mark this step approved
        db_run($pdo,
            "UPDATE request_approvals SET status='Approved',acted_by=?,acted_at=NOW(),remarks=? WHERE id=?",
            array($my_uid, $remarks, $ra_id)
        );

        // Find next pending step
        $next = db_fetch($pdo,
            "SELECT * FROM request_approvals
             WHERE request_id=? AND status='Pending'
             ORDER BY step_order ASC LIMIT 1",
            array($req_id)
        );

        if ($next) {
            db_run($pdo,"UPDATE employee_requests SET current_step=? WHERE id=?",
                array($next['step_order'], $req_id));
        } else {
            db_run($pdo,"UPDATE employee_requests SET status='Approved' WHERE id=?",array($req_id));
        }

        audit_log($pdo,'UPDATE','employee_requests',$req_id,
            array('action'=>'noted_by_academic_head','dept'=>$my_dept_name,'remarks'=>$remarks));
        prg_redirect('academic_head_dashboard.php','Request noted and forwarded to next approver.');
    }

    // ── Return request ────────────────────────────────────────
    if ($post_action === 'return_request') {
        $ra_id   = (int)$_POST['ra_id'];
        $req_id  = (int)$_POST['req_id'];
        $reason  = clean_string(isset($_POST['remarks']) ? $_POST['remarks'] : '', 500);

        db_run($pdo,
            "UPDATE request_approvals SET status='Rejected',acted_by=?,acted_at=NOW(),remarks=? WHERE id=?",
            array($my_uid, $reason, $ra_id)
        );
        db_run($pdo,
            "UPDATE employee_requests SET status='Returned',rejection_reason=? WHERE id=?",
            array($reason, $req_id)
        );
        prg_redirect('academic_head_dashboard.php','Request returned.');
    }
}

// ── Load pending requests for this academic head ──────────────
// Dept-scoped if linked to a department, otherwise all
$dept_filter_sql    = $my_dept_id ? "AND e.department_id=?" : "";
$dept_filter_params = $my_dept_id ? array($my_dept_id)      : array();

$pending_requests = db_fetchall($pdo,
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
       AND ra.role_required IN ('manager','academic_head')
       AND er.current_step=ra.step_order
       AND er.status IN ('Pending','In Progress')
       $dept_filter_sql
     ORDER BY er.created_at ASC",
    $dept_filter_params
);

// Teaching loads pending dept head review
$pending_loads = db_fetchall($pdo,
    "SELECT tl.*, e.full_name, e.employee_id AS emp_code, d.name AS dept
     FROM teaching_loads tl
     JOIN employees e ON tl.employee_id=e.id
     LEFT JOIN departments d ON e.department_id=d.id
     WHERE tl.status='For Dept Head Review'
     ".($my_dept_id ? "AND e.department_id=?" : "")."
     ORDER BY tl.created_at ASC",
    $my_dept_id ? array($my_dept_id) : array()
);

// All dept requests history
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$hist_where  = "1=1";
$hist_params = array();
if ($my_dept_id) { $hist_where .= " AND e.department_id=?"; $hist_params[] = $my_dept_id; }
if ($filter_status) { $hist_where .= " AND er.status=?"; $hist_params[] = $filter_status; }

$all_requests = db_fetchall($pdo,
    "SELECT er.*, rt.name AS type_name,
            e.full_name AS emp_name, e.employee_id AS emp_code,
            d.name AS dept_name
     FROM employee_requests er
     JOIN request_types rt ON er.request_type_id=rt.id
     JOIN employees e ON er.employee_id=e.id
     LEFT JOIN departments d ON e.department_id=d.id
     WHERE $hist_where
     ORDER BY er.created_at DESC LIMIT 100",
    $hist_params
);

// Teaching loads for department (current SY/Sem)
$dept_loads = db_fetchall($pdo,
    "SELECT tl.*, e.full_name, e.employee_id AS emp_code
     FROM teaching_loads tl
     JOIN employees e ON tl.employee_id=e.id
     WHERE tl.school_year=? AND tl.semester=?
     ".($my_dept_id ? "AND e.department_id=?" : "")."
     ORDER BY tl.status, tl.created_at DESC",
    $my_dept_id
        ? array($cur_sy, $cur_sem, $my_dept_id)
        : array($cur_sy, $cur_sem)
);

// Department employees
$dept_employees = db_fetchall($pdo,
    "SELECT e.*, p.title AS position, ec.name AS cat_name
     FROM employees e
     LEFT JOIN positions p ON e.position_id=p.id
     LEFT JOIN employee_classifications ec ON e.classification_id=ec.id
     WHERE e.employment_status='Active'
     ".($my_dept_id ? "AND e.department_id=?" : "")."
     ORDER BY e.full_name",
    $my_dept_id ? array($my_dept_id) : array()
);

$status_colors = array(
    'Pending'=>'warning','In Progress'=>'info','Approved'=>'success',
    'Rejected'=>'danger','Returned'=>'secondary','Cancelled'=>'dark'
);
$load_badges = array(
    'Draft'=>'secondary','For Faculty Review'=>'info','Faculty Confirmed'=>'primary',
    'For Dept Head Review'=>'warning','Dept Head Approved'=>'info',
    'For VP Review'=>'warning','VP Recommended'=>'primary',
    'For President Approval'=>'warning','Approved'=>'success',
    'Rejected'=>'danger','Returned'=>'warning'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Academic Head Dashboard | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .dept-banner {
      background:linear-gradient(135deg,#0f3460,#16213e);
      color:#fff; border-radius:12px; padding:16px 22px; margin-bottom:20px;
    }
    .dept-banner .role-label { font-size:11px; opacity:.5; text-transform:uppercase; letter-spacing:.1em; }
    .dept-banner .dept-name  { font-size:20px; font-weight:700; margin:4px 0; }
    .dept-banner .dept-meta  { font-size:12px; opacity:.6; }
    .stat-box { border-radius:10px; padding:16px; text-align:center; margin-bottom:12px; background:#fff; border:1px solid #dee2e6; }
    .stat-box .stat-num { font-size:28px; font-weight:700; }
    .stat-box .stat-lbl { font-size:11px; color:#6c757d; margin-top:2px; }
    .req-card { border-left:4px solid #007bff; border-radius:0 8px 8px 0;
                background:#fff; padding:14px; margin-bottom:10px;
                box-shadow:0 1px 4px rgba(0,0,0,.07); }
    .req-card.teaching { border-left-color:#6f42c1; }
    .req-card .req-type { font-size:11px; font-weight:700; text-transform:uppercase; color:#6c757d; }
    .req-card .req-name { font-size:15px; font-weight:700; color:#343a40; }
    .req-card .req-meta { font-size:12px; color:#6c757d; margin-top:4px; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0"><i class="fas fa-user-graduate mr-2"></i>Academic Head Dashboard</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">Academic Head</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- Department banner -->
    <div class="dept-banner">
      <div class="row align-items-center">
        <div class="col-md-8">
          <div class="role-label">Academic Head / Dean / Principal</div>
          <div class="dept-name"><?= htmlspecialchars($my_dept_name) ?></div>
          <div class="dept-meta">
            <?= htmlspecialchars($cur_sem.' — '.$cur_sy) ?>
            &nbsp;|&nbsp; <?= count($dept_employees) ?> active faculty/staff
          </div>
        </div>
        <div class="col-md-4 text-right">
          <a href="employee_requests.php" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-list mr-1"></i> All Requests
          </a>
          <a href="teaching_load.php" class="btn btn-sm btn-light">
            <i class="fas fa-chalkboard-teacher mr-1"></i> Teaching Loads
          </a>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="row mb-3">
      <div class="col-6 col-md-3">
        <div class="stat-box">
          <div class="stat-num text-warning"><?= count($pending_requests) ?></div>
          <div class="stat-lbl">Pending Requests</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-box">
          <div class="stat-num text-purple" style="color:#6f42c1;"><?= count($pending_loads) ?></div>
          <div class="stat-lbl">Loads Awaiting Review</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-box">
          <div class="stat-num text-success"><?= count($dept_loads) ?></div>
          <div class="stat-lbl">Total Loads (<?= $cur_sem ?>)</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-box">
          <div class="stat-num text-info"><?= count($dept_employees) ?></div>
          <div class="stat-lbl">Faculty / Staff</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="card">
      <div class="card-header p-0">
        <ul class="nav nav-tabs" id="ahTabs">
          <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#tab-requests">
              <i class="fas fa-inbox mr-1 text-warning"></i> Pending Requests
              <?php if (!empty($pending_requests)): ?>
                <span class="badge badge-warning ml-1"><?= count($pending_requests) ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-loads">
              <i class="fas fa-chalkboard-teacher mr-1" style="color:#6f42c1;"></i> Teaching Loads
              <?php if (!empty($pending_loads)): ?>
                <span class="badge badge-warning ml-1"><?= count($pending_loads) ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-history">
              <i class="fas fa-history mr-1"></i> Request History
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-faculty">
              <i class="fas fa-users mr-1"></i> Faculty / Staff
              <span class="badge badge-secondary ml-1"><?= count($dept_employees) ?></span>
            </a>
          </li>
        </ul>
      </div>
      <div class="card-body tab-content" style="padding:20px;">

        <!-- ══ TAB 1: PENDING REQUESTS ══ -->
        <div class="tab-pane fade show active" id="tab-requests">
          <?php if (empty($pending_requests)): ?>
          <div class="text-center text-muted py-5">
            <i class="fas fa-check-circle fa-3x d-block mb-3" style="opacity:.2;color:#28a745;"></i>
            <strong>No pending requests.</strong><br>
            <small>All requests from your department are up to date.</small>
          </div>
          <?php else: ?>
          <p class="text-muted mb-3" style="font-size:13px;">
            The following requests require your review and signature before forwarding to the VP.
          </p>
          <?php foreach ($pending_requests as $r): ?>
          <div class="req-card">
            <div class="row align-items-start">
              <div class="col-md-5">
                <div class="req-type">
                  <?= htmlspecialchars($r['type_name']) ?>
                  <?php if ($r['dept_name']): ?>
                    &mdash; <?= htmlspecialchars($r['dept_name']) ?>
                  <?php endif; ?>
                </div>
                <div class="req-name"><?= htmlspecialchars($r['emp_name']) ?></div>
                <div class="req-meta">
                  <code><?= htmlspecialchars($r['reference_no']) ?></code>
                  &nbsp;|&nbsp; <?= date('M d, Y', strtotime($r['created_at'])) ?>
                </div>
                <?php if ($r['date_from']): ?>
                <div class="req-meta">
                  <i class="fas fa-calendar mr-1"></i>
                  <?= date('M d', strtotime($r['date_from'])) ?> –
                  <?= date('M d, Y', strtotime($r['date_to'])) ?>
                  (<?= $r['days_applied'] ?> day(s))
                </div>
                <?php endif; ?>
                <?php if ($r['purpose']): ?>
                <div class="req-meta mt-1">
                  <em><?= htmlspecialchars(substr($r['purpose'],0,100)) ?><?= strlen($r['purpose'])>100?'...':'' ?></em>
                </div>
                <?php endif; ?>
                <div class="mt-1">
                  <span class="badge badge-primary" style="font-size:10px;">
                    Your role: <?= htmlspecialchars($r['step_label']) ?>
                  </span>
                  <span class="badge badge-info ml-1" style="font-size:10px;">
                    Action: <?= htmlspecialchars($r['action_label']) ?>
                  </span>
                </div>
              </div>
              <div class="col-md-7">
                <form method="POST" action="" class="mb-2">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="post_action" value="approve_step">
                  <input type="hidden" name="ra_id"  value="<?= $r['ra_id'] ?>">
                  <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                  <div class="form-group mb-2">
                    <textarea name="remarks" class="form-control form-control-sm" rows="2"
                              placeholder="Add notes or remarks (optional)..."></textarea>
                  </div>
                  <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-paper-plane mr-1"></i>
                    <?= htmlspecialchars($r['action_label']) ?> &amp; Forward
                  </button>
                </form>
                <form method="POST" action="">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="post_action" value="return_request">
                  <input type="hidden" name="ra_id"  value="<?= $r['ra_id'] ?>">
                  <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                  <div class="input-group input-group-sm">
                    <textarea name="remarks" class="form-control form-control-sm" rows="1"
                              placeholder="Reason for returning..." required style="resize:none;"></textarea>
                    <div class="input-group-append">
                      <button type="submit" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-undo mr-1"></i> Return
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- ══ TAB 2: TEACHING LOADS ══ -->
        <div class="tab-pane fade" id="tab-loads">
          <?php if (!empty($pending_loads)): ?>
          <div class="alert alert-warning mb-3" style="font-size:13px;">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <strong><?= count($pending_loads) ?> teaching load(s) awaiting your review.</strong>
            Review and note these before forwarding to VP.
          </div>
          <?php foreach ($pending_loads as $l): ?>
          <div class="req-card teaching">
            <div class="row align-items-center">
              <div class="col-md-8">
                <div class="req-type">Teaching Load — <?= htmlspecialchars($l['dept'] ?: '—') ?></div>
                <div class="req-name">
                  <?= htmlspecialchars($l['subject_title']) ?>
                  <?php if ($l['subject_code']): ?>
                    <small class="text-muted">(<?= htmlspecialchars($l['subject_code']) ?>)</small>
                  <?php endif; ?>
                </div>
                <div class="req-meta">
                  <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($l['full_name']) ?>
                  &nbsp;|&nbsp; <?= htmlspecialchars($l['day_of_week']) ?>
                  &nbsp;|&nbsp; <?= date('h:i A',strtotime($l['time_start'])) ?> – <?= date('h:i A',strtotime($l['time_end'])) ?>
                  &nbsp;|&nbsp; Room: <?= htmlspecialchars($l['room'] ?: '—') ?>
                </div>
                <div class="req-meta">
                  <?= htmlspecialchars($l['student_program'] ?: '—') ?>
                  &nbsp;|&nbsp; <?= $l['units'] ?> units
                  &nbsp;|&nbsp; <?= htmlspecialchars($l['level']) ?>
                </div>
              </div>
              <div class="col-md-4 text-right">
                <a href="teaching_load.php?view=<?= $l['id'] ?>" class="btn btn-primary btn-sm">
                  <i class="fas fa-tasks mr-1"></i> Review &amp; Note
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <hr>
          <?php endif; ?>

          <!-- All dept teaching loads table -->
          <h6 class="mb-3">
            All Teaching Loads — <?= htmlspecialchars($my_dept_name) ?>
            <small class="text-muted">(<?= $cur_sem ?> <?= $cur_sy ?>)</small>
          </h6>
          <table class="table table-sm table-bordered table-hover" id="loadsTable">
            <thead class="thead-light">
              <tr>
                <th>Faculty</th><th>Subject</th><th>Day &amp; Time</th>
                <th>Room</th><th>Units</th><th>Status</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dept_loads as $l):
                $sb = isset($load_badges[$l['status']]) ? $load_badges[$l['status']] : 'secondary';
              ?>
              <tr>
                <td style="font-size:12px;">
                  <strong><?= htmlspecialchars($l['full_name']) ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($l['emp_code']) ?></small>
                </td>
                <td style="font-size:12px;">
                  <strong><?= htmlspecialchars($l['subject_title']) ?></strong>
                  <?php if ($l['subject_code']): ?>
                    <br><small><?= htmlspecialchars($l['subject_code']) ?></small>
                  <?php endif; ?>
                </td>
                <td style="font-size:12px;">
                  <?= htmlspecialchars($l['day_of_week']) ?><br>
                  <small><?= date('h:i A',strtotime($l['time_start'])) ?> – <?= date('h:i A',strtotime($l['time_end'])) ?></small>
                </td>
                <td style="font-size:12px;"><?= htmlspecialchars($l['room'] ?: '—') ?></td>
                <td style="font-size:12px;"><?= $l['units'] ?></td>
                <td>
                  <span class="badge badge-<?= $sb ?>" style="font-size:10px;"><?= $l['status'] ?></span>
                </td>
                <td>
                  <a href="teaching_load.php?view=<?= $l['id'] ?>" class="btn btn-xs btn-info">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($dept_loads)): ?>
              <tr><td colspan="7" class="text-center text-muted py-3">No teaching loads for this semester.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- ══ TAB 3: REQUEST HISTORY ══ -->
        <div class="tab-pane fade" id="tab-history">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">All Requests — <?= htmlspecialchars($my_dept_name) ?></h6>
            <form method="GET" action="" class="form-inline" style="gap:6px;">
              <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (array('Pending','In Progress','Approved','Rejected','Returned') as $st): ?>
                  <option <?= $filter_status===$st?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
              </select>
              <a href="academic_head_dashboard.php" class="btn btn-sm btn-secondary">Reset</a>
            </form>
          </div>
          <table class="table table-sm table-bordered table-hover" id="histTable">
            <thead class="thead-light">
              <tr>
                <th>Reference</th><th>Employee</th><th>Dept</th>
                <th>Type</th><th>Filed</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($all_requests as $r):
                $sc = isset($status_colors[$r['status']]) ? $status_colors[$r['status']] : 'secondary';
              ?>
              <tr>
                <td><code style="font-size:11px;"><?= htmlspecialchars($r['reference_no']) ?></code></td>
                <td style="font-size:12px;">
                  <strong><?= htmlspecialchars($r['emp_name']) ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($r['emp_code']) ?></small>
                </td>
                <td style="font-size:12px;"><?= htmlspecialchars($r['dept_name'] ?: '—') ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($r['type_name']) ?></td>
                <td style="font-size:12px;"><?= date('M d, Y',strtotime($r['created_at'])) ?></td>
                <td><span class="badge badge-<?= $sc ?>" style="font-size:10px;"><?= $r['status'] ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($all_requests)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No requests found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- ══ TAB 4: FACULTY / STAFF ══ -->
        <div class="tab-pane fade" id="tab-faculty">
          <table class="table table-sm table-bordered table-hover" id="facultyTable">
            <thead class="thead-light">
              <tr>
                <th>Employee ID</th><th>Name</th><th>Position</th>
                <th>Classification</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dept_employees as $e): ?>
              <tr>
                <td><a href="view_employee.php?id=<?= $e['id'] ?>" style="font-size:12px;">
                  <?= htmlspecialchars($e['employee_id']) ?>
                </a></td>
                <td style="font-size:12px;font-weight:600;"><?= htmlspecialchars($e['full_name']) ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($e['position'] ?: '—') ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($e['cat_name'] ?: '—') ?></td>
                <td>
                  <span class="badge badge-<?= $e['employment_status']==='Active'?'success':'secondary' ?>"
                        style="font-size:10px;">
                    <?= htmlspecialchars($e['employment_status']) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($dept_employees)): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">No employees found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div><!-- /.tab-content -->
    </div><!-- /.card -->

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    if ($.fn.DataTable) {
        $('#loadsTable').DataTable({ pageLength:25, order:[[5,'asc']], dom:'lfrtip' });
        $('#histTable').DataTable({ pageLength:25, order:[[4,'desc']], dom:'lfrtip' });
        $('#facultyTable').DataTable({ pageLength:25, order:[[1,'asc']], dom:'lfrtip' });
    }
});
</script>
</body>
</html>