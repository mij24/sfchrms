<?php
// ============================================================
// FILE: pages/dept_secretary_dashboard.php
// Department Secretary Dashboard
// - View requests from own department employees
// - Endorse / add remarks / forward to Dept Head
// - Create Teaching Loads for dept faculty
// - Department-scoped: only sees own department data
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// Must be dept_secretary role
if (!is_secretary()) {
    http_response_code(403);
    include '../includes/403.php';
    exit;
}

// ── Get secretary's own department via linked employee ────────────
$my_uid     = (int)$_SESSION['user_id'];
$my_user    = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array($my_uid));
$my_emp_id  = $my_user ? (int)$my_user['employee_id'] : 0;

if (!$my_emp_id) {
    // Secretary account not linked to an employee record
    ?><!DOCTYPE html><html><head><meta charset="utf-8">
    <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
    </head><body class="hold-transition sidebar-mini"><div class="wrapper">
    <?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
    <div class="content-wrapper"><div class="content"><div class="container-fluid mt-4">
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-triangle mr-2"></i>
      Your account is not linked to an employee record. Contact HR to link your account —
      this is required to determine your assigned department.
    </div></div></div></div>
    <?php include '../includes/footer.php'; ?></div></body></html>
    <?php exit;
}

// Get secretary's employee record + department
$my_emp = db_fetch($pdo,
    "SELECT e.*, d.name AS dept_name, d.id AS dept_id
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     WHERE e.id=?",
    array($my_emp_id)
);

$my_dept_id   = $my_emp ? (int)$my_emp['dept_id']   : 0;
$my_dept_name = $my_emp ? ($my_emp['dept_name'] ?: 'Unknown Department') : 'Unknown Department';

if (!$my_dept_id) {
    ?><!DOCTYPE html><html><head><meta charset="utf-8">
    <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
    </head><body class="hold-transition sidebar-mini"><div class="wrapper">
    <?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
    <div class="content-wrapper"><div class="content"><div class="container-fluid mt-4">
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-triangle mr-2"></i>
      Your employee record has no assigned department. Contact HR to assign your department.
    </div></div></div></div>
    <?php include '../includes/footer.php'; ?></div></body></html>
    <?php exit;
}

// ── HRMS timezone ─────────────────────────────────────────────────
$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz = 'Asia/Manila'; $_hrms_delta = 0;
foreach ($_tz_row as $_r) {
    if ($_r['setting_key']==='system_timezone'     && $_r['setting_value']) $_hrms_tz    = $_r['setting_value'];
    if ($_r['setting_key']==='clock_delta_seconds')                          $_hrms_delta = (int)$_r['setting_value'];
}
try { date_default_timezone_set($_hrms_tz); } catch(Exception $e) { date_default_timezone_set('Asia/Manila'); }

$cur_sy  = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='current_school_year'") ?: '2025-2026';
$cur_sem = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='current_semester'")    ?: '1st Semester';

// ── POST: Endorse / forward request ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $post_action = trim(isset($_POST['post_action']) ? $_POST['post_action'] : '');

    // ── Endorse a request step ───────────────────────────────────
    if ($post_action === 'endorse') {
        $ra_id   = (int)$_POST['ra_id'];
        $req_id  = (int)$_POST['req_id'];
        $remarks = clean_string(isset($_POST['remarks']) ? $_POST['remarks'] : '', 500);

        // Verify this step belongs to dept_secretary role
        $step = db_fetch($pdo,
            "SELECT ra.*, er.employee_id AS req_emp_id
             FROM request_approvals ra
             JOIN employee_requests er ON ra.request_id=er.id
             WHERE ra.id=? AND ra.role_required='dept_secretary' AND ra.status='Pending'",
            array($ra_id)
        );

        // Verify employee is in secretary's department
        if ($step) {
            $req_emp = db_fetch($pdo,"SELECT department_id FROM employees WHERE id=?",array($step['req_emp_id']));
            if (!$req_emp || (int)$req_emp['department_id'] !== $my_dept_id) {
                $step = null; // not in my department
            }
        }

        if (!$step) {
            prg_redirect('dept_secretary_dashboard.php','Access denied or invalid request.');
        }

        // Mark step as endorsed
        db_run($pdo,
            "UPDATE request_approvals SET status='Approved',acted_by=?,acted_at=NOW(),remarks=? WHERE id=?",
            array($my_uid, $remarks, $ra_id)
        );

        // Advance request to next step
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
            array('action'=>'endorsed_by_secretary','dept'=>$my_dept_name,'remarks'=>$remarks));
        prg_redirect('dept_secretary_dashboard.php','Request endorsed and forwarded to Department Head.');
    }

    // ── Return request to employee ───────────────────────────────
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
        prg_redirect('dept_secretary_dashboard.php','Request returned to employee for correction.');
    }
}

