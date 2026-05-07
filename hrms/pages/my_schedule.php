<?php
// ============================================
// FILE: pages/my_schedule.php
// Self-Service: My Schedule tab
// Shows Clock In/Out rules + Teaching Load
// dynamically based on employee type
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

$user    = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array((int)$_SESSION['user_id']));
$emp_id  = $user ? (int)$user['employee_id'] : 0;

if (!$emp_id) { ?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<title>My Schedule | HRMS</title>
<link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head><body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; include '../includes/sidebar.php'; ?>
<div class="content-wrapper"><div class="content"><div class="container-fluid mt-4">
<div class="alert alert-warning">Your account is not linked to an employee record. Contact HR.</div>
</div></div></div>
<?php include '../includes/footer.php'; ?></div></body></html>
<?php exit; }

// ── Load employee with classification ────────────────────────────
$emp = db_fetch($pdo,
    "SELECT e.*, d.name AS dept, p.title AS position,
            ec.name AS cat_name, ec.parent_id AS cat_parent_id
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN positions p ON e.position_id=p.id
     LEFT JOIN employee_classifications ec ON e.classification_id=ec.id
     WHERE e.id=?",
    array($emp_id)
);

// ── Determine employee type ──────────────────────────────────────
$cat_name   = isset($emp['cat_name']) ? strtolower($emp['cat_name']) : '';
$is_teaching_cat = (strpos($cat_name,'teach') !== false || strpos($cat_name,'faculty') !== false
                 || strpos($cat_name,'instructor') !== false || strpos($cat_name,'lecturer') !== false
                 || strpos($cat_name,'professor') !== false);
$is_part_time    = !empty($emp['is_part_time']);
$has_tload       = !empty($emp['has_teaching_load']);

// Employee types:
// A: Non-Teaching, no load
// B: Non-Teaching WITH load (part-time teaching)
// C: Teaching / Faculty

if ($is_teaching_cat) {
    $emp_type = 'C'; // Teaching
} elseif ($is_part_time || $has_tload) {
    $emp_type = 'B'; // Non-Teaching with load
} else {
    $emp_type = 'A'; // Non-Teaching, pure
}

