<?php
// ============================================
// FILE: pages/teaching_load.php
// Teaching Load Management
// Create → Faculty Review → Dept Head →
// VP Review → President Approval → HR Copy
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// ── HRMS timezone + delta ─────────────────────────────────────────
$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz = 'Asia/Manila'; $_hrms_delta = 0;
foreach ($_tz_row as $_r) {
    if ($_r['setting_key']==='system_timezone'     && $_r['setting_value']) $_hrms_tz    = $_r['setting_value'];
    if ($_r['setting_key']==='clock_delta_seconds')                          $_hrms_delta = (int)$_r['setting_value'];
}
try { date_default_timezone_set($_hrms_tz); } catch(Exception $e) { date_default_timezone_set('Asia/Manila'); }
// ── END ──────────────────────────────────────────────────────────

$cur_sy  = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='current_school_year'") ?: '2025-2026';
$cur_sem = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='current_semester'")    ?: '1st Semester';

$user    = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array((int)$_SESSION['user_id']));
$my_uid  = (int)$_SESSION['user_id'];
$my_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';

// Get my employee_id if linked
$my_emp_id = $user ? (int)$user['employee_id'] : 0;

// ── Step labels ───────────────────────────────────────────────────
$step_labels = array(
    1 => 'Created',
    2 => 'Faculty Confirmation',
    3 => 'Department Head Review',
    4 => 'VP Review',
    5 => 'President Approval',
    6 => 'HR Copy Received',
);

$status_badge = array(
    'Draft'                  => 'secondary',
    'For Faculty Review'     => 'info',
    'Faculty Confirmed'      => 'primary',
    'For Dept Head Review'   => 'warning',
    'Dept Head Approved'     => 'primary',
    'For VP Review'          => 'warning',
    'VP Recommended'         => 'primary',
    'For President Approval' => 'warning',
    'Approved'               => 'success',
    'Rejected'               => 'danger',
    'Returned'               => 'warning',
);