// ── Load pending requests for secretary's department ──────────────
// These are requests with a step assigned to 'dept_secretary' role
// AND the requesting employee is in my department
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
       AND ra.role_required='dept_secretary'
       AND er.current_step=ra.step_order
       AND er.status IN ('Pending','In Progress')
       AND e.department_id=?
     ORDER BY er.created_at ASC",
    array($my_dept_id)
);

// All requests from my department (history)
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$hist_where  = "e.department_id=?";
$hist_params = array($my_dept_id);
if ($filter_status) { $hist_where .= " AND er.status=?"; $hist_params[] = $filter_status; }

$all_dept_requests = db_fetchall($pdo,
    "SELECT er.*, rt.name AS type_name,
            e.full_name AS emp_name, e.employee_id AS emp_code
     FROM employee_requests er
     JOIN request_types rt ON er.request_type_id=rt.id
     JOIN employees e ON er.employee_id=e.id
     WHERE $hist_where
     ORDER BY er.created_at DESC
     LIMIT 100",
    $hist_params
);

// Teaching loads for my department (current SY/Sem)
$dept_loads = db_fetchall($pdo,
    "SELECT tl.*, e.full_name, e.employee_id AS emp_code
     FROM teaching_loads tl
     JOIN employees e ON tl.employee_id=e.id
     WHERE e.department_id=?
       AND tl.school_year=? AND tl.semester=?
     ORDER BY tl.created_at DESC",
    array($my_dept_id, $cur_sy, $cur_sem)
);

// Faculty in my department for teaching load assignment
$dept_faculty = db_fetchall($pdo,
    "SELECT e.id, e.full_name, e.employee_id, e.is_part_time,
            ec.name AS cat_name, p.title AS position
     FROM employees e
     LEFT JOIN employee_classifications ec ON e.classification_id=ec.id
     LEFT JOIN positions p ON e.position_id=p.id
     WHERE e.department_id=? AND e.employment_status='Active'
     ORDER BY e.full_name",
    array($my_dept_id)
);

// School years
$cur_year   = (int)date('Y', time()+$_hrms_delta);
$sy_options = array();
for ($y=$cur_year+1; $y>=$cur_year-2; $y--) $sy_options[] = $y.'-'.($y+1);

$semesters = array('1st Semester','2nd Semester','Summer');
$levels    = array('Nursery','Elementary','Junior High','Senior High','College','Graduate School');
$all_days  = array('Mon'=>'Monday','Tue'=>'Tuesday','Wed'=>'Wednesday','Thu'=>'Thursday',
                   'Fri'=>'Friday','Sat'=>'Saturday','Sun'=>'Sunday');