// ── Load shift settings ──────────────────────────────────────────
$grace_mins  = (int)(db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='grace_minutes'") ?: 15);
$lunch_start = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='lunch_start'") ?: '12:00:00';
$lunch_end   = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='lunch_end'")   ?: '13:00:00';

$shift_start = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='shift_start_default'")    ?: '08:00:00';
$shift_end   = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='shift_end_non_teaching'") ?: '17:00:00';

// For teaching, check level
$is_basic_ed = false; // default — set below for Type C
if ($emp_type === 'C') {
    $cat_l = strtolower($cat_name);
    $is_basic_ed = (strpos($cat_l,'elementary') !== false || strpos($cat_l,'junior') !== false
                 || strpos($cat_l,'senior') !== false || strpos($cat_l,'nursery') !== false
                 || strpos($cat_l,'primary') !== false);
    if ($is_basic_ed) {
        $shift_start = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='shift_start_basic_ed'") ?: '07:00:00';
        $shift_end   = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='shift_end_basic_ed'")   ?: '16:00:00';
    } else {
        $shift_start = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='shift_start_college'") ?: '07:00:00';
        $shift_end   = '17:00:00';
    }
}

// ── Filter for teaching load history ────────────────────────────
$cur_sy  = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='current_school_year'") ?: '2025-2026';
$cur_sem = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='current_semester'")    ?: '1st Semester';

$hist_filter = isset($_GET['hist']) ? trim($_GET['hist']) : 'current';
$semesters   = array('1st Semester','2nd Semester','Summer');

// Build load query
$load_where  = "tl.employee_id = ?";
$load_params = array($emp_id);

switch ($hist_filter) {
    case 'current':
        $load_where .= " AND tl.school_year=? AND tl.semester=?";
        $load_params[] = $cur_sy; $load_params[] = $cur_sem;
        break;
    case 'prev_sem':
        // Previous semester logic
        if ($cur_sem === '1st Semester') {
            // Prev sem = Summer of previous year
            $prev_year = date('Y',strtotime($cur_sy)) - 1;
            $load_where .= " AND tl.school_year LIKE ? AND tl.semester='Summer'";
            $load_params[] = $prev_year.'%';
        } else {
            $load_where .= " AND tl.school_year=? AND tl.semester='1st Semester'";
            $load_params[] = $cur_sy;
        }
        break;
    case 'current_sy':
        $load_where .= " AND tl.school_year=?";
        $load_params[] = $cur_sy;
        break;
    case 'prev_sy':
        // Subtract 1 from the starting year
        $parts = explode('-',$cur_sy);
        $prev_sy = ((int)$parts[0]-1).'-'.(int)$parts[0];
        $load_where .= " AND tl.school_year=?";
        $load_params[] = $prev_sy;
        break;
    case 'all':
        // No additional filter
        break;
}

// Only show approved loads in self-service (all statuses for history visibility)
$teaching_loads = db_fetchall($pdo,
    "SELECT tl.* FROM teaching_loads tl
     WHERE $load_where
     ORDER BY tl.school_year DESC, tl.semester, tl.day_of_week, tl.time_start",
    $load_params
);

// Show loads in schedule once Dept Head approves — faculty can see schedule
// while approval continues to VP and President
$active_loads = db_fetchall($pdo,
    "SELECT * FROM teaching_loads
     WHERE employee_id=? AND school_year=? AND semester=?
       AND status IN ('Dept Head Approved','For VP Review','VP Recommended',
                      'For President Approval','Approved')
     ORDER BY day_of_week, time_start",
    array($emp_id,$cur_sy,$cur_sem)
);

// Current offset schedule
$offset_load = null;
foreach ($active_loads as $al) {
    if ($al['has_offset'] && !empty($al['offset_time_start'])) {
        $offset_load = $al;
        break;
    }
}
if ($offset_load) {
    $shift_start = $offset_load['offset_time_start'];
    $shift_end   = $offset_load['offset_time_end'];
}

$hist_labels = array(
    'current'    => 'Current Semester ('.$cur_sem.' '.$cur_sy.')',
    'prev_sem'   => 'Previous Semester',
    'current_sy' => 'Current School Year ('.$cur_sy.')',
    'prev_sy'    => 'Previous School Year',
    'all'        => 'All History',
);
$days_order = array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday',
                    'Mon/Wed/Fri','Tue/Thu','Mon/Tue/Wed/Thu/Fri');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Schedule | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .rule-card { background:#f8f9fa; border-radius:10px; padding:16px; margin-bottom:12px; }
    .rule-card .rule-title { font-size:11px; font-weight:700; text-transform:uppercase;
                             color:#6c757d; margin-bottom:10px; letter-spacing:.05em; }
    .rule-row  { display:flex; align-items:flex-start; gap:10px; margin-bottom:8px; font-size:13px; }
    .rule-icon { width:28px; height:28px; border-radius:50%; display:flex; align-items:center;
                 justify-content:center; flex-shrink:0; font-size:12px; }
    .rule-icon.green  { background:#d4edda; color:#155724; }
    .rule-icon.red    { background:#f8d7da; color:#721c24; }
    .rule-icon.yellow { background:#fff3cd; color:#856404; }
    .rule-icon.blue   { background:#d1ecf1; color:#0c5460; }
    .rule-icon.purple { background:#e2d9f3; color:#4a148c; }
    .type-badge { display:inline-flex; align-items:center; gap:6px; background:#e8f4f8;
                  border-radius:20px; padding:4px 12px; font-size:12px; font-weight:600;
                  color:#0c5460; margin-bottom:16px; }
    .sched-day-header { background:#343a40; color:#fff; padding:6px 12px; border-radius:6px;
                        font-size:12px; font-weight:700; margin-bottom:6px; }
    .sched-row { background:#fff; border:1px solid #dee2e6; border-radius:6px; padding:10px 14px;
                 margin-bottom:6px; font-size:13px; }
    .sched-row .sched-time { font-weight:700; color:#007bff; min-width:130px; }
    .offset-notice { background:linear-gradient(135deg,#fff3cd,#ffeeba); border-radius:10px;
                     padding:14px; margin-bottom:12px; font-size:13px; }
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1 class="m-0">My Schedule</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
          <li class="breadcrumb-item"><a href="my_profile.php">My Profile</a></li>
          <li class="breadcrumb-item active">My Schedule</li>
        </ol>
      </div>
    </div>
  </div></div>

  <div class="content"><div class="container-fluid">

    <!-- Employee type badge -->
    <div class="type-badge">
      <?php if ($emp_type === 'C'): ?>
        <i class="fas fa-chalkboard-teacher text-info"></i>
        Teaching Personnel / Faculty
        <?php if ($is_basic_ed): ?> — Basic Education<?php else: ?> — College / Graduate<?php endif; ?>
      <?php elseif ($emp_type === 'B'): ?>
        <i class="fas fa-user-tie text-primary"></i>
        Non-Teaching Staff with Part-Time Teaching Load
      <?php else: ?>
        <i class="fas fa-user text-secondary"></i>
        Non-Teaching Personnel
      <?php endif; ?>
      &mdash; <?= htmlspecialchars($emp['dept'] ?: '—') ?>
    </div>

    <div class="row">
      <!-- Left: Clock In/Out Rules -->
      <div class="col-md-5">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-clock mr-1 text-primary"></i>
              Clock In/Out Rules &amp; Protocols
            </h3>
          </div>
          <div class="card-body">

            <?php if ($offset_load): ?>
            <!-- Offset notice -->
            <div class="offset-notice">
              <i class="fas fa-exchange-alt mr-1 text-warning"></i>
              <strong>Offset Schedule Active</strong> — Your office hours are adjusted due to your teaching load.
              <?php if ($offset_load['offset_notes']): ?>
                <div class="text-muted mt-1" style="font-size:12px;"><?= htmlspecialchars($offset_load['offset_notes']) ?></div>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Required time in/out -->
            <div class="rule-card">
              <div class="rule-title">Required Working Hours</div>
              <div class="rule-row">
                <div class="rule-icon green"><i class="fas fa-sign-in-alt"></i></div>
                <div>
                  <strong>Clock In before:</strong>
                  <?= date('h:i A',strtotime($shift_start)) ?>
                  <div class="text-muted" style="font-size:11px;">
                    Late threshold: <?= date('h:i A',strtotime($shift_start) + ($grace_mins*60)) ?>
                    (+<?= $grace_mins ?> min grace period)
                  </div>
                </div>
              </div>
              <div class="rule-row">
                <div class="rule-icon red"><i class="fas fa-sign-out-alt"></i></div>
                <div>
                  <strong>Clock Out at:</strong>
                  <?= date('h:i A',strtotime($shift_end)) ?>
                  <div class="text-muted" style="font-size:11px;">
                    Earlier than this = Undertime (flagged to HR)
                  </div>
                </div>
              </div>
              <div class="rule-row">
                <div class="rule-icon blue"><i class="fas fa-clock"></i></div>
                <div>
                  <strong>Required:</strong> 8 hours per day
                  <?php if ($emp_type === 'C' && !$is_basic_ed): ?>
                    <div class="text-muted" style="font-size:11px;">
                      Flexible start time — must complete 8 hours total.
                      If class runs past <?= date('h:i A',strtotime($shift_end)) ?>, stay until class ends.
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Lunch break rules -->
            <div class="rule-card">
              <div class="rule-title">Lunch Break</div>
              <?php
              $has_lunch_class = false;
              foreach ($active_loads as $al) {
                  $ts = strtotime($al['time_start']);
                  $te = strtotime($al['time_end']);
                  $ls = strtotime($lunch_start);
                  $le = strtotime($lunch_end);
                  if ($ts <= $ls && $te >= $le) { $has_lunch_class = true; break; }
                  if ($ts >= $ls && $ts < $le)  { $has_lunch_class = true; break; }
              }
              ?>
              <?php if ($has_lunch_class): ?>
              <div class="rule-row">
                <div class="rule-icon purple"><i class="fas fa-chalkboard"></i></div>
                <div>
                  <strong>Class during lunch:</strong>
                  AM Clock Out and PM Clock In are <strong>not required</strong> today
                  when you have class during <?= date('h:i A',strtotime($lunch_start)) ?> &mdash;
                  <?= date('h:i A',strtotime($lunch_end)) ?>.
                </div>
              </div>
              <?php else: ?>
              <div class="rule-row">
                <div class="rule-icon yellow"><i class="fas fa-utensils"></i></div>
                <div>
                  <strong>Lunch Break:</strong>
                  <?= date('h:i A',strtotime($lunch_start)) ?> &mdash; <?= date('h:i A',strtotime($lunch_end)) ?>
                  <div class="text-muted" style="font-size:11px;">
                    Clock Out for AM before 1:00 PM. Clock In for PM before 1:00 PM.
                    Past 1:00 PM = flagged.
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <!-- Late / Grace period -->
            <div class="rule-card">
              <div class="rule-title">Late &amp; Grace Period</div>
              <div class="rule-row">
                <div class="rule-icon yellow"><i class="fas fa-hourglass-half"></i></div>
                <div>
                  Grace period: <strong><?= $grace_mins ?> minutes</strong> after
                  <?= date('h:i A',strtotime($shift_start)) ?>.
                  <div class="text-muted" style="font-size:11px;">
                    Arriving after <?= date('h:i A',strtotime($shift_start)+($grace_mins*60)) ?> is marked <strong>Late</strong>.
                    Tardiness is computed from shift start, not from grace end.
                  </div>
                </div>
              </div>
            </div>

            <?php if ($emp_type === 'B' || $emp_type === 'C'): ?>
            <!-- Teaching-specific rules -->
            <div class="rule-card">
              <div class="rule-title">Teaching-Related Rules</div>
              <?php if ($emp_type === 'B'): ?>
              <div class="rule-row">
                <div class="rule-icon purple"><i class="fas fa-exchange-alt"></i></div>
                <div>
                  <strong>Offset / Compensatory Time:</strong>
                  Teaching hours outside your regular schedule are offset against
                  your regular work hours as approved by your Department Head.
                </div>
              </div>
              <div class="rule-row">
                <div class="rule-icon blue"><i class="fas fa-user-check"></i></div>
                <div>
                  <strong>Offset Approval:</strong>
                  Offset schedules are approved by the Department Head,
                  recommended by VP, and approved by the President.
                  HR integrates the approved offset into your timekeeping.
                </div>
              </div>
              <?php endif; ?>
              <?php if ($emp_type === 'C'): ?>
              <div class="rule-row">
                <div class="rule-icon green"><i class="fas fa-user-graduate"></i></div>
                <div>
                  <strong>Class Attendance:</strong>
                  You are expected to be present in your assigned room
                  <strong>5–10 minutes before</strong> your class starts.
                </div>
              </div>
              <div class="rule-row">
                <div class="rule-icon yellow"><i class="fas fa-book-open"></i></div>
                <div>
                  <strong>Vacant Hours:</strong>
                  Periods between classes count as work time.
                  You must remain on campus unless approved otherwise.
                </div>
              </div>
              <div class="rule-row">
                <div class="rule-icon red"><i class="fas fa-clock"></i></div>
                <div>
                  <strong>Extra / Overload Class:</strong>
                  Classes beyond your regular load require a separate overload form
                  and may count as overtime or additional compensation.
                </div>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Overtime rules -->
            <div class="rule-card">
              <div class="rule-title">Overtime &amp; Undertime</div>
              <div class="rule-row">
                <div class="rule-icon green"><i class="fas fa-plus-circle"></i></div>
                <div>
                  <strong>Overtime:</strong> Clocking out after <?= date('h:i A',strtotime($shift_end)) ?>
                  generates overtime hours (subject to HR approval for compensation).
                </div>
              </div>
              <div class="rule-row">
                <div class="rule-icon red"><i class="fas fa-minus-circle"></i></div>
                <div>
                  <strong>Undertime:</strong> Clocking out before <?= date('h:i A',strtotime($shift_end)) ?>
                  is flagged as undertime and notified to HR.
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- Right: Class schedule & history (shown for Type B and C) -->
      <div class="col-md-7">

        <?php if ($emp_type !== 'A'): ?>

        <!-- Current semester class schedule -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
              <i class="fas fa-calendar-alt mr-1 text-success"></i>
              Current Class Schedule
            </h3>
            <small class="text-muted"><?= htmlspecialchars($cur_sem.' '.$cur_sy) ?></small>
          </div>
          <div class="card-body">
            <?php if (empty($active_loads)): ?>
            <div class="text-center text-muted py-3" style="font-size:13px;">
              <i class="fas fa-calendar-times fa-2x d-block mb-2" style="opacity:.2;"></i>
              No approved teaching load for the current semester.
              <?php if (is_manager()): ?>
                <br><a href="teaching_load.php" class="btn btn-sm btn-primary mt-2">
                  <i class="fas fa-plus mr-1"></i> Create Teaching Load
                </a>
              <?php endif; ?>
            </div>
            <?php else: ?>

            <?php if ($offset_load): ?>
            <div class="alert alert-warning py-2 mb-3" style="font-size:12px;">
              <i class="fas fa-exchange-alt mr-1"></i>
              <strong>Adjusted Office Hours:</strong>
              <?= date('h:i A',strtotime($offset_load['offset_time_start'])) ?> &mdash;
              <?= date('h:i A',strtotime($offset_load['offset_time_end'])) ?>
              (due to teaching load offset)
            </div>
            <?php endif; ?>

            <div class="table-responsive">
              <table class="table table-sm table-bordered" id="schedTable">
                <thead class="thead-dark">
                  <tr>
                    <th>Subject</th><th>Code</th><th>Program</th>
                    <th>Day</th><th>Time</th><th>Room</th><th>Level</th><th>Units</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($active_loads as $al): ?>
                  <tr>
                    <td style="font-size:12px;"><strong><?= htmlspecialchars($al['subject_title']) ?></strong></td>
                    <td style="font-size:11px;"><?= htmlspecialchars($al['subject_code'] ?: '—') ?></td>
                    <td style="font-size:11px;"><?= htmlspecialchars($al['student_program'] ?: '—') ?></td>
                    <td style="font-size:12px;font-weight:600;"><?= htmlspecialchars($al['day_of_week']) ?></td>
                    <td style="font-size:12px;">
                      <?= date('h:i A',strtotime($al['time_start'])) ?><br>
                      <span class="text-muted"><?= date('h:i A',strtotime($al['time_end'])) ?></span>
                    </td>
                    <td style="font-size:12px;"><?= htmlspecialchars($al['room'] ?: '—') ?></td>
                    <td><span class="badge badge-info" style="font-size:9px;"><?= htmlspecialchars($al['level']) ?></span></td>
                    <td style="font-size:12px;"><?= $al['units'] ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <!-- Total row -->
                  <tr class="table-dark">
                    <td colspan="7" class="text-right" style="font-size:12px;font-weight:700;">
                      Total Units:
                    </td>
                    <td style="font-size:13px;font-weight:700;">
                      <?= array_sum(array_column($active_loads,'units')) ?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Teaching Load History -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
              <i class="fas fa-history mr-1"></i> Teaching Load History
            </h3>
          </div>
          <div class="card-body">

            <!-- History filter tabs -->
            <div class="d-flex flex-wrap mb-3" style="gap:6px;">
              <?php foreach ($hist_labels as $hk => $hl): ?>
              <a href="my_schedule.php?hist=<?= $hk ?>"
                 class="btn btn-sm <?= $hist_filter===$hk ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= $hl ?>
              </a>
              <?php endforeach; ?>
            </div>

            <?php if (empty($teaching_loads)): ?>
            <div class="text-center text-muted py-3" style="font-size:13px;">
              <i class="fas fa-inbox fa-2x d-block mb-2" style="opacity:.2;"></i>
              No teaching load records found for this filter.
            </div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover" id="histTable">
                <thead class="thead-light">
                  <tr>
                    <th>SY / Sem</th><th>Subject</th><th>Code</th>
                    <th>Day</th><th>Time</th><th>Room</th><th>Program</th>
                    <th>Units</th><th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($teaching_loads as $tl):
                    $sb = array(
                      'Draft'=>'secondary','For Faculty Review'=>'info','Faculty Confirmed'=>'primary',
                      'For Dept Head Review'=>'warning','Dept Head Approved'=>'primary',
                      'For VP Review'=>'warning','VP Recommended'=>'primary',
                      'For President Approval'=>'warning','Approved'=>'success',
                      'Rejected'=>'danger','Returned'=>'warning',
                    );
                    $sc = isset($sb[$tl['status']]) ? $sb[$tl['status']] : 'secondary';
                  ?>
                  <tr>
                    <td style="font-size:11px;">
                      <?= htmlspecialchars($tl['school_year']) ?><br>
                      <span class="text-muted"><?= htmlspecialchars($tl['semester']) ?></span>
                    </td>
                    <td style="font-size:12px;"><strong><?= htmlspecialchars($tl['subject_title']) ?></strong></td>
                    <td style="font-size:11px;"><?= htmlspecialchars($tl['subject_code'] ?: '—') ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($tl['day_of_week']) ?></td>
                    <td style="font-size:12px;">
                      <?= date('h:i A',strtotime($tl['time_start'])) ?> &mdash;
                      <?= date('h:i A',strtotime($tl['time_end'])) ?>
                    </td>
                    <td style="font-size:12px;"><?= htmlspecialchars($tl['room'] ?: '—') ?></td>
                    <td style="font-size:11px;"><?= htmlspecialchars($tl['student_program'] ?: '—') ?></td>
                    <td style="font-size:12px;"><?= $tl['units'] ?></td>
                    <td>
                      <span class="badge badge-<?= $sc ?>" style="font-size:9px;">
                        <?= $tl['status'] ?>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <?php else: ?>
        <!-- Type A: Non-teaching, no load -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-info-circle mr-1 text-info"></i> Schedule Information
            </h3>
          </div>
          <div class="card-body">
            <div class="alert alert-info mb-0" style="font-size:13px;">
              <i class="fas fa-user-tie mr-1"></i>
              As a <strong>Non-Teaching Staff</strong>, you are on a regular office schedule.<br>
              Your attendance is governed by the Clock In/Out rules listed on the left.<br>
              If you are assigned a part-time teaching load, please contact HR to update your classification.
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
$(function(){
    if ($('#schedTable').length && $.fn.DataTable) {
        $('#schedTable').DataTable({ paging:false, searching:false, info:false, dom:'t' });
    }
    if ($('#histTable').length && $.fn.DataTable) {
        $('#histTable').DataTable({ pageLength:15, order:[[0,'desc']], dom:'lfrtip' });
    }
});
</script>
</body>
</html>