// ── POST handling ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $post_action = isset($_POST['post_action']) ? trim($_POST['post_action']) : '';

    // ── CREATE / EDIT teaching load ──────────────────────────────
    if ($post_action === 'create' || $post_action === 'edit') {
        if (!is_manager()) { prg_redirect('teaching_load.php','Access denied.'); }

        $edit_id      = ($post_action === 'edit') ? (int)$_POST['load_id'] : 0;
        $emp_id       = (int)$_POST['employee_id'];
        $subj_title   = clean_string($_POST['subject_title']);
        $subj_code    = clean_string($_POST['subject_code']);
        $program      = clean_string($_POST['student_program']);
        $section      = clean_string($_POST['section']);
        // Build day_of_week string from checkboxes array
        $days_raw = isset($_POST['days']) && is_array($_POST['days']) ? $_POST['days'] : array();
        $allowed_days = array('Mon','Tue','Wed','Thu','Fri','Sat','Sun');
        $days_clean   = array();
        foreach ($days_raw as $d) {
            $d = trim($d);
            if (in_array($d, $allowed_days)) $days_clean[] = $d;
        }
        if (empty($days_clean)) {
            prg_redirect_error('teaching_load.php', 'Please select at least one day for the schedule.');
        }
        $days = implode('/', $days_clean);
        $time_start   = clean_string($_POST['time_start']);
        $time_end     = clean_string($_POST['time_end']);
        $room         = clean_string($_POST['room']);
        $units        = (float)$_POST['units'];
        $level        = clean_string($_POST['level']);
        $sy           = clean_string($_POST['school_year']);
        $sem          = clean_string($_POST['semester']);
        $has_offset   = !empty($_POST['has_offset']) ? 1 : 0;
        $off_start    = $has_offset ? clean_string($_POST['offset_time_start']) : null;
        $off_end      = $has_offset ? clean_string($_POST['offset_time_end'])   : null;
        $off_notes    = $has_offset ? clean_string($_POST['offset_notes'],500)  : null;

        // ── Duplicate / conflict detection ───────────────────────
        // Get all active loads for same SY+Semester (exclude self on edit)
        $existing_loads = db_fetchall($pdo,
            "SELECT * FROM teaching_loads
             WHERE school_year=? AND semester=?
               AND status NOT IN ('Rejected','Returned')
               AND id != ?",
            array($sy, $sem, $edit_id ?: 0)
        );

        // Parse day string into individual day tokens
        // e.g. "Mon/Wed/Fri" → ['Mon','Wed','Fri']
        // e.g. "Monday" → ['Monday']
        function parse_days($day_str) {
            // Normalize separators
            $str  = str_replace(array('/',',','\\'), '|', $day_str);
            $parts = array_map('trim', explode('|', $str));
            $out  = array();
            foreach ($parts as $p) {
                if ($p) $out[] = strtolower(substr($p, 0, 3)); // first 3 chars: mon,tue,wed...
            }
            return $out;
        }

        function days_overlap($days_a, $days_b) {
            $a = parse_days($days_a);
            $b = parse_days($days_b);
            foreach ($a as $da) {
                foreach ($b as $db) {
                    if ($da === $db) return true;
                }
            }
            return false;
        }

        // Check time overlap: two ranges overlap if start_A < end_B AND end_A > start_B
        function time_overlap($s1, $e1, $s2, $e2) {
            $ts1 = strtotime('2000-01-01 '.$s1);
            $te1 = strtotime('2000-01-01 '.$e1);
            $ts2 = strtotime('2000-01-01 '.$s2);
            $te2 = strtotime('2000-01-01 '.$e2);
            return ($ts1 < $te2 && $te1 > $ts2);
        }

        $room_conflicts    = array();
        $teacher_conflicts = array();

        foreach ($existing_loads as $ex) {
            // Only check if days overlap
            if (!days_overlap($days, $ex['day_of_week'])) continue;
            // Only check if times overlap
            if (!time_overlap($time_start, $time_end, $ex['time_start'], $ex['time_end'])) continue;

            // Room conflict: same room, same day, overlapping time
            if ($room && $ex['room'] && strtolower(trim($room)) === strtolower(trim($ex['room']))) {
                $room_conflicts[] = $ex;
            }

            // Teacher conflict: same employee, same day, overlapping time
            if ($ex['employee_id'] == $emp_id) {
                $teacher_conflicts[] = $ex;
            }
        }

        // Build conflict message
        $conflict_msgs = array();
        foreach ($room_conflicts as $c) {
            $conflict_msgs[] = 'Room conflict: Room "'.htmlspecialchars($c['room']).'" is already assigned to "'
                .htmlspecialchars($c['subject_title']).'" on '.$c['day_of_week'].' '
                .date('h:i A',strtotime($c['time_start'])).' - '.date('h:i A',strtotime($c['time_end']))
                .' ('.$c['status'].')';
        }
        foreach ($teacher_conflicts as $c) {
            // Get teacher name
            $tname = db_value($pdo,"SELECT full_name FROM employees WHERE id=?",array($c['employee_id']));
            $conflict_msgs[] = 'Teacher conflict: '.htmlspecialchars($tname ?: 'This employee')
                .' is already assigned to "'.htmlspecialchars($c['subject_title']).'" on '
                .$c['day_of_week'].' '.date('h:i A',strtotime($c['time_start'])).' - '
                .date('h:i A',strtotime($c['time_end'])).' ('.$c['status'].')';
        }

        if (!empty($conflict_msgs)) {
            prg_redirect_error('teaching_load.php', implode(' | ', $conflict_msgs));
        }
        // ── END conflict detection ───────────────────────────────

        if ($edit_id) {
            db_run($pdo,
                "UPDATE teaching_loads SET employee_id=?,subject_title=?,subject_code=?,
                 student_program=?,section=?,day_of_week=?,time_start=?,time_end=?,room=?,
                 units=?,level=?,school_year=?,semester=?,has_offset=?,
                 offset_time_start=?,offset_time_end=?,offset_notes=?
                 WHERE id=? AND status='Draft'",
                array($emp_id,$subj_title,$subj_code,$program,$section,$days,
                      $time_start,$time_end,$room,$units,$level,$sy,$sem,$has_offset,
                      $off_start,$off_end,$off_notes,$edit_id)
            );
            audit_log($pdo,'UPDATE','teaching_loads',$edit_id,array('action'=>'edit'));
            prg_redirect('teaching_load.php','Teaching load updated.');
        } else {
            db_run($pdo,
                "INSERT INTO teaching_loads
                 (school_year,semester,employee_id,subject_title,subject_code,
                  student_program,section,day_of_week,time_start,time_end,room,units,level,
                  has_offset,offset_time_start,offset_time_end,offset_notes,
                  status,current_step,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Draft',1,?)",
                array($sy,$sem,$emp_id,$subj_title,$subj_code,$program,$section,
                      $days,$time_start,$time_end,$room,$units,$level,
                      $has_offset,$off_start,$off_end,$off_notes,$my_uid)
            );
            $new_id = db_lastid($pdo);
            audit_log($pdo,'INSERT','teaching_loads',$new_id,array('action'=>'create'));
            prg_redirect('teaching_load.php','Teaching load created successfully. Submit for faculty review when ready.');
        }
    }

    // ── SUBMIT for Faculty Review ────────────────────────────────
    if ($post_action === 'submit_faculty') {
        $lid = (int)$_POST['load_id'];
        $load = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load || $load['status'] !== 'Draft') {
            prg_redirect('teaching_load.php','Invalid action.');
        }
        db_run($pdo,
            "UPDATE teaching_loads SET status='For Faculty Review',current_step=2 WHERE id=?",
            array($lid)
        );
        audit_log($pdo,'UPDATE','teaching_loads',$lid,array('action'=>'submitted_for_faculty_review'));
        prg_redirect('teaching_load.php?view='.$lid,'Submitted for faculty confirmation.');
    }

    // ── FACULTY CONFIRM ──────────────────────────────────────────
    if ($post_action === 'faculty_confirm') {
        $lid     = (int)$_POST['load_id'];
        $remarks = clean_string(isset($_POST['remarks']) ? $_POST['remarks'] : '', 500);
        $load    = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));

        // Faculty can only confirm their own load
        if (!$load || $load['status'] !== 'For Faculty Review' || $load['employee_id'] != $my_emp_id) {
            prg_redirect('teaching_load.php','Access denied or invalid state.');
        }
        db_run($pdo,
            "UPDATE teaching_loads SET status='For Dept Head Review',current_step=3 WHERE id=?",
            array($lid)
        );
        db_run($pdo,
            "INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks)
             VALUES (?,2,?,?,?,'Confirmed',?)",
            array($lid,'Faculty Confirmation',$my_uid,$_SESSION['username'],$remarks)
        );
        prg_redirect('teaching_load.php?view='.$lid,'Teaching load confirmed. Forwarded to Department Head.');
    }

    // ── DEPT HEAD APPROVE ────────────────────────────────────────
    if ($post_action === 'dept_head_approve') {
        $lid     = (int)$_POST['load_id'];
        $remarks = clean_string(isset($_POST['remarks']) ? $_POST['remarks'] : '', 500);
        $load    = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load || $load['status'] !== 'For Dept Head Review' || !has_role(ROLE_ACADEMIC_HEAD)) {
            prg_redirect('teaching_load.php','Access denied or invalid state.');
        }
        db_run($pdo,
            "UPDATE teaching_loads SET status='For VP Review',current_step=4 WHERE id=?",
            array($lid)
        );
        db_run($pdo,
            "INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks)
             VALUES (?,3,?,?,?,'Noted',?)",
            array($lid,'Department Head Review',$my_uid,$_SESSION['username'],$remarks)
        );
        prg_redirect('teaching_load.php?view='.$lid,'Noted and forwarded to VP for recommendation.');
    }

    // ── VP RECOMMEND ─────────────────────────────────────────────
    if ($post_action === 'vp_recommend') {
        $lid     = (int)$_POST['load_id'];
        $remarks = clean_string(isset($_POST['remarks']) ? $_POST['remarks'] : '', 500);
        $load    = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load || $load['status'] !== 'For VP Review' || !has_role(ROLE_ACADEMIC_HEAD)) {
            prg_redirect('teaching_load.php','Access denied or invalid state.');
        }
        db_run($pdo,
            "UPDATE teaching_loads SET status='For President Approval',current_step=5 WHERE id=?",
            array($lid)
        );
        db_run($pdo,
            "INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks)
             VALUES (?,4,?,?,?,'Recommended',?)",
            array($lid,'VP Review',$my_uid,$_SESSION['username'],$remarks)
        );
        prg_redirect('teaching_load.php?view='.$lid,'Recommended for approval. Forwarded to President.');
    }

    // ── PRESIDENT APPROVE ────────────────────────────────────────
    if ($post_action === 'president_approve') {
        $lid     = (int)$_POST['load_id'];
        $remarks = clean_string(isset($_POST['remarks']) ? $_POST['remarks'] : '', 500);
        $load    = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load || $load['status'] !== 'For President Approval' || !has_role(ROLE_PRESIDENT)) {
            prg_redirect('teaching_load.php','Access denied or invalid state.');
        }
        db_run($pdo,
            "UPDATE teaching_loads SET status='Approved',current_step=6 WHERE id=?",
            array($lid)
        );
        db_run($pdo,
            "INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks)
             VALUES (?,5,?,?,?,'Approved',?)",
            array($lid,'President Approval',$my_uid,$_SESSION['username'],$remarks)
        );
        // Flag employee as having teaching load
        db_run($pdo,
            "UPDATE employees SET has_teaching_load=1 WHERE id=?",
            array($load['employee_id'])
        );
        prg_redirect('teaching_load.php?view='.$lid,'Teaching load APPROVED. HR has been notified.');
    }

    // ── HR RECEIVE ───────────────────────────────────────────────
    if ($post_action === 'hr_receive') {
        $lid  = (int)$_POST['load_id'];
        $load = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load || $load['status'] !== 'Approved' || !is_hr()) {
            prg_redirect('teaching_load.php','Access denied or invalid state.');
        }
        db_run($pdo,
            "INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks)
             VALUES (?,6,?,?,?,'HR Received','Copy received and filed.')",
            array($lid,'HR Copy',$my_uid,$_SESSION['username'])
        );
        prg_redirect('teaching_load.php?view='.$lid,'HR copy acknowledged. Load is now active in attendance system.');
    }

    // ── REJECT / RETURN ──────────────────────────────────────────
    if ($post_action === 'reject' || $post_action === 'return') {
        $lid     = (int)$_POST['load_id'];
        $reason  = clean_string(isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : '', 500);
        $load    = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load) { prg_redirect('teaching_load.php','Not found.'); }

        $new_status = ($post_action === 'reject') ? 'Rejected' : 'Returned';
        $action_str = ($post_action === 'reject') ? 'Rejected' : 'Returned';

        db_run($pdo,
            "UPDATE teaching_loads SET status=?,rejection_reason=? WHERE id=?",
            array($new_status,$reason,$lid)
        );
        db_run($pdo,
            "INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks)
             VALUES (?,?,?,?,?,?,?)",
            array($lid,$load['current_step'],$step_labels[$load['current_step']],
                  $my_uid,$_SESSION['username'],$action_str,$reason)
        );
        if ($post_action === 'return') {
            // Return to Draft so creator can revise
            db_run($pdo,"UPDATE teaching_loads SET status='Draft',current_step=1 WHERE id=?",array($lid));
        }
        prg_redirect('teaching_load.php?view='.$lid,'Action recorded.');
    }

    // ── DELETE (Draft only) ──────────────────────────────────────
    if ($post_action === 'delete') {
        $lid  = (int)$_POST['load_id'];
        $load = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load || $load['status'] !== 'Draft' || !is_manager()) {
            prg_redirect('teaching_load.php','Cannot delete.');
        }
        db_run($pdo,"DELETE FROM teaching_loads WHERE id=? AND status='Draft'",array($lid));
        audit_log($pdo,'DELETE','teaching_loads',$lid,array());
        prg_redirect('teaching_load.php','Teaching load deleted.');
    }
}