$status_colors = array(
    'Pending'=>'warning','In Progress'=>'info','Approved'=>'success',
    'Rejected'=>'danger','Returned'=>'secondary','Cancelled'=>'dark'
);
$load_badges = array(
    'Draft'=>'secondary','For Faculty Review'=>'info','Faculty Confirmed'=>'primary',
    'For Dept Head Review'=>'warning','Approved'=>'success','Rejected'=>'danger','Returned'=>'warning'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Secretary Dashboard | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .dept-badge {
      background:linear-gradient(135deg,#1a1a2e,#16213e);
      color:#74c0fc; border-radius:10px; padding:12px 20px;
      font-size:13px; margin-bottom:20px;
    }
    .dept-badge strong { font-size:16px; display:block; }
    .req-card { border-left:4px solid #ffc107; border-radius:0 8px 8px 0;
                background:#fff; padding:14px; margin-bottom:10px;
                box-shadow:0 1px 4px rgba(0,0,0,.07); }
    .req-card .req-type { font-size:11px; font-weight:700; text-transform:uppercase;
                          color:#6c757d; margin-bottom:4px; }
    .req-card .req-name { font-size:15px; font-weight:700; color:#343a40; }
    .req-card .req-meta { font-size:12px; color:#6c757d; margin-top:4px; }
    .stat-box { border-radius:10px; padding:16px; text-align:center; margin-bottom:12px; }
    .stat-box .stat-num { font-size:28px; font-weight:700; }
    .stat-box .stat-lbl { font-size:11px; color:#6c757d; margin-top:2px; }
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
          <i class="fas fa-clipboard-list mr-2"></i>Department Secretary Dashboard
        </h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">Secretary Dashboard</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- Department banner -->
    <div class="dept-badge">
      <i class="fas fa-building mr-2"></i>
      <strong><?= htmlspecialchars($my_dept_name) ?></strong>
      You are viewing requests and records for your assigned department only.
    </div>

    <!-- Stats row -->
    <div class="row mb-3">
      <div class="col-6 col-md-3">
        <div class="stat-box bg-white border">
          <div class="stat-num text-warning"><?= count($pending_requests) ?></div>
          <div class="stat-lbl">Awaiting Your Action</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-box bg-white border">
          <div class="stat-num text-info"><?= count($all_dept_requests) ?></div>
          <div class="stat-lbl">Total Dept Requests</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-box bg-white border">
          <div class="stat-num text-success"><?= count($dept_loads) ?></div>
          <div class="stat-lbl">Teaching Loads (<?= $cur_sem ?>)</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-box bg-white border">
          <div class="stat-num text-primary"><?= count($dept_faculty) ?></div>
          <div class="stat-lbl">Faculty / Staff in Dept</div>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <div class="card">
      <div class="card-header p-0">
        <ul class="nav nav-tabs" id="secTabs">
          <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#tab-pending">
              <i class="fas fa-bell mr-1 text-warning"></i>
              Pending Action
              <?php if (!empty($pending_requests)): ?>
                <span class="badge badge-warning ml-1"><?= count($pending_requests) ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-all">
              <i class="fas fa-list mr-1"></i> All Dept Requests
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-loads">
              <i class="fas fa-chalkboard-teacher mr-1"></i> Teaching Loads
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-create">
              <i class="fas fa-plus-circle mr-1 text-primary"></i> Create Teaching Load
            </a>
          </li>
        </ul>
      </div>
      <div class="card-body tab-content" style="padding:20px;">

        <!-- ══ TAB 1: PENDING ACTION ══ -->
        <div class="tab-pane fade show active" id="tab-pending">
          <?php if (empty($pending_requests)): ?>
          <div class="text-center text-muted py-5">
            <i class="fas fa-check-circle fa-3x d-block mb-3" style="opacity:.2;color:#28a745;"></i>
            <strong>No pending actions.</strong><br>
            <small>All requests from your department are up to date.</small>
          </div>
          <?php else: ?>
          <p class="text-muted mb-3" style="font-size:13px;">
            The following requests from <strong><?= htmlspecialchars($my_dept_name) ?></strong>
            require your endorsement before they can proceed to the Department Head.
          </p>
          <?php foreach ($pending_requests as $r): ?>
          <div class="req-card">
            <div class="row align-items-center">
              <div class="col-md-6">
                <div class="req-type"><?= htmlspecialchars($r['type_name']) ?></div>
                <div class="req-name"><?= htmlspecialchars($r['emp_name']) ?></div>
                <div class="req-meta">
                  <code><?= htmlspecialchars($r['reference_no']) ?></code>
                  &mdash; Filed <?= date('M d, Y', strtotime($r['created_at'])) ?>
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
                  <i class="fas fa-comment mr-1"></i>
                  <em><?= htmlspecialchars(substr($r['purpose'],0,80)) ?><?= strlen($r['purpose'])>80?'...':'' ?></em>
                </div>
                <?php endif; ?>
              </div>
              <div class="col-md-6">
                <!-- Endorse form -->
                <form method="POST" action="">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="post_action" value="endorse">
                  <input type="hidden" name="ra_id"  value="<?= $r['ra_id'] ?>">
                  <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                  <div class="form-group mb-2">
                    <textarea name="remarks" class="form-control form-control-sm" rows="2"
                              placeholder="Add notes or remarks (optional)..."></textarea>
                  </div>
                  <button type="submit" class="btn btn-success btn-sm">
                    <i class="fas fa-paper-plane mr-1"></i> Digital Sign & Endorse to Dept Head
                  </button>
                </form>
                <a href="request_detail.php?id=<?= $r['id'] ?>" class="btn btn-info btn-sm mt-1">
                  <i class="fas fa-eye mr-1"></i> View Full Request
                </a>
                <!-- Return form -->
                <form method="POST" action="" class="mt-2">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="post_action" value="return_request">
                  <input type="hidden" name="ra_id"  value="<?= $r['ra_id'] ?>">
                  <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                  <div class="form-group mb-2">
                    <textarea name="remarks" class="form-control form-control-sm" rows="1"
                              placeholder="Reason for returning..." required></textarea>
                  </div>
                  <button type="submit" class="btn btn-outline-warning btn-sm">
                    <i class="fas fa-undo mr-1"></i> Return to Employee
                  </button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- ══ TAB 2: ALL DEPT REQUESTS ══ -->
        <div class="tab-pane fade" id="tab-all">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">All Requests — <?= htmlspecialchars($my_dept_name) ?></h6>
            <form method="GET" action="" class="form-inline" style="gap:6px;">
              <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (array('Pending','In Progress','Approved','Rejected','Returned') as $st): ?>
                  <option <?= $filter_status===$st?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
              </select>
              <a href="dept_secretary_dashboard.php" class="btn btn-sm btn-secondary">Reset</a>
            </form>
          </div>
          <table class="table table-sm table-bordered table-hover" id="allReqTable">
            <thead class="thead-light">
              <tr>
                <th>Reference</th><th>Employee</th><th>Request Type</th>
                <th>Filed</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($all_dept_requests as $r):
                $sc = isset($status_colors[$r['status']]) ? $status_colors[$r['status']] : 'secondary';
              ?>
              <tr>
                <td><code style="font-size:11px;"><?= htmlspecialchars($r['reference_no']) ?></code></td>
                <td style="font-size:12px;">
                  <strong><?= htmlspecialchars($r['emp_name']) ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($r['emp_code']) ?></small>
                </td>
                <td style="font-size:12px;"><?= htmlspecialchars($r['type_name']) ?></td>
                <td style="font-size:12px;"><?= date('M d, Y',strtotime($r['created_at'])) ?></td>
                <td><span class="badge badge-<?= $sc ?>" style="font-size:10px;"><?= $r['status'] ?></span></td>
              <td>
                  <a href="request_detail.php?id=<?= $r['id'] ?>" class="btn btn-xs btn-info">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($all_dept_requests)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No requests found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- ══ TAB 3: TEACHING LOADS ══ -->
        <div class="tab-pane fade" id="tab-loads">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">
              Teaching Loads — <?= htmlspecialchars($my_dept_name) ?>
              <small class="text-muted">(<?= htmlspecialchars($cur_sem.' '.$cur_sy) ?>)</small>
            </h6>
            <a href="#tab-create" data-toggle="tab" class="btn btn-sm btn-primary">
              <i class="fas fa-plus mr-1"></i> Create New Load
            </a>
          </div>
          <table class="table table-sm table-bordered table-hover" id="loadsTable">
            <thead class="thead-light">
              <tr>
                <th>Faculty</th><th>Subject</th><th>Day &amp; Time</th>
                <th>Room</th><th>Level</th><th>Units</th><th>Status</th><th></th>
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
                    <br><small class="text-muted"><?= htmlspecialchars($l['subject_code']) ?></small>
                  <?php endif; ?>
                </td>
                <td style="font-size:12px;">
                  <?= htmlspecialchars($l['day_of_week']) ?><br>
                  <small><?= date('h:i A',strtotime($l['time_start'])) ?> – <?= date('h:i A',strtotime($l['time_end'])) ?></small>
                </td>
                <td style="font-size:12px;"><?= htmlspecialchars($l['room'] ?: '—') ?></td>
                <td style="font-size:11px;"><?= htmlspecialchars($l['level']) ?></td>
                <td style="font-size:12px;"><?= $l['units'] ?></td>
                <td>
                  <span class="badge badge-<?= $sb ?>" style="font-size:10px;"><?= $l['status'] ?></span>
                  <?php if ($l['has_offset']): ?>
                    <span class="badge badge-warning ml-1" style="font-size:9px;">Offset</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="teaching_load.php?view=<?= $l['id'] ?>" class="btn btn-xs btn-info">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($dept_loads)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-3">
                  No teaching loads for the current semester.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- ══ TAB 4: CREATE TEACHING LOAD ══ -->
        <div class="tab-pane fade" id="tab-create">
          <h6 class="mb-3">
            <i class="fas fa-plus-circle mr-1 text-primary"></i>
            Create Teaching Load — <?= htmlspecialchars($my_dept_name) ?>
          </h6>
          <p class="text-muted" style="font-size:13px;">
            Teaching loads created here are automatically scoped to your department.
            After creation, submit for Faculty confirmation.
          </p>

          <form method="POST" action="teaching_load.php" id="createLoadForm">
            <?php csrf_field(); ?>
            <input type="hidden" name="post_action" value="create">
            <div class="row">

              <!-- Column 1 -->
              <div class="col-md-4">
                <div class="form-group">
                  <label>Assign To — <?= htmlspecialchars($my_dept_name) ?> <span class="text-danger">*</span></label>
                  <select name="employee_id" class="form-control form-control-sm" required>
                    <option value="">Select faculty / staff...</option>
                    <?php foreach ($dept_faculty as $f): ?>
                    <option value="<?= $f['id'] ?>"
                            data-parttime="<?= $f['is_part_time'] ? '1':'0' ?>"
                            data-cat="<?= htmlspecialchars($f['cat_name'] ?: '') ?>">
                      <?= htmlspecialchars($f['full_name']) ?>
                      <?php if ($f['position']): ?>(<?= htmlspecialchars($f['position']) ?>)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (empty($dept_faculty)): ?>
                    <small class="text-danger">No active employees found in your department.</small>
                  <?php endif; ?>
                </div>
                <div class="row">
                  <div class="col-7">
                    <div class="form-group">
                      <label>School Year</label>
                      <select name="school_year" class="form-control form-control-sm">
                        <?php foreach ($sy_options as $sy): ?>
                          <option <?= $sy===$cur_sy?'selected':'' ?>><?= $sy ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-5">
                    <div class="form-group">
                      <label>Semester</label>
                      <select name="semester" class="form-control form-control-sm">
                        <?php foreach ($semesters as $s): ?>
                          <option <?= $s===$cur_sem?'selected':'' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label>Subject Title <span class="text-danger">*</span></label>
                  <input type="text" name="subject_title" class="form-control form-control-sm"
                         placeholder="e.g. Introduction to Computing" required>
                </div>
                <div class="form-group">
                  <label>Subject Code</label>
                  <input type="text" name="subject_code" class="form-control form-control-sm"
                         placeholder="e.g. CS101">
                </div>
              </div>

              <!-- Column 2 -->
              <div class="col-md-4">
                <div class="form-group">
                  <label>Student Program / Course</label>
                  <input type="text" name="student_program" class="form-control form-control-sm"
                         placeholder="e.g. BS Computer Science">
                </div>
                <div class="form-group">
                  <label>Section</label>
                  <input type="text" name="section" class="form-control form-control-sm"
                         placeholder="e.g. CS3A">
                </div>
                <div class="form-group">
                  <label>Level</label>
                  <select name="level" class="form-control form-control-sm">
                    <?php foreach ($levels as $lv): ?>
                      <option <?= $lv==='College'?'selected':'' ?>><?= $lv ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Units</label>
                  <input type="number" name="units" class="form-control form-control-sm"
                         step="0.5" min="0" value="3">
                </div>
                <div class="form-group">
                  <label>Time <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <input type="time" name="time_start" class="form-control form-control-sm" required>
                    <div class="input-group-prepend input-group-append">
                      <span class="input-group-text" style="font-size:12px;padding:0 8px;">to</span>
                    </div>
                    <input type="time" name="time_end" class="form-control form-control-sm" required>
                  </div>
                </div>
                <div class="form-group">
                  <label>Room</label>
                  <input type="text" name="room" class="form-control form-control-sm"
                         placeholder="e.g. Room 201">
                </div>
              </div>

              <!-- Column 3: Days -->
              <div class="col-md-4">
                <div class="form-group">
                  <label>Day(s) <span class="text-danger">*</span></label>
                  <div style="background:#f8f9fa;border:1px solid #ced4da;border-radius:6px;padding:10px 12px;">
                    <div class="row">
                      <?php foreach ($all_days as $short => $full): ?>
                      <div class="col-6">
                        <div class="custom-control custom-checkbox mb-1">
                          <input type="checkbox" class="custom-control-input"
                                 name="days[]" id="cday_<?= $short ?>" value="<?= $short ?>">
                          <label class="custom-control-label" for="cday_<?= $short ?>"
                                 style="font-size:13px;"><?= $full ?></label>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <div style="border-top:1px solid #dee2e6;padding-top:8px;margin-top:6px;">
                      <small class="text-muted d-block mb-1">Quick select:</small>
                      <button type="button" class="btn btn-xs btn-outline-primary mr-1 mb-1"
                              onclick="setDays2(['Mon','Wed','Fri'])">MWF</button>
                      <button type="button" class="btn btn-xs btn-outline-primary mr-1 mb-1"
                              onclick="setDays2(['Tue','Thu'])">TTh</button>
                      <button type="button" class="btn btn-xs btn-outline-primary mr-1 mb-1"
                              onclick="setDays2(['Mon','Tue','Wed','Thu','Fri'])">Mon–Fri</button>
                      <button type="button" class="btn btn-xs btn-outline-danger mb-1"
                              onclick="setDays2([])">Clear</button>
                    </div>
                  </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-2">
                  <i class="fas fa-plus-circle mr-1"></i> Create Teaching Load
                </button>
              </div>

            </div>
          </form>
        </div>

      </div><!-- /.card-body.tab-content -->
    </div><!-- /.card -->

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    if ($('#allReqTable').length && $.fn.DataTable)
        $('#allReqTable').DataTable({ pageLength:25, order:[[3,'desc']], dom:'lfrtip' });
    if ($('#loadsTable').length && $.fn.DataTable)
        $('#loadsTable').DataTable({ pageLength:25, order:[[6,'asc']], dom:'lfrtip' });
});

function setDays2(days) {
    ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].forEach(function(d) {
        var cb = document.getElementById('cday_' + d);
        if (cb) cb.checked = days.indexOf(d) !== -1;
    });
}

// Validate at least one day checked
document.getElementById('createLoadForm') && document.getElementById('createLoadForm').addEventListener('submit', function(e){
    var checked = document.querySelectorAll('input[name="days[]"]:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert('Please select at least one day for the schedule.');
    }
});
</script>
</body>
</html>