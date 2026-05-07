<?php
// FILE: pages/dashboard.php — HR Analytics Dashboard
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';

// Redirect non-HR roles
$rd = array(
    'dept_secretary'=>'dept_secretary_dashboard.php','exec_secretary'=>'exec_secretary_dashboard.php',
    'manager'=>'academic_head_dashboard.php','academic_head'=>'academic_head_dashboard.php',
    'non_academic_head'=>'non_academic_head_dashboard.php','registrar_head'=>'registrar_head_dashboard.php',
    'vp_academic'=>'vp_academic_dashboard.php','vp_admin'=>'vp_admin_dashboard.php',
    'president'=>'president_dashboard.php','board_director'=>'board_director_dashboard.php',
    'clinic_head'=>'clinic_head_dashboard.php','clinic_staff'=>'clinic_staff_dashboard.php',
    'employee'=>'employee_dashboard.php',
);
if (isset($rd[$role])) { header('Location: '.$rd[$role]); exit; }

// Timezone
$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz='Asia/Manila'; $_hrms_delta=0;
foreach($_tz_row as $_r){
    if($_r['setting_key']==='system_timezone'&&$_r['setting_value']) $_hrms_tz=$_r['setting_value'];
    if($_r['setting_key']==='clock_delta_seconds') $_hrms_delta=(int)$_r['setting_value'];
}
try{ date_default_timezone_set($_hrms_tz); }catch(Exception $e){ date_default_timezone_set('Asia/Manila'); }

$my_uid = (int)$_SESSION['user_id'];
$today  = date('Y-m-d', time()+$_hrms_delta);

// ── STATS ──────────────────────────────────────────────────────────
$total_emp      = (int)db_value($pdo,"SELECT COUNT(*) FROM employees WHERE employment_status='Active'");
$present_today  = (int)db_value($pdo,"SELECT COUNT(*) FROM attendance WHERE attendance_date=? AND status='Present'",array($today));
$absent_today   = (int)db_value($pdo,"SELECT COUNT(*) FROM attendance WHERE attendance_date=? AND status='Absent'",array($today));
$late_today     = (int)db_value($pdo,"SELECT COUNT(*) FROM attendance WHERE attendance_date=? AND status='Late'",array($today));
$on_leave_today = (int)db_value($pdo,"SELECT COUNT(*) FROM leave_requests WHERE date_from<=? AND date_to>=? AND status='Approved'",array($today,$today));
$pending_req    = (int)db_value($pdo,"SELECT COUNT(*) FROM employee_requests WHERE status IN ('Pending','Pending Clarification')");

// ── TEACHING vs NON-TEACHING SPLIT (for KPI strip) ────────────────
$teaching_present  = 0; $nonteaching_present = 0;
$teaching_absent   = 0; $nonteaching_absent  = 0;
try {
    $teaching_present  = (int)db_value($pdo,
        "SELECT COUNT(*) FROM attendance a
         JOIN employees e ON a.employee_id=e.id
         JOIN employee_classifications ec ON e.classification_id=ec.id
         WHERE a.attendance_date=? AND a.status='Present' AND ec.category='Teaching'",
        array($today));
    $nonteaching_present = $present_today - $teaching_present;
    $teaching_absent   = (int)db_value($pdo,
        "SELECT COUNT(*) FROM attendance a
         JOIN employees e ON a.employee_id=e.id
         JOIN employee_classifications ec ON e.classification_id=ec.id
         WHERE a.attendance_date=? AND a.status='Absent' AND ec.category='Teaching'",
        array($today));
    $nonteaching_absent = $absent_today - $teaching_absent;
} catch(Exception $e) {}