// ── VIEW single record ────────────────────────────────────────────
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_load = null;
$view_trail = array();
if ($view_id) {
    $view_load = db_fetch($pdo,
        "SELECT tl.*, e.full_name, e.employee_id AS emp_code, e.is_part_time,
                d.name AS dept, p.title AS position, ec.name AS cat_name
         FROM teaching_loads tl
         JOIN employees e ON tl.employee_id=e.id
         LEFT JOIN departments d ON e.department_id=d.id
         LEFT JOIN positions p ON e.position_id=p.id
         LEFT JOIN employee_classifications ec ON e.classification_id=ec.id
         WHERE tl.id=?",
        array($view_id)
    );
    $view_trail = db_fetchall($pdo,
        "SELECT * FROM teaching_load_approvals WHERE load_id=? ORDER BY step,acted_at",
        array($view_id)
    );
}

// ── Filter / list ─────────────────────────────────────────────────
$filter_sy    = isset($_GET['sy'])     ? trim($_GET['sy'])     : $cur_sy;
$filter_sem   = isset($_GET['sem'])    ? trim($_GET['sem'])    : '';
$filter_status= isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_emp   = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : 0;

// Role-based filter: faculty only see their own
$where  = array("1=1");
$params = array();

// Faculty (plain employee) only sees their own
if (!is_manager()) {
    $where[]  = "tl.employee_id = ?";
    $params[] = $my_emp_id;
}

if ($filter_sy) { $where[] = "tl.school_year=?"; $params[] = $filter_sy; }
if ($filter_sem){ $where[] = "tl.semester=?";     $params[] = $filter_sem; }
if ($filter_status){ $where[] = "tl.status=?";    $params[] = $filter_status; }
if ($filter_emp && is_manager()) { $where[] = "tl.employee_id=?"; $params[] = $filter_emp; }

$loads = db_fetchall($pdo,
    "SELECT tl.*, e.full_name, e.employee_id AS emp_code, d.name AS dept
     FROM teaching_loads tl
     JOIN employees e ON tl.employee_id=e.id
     LEFT JOIN departments d ON e.department_id=d.id
     WHERE ".implode(" AND ",$where)."
     ORDER BY tl.created_at DESC",
    $params
);

// Load employees list for form
$all_employees = db_fetchall($pdo,
    "SELECT e.id, e.full_name, e.employee_id, d.name AS dept,
            ec.name AS cat_name, e.is_part_time
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN employee_classifications ec ON e.classification_id=ec.id
     WHERE e.employment_status='Active'
     ORDER BY e.full_name"
);

$semesters   = array('1st Semester','2nd Semester','Summer');
$levels      = array('Nursery','Elementary','Junior High','Senior High','College','Graduate School');

// School years dropdown (last 3 + next)
$sy_options = array();
$cur_year   = (int)date('Y', time()+$_hrms_delta);
for ($y=$cur_year+1; $y>=$cur_year-2; $y--) {
    $sy_options[] = $y.'-'.($y+1);
}