// ── ATTENDANCE TREND Teaching vs Non-Teaching (last 14 days) ────────
$trend_days=array(); $trend_teach_p=array(); $trend_nonteach_p=array();
$trend_teach_a=array(); $trend_nonteach_a=array();
try {
    for ($i=13; $i>=0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days", time()+$_hrms_delta));
        $trend_days[]      = date('M d', strtotime($d));
        $trend_teach_p[]   = (int)db_value($pdo,
            "SELECT COUNT(*) FROM attendance a JOIN employees e ON a.employee_id=e.id
             JOIN employee_classifications ec ON e.classification_id=ec.id
             WHERE a.attendance_date=? AND a.status='Present' AND ec.category='Teaching'",array($d));
        $trend_nonteach_p[]= (int)db_value($pdo,
            "SELECT COUNT(*) FROM attendance a JOIN employees e ON a.employee_id=e.id
             LEFT JOIN employee_classifications ec ON e.classification_id=ec.id
             WHERE a.attendance_date=? AND a.status='Present' AND (ec.category!='Teaching' OR ec.id IS NULL)",array($d));
        $trend_teach_a[]   = (int)db_value($pdo,
            "SELECT COUNT(*) FROM attendance a JOIN employees e ON a.employee_id=e.id
             JOIN employee_classifications ec ON e.classification_id=ec.id
             WHERE a.attendance_date=? AND a.status='Absent' AND ec.category='Teaching'",array($d));
        $trend_nonteach_a[]= (int)db_value($pdo,
            "SELECT COUNT(*) FROM attendance a JOIN employees e ON a.employee_id=e.id
             LEFT JOIN employee_classifications ec ON e.classification_id=ec.id
             WHERE a.attendance_date=? AND a.status='Absent' AND (ec.category!='Teaching' OR ec.id IS NULL)",array($d));
    }
} catch(Exception $e) {}

// ── DEPARTMENT HEADCOUNT ──────────────────────────────────────────
$dept_data = array();
try {
    $dept_data = db_fetchall($pdo,
        "SELECT d.name, COUNT(e.id) AS cnt
         FROM departments d
         LEFT JOIN employees e ON e.department_id=d.id AND e.employment_status='Active'
         GROUP BY d.id ORDER BY cnt DESC LIMIT 8") ?: array();
} catch(Exception $e) {}

// ── LEAVE ANALYTICS (current year) ────────────────────────────────
$leave_data = array();
try {
    $leave_data = db_fetchall($pdo,
        "SELECT leave_type, COUNT(*) AS requests, SUM(days_applied) AS total_days
         FROM leave_requests
         WHERE YEAR(created_at)=YEAR(CURDATE()) AND status IN ('Approved','Pending')
         GROUP BY leave_type ORDER BY total_days DESC") ?: array();
    // Leave impact score: teaching-staff days * 1.5 (class coverage weight)
    $leave_impact_score = 0;
    try {
        $teach_days = (float)db_value($pdo,
            "SELECT COALESCE(SUM(lr.days_applied),0) FROM leave_requests lr
             JOIN employees e ON lr.employee_id=e.id
             JOIN employee_classifications ec ON e.classification_id=ec.id
             WHERE MONTH(lr.created_at)=MONTH(CURDATE()) AND YEAR(lr.created_at)=YEAR(CURDATE())
               AND lr.status='Approved' AND ec.category='Teaching'");
        $nonteach_days = (float)db_value($pdo,
            "SELECT COALESCE(SUM(lr.days_applied),0) FROM leave_requests lr
             JOIN employees e ON lr.employee_id=e.id
             LEFT JOIN employee_classifications ec ON e.classification_id=ec.id
             WHERE MONTH(lr.created_at)=MONTH(CURDATE()) AND YEAR(lr.created_at)=YEAR(CURDATE())
               AND lr.status='Approved' AND (ec.category!='Teaching' OR ec.id IS NULL)");
        $leave_impact_score = round(($teach_days * 1.5) + ($nonteach_days * 1.0), 1);
    } catch(Exception $e) {}
} catch(Exception $e) {}

// ── WORKFORCE INSIGHTS ────────────────────────────────────────────
$teaching_count     = 0; $non_teaching_count = 0;
$absent_rate        = 0; $avg_late_per_day   = 0;
try {
    $teaching_count     = (int)db_value($pdo,"SELECT COUNT(*) FROM employees e LEFT JOIN employee_classifications ec ON e.classification_id=ec.id WHERE e.employment_status='Active' AND ec.category='Teaching'");
    $non_teaching_count = $total_emp - $teaching_count;
    $total_workdays     = (int)db_value($pdo,"SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE MONTH(attendance_date)=MONTH(CURDATE()) AND YEAR(attendance_date)=YEAR(CURDATE())");
    if ($total_workdays > 0 && $total_emp > 0) {
        $total_absences = (int)db_value($pdo,"SELECT COUNT(*) FROM attendance WHERE MONTH(attendance_date)=MONTH(CURDATE()) AND YEAR(attendance_date)=YEAR(CURDATE()) AND status='Absent'");
        $absent_rate    = round(($total_absences / ($total_workdays * $total_emp)) * 100, 1);
        $total_late_cnt = (int)db_value($pdo,"SELECT COUNT(*) FROM attendance WHERE MONTH(attendance_date)=MONTH(CURDATE()) AND YEAR(attendance_date)=YEAR(CURDATE()) AND status='Late'");
        $avg_late_per_day = $total_workdays > 0 ? round($total_late_cnt / $total_workdays, 1) : 0;
    }
} catch(Exception $e) {}

// ── HR ALERTS ─────────────────────────────────────────────────────
$alerts = array();
try {
    // Late 3+ times this week
    $week_start = date('Y-m-d', strtotime('monday this week', time()+$_hrms_delta));
    $late3 = (int)db_value($pdo,"SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date>=? AND status='Late' GROUP BY employee_id HAVING COUNT(*)>=3",array($week_start));
    // Use a different approach - count employees with 3+ late this week
    $late3_emps = db_fetchall($pdo,
        "SELECT e.full_name, COUNT(*) AS late_count FROM attendance a
         JOIN employees e ON a.employee_id=e.id
         WHERE a.attendance_date>=? AND a.status='Late'
         GROUP BY a.employee_id HAVING COUNT(*)>=3 LIMIT 5",
        array($week_start)) ?: array();
    foreach($late3_emps as $le) {
        $alerts[] = array('type'=>'warning','icon'=>'fa-clock','msg'=>$le['full_name'].' late '.$le['late_count'].'x this week');
    }
    // 5+ absences this month
    $abs5_emps = db_fetchall($pdo,
        "SELECT e.full_name, COUNT(*) AS abs_count FROM attendance a
         JOIN employees e ON a.employee_id=e.id
         WHERE MONTH(a.attendance_date)=MONTH(CURDATE()) AND YEAR(a.attendance_date)=YEAR(CURDATE())
               AND a.status='Absent'
         GROUP BY a.employee_id HAVING COUNT(*)>=5 LIMIT 5") ?: array();
    foreach($abs5_emps as $ae) {
        $alerts[] = array('type'=>'danger','icon'=>'fa-user-times','msg'=>$ae['full_name'].' — '.$ae['abs_count'].' absences this month');
    }
    // Probationary contracts review due (within 30 days)
    $prob_due = db_fetchall($pdo,
        "SELECT full_name, date_hired FROM employees
         WHERE employment_type='Probationary' AND employment_status='Active'
               AND date_hired IS NOT NULL
               AND DATE_ADD(date_hired, INTERVAL 6 MONTH) BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)
         LIMIT 5") ?: array();
    foreach($prob_due as $pd) {
        $review = date('M d', strtotime($pd['date_hired'].' +6 months'));
        $alerts[] = array('type'=>'info','icon'=>'fa-file-contract','msg'=>$pd['full_name'].' contract review due '.$review);
    }
    // Overloaded teachers (>30 hrs/week via teaching loads)
    try {
        $overloaded = db_fetchall($pdo,
            "SELECT e.full_name, tl.total_hours FROM teaching_loads tl
             JOIN employees e ON tl.employee_id=e.id
             WHERE tl.status IN ('Approved','For President Approval','President Approved')
               AND tl.total_hours > 30 LIMIT 5") ?: array();
        foreach($overloaded as $ov) {
            $alerts[] = array('type'=>'danger','icon'=>'fa-fire',
                'msg'=>htmlspecialchars($ov['full_name']).' overloaded ('.$ov['total_hours'].' hrs/wk)');
        }
    } catch(Exception $e) {}
    // Teachers absent today (school-specific: no sub assigned flag)
    try {
        $absent_teach_today = (int)db_value($pdo,
            "SELECT COUNT(*) FROM attendance a
             JOIN employees e ON a.employee_id=e.id
             JOIN employee_classifications ec ON e.classification_id=ec.id
             WHERE a.attendance_date=? AND a.status='Absent' AND ec.category='Teaching'",
            array($today));
        if ($absent_teach_today > 0) {
            $alerts[] = array('type'=>'danger','icon'=>'fa-chalkboard',
                'msg'=>$absent_teach_today.' teacher(s) absent today — check substitute assignments');
        }
    } catch(Exception $e) {}

    if (empty($alerts)) {
        $alerts[] = array('type'=>'success','icon'=>'fa-check-circle','msg'=>'No critical HR alerts today.');
    }
} catch(Exception $e) {
    $alerts[] = array('type'=>'secondary','icon'=>'fa-info-circle','msg'=>'Alert data unavailable.');
}

// ── MONTHLY SUMMARY ───────────────────────────────────────────────
$month_summary = array('workdays'=>0,'avg_att'=>0,'total_leaves'=>0);
try {
    $month_summary['workdays']     = (int)db_value($pdo,"SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE MONTH(attendance_date)=MONTH(CURDATE()) AND YEAR(attendance_date)=YEAR(CURDATE())");
    $month_summary['total_leaves'] = (int)db_value($pdo,"SELECT COUNT(*) FROM leave_requests WHERE MONTH(date_from)=MONTH(CURDATE()) AND YEAR(date_from)=YEAR(CURDATE()) AND status='Approved'");
    if ($month_summary['workdays'] > 0 && $total_emp > 0) {
        $total_present_month = (int)db_value($pdo,"SELECT COUNT(*) FROM attendance WHERE MONTH(attendance_date)=MONTH(CURDATE()) AND YEAR(attendance_date)=YEAR(CURDATE()) AND status='Present'");
        $month_summary['avg_att'] = round(($total_present_month / ($month_summary['workdays'] * $total_emp)) * 100, 1);
    }
} catch(Exception $e) {}

// ── TOP/BOTTOM EMPLOYEES ──────────────────────────────────────────
$most_punctual = array(); $most_absent = array();
try {
    $most_punctual = db_fetchall($pdo,
        "SELECT e.full_name, COUNT(*) AS present_count FROM attendance a
         JOIN employees e ON a.employee_id=e.id
         WHERE MONTH(a.attendance_date)=MONTH(CURDATE()) AND YEAR(a.attendance_date)=YEAR(CURDATE())
               AND a.status='Present' AND e.employment_status='Active'
         GROUP BY a.employee_id ORDER BY present_count DESC LIMIT 5") ?: array();
    $most_absent = db_fetchall($pdo,
        "SELECT e.full_name, COUNT(*) AS absent_count FROM attendance a
         JOIN employees e ON a.employee_id=e.id
         WHERE MONTH(a.attendance_date)=MONTH(CURDATE()) AND YEAR(a.attendance_date)=YEAR(CURDATE())
               AND a.status='Absent' AND e.employment_status='Active'
         GROUP BY a.employee_id ORDER BY absent_count DESC LIMIT 5") ?: array();
} catch(Exception $e) {}

// ── ANNOUNCEMENTS ─────────────────────────────────────────────────
$announcements = array();
try {
    $announcements = db_fetchall($pdo,
        "SELECT a.*, u.username AS posted_by_name FROM announcements a
         LEFT JOIN users u ON a.posted_by=u.id
         WHERE a.is_active=1 ORDER BY a.created_at DESC LIMIT 10") ?: array();
} catch(Exception $e) {}
$priority_colors = array('Normal'=>'secondary','Important'=>'warning','Urgent'=>'danger');
$border_hex      = array('secondary'=>'#6c757d','warning'=>'#ffc107','danger'=>'#dc3545');

// ── CALENDAR NOTES ────────────────────────────────────────────────
$notes_table_exists = false;
try { $pdo->query("SELECT 1 FROM dashboard_notes LIMIT 1"); $notes_table_exists=true; } catch(Exception $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST' && $notes_table_exists) {
    csrf_verify();
    $act = isset($_POST['note_action']) ? $_POST['note_action'] : '';
    if ($act==='save_note') {
        $nd   = clean_string(isset($_POST['note_date'])     ? $_POST['note_date']     : '', 20);
        $nd2  = clean_string(isset($_POST['note_date_end']) ? $_POST['note_date_end'] : '', 20) ?: null;
        $ntxt = clean_string(isset($_POST['note_text'])     ? $_POST['note_text']     : '', 1000);
        $ncol = clean_string(isset($_POST['note_color'])    ? $_POST['note_color']    : 'primary', 20);
        $nid  = (int)(isset($_POST['note_id'])              ? $_POST['note_id']       : 0);
        if ($nd && $ntxt) {
            if ($nid) db_run($pdo,"UPDATE dashboard_notes SET note_date=?,note_date_end=?,note=?,color=? WHERE id=? AND user_id=?",array($nd,$nd2,$ntxt,$ncol,$nid,$my_uid));
            else       db_run($pdo,"INSERT INTO dashboard_notes (user_id,note_date,note_date_end,note,color) VALUES (?,?,?,?,?)",array($my_uid,$nd,$nd2,$ntxt,$ncol));
        }
        prg_redirect('dashboard.php','Note saved.');
    }
    if ($act==='delete_note') {
        $nid=(int)(isset($_POST['note_id'])?$_POST['note_id']:0);
        if ($nid) db_run($pdo,"DELETE FROM dashboard_notes WHERE id=? AND user_id=?",array($nid,$my_uid));
        prg_redirect('dashboard.php','Note deleted.');
    }
}

$raw_notes = array();
if ($notes_table_exists) {
    try {
        $cal_y = isset($_GET['cal_y']) ? (int)$_GET['cal_y'] : (int)date('Y',time()+$_hrms_delta);
        $cal_m = isset($_GET['cal_m']) ? (int)$_GET['cal_m'] : (int)date('n',time()+$_hrms_delta);
        $ms = sprintf('%04d-%02d-01',$cal_y,$cal_m);
        $me = date('Y-m-t',strtotime($ms));
        $raw_notes = db_fetchall($pdo,"SELECT * FROM dashboard_notes WHERE user_id=? AND note_date BETWEEN ? AND ? ORDER BY note_date",array($my_uid,$ms,$me)) ?: array();
    } catch(Exception $e) {}
}
$all_notes_json = json_encode($raw_notes);
$cal_y_init = isset($cal_y) ? $cal_y : (int)date('Y',time()+$_hrms_delta);
$cal_m_init = isset($cal_m) ? $cal_m : (int)date('n',time()+$_hrms_delta);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>HR Dashboard | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
  <style>

    .chart-card{border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:16px;}
    .insight-badge{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;
      font-size:12px;font-weight:600;background:#f8f9fa;border:1px solid #dee2e6;margin:3px;}

    .alert-item{border-left:4px solid;border-radius:0 6px 6px 0;padding:7px 12px;
      margin-bottom:7px;font-size:12px;}
    .ann-item{border-left:4px solid;border-radius:0 6px 6px 0;padding:8px 12px;
      margin-bottom:7px;background:#fafafa;}
    .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;}
    .cal-cell{min-height:46px;padding:3px;border-radius:4px;cursor:pointer;border:1px solid transparent;transition:.1s;}
    .cal-cell:hover{background:#f0f7ff;border-color:#007bff33;}
    .cal-cell.today{background:#e8f4ff;border-color:#007bff;}
    .cal-cell.today .day-num{color:#007bff;font-weight:700;}
    .cal-cell.has-note{background:#fff8e1;}
    .cal-cell.other-month{opacity:.3;pointer-events:none;}
    .cal-note-chip{font-size:9px;border-radius:3px;padding:1px 4px;white-space:nowrap;
      overflow:hidden;text-overflow:ellipsis;max-width:100%;display:block;margin-top:1px;color:#fff;}
    .note-color-swatch{display:inline-block;width:22px;height:22px;border-radius:50%;
      border:2px solid transparent;transition:.1s;cursor:pointer;}
    .section-title{font-size:14px;font-weight:700;color:#495057;border-bottom:2px solid #007bff;
      padding-bottom:6px;margin-bottom:14px;}

  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
<div class="content"><div class="container-fluid">
    <?= prg_flash() ?>

    <!-- ── KPI STRIP ── -->
    <div class="row mb-3" style="margin-left:-6px;margin-right:-6px;">
      <?php
      $kpi_items = array(
        array('fa-users',              '#007bff', 'Total Employees',  $total_emp),
        array('fa-chalkboard-teacher', '#6f42c1', 'Teaching Staff',   $teaching_count),
        array('fa-briefcase',          '#17a2b8', 'Non-Teaching',     $non_teaching_count),
        array('fa-user-check',         '#28a745', 'Present Today',    $present_today),
        array('fa-user-times',         '#dc3545', 'Absent Today',     $absent_today),
        array('fa-clock',              '#ff9800', 'Late Today',       $late_today),
        array('fa-calendar-minus',     '#9c27b0', 'On Leave',         $on_leave_today),
        array('fa-file-alt',           '#607d8b', 'Pending Requests', $pending_req),
      );
      foreach($kpi_items as $k): ?>
      <div class="col-6 col-sm-4 col-md-3 col-lg mb-2 px-1">
        <div class="d-flex align-items-center p-2 rounded kpi-item"
             style="background:#fff;border:1px solid #e9ecef;box-shadow:0 1px 4px rgba(0,0,0,.06);height:58px;gap:10px;">
          <div style="width:34px;height:34px;border-radius:8px;background:<?= $k[1] ?>22;
                      display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            </div>
          <div style="min-width:0;">
            <div style="font-size:20px;font-weight:700;line-height:1;color:<?= $k[1] ?>;"><?= $k[3] ?></div>
            <div style="font-size:10px;color:#6c757d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= $k[2] ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ══════════════════════════════════════════════════════ -->
    <!-- MAIN LAYOUT: LEFT 70% analytics | RIGHT 30% actions  -->
    <!-- ══════════════════════════════════════════════════════ -->
    <div class="row">


    <!-- ══════════════════════════════════════════════════════ -->
    <!-- MAIN LAYOUT: LEFT 70% analytics | RIGHT 30% actions  -->
    <!-- ══════════════════════════════════════════════════════ -->
    <div class="row">

      <!-- ── LEFT 70%: Analytics ── -->
      <div class="col-md-8">

        <!-- A. Teaching vs Non-Teaching Attendance Trend -->
        <div class="card chart-card">
          <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title" style="font-size:13px;">
              Attendance Trend — Teaching vs Non-Teaching (14 Days)
            </h3>
            <div>
              <span class="badge badge-primary" style="font-size:9px;">Teaching</span>
              <span class="badge badge-info ml-1" style="font-size:9px;">Non-Teaching</span>
            </div>
          </div>
          <div class="card-body py-2 px-3">
            <canvas id="attendanceTrendChart" height="100"></canvas>
          </div>
        </div>

        <!-- B & C: Dept Breakdown + Leave Analytics side by side -->
        <div class="row">
          <div class="col-md-6">
            <div class="card chart-card">
              <div class="card-header py-2">
                <h3 class="card-title" style="font-size:13px;">
                  Department Headcount
                </h3>
              </div>
              <div class="card-body py-2 px-3">
                <canvas id="deptChart" height="160"></canvas>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card chart-card">
              <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h3 class="card-title" style="font-size:13px;">
                  Leave Analytics (This Year)
                </h3>
                <span class="badge badge-danger" style="font-size:9px;" title="Teaching days × 1.5 + Non-teaching days × 1.0">
                  Impact: <?= $leave_impact_score ?>
                </span>
              </div>
              <div class="card-body py-2 px-3">
                <?php if (empty($leave_data)): ?>
                <div class="text-center text-muted py-4" style="font-size:12px;">No leave data yet.</div>
                <?php else: ?>
                <canvas id="leaveChart" height="160"></canvas>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- D. Workforce Insights -->
        <div class="card chart-card">
          <div class="card-header py-2">
            <h3 class="card-title" style="font-size:13px;">
              Workforce Insights
            </h3>
          </div>
          <div class="card-body py-2">
            <div class="d-flex flex-wrap">
              <div class="insight-badge">
                Teaching: <strong class="ml-1"><?= $teaching_count ?></strong>
              </div>
              <div class="insight-badge">
                Non-Teaching: <strong class="ml-1"><?= $non_teaching_count ?></strong>
              </div>
              <div class="insight-badge">
                Absenteeism Rate: <strong class="ml-1"><?= $absent_rate ?>%</strong>
              </div>
              <div class="insight-badge">
                Avg Late/Day: <strong class="ml-1"><?= $avg_late_per_day ?></strong>
              </div>
              <div class="insight-badge">
                Total Active: <strong class="ml-1"><?= $total_emp ?></strong>
              </div>
              <?php if ($teaching_count > 0 && $non_teaching_count > 0): ?>
              <div class="insight-badge">
                Teaching Ratio:
                <strong class="ml-1">
                  1:<?= round($non_teaching_count/$teaching_count,1) ?>
                </strong>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Bottom section: Monthly summary + Top/Bottom employees -->
        <div class="row">
          <!-- Monthly Summary -->
          <div class="col-md-4">
            <div class="card chart-card">
              <div class="card-header py-2">
                <h3 class="card-title" style="font-size:12px;">
                  Monthly Summary
                </h3>
              </div>
              <div class="card-body p-0">
                <table class="table table-sm mb-0" style="font-size:12px;">
                  <tr><td class="text-muted pl-3">Total Workdays</td>
                      <td class="font-weight-bold text-right pr-3"><?= $month_summary['workdays'] ?></td></tr>
                  <tr><td class="text-muted pl-3">Avg Attendance</td>
                      <td class="font-weight-bold text-right pr-3 text-success"><?= $month_summary['avg_att'] ?>%</td></tr>
                  <tr><td class="text-muted pl-3">Leaves Approved</td>
                      <td class="font-weight-bold text-right pr-3"><?= $month_summary['total_leaves'] ?></td></tr>
                  <tr><td class="text-muted pl-3">Absenteeism Rate</td>
                      <td class="font-weight-bold text-right pr-3 text-danger"><?= $absent_rate ?>%</td></tr>
                </table>
              </div>
            </div>
          </div>
          <!-- Most Punctual -->
          <div class="col-md-4">
            <div class="card chart-card">
              <div class="card-header py-2">
                <h3 class="card-title" style="font-size:12px;">
                  Most Punctual Faculty
                </h3>
              </div>
              <div class="card-body p-0">
                <?php if (empty($most_punctual)): ?>
                <div class="text-center text-muted py-3" style="font-size:12px;">No data yet.</div>
                <?php else: ?>
                <table class="table table-sm mb-0" style="font-size:11px;">
                  <?php foreach($most_punctual as $i=>$e): ?>
                  <tr>
                    <td class="pl-3"><span class="badge badge-warning mr-1"><?= $i+1 ?></span><?= htmlspecialchars($e['full_name']) ?></td>
                    <td class="text-right pr-3 text-success font-weight-bold"><?= $e['present_count'] ?>d</td>
                  </tr>
                  <?php endforeach; ?>
                </table>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <!-- Most Absent -->
          <div class="col-md-4">
            <div class="card chart-card">
              <div class="card-header py-2">
                <h3 class="card-title" style="font-size:12px;">
                  Most Absent Staff
                </h3>
              </div>
              <div class="card-body p-0">
                <?php if (empty($most_absent)): ?>
                <div class="text-center text-muted py-3" style="font-size:12px;">No absences this month.</div>
                <?php else: ?>
                <table class="table table-sm mb-0" style="font-size:11px;">
                  <?php foreach($most_absent as $i=>$e): ?>
                  <tr>
                    <td class="pl-3"><span class="badge badge-danger mr-1"><?= $i+1 ?></span><?= htmlspecialchars($e['full_name']) ?></td>
                    <td class="text-right pr-3 text-danger font-weight-bold"><?= $e['absent_count'] ?>x</td>
                  </tr>
                  <?php endforeach; ?>
                </table>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /.col-md-8 LEFT -->

      <!-- ── RIGHT 30%: Actions + Calendar + Announcements + Alerts ── -->
      <div class="col-md-4">

        <!-- Quick Actions (School-Focused) -->
        <div class="card chart-card">
          <div class="card-header py-2">
            <h3 class="card-title" style="font-size:13px;">
              Quick Actions
            </h3>
          </div>
          <div class="card-body p-2">
            <div class="row" style="margin:0;gap:0;">
              <?php
              $qa2 = array(
                array('add_employee.php?type=teaching','fa-user-plus','Add Teacher','primary'),
                array('employee_requests.php','fa-check-circle','Approve Request','success'),
                array('reports.php?type=faculty','fa-chart-bar','Faculty Report','info'),
                array('attendance.php','fa-clock','Fix Attendance','warning'),
                array('teaching_load.php','fa-chalkboard-teacher','Teaching Loads','purple'),
                array('payroll.php','fa-money-bill-wave','Run Payroll','dark'),
              );
              foreach($qa2 as $q): ?>
              <div class="col-4 mb-2 px-1">
                <a href="<?= $q[0] ?>" class="d-flex flex-column align-items-center justify-content-center p-2
                          rounded text-decoration-none"
                   style="border:2px solid <?= $q[2]==='Teaching Loads'?'#6f42c1':'' ?>;
                          height:62px;transition:.15s;background:#f8f9fa;"
                   onmouseover="this.style.background='#fff';this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 10px rgba(0,0,0,.1)'"
                   onmouseout="this.style.background='#f8f9fa';this.style.transform='';this.style.boxShadow=''">
                  <span style="font-size:10px;font-weight:600;color:#495057;margin-top:4px;text-align:center;
                               line-height:1.2;"><?= $q[2] ?></span>
                </a>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>



        <!-- B. Calendar with Notes -->
        <div class="card card-outline card-info">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <h3 class="card-title" style="font-size:13px;">
              <span id="calMonthLabel"></span>
            </h3>
            <div class="d-flex" style="gap:3px;">
              <button class="btn btn-xs btn-outline-secondary" onclick="changeMonth(-1)">&#8249;</button>
              <button class="btn btn-xs btn-outline-secondary" onclick="changeMonth(0)">Today</button>
              <button class="btn btn-xs btn-outline-secondary" onclick="changeMonth(1)">&#8250;</button>
            </div>
          </div>
          <div class="card-body p-2">
            <div class="cal-grid mb-1">
              <?php foreach(array('Su','Mo','Tu','We','Th','Fr','Sa') as $dh): ?>
              <div style="text-align:center;font-size:10px;font-weight:700;color:#6c757d;padding:2px 0;"><?= $dh ?></div>
              <?php endforeach; ?>
            </div>
            <div class="cal-grid" id="calGrid"></div>
          </div>
          <div class="card-footer p-1">
            <button class="btn btn-xs btn-outline-info btn-block" onclick="openNoteModal(null,null)">
              Add Note
            </button>
          </div>
        </div>

        <!-- C. Announcements -->
        <div class="card card-outline card-warning">
          <div class="card-header py-2">
            <h3 class="card-title" style="font-size:13px;">
              Announcements
            </h3>
          </div>
          <div class="card-body p-2" style="max-height:280px;overflow-y:auto;">
            <?php if (empty($announcements)): ?>
              <div class="text-center text-muted py-3" style="font-size:12px;">No announcements.</div>
            <?php else: ?>
              <?php foreach($announcements as $ann):
                $pc = isset($priority_colors[$ann['priority']]) ? $priority_colors[$ann['priority']] : 'secondary';
                $bc = isset($border_hex[$pc]) ? $border_hex[$pc] : '#6c757d';
              ?>
              <div class="ann-item" style="border-color:<?= $bc ?>;">
                <div class="d-flex justify-content-between align-items-start">
                  <strong style="font-size:12px;"><?= htmlspecialchars($ann['title']) ?></strong>
                  <span class="badge badge-<?= $pc ?> ml-1" style="font-size:9px;">
                    <?= htmlspecialchars(isset($ann['priority'])?$ann['priority']:'Normal') ?>
                  </span>
                </div>
                <div style="font-size:11px;color:#555;margin-top:2px;line-height:1.4;">
                  <?= nl2br(htmlspecialchars(substr($ann['body'],0,100))) ?><?= strlen($ann['body'])>100?'...':'' ?>
                </div>
                <div style="font-size:10px;color:#adb5bd;margin-top:3px;">
                  <?= date('M d, Y',strtotime($ann['created_at'])) ?>
                  <?php if (!empty($ann['posted_by_name'])): ?>&middot; <?= htmlspecialchars($ann['posted_by_name']) ?><?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="card-footer p-1">
            <a href="announcements.php" class="btn btn-xs btn-warning btn-block">
              Post Announcement
            </a>
          </div>
        </div>

        <!-- D. HR Alerts -->
        <div class="card card-outline card-danger">
          <div class="card-header py-2">
            <h3 class="card-title" style="font-size:13px;">
              HR Alerts
            </h3>
          </div>
          <div class="card-body p-2">
            <?php foreach($alerts as $al): ?>
            <div class="alert-item" style="border-color:<?= $al['type']==='danger'?'#dc3545':($al['type']==='warning'?'#ffc107':($al['type']==='info'?'#17a2b8':'#28a745')) ?>;background:<?= $al['type']==='danger'?'#fff5f5':($al['type']==='warning'?'#fffdf0':($al['type']==='info'?'#f0faff':'#f0fff4')) ?>;">
              <?= htmlspecialchars($al['msg']) ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /.col-md-4 RIGHT -->
    </div><!-- /.row main layout -->

  </div></div>
</div>

<!-- Note Modal -->
<div class="modal fade" id="noteModal" tabindex="-1">
  <div class="modal-dialog" style="max-width:370px;">
    <div class="modal-content">
      <div class="modal-header bg-info text-white py-2">
        <h6 class="modal-title mb-0"><span id="noteModalTitle">Note</span></h6>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="note_action" value="save_note">
        <input type="hidden" name="note_id" id="noteId" value="0">
        <div class="modal-body p-3">
          <div class="row">
            <div class="col-6">
              <div class="form-group mb-2">
                <label style="font-size:11px;font-weight:600;">Date *</label>
                <input type="date" name="note_date" id="noteDate" class="form-control form-control-sm" required>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group mb-2">
                <label style="font-size:11px;font-weight:600;">End Date <small class="text-muted">(range)</small></label>
                <input type="date" name="note_date_end" id="noteDateEnd" class="form-control form-control-sm">
              </div>
            </div>
          </div>
          <div class="form-group mb-2">
            <label style="font-size:11px;font-weight:600;">Note *</label>
            <textarea name="note_text" id="noteText" class="form-control form-control-sm" rows="3" required></textarea>
          </div>
          <div class="form-group mb-0">
            <label style="font-size:11px;font-weight:600;">Color</label>
            <div class="d-flex" style="gap:8px;margin-top:4px;">
              <?php foreach(array('primary'=>'#007bff','success'=>'#28a745','warning'=>'#ffc107','danger'=>'#dc3545','info'=>'#17a2b8','dark'=>'#343a40') as $cn=>$ch): ?>
              <label style="cursor:pointer;margin:0;">
                <input type="radio" name="note_color" value="<?= $cn ?>" class="sr-only note-color-radio" <?= $cn==='primary'?'checked':'' ?>>
                <span class="note-color-swatch" style="background:<?= $ch ?>;"></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="modal-footer p-2 d-flex justify-content-between">
          <button type="button" class="btn btn-xs btn-outline-danger" id="deleteNoteBtn" style="display:none;" onclick="deleteNote()">
            Delete
          </button>
          <div class="ml-auto d-flex" style="gap:6px;">
            <button type="button" class="btn btn-xs btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-xs btn-info">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
<form method="POST" id="deleteNoteForm" style="display:none;">
  <?php csrf_field(); ?>
  <input type="hidden" name="note_action" value="delete_note">
  <input type="hidden" name="note_id" id="deleteNoteId" value="">
</form>

<?php include '../includes/footer.php'; ?>
<script>
var _delta    = <?php echo (int)$_hrms_delta; ?>;
var _today    = '<?php echo $today; ?>';
var _allNotes = <?php echo $all_notes_json; ?>;
var _calY     = <?php echo (int)$cal_y_init; ?>;
var _calM     = <?php echo (int)$cal_m_init - 1; ?>;



// Chart.js defaults
Chart.defaults.font.family = "'Source Sans Pro','Helvetica Neue',Helvetica,Arial,sans-serif";
Chart.defaults.font.size   = 11;

// A. Teaching vs Non-Teaching Attendance Trend
var trendLabels    = <?php echo json_encode($trend_days); ?>;
var trendTeachP    = <?php echo json_encode($trend_teach_p); ?>;
var trendNonP      = <?php echo json_encode($trend_nonteach_p); ?>;
var trendTeachA    = <?php echo json_encode($trend_teach_a); ?>;
var trendNonA      = <?php echo json_encode($trend_nonteach_a); ?>;
new Chart(document.getElementById('attendanceTrendChart'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [
            {label:'Teaching — Present',     data:trendTeachP, borderColor:'#007bff',
             backgroundColor:'rgba(0,123,255,.08)', borderWidth:2.5, pointRadius:3, fill:true, tension:.35},
            {label:'Non-Teaching — Present', data:trendNonP,   borderColor:'#17a2b8',
             backgroundColor:'rgba(23,162,184,.06)', borderWidth:2, pointRadius:3, fill:true, tension:.35,
             borderDash:[5,3]},
            {label:'Teaching — Absent',      data:trendTeachA, borderColor:'#dc3545',
             backgroundColor:'rgba(220,53,69,.07)', borderWidth:2, pointRadius:3, fill:false, tension:.35},
            {label:'Non-Teaching — Absent',  data:trendNonA,   borderColor:'#fd7e14',
             backgroundColor:'transparent', borderWidth:1.5, pointRadius:2, fill:false, tension:.35,
             borderDash:[4,4]},
        ]
    },
    options: {
        responsive:true,
        plugins:{ legend:{position:'top', labels:{boxWidth:14,padding:10,font:{size:10}}} },
        scales:{
            y:{ beginAtZero:true, grid:{color:'rgba(0,0,0,.05)'}, ticks:{font:{size:10}} },
            x:{ grid:{display:false}, ticks:{font:{size:10}, maxRotation:45} }
        }
    }
});

// B. Dept Chart
var deptLabels = <?php echo json_encode(array_column($dept_data,'name')); ?>;
var deptCounts = <?php echo json_encode(array_column($dept_data,'cnt')); ?>;
var deptColors = ['#007bff','#28a745','#ffc107','#dc3545','#17a2b8','#6f42c1','#fd7e14','#20c997'];
new Chart(document.getElementById('deptChart'), {
    type: 'bar',
    data: {
        labels: deptLabels,
        datasets:[{label:'Employees', data:deptCounts,
                   backgroundColor:deptColors.slice(0,deptLabels.length), borderRadius:4}]
    },
    options:{ responsive:true, indexAxis:'y',
              plugins:{legend:{display:false}},
              scales:{ x:{beginAtZero:true,grid:{color:'rgba(0,0,0,.05)'}}, y:{grid:{display:false}} } }
});

// C. Leave Chart
<?php if (!empty($leave_data)): ?>
var leaveLabels = <?php echo json_encode(array_column($leave_data,'leave_type')); ?>;
var leaveDays   = <?php echo json_encode(array_column($leave_data,'total_days')); ?>;
var leaveColors = ['#007bff','#28a745','#ff9800','#dc3545','#6f42c1','#17a2b8','#fd7e14'];
new Chart(document.getElementById('leaveChart'), {
    type: 'doughnut',
    data: { labels:leaveLabels, datasets:[{data:leaveDays,
             backgroundColor:leaveColors.slice(0,leaveLabels.length), borderWidth:2, hoverOffset:6}] },
    options:{ responsive:true, plugins:{ legend:{position:'bottom',labels:{boxWidth:11,padding:8,font:{size:10}}} } }
});
<?php endif; ?>

// Calendar
var _notesByDate = {};
_allNotes.forEach(function(n){ if(!_notesByDate[n.note_date])_notesByDate[n.note_date]=[];_notesByDate[n.note_date].push(n); });
var noteColors   = {primary:'#007bff',success:'#28a745',warning:'#ffc107',danger:'#dc3545',info:'#17a2b8',dark:'#343a40'};
var monthNames   = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function renderCal(){
    document.getElementById('calMonthLabel').textContent = monthNames[_calM]+' '+_calY;
    var grid=document.getElementById('calGrid'); grid.innerHTML='';
    var first=new Date(_calY,_calM,1).getDay();
    var dim=new Date(_calY,_calM+1,0).getDate();
    var dip=new Date(_calY,_calM,0).getDate();
    for(var i=first-1;i>=0;i--) grid.appendChild(makeCell(_calY,_calM-1,dip-i,true));
    for(var d=1;d<=dim;d++)     grid.appendChild(makeCell(_calY,_calM,d,false));
    var total=first+dim; var rem=total%7===0?0:7-(total%7);
    for(var d2=1;d2<=rem;d2++)  grid.appendChild(makeCell(_calY,_calM+1,d2,true));
}
function makeCell(y,m,d,other){
    var mo=((m%12)+12)%12; var yr=y+Math.floor(m/12);
    var ds=yr+'-'+String(mo+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');
    var cell=document.createElement('div');
    cell.className='cal-cell'+(other?' other-month':'');
    if(ds===_today) cell.classList.add('today');
    var dn=_notesByDate[ds]||[];
    if(dn.length>0) cell.classList.add('has-note');
    cell.innerHTML='<div class="day-num" style="font-size:11px;font-weight:600;color:#495057;">'+d+'</div>';
    dn.slice(0,1).forEach(function(n){
        var chip=document.createElement('span'); chip.className='cal-note-chip';
        chip.style.background=noteColors[n.color]||'#007bff'; chip.textContent=n.note;
        cell.appendChild(chip);
    });
    if(dn.length>1){ var more=document.createElement('span'); more.style.cssText='font-size:9px;color:#6c757d;';
                     more.textContent='+'+( dn.length-1)+' more'; cell.appendChild(more); }
    cell.onclick=function(){ openNoteModal(ds,dn[0]||null); };
    return cell;
}
function changeMonth(dir){
    if(dir===0){ window.location.href='dashboard.php'; return; }
    _calM+=dir;
    if(_calM>11){_calM=0;_calY++;} if(_calM<0){_calM=11;_calY--;}
    window.location.href='dashboard.php?cal_y='+_calY+'&cal_m='+(_calM+1);
}

// Note modal
function openNoteModal(ds,en){
    document.getElementById('noteId').value      = en?en.id:'0';
    document.getElementById('noteDate').value    = ds||_today;
    document.getElementById('noteDateEnd').value = (en&&en.note_date_end)?en.note_date_end:'';
    document.getElementById('noteText').value    = en?en.note:'';
    document.getElementById('noteModalTitle').textContent = ds?'Note — '+ds:'Add Note';
    document.getElementById('deleteNoteBtn').style.display = en?'inline-flex':'none';
    var col=en?en.color:'primary';
    document.querySelectorAll('.note-color-radio').forEach(function(r){
        r.checked=(r.value===col); r.nextElementSibling.style.borderColor=r.checked?'#333':'transparent';
    });
    $('#noteModal').modal('show');
}
function deleteNote(){
    if(!confirm('Delete this note?')) return;
    document.getElementById('deleteNoteId').value=document.getElementById('noteId').value;
    document.getElementById('deleteNoteForm').submit();
}
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.note-color-radio').forEach(function(r){
        r.addEventListener('change',function(){
            document.querySelectorAll('.note-color-swatch').forEach(function(s){s.style.borderColor='transparent';});
            if(this.checked) this.nextElementSibling.style.borderColor='#333';
        });
        if(r.checked) r.nextElementSibling.style.borderColor='#333';
    });
    renderCal();
});
</script>
</body>
</html>