// Actions available to current user for a given load
function can_act($load, $my_emp_id, $my_role) {
    $s = $load['status'];
    if ($s === 'For Faculty Review'     && $load['employee_id'] == $my_emp_id) return 'faculty_confirm';
    if ($s === 'For Dept Head Review'   && has_role(ROLE_ACADEMIC_HEAD))       return 'dept_head_approve';
    if ($s === 'For VP Review'          && has_role(ROLE_ACADEMIC_HEAD))       return 'vp_recommend';
    if ($s === 'For President Approval' && has_role(ROLE_PRESIDENT))           return 'president_approve';
    if ($s === 'Approved'               && is_hr())                            return 'hr_receive';
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Teaching Load Management | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .step-bar { display:flex; gap:0; margin-bottom:20px; }
    .step-item {
      flex:1; text-align:center; padding:8px 4px; font-size:11px;
      border-top:3px solid #dee2e6; color:#adb5bd; position:relative;
    }
    .step-item.done  { border-color:#28a745; color:#28a745; font-weight:600; }
    .step-item.active{ border-color:#007bff; color:#007bff; font-weight:700; }
    .step-item.rejected{ border-color:#dc3545; color:#dc3545; }
    .step-num {
      width:24px;height:24px;border-radius:50%;display:inline-flex;
      align-items:center;justify-content:center;font-size:11px;font-weight:700;
      background:#dee2e6;color:#fff;margin-bottom:4px;
    }
    .step-item.done   .step-num { background:#28a745; }
    .step-item.active .step-num { background:#007bff; }
    .step-item.rejected .step-num{ background:#dc3545; }
    .trail-item { border-left:3px solid #dee2e6; padding:8px 14px; margin-bottom:8px; font-size:13px; }
    .trail-item.confirmed   { border-color:#007bff; }
    .trail-item.noted       { border-color:#17a2b8; }
    .trail-item.recommended { border-color:#6f42c1; }
    .trail-item.approved    { border-color:#28a745; }
    .trail-item.rejected    { border-color:#dc3545; }
    .trail-item.returned    { border-color:#fd7e14; }
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
          <i class="fas fa-chalkboard-teacher mr-2"></i>Teaching Load Management
        </h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">Teaching Load</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <?php if ($view_load): ?>
    <!-- ══ DETAIL VIEW ══ -->
    <div class="mb-3">
      <a href="teaching_load.php" class="btn btn-sm btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to list
      </a>
    </div>

    <!-- Step progress bar -->
    <?php
    $step_defs = array(
      1 => 'Created',
      2 => 'Faculty Confirmed',
      3 => 'Dept Head Noted',
      4 => 'VP Recommended',
      5 => 'President Approved',
      6 => 'HR Received',
    );
    $cur_step = (int)$view_load['current_step'];
    $is_rejected = in_array($view_load['status'],array('Rejected','Returned'));
    ?>
    <div class="step-bar">
      <?php foreach ($step_defs as $sn => $sl): ?>
      <?php
      if ($is_rejected && $sn >= $cur_step) $cls = 'rejected';
      elseif ($sn < $cur_step) $cls = 'done';
      elseif ($sn == $cur_step) $cls = 'active';
      else $cls = '';
      ?>
      <div class="step-item <?= $cls ?>">
        <div class="step-num"><?= $sn ?></div>
        <div><?= $sl ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row">
      <div class="col-md-8">
        <!-- Load details card -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
              <?= htmlspecialchars($view_load['subject_title']) ?>
              <?php if ($view_load['subject_code']): ?>
                <small class="text-muted">(<?= htmlspecialchars($view_load['subject_code']) ?>)</small>
              <?php endif; ?>
            </h3>
            <span class="badge badge-<?= isset($status_badge[$view_load['status']]) ? $status_badge[$view_load['status']] : 'secondary' ?> ml-2">
              <?= $view_load['status'] ?>
            </span>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <table class="table table-sm table-borderless" style="font-size:13px;">
                  <tr><td class="text-muted" width="140">Faculty / Staff</td>
                      <td><strong><?= htmlspecialchars($view_load['full_name']) ?></strong>
                          <br><small class="text-muted"><?= htmlspecialchars($view_load['emp_code']) ?> &mdash; <?= htmlspecialchars($view_load['dept'] ?: '—') ?></small></td></tr>
                  <tr><td class="text-muted">Program / Course</td>
                      <td><?= htmlspecialchars($view_load['student_program'] ?: '—') ?></td></tr>
                  <tr><td class="text-muted">Section</td>
                      <td><?= htmlspecialchars($view_load['section'] ?: '—') ?></td></tr>
                  <tr><td class="text-muted">Level</td>
                      <td><?= htmlspecialchars($view_load['level']) ?></td></tr>
                  <tr><td class="text-muted">Units</td>
                      <td><?= $view_load['units'] ?></td></tr>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-sm table-borderless" style="font-size:13px;">
                  <tr><td class="text-muted" width="140">School Year</td>
                      <td><?= htmlspecialchars($view_load['school_year']) ?></td></tr>
                  <tr><td class="text-muted">Semester</td>
                      <td><?= htmlspecialchars($view_load['semester']) ?></td></tr>
                  <tr><td class="text-muted">Day(s)</td>
                      <td><?= htmlspecialchars($view_load['day_of_week']) ?></td></tr>
                  <tr><td class="text-muted">Time</td>
                      <td><?= date('h:i A',strtotime($view_load['time_start'])) ?> &mdash; <?= date('h:i A',strtotime($view_load['time_end'])) ?></td></tr>
                  <tr><td class="text-muted">Room</td>
                      <td><?= htmlspecialchars($view_load['room'] ?: '—') ?></td></tr>
                </table>
              </div>
            </div>

            <?php if ($view_load['has_offset']): ?>
            <div class="alert alert-warning mb-0" style="font-size:13px;">
              <i class="fas fa-exchange-alt mr-1"></i>
              <strong>Offset Schedule:</strong>
              Adjusted office time:
              <?= date('h:i A',strtotime($view_load['offset_time_start'])) ?> &mdash;
              <?= date('h:i A',strtotime($view_load['offset_time_end'])) ?>
              <?php if ($view_load['offset_notes']): ?>
                <br><small><?= htmlspecialchars($view_load['offset_notes']) ?></small>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Approval trail -->
        <div class="card">
          <div class="card-header"><h3 class="card-title">Approval Trail</h3></div>
          <div class="card-body">
            <?php if (empty($view_trail)): ?>
              <p class="text-muted" style="font-size:13px;">No actions recorded yet.</p>
            <?php else: ?>
              <?php foreach ($view_trail as $t):
                $tc = strtolower($t['action']);
              ?>
              <div class="trail-item <?= $tc ?>">
                <div>
                  <strong><?= htmlspecialchars($t['step_label']) ?></strong>
                  <span class="badge badge-<?= isset($status_badge[$t['action']]) ? $status_badge[$t['action']] : 'secondary' ?> ml-1" style="font-size:10px;">
                    <?= $t['action'] ?>
                  </span>
                  <small class="text-muted float-right">
                    <?= date('M d, Y h:i A',strtotime($t['acted_at'])) ?>
                  </small>
                </div>
                <div style="font-size:12px;color:#6c757d;margin-top:2px;">
                  By: <?= htmlspecialchars($t['actor_name'] ?: '—') ?>
                  <?php if ($t['remarks']): ?>
                    <br>Remarks: <em><?= htmlspecialchars($t['remarks']) ?></em>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <!-- Action panel -->
        <?php $action_key = can_act($view_load, $my_emp_id, $my_role); ?>

        <?php if ($view_load['status'] === 'Draft' && is_manager()): ?>
        <!-- Submit for Faculty Review -->
        <div class="card card-outline card-primary">
          <div class="card-header"><h3 class="card-title">Actions</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="post_action" value="submit_faculty">
              <input type="hidden" name="load_id" value="<?= $view_load['id'] ?>">
              <button type="submit" class="btn btn-primary btn-block mb-2">
                <i class="fas fa-paper-plane mr-1"></i> Submit for Faculty Review
              </button>
            </form>
            <form method="POST" action="" onsubmit="return confirm('Delete this teaching load?')">
              <?php csrf_field(); ?>
              <input type="hidden" name="post_action" value="delete">
              <input type="hidden" name="load_id" value="<?= $view_load['id'] ?>">
              <button type="submit" class="btn btn-outline-danger btn-block btn-sm">
                <i class="fas fa-trash mr-1"></i> Delete (Draft)
              </button>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($action_key): ?>
        <div class="card card-outline card-success">
          <div class="card-header">
            <h3 class="card-title">Your Action Required</h3>
          </div>
          <div class="card-body">
            <?php
            $btn_labels = array(
              'faculty_confirm'   => array('Confirm Teaching Load','btn-success','Confirmed'),
              'dept_head_approve' => array('Noted & Forward to VP','btn-info','Noted'),
              'vp_recommend'      => array('Recommend for Approval','btn-primary','Recommended'),
              'president_approve' => array('Approve Teaching Load','btn-success','Approved'),
              'hr_receive'        => array('Acknowledge HR Copy','btn-secondary','HR Received'),
            );
            $btn = $btn_labels[$action_key];
            ?>
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="post_action" value="<?= $action_key ?>">
              <input type="hidden" name="load_id" value="<?= $view_load['id'] ?>">
              <?php if ($action_key !== 'hr_receive'): ?>
              <div class="form-group">
                <label style="font-size:12px;">Remarks (optional)</label>
                <textarea name="remarks" class="form-control form-control-sm" rows="3"
                          placeholder="Add notes or comments..."></textarea>
              </div>
              <?php endif; ?>
              <button type="submit" class="btn <?= $btn[1] ?> btn-block mb-2">
                <i class="fas fa-check-circle mr-1"></i> <?= $btn[0] ?>
              </button>
            </form>

            <?php if ($action_key !== 'faculty_confirm' && $action_key !== 'hr_receive'): ?>
            <!-- Return / Reject option -->
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="post_action" value="return">
              <input type="hidden" name="load_id" value="<?= $view_load['id'] ?>">
              <div class="form-group">
                <label style="font-size:12px;">Reason for returning</label>
                <textarea name="rejection_reason" class="form-control form-control-sm" rows="2"
                          placeholder="State reason for revision..." required></textarea>
              </div>
              <button type="submit" class="btn btn-outline-warning btn-block btn-sm">
                <i class="fas fa-undo mr-1"></i> Return for Revision
              </button>
            </form>
            <?php if (has_role(ROLE_PRESIDENT)): ?>
            <form method="POST" action="" class="mt-2">
              <?php csrf_field(); ?>
              <input type="hidden" name="post_action" value="reject">
              <input type="hidden" name="load_id" value="<?= $view_load['id'] ?>">
              <div class="form-group">
                <label style="font-size:12px;">Rejection reason</label>
                <textarea name="rejection_reason" class="form-control form-control-sm" rows="2"
                          placeholder="State reason for rejection..." required></textarea>
              </div>
              <button type="submit" class="btn btn-outline-danger btn-block btn-sm">
                <i class="fas fa-times-circle mr-1"></i> Reject
              </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php else: ?>
    <!-- ══ LIST VIEW ══ -->

    <!-- Create form: full width, collapsible -->
    <?php if (is_manager()): ?>
    <div class="card card-primary card-outline mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle mr-1"></i> New Teaching Load</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="collapse">
            <i class="fas fa-minus"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" action="" id="createForm">
          <?php csrf_field(); ?>
          <input type="hidden" name="post_action" value="create">
          <div class="row">

            <!-- Column 1: Who, What -->
            <div class="col-md-4">
              <div class="form-group">
                <label>Assign To <span class="text-danger">*</span></label>
                <select name="employee_id" class="form-control form-control-sm" required
                        id="empSelect" onchange="checkOffset()">
                  <option value="">Select employee...</option>
                  <?php foreach ($all_employees as $e): ?>
                  <option value="<?= $e['id'] ?>"
                          data-parttime="<?= $e['is_part_time'] ? '1' : '0' ?>"
                          data-cat="<?= htmlspecialchars($e['cat_name'] ?: '') ?>">
                    <?= htmlspecialchars($e['full_name']) ?>
                    (<?= htmlspecialchars($e['emp_code']) ?>)
                    — <?= htmlspecialchars($e['dept'] ?: '—') ?>
                  </option>
                  <?php endforeach; ?>
                </select>
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
            </div>

            <!-- Column 2: Schedule details -->
            <div class="col-md-4">
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
              <!-- Offset schedule for non-teaching with load -->
              <div id="offsetSection" style="display:none;">
                <div class="alert alert-warning p-2 mb-2" style="font-size:12px;">
                  <i class="fas fa-exchange-alt mr-1"></i>
                  Non-Teaching with teaching load — assign offset schedule below.
                </div>
                <div class="form-group">
                  <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="hasOffset"
                           name="has_offset" value="1" onchange="toggleOffsetFields()">
                    <label class="custom-control-label" for="hasOffset">Assign offset schedule</label>
                  </div>
                </div>
                <div id="offsetFields" style="display:none;">
                  <div class="form-group">
                    <label style="font-size:11px;">Adjusted Office Hours</label>
                    <div class="input-group">
                      <input type="time" name="offset_time_start" class="form-control form-control-sm">
                      <div class="input-group-prepend input-group-append">
                        <span class="input-group-text" style="font-size:12px;padding:0 8px;">to</span>
                      </div>
                      <input type="time" name="offset_time_end" class="form-control form-control-sm">
                    </div>
                  </div>
                  <div class="form-group">
                    <label style="font-size:11px;">Offset Notes</label>
                    <textarea name="offset_notes" class="form-control form-control-sm" rows="2"
                              placeholder="Reason for offset, days affected, etc."></textarea>
                  </div>
                </div>
              </div>
            </div>

            <!-- Column 3: Days -->
            <div class="col-md-4">
              <div class="form-group">
                <label>Day(s) <span class="text-danger">*</span></label>
                <div style="background:#f8f9fa;border:1px solid #ced4da;border-radius:6px;padding:10px 12px;">
                  <div class="row">
                    <?php
                    $all_days = array(
                      'Mon'=>'Monday','Tue'=>'Tuesday','Wed'=>'Wednesday','Thu'=>'Thursday',
                      'Fri'=>'Friday','Sat'=>'Saturday','Sun'=>'Sunday'
                    );
                    foreach ($all_days as $short => $full):
                    ?>
                    <div class="col-6">
                      <div class="custom-control custom-checkbox mb-1">
                        <input type="checkbox" class="custom-control-input"
                               name="days[]" id="day_<?= $short ?>" value="<?= $short ?>">
                        <label class="custom-control-label" for="day_<?= $short ?>"
                               style="font-size:13px;"><?= $full ?></label>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <div style="border-top:1px solid #dee2e6;padding-top:8px;margin-top:6px;">
                    <small class="text-muted d-block mb-1">Quick select:</small>
                    <button type="button" class="btn btn-xs btn-outline-primary mr-1 mb-1"
                            onclick="setDays(['Mon','Wed','Fri'])">MWF</button>
                    <button type="button" class="btn btn-xs btn-outline-primary mr-1 mb-1"
                            onclick="setDays(['Tue','Thu'])">TTh</button>
                    <button type="button" class="btn btn-xs btn-outline-primary mr-1 mb-1"
                            onclick="setDays(['Mon','Tue','Wed','Thu','Fri'])">Mon–Fri</button>
                    <button type="button" class="btn btn-xs btn-outline-primary mr-1 mb-1"
                            onclick="setDays(['Sat'])">Sat only</button>
                    <button type="button" class="btn btn-xs btn-outline-danger mb-1"
                            onclick="setDays([])">Clear</button>
                  </div>
                </div>
              </div>
              <button type="submit" class="btn btn-primary btn-block mt-2">
                <i class="fas fa-plus-circle mr-1"></i> Create Teaching Load
              </button>
            </div>

          </div><!-- /.row -->
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Filters + List: full width -->
    <div class="card card-outline card-secondary mb-3">
      <div class="card-body py-2">
        <form method="GET" action="">
          <div class="row">
            <div class="col-6 col-md-2 mb-2">
              <select name="sy" class="form-control form-control-sm">
                <?php foreach ($sy_options as $sy): ?>
                  <option <?= $sy===$filter_sy?'selected':'' ?>><?= $sy ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-2 mb-2">
              <select name="sem" class="form-control form-control-sm">
                <option value="">All Semesters</option>
                <?php foreach ($semesters as $s): ?>
                  <option <?= $s===$filter_sem?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-2 mb-2">
              <select name="status" class="form-control form-control-sm">
                <option value="">All Statuses</option>
                <?php foreach (array_keys($status_badge) as $st): ?>
                  <option <?= $st===$filter_status?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (is_manager()): ?>
            <div class="col-6 col-md-3 mb-2">
              <select name="emp_id" class="form-control form-control-sm">
                <option value="0">All Employees</option>
                <?php foreach ($all_employees as $e): ?>
                  <option value="<?= $e['id'] ?>" <?= $filter_emp==$e['id']?'selected':'' ?>>
                    <?= htmlspecialchars($e['full_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="col-6 col-md-3 mb-2">
              <button type="submit" class="btn btn-sm btn-primary mr-1">
                <i class="fas fa-filter mr-1"></i> Filter
              </button>
              <a href="teaching_load.php" class="btn btn-sm btn-secondary">Reset</a>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Load list table: full width -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          Teaching Loads
          <span class="badge badge-primary ml-1"><?= count($loads) ?></span>
        </h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-bordered table-hover mb-0" id="loadTable">
          <thead class="thead-light">
            <tr>
              <th>Faculty / Staff</th>
              <th>Subject</th>
              <th>Day &amp; Time</th>
              <th>Room</th>
              <th>SY / Sem</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($loads as $l):
              $sb  = isset($status_badge[$l['status']]) ? $status_badge[$l['status']] : 'secondary';
              $act = can_act($l, $my_emp_id, $my_role);
            ?>
            <tr>
              <td>
                <strong style="font-size:12px;"><?= htmlspecialchars($l['full_name']) ?></strong>
                <br><small class="text-muted"><?= htmlspecialchars($l['emp_code']) ?> &mdash; <?= htmlspecialchars($l['dept'] ?: '—') ?></small>
              </td>
              <td style="font-size:12px;">
                <strong><?= htmlspecialchars($l['subject_title']) ?></strong>
                <?php if ($l['subject_code']): ?>
                  <br><small class="text-muted"><?= htmlspecialchars($l['subject_code']) ?></small>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;">
                <?= htmlspecialchars($l['day_of_week']) ?><br>
                <small><?= date('h:i A',strtotime($l['time_start'])) ?> &mdash; <?= date('h:i A',strtotime($l['time_end'])) ?></small>
              </td>
              <td style="font-size:12px;"><?= htmlspecialchars($l['room'] ?: '—') ?></td>
              <td style="font-size:11px;">
                <?= htmlspecialchars($l['school_year']) ?><br>
                <small><?= htmlspecialchars($l['semester']) ?></small>
              </td>
              <td>
                <span class="badge badge-<?= $sb ?>" style="font-size:10px;"><?= $l['status'] ?></span>
                <?php if ($l['has_offset']): ?>
                  <br><span class="badge badge-warning" style="font-size:9px;">Offset</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="teaching_load.php?view=<?= $l['id'] ?>"
                   class="btn btn-xs <?= $act ? 'btn-warning' : 'btn-info' ?>">
                  <?= $act ? '<i class="fas fa-tasks mr-1"></i> Act' : '<i class="fas fa-eye mr-1"></i> View' ?>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($loads)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">
                <i class="fas fa-chalkboard fa-2x d-block mb-2" style="opacity:.2;"></i>
                No teaching loads found for this filter.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php endif; // view_load ?>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    if ($('#loadTable').length && $.fn.DataTable) {
        $('#loadTable').DataTable({ pageLength:25, order:[[5,'asc']], dom:'lfrtip' });
    }
});

function setDays(days) {
    ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].forEach(function(d) {
        var cb = document.getElementById('day_' + d);
        if (cb) cb.checked = days.indexOf(d) !== -1;
    });
}

// Client-side guard: warn if no day checked on submit
document.getElementById('createForm') && document.getElementById('createForm').addEventListener('submit', function(e) {
    var checked = document.querySelectorAll('input[name="days[]"]:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert('Please select at least one day for the schedule.');
    }
});
function checkOffset() {
    var sel = document.getElementById('empSelect');
    var opt = sel.options[sel.selectedIndex];
    var isPartTime = opt ? opt.getAttribute('data-parttime') : '0';
    var cat = opt ? (opt.getAttribute('data-cat') || '') : '';
    var isNonTeaching = (cat.toLowerCase().indexOf('non') !== -1 || cat === '');
    var show = (isPartTime === '1' || isNonTeaching);
    document.getElementById('offsetSection').style.display = show ? 'block' : 'none';
}

function toggleOffsetFields() {
    var cb = document.getElementById('hasOffset');
    document.getElementById('offsetFields').style.display = cb.checked ? 'block' : 'none';
}
</script>
</body>
</html>