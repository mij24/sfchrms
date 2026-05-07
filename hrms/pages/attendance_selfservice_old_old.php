<?php
// ============================================
// FILE: pages/attendance_selfservice.php
// Multi-period Clock In/Out AJAX handler:
//   am_in  = Morning clock-in
//   am_out = Lunch break clock-out
//   pm_in  = After-lunch return (must be before 1:00 PM)
//   pm_out = End-of-day clock-out
// Non-teaching staff may skip AM Out/PM In
// Late PM return flagged if PM In > 1:00 PM
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';

$user   = db_fetch($pdo, "SELECT * FROM users WHERE id=?", array((int)$_SESSION['user_id']));
$emp_id = $user ? (int)$user['employee_id'] : 0;

if (!$emp_id) {
    if (isset($_POST['ajax_clock'])) {
        header('Content-Type: application/json');
        echo json_encode(array('ok'=>false,'msg'=>'Account not linked to employee record.'));
        exit;
    }
    echo not_linked_error();
    exit;
}

// ── Settings ────────────────────────────────────────────────────
$shift_start = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='shift_start_default'") ?: '08:00:00';
$shift_end   = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='shift_end_default'")   ?: '17:00:00';
$grace_mins  = (int)(db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key='grace_minutes'") ?: 15);
$pm_cutoff   = '13:00:00'; // 1:00 PM hard cutoff for PM return

$today    = date('Y-m-d');
$now_time = date('H:i:s');
$now_dt   = date('Y-m-d H:i:s');

// ── AJAX Handler ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_clock'])) {
    header('Content-Type: application/json');
    csrf_verify();

    $action      = clean_string($_POST['action'] ?? '');
    $lat         = isset($_POST['lat'])      ? (float)$_POST['lat']      : null;
    $lng         = isset($_POST['lng'])      ? (float)$_POST['lng']      : null;
    $accuracy    = isset($_POST['accuracy']) ? (float)$_POST['accuracy'] : null;
    $address     = isset($_POST['address'])  ? clean_string($_POST['address'],255) : null;
    $device_info = isset($_POST['device'])   ? clean_string($_POST['device'],255)  : null;
    $ip          = $_SERVER['REMOTE_ADDR'];

    // Re-fetch attendance for today
    $att = db_fetch($pdo,
        "SELECT * FROM attendance WHERE employee_id=? AND attendance_date=?",
        array($emp_id, $today)
    );

    // ── Save selfie ────────────────────────────────────────────
    $photo_path = null;
    if (!empty($_POST['photo_b64'])) {
        $b64 = $_POST['photo_b64'];
        if (strpos($b64,',') !== false) $b64 = explode(',',$b64,2)[1];
        $img = base64_decode($b64);
        if ($img) {
            $dir = '../uploads/mobile_selfie/';
            if (!is_dir($dir)) mkdir($dir,0755,true);
            $fname = 'selfie_'.$emp_id.'_'.time().'.jpg';
            file_put_contents($dir.$fname, $img);
            $photo_path = $fname;
        }
    }

    // ── Log helper ─────────────────────────────────────────────
    function write_log($pdo,$emp_id,$label,$now_dt,$photo_path,$lat,$lng,$accuracy,$address,$device_info,$ip,$att_id) {
        try {
            db_run($pdo,
                "INSERT INTO mobile_attendance_log
                 (employee_id,action,log_datetime,photo_path,latitude,longitude,
                  accuracy_meters,address_text,device_info,ip_address,linked_attendance_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                array($emp_id,$label,$now_dt,$photo_path,$lat,$lng,$accuracy,$address,$device_info,$ip,$att_id)
            );
        } catch (Exception $e) {}
    }

    // Detect if pm columns exist in attendance table
    $has_pm = false;
    try {
        $cols = db_fetchall($pdo,"SHOW COLUMNS FROM attendance LIKE 'pm_time_in'");
        $has_pm = !empty($cols);
    } catch (Exception $e) { $has_pm = false; }

    // ──────────────────────────────────────────────────────────
    // AM CLOCK IN  (actions: am_in | in)
    // ──────────────────────────────────────────────────────────
    if ($action === 'am_in' || $action === 'in') {

        if ($att && $att['time_in'] && $att['time_in'] !== '00:00:00') {
            echo json_encode(array('ok'=>false,
                'msg'=>'Already clocked in this morning at '.date('h:i A',strtotime($att['time_in'])).'.'));
            exit;
        }

        $shift_ts = strtotime($today.' '.$shift_start);
        $grace_ts = $shift_ts + ($grace_mins * 60);
        $in_ts    = strtotime($today.' '.$now_time);
        $tardy    = ($in_ts > $grace_ts) ? (int)round(($in_ts - $shift_ts)/60) : 0;
        $status   = ($in_ts > $grace_ts) ? 'Late' : 'Present';

        if (!$att) {
            db_run($pdo,
                "INSERT INTO attendance (employee_id,attendance_date,time_in,status,tardiness_minutes) VALUES (?,?,?,?,?)",
                array($emp_id,$today,$now_time,$status,$tardy)
            );
            $att_id = (int)db_value($pdo,"SELECT id FROM attendance WHERE employee_id=? AND attendance_date=?",array($emp_id,$today));
        } else {
            db_run($pdo,
                "UPDATE attendance SET time_in=?,status=?,tardiness_minutes=? WHERE id=?",
                array($now_time,$status,$tardy,$att['id'])
            );
            $att_id = $att['id'];
        }

        write_log($pdo,$emp_id,'AM Clock In',$now_dt,$photo_path,$lat,$lng,$accuracy,$address,$device_info,$ip,$att_id);

        $msg = $status === 'Late'
            ? 'AM Clock In at '.date('h:i A').' — Late by '.$tardy.' min(s). Shift started '.date('h:i A',strtotime($shift_start)).'.'
            : 'Good '.date('A').'! AM Clock In at '.date('h:i A').'.';
        echo json_encode(array('ok'=>true,'msg'=>$msg,'status'=>$status,'time'=>date('h:i A')));
        exit;
    }

    // ──────────────────────────────────────────────────────────
    // AM CLOCK OUT  (lunch break)  actions: am_out
    // ──────────────────────────────────────────────────────────
    if ($action === 'am_out') {

        if (!$att || !$att['time_in'] || $att['time_in'] === '00:00:00') {
            echo json_encode(array('ok'=>false,'msg'=>'No AM Clock In found for today. Please clock in first.'));
            exit;
        }
        if ($att['time_out'] && $att['time_out'] !== '00:00:00') {
            echo json_encode(array('ok'=>false,
                'msg'=>'AM Clock Out already recorded at '.date('h:i A',strtotime($att['time_out'])).'.'));
            exit;
        }

        db_run($pdo,"UPDATE attendance SET time_out=? WHERE id=?",array($now_time,$att['id']));
        write_log($pdo,$emp_id,'AM Clock Out',$now_dt,$photo_path,$lat,$lng,$accuracy,$address,$device_info,$ip,$att['id']);

        echo json_encode(array('ok'=>true,
            'msg'=>'Lunch break started. Clock Out at '.date('h:i A').'. Please return before 1:00 PM.',
            'time'=>date('h:i A')));
        exit;
    }

    // ──────────────────────────────────────────────────────────
    // PM CLOCK IN  (return from lunch; must be before 1:00 PM)
    // ──────────────────────────────────────────────────────────
    if ($action === 'pm_in') {

        if (!$att) {
            echo json_encode(array('ok'=>false,'msg'=>'No attendance record for today. Complete AM Clock In first.'));
            exit;
        }

        if ($has_pm && $att['pm_time_in'] && $att['pm_time_in'] !== '00:00:00') {
            echo json_encode(array('ok'=>false,
                'msg'=>'PM Clock In already recorded at '.date('h:i A',strtotime($att['pm_time_in'])).'.'));
            exit;
        }

        $pm_in_ts  = strtotime($today.' '.$now_time);
        $cutoff_ts = strtotime($today.' '.$pm_cutoff);
        $pm_late   = ($pm_in_ts > $cutoff_ts);
        $pm_late_m = $pm_late ? (int)round(($pm_in_ts - $cutoff_ts)/60) : 0;

        if ($has_pm) {
            $pm_status = $pm_late ? 'Late Return' : 'On Time';
            db_run($pdo,
                "UPDATE attendance SET pm_time_in=?, pm_status=? WHERE id=?",
                array($now_time, $pm_status, $att['id'])
            );
        } else {
            // No pm columns — store in remarks
            $rem  = ($att['remarks'] ? $att['remarks'].'; ' : '');
            $rem .= 'PM In: '.$now_time.($pm_late?' [LATE RETURN +'.  $pm_late_m.'min]':'');
            db_run($pdo,"UPDATE attendance SET remarks=? WHERE id=?",array($rem,$att['id']));
        }

        write_log($pdo,$emp_id,'PM Clock In',$now_dt,$photo_path,$lat,$lng,$accuracy,$address,$device_info,$ip,$att['id']);

        if ($pm_late) {
            $msg = 'PM Clock In at '.date('h:i A').' — '.$pm_late_m.' minute(s) late from 1:00 PM cut-off. '
                  .'This is flagged. You may file a Request to justify the late return.';
        } else {
            $msg = 'Welcome back! PM Clock In at '.date('h:i A').'.';
        }
        echo json_encode(array('ok'=>true,'msg'=>$msg,'pm_late'=>$pm_late,'time'=>date('h:i A')));
        exit;
    }

    // ──────────────────────────────────────────────────────────
    // PM CLOCK OUT  (end of day)  actions: pm_out | out
    // ──────────────────────────────────────────────────────────
    if ($action === 'pm_out' || $action === 'out') {

        if (!$att) {
            echo json_encode(array('ok'=>false,'msg'=>'No attendance record for today.'));
            exit;
        }

        if ($has_pm) {
            if ($att['pm_time_out'] && $att['pm_time_out'] !== '00:00:00') {
                echo json_encode(array('ok'=>false,
                    'msg'=>'PM Clock Out already recorded at '.date('h:i A',strtotime($att['pm_time_out'])).'.'));
                exit;
            }
            $shift_end_ts = strtotime($today.' '.$shift_end);
            $out_ts       = strtotime($today.' '.$now_time);
            $ot_hrs       = max(0, round(($out_ts - $shift_end_ts)/3600, 2));
            db_run($pdo,
                "UPDATE attendance SET pm_time_out=?, overtime_hours=? WHERE id=?",
                array($now_time, $ot_hrs, $att['id'])
            );
        } else {
            // No pm columns — update time_out for legacy
            if ($att['time_out'] && $att['time_out'] !== '00:00:00'
                && !strpos($att['remarks']??'','PM In:')) {
                echo json_encode(array('ok'=>false,
                    'msg'=>'Already clocked out at '.date('h:i A',strtotime($att['time_out'])).'.'));
                exit;
            }
            $shift_end_ts = strtotime($today.' '.$shift_end);
            $out_ts       = strtotime($today.' '.$now_time);
            $ot_hrs       = max(0, round(($out_ts - $shift_end_ts)/3600, 2));
            $rem  = ($att['remarks'] ? $att['remarks'].'; ' : '');
            $rem .= 'PM Out: '.$now_time;
            db_run($pdo,
                "UPDATE attendance SET time_out=?, overtime_hours=?, remarks=? WHERE id=?",
                array($now_time, $ot_hrs, $rem, $att['id'])
            );
        }

        write_log($pdo,$emp_id,'PM Clock Out',$now_dt,$photo_path,$lat,$lng,$accuracy,$address,$device_info,$ip,$att['id']);

        $msg  = 'PM Clock Out at '.date('h:i A').'. ';
        $msg .= $ot_hrs > 0
            ? 'Overtime: '.$ot_hrs.'h recorded. Great work today!'
            : 'Have a good rest!';
        echo json_encode(array('ok'=>true,'msg'=>$msg,'overtime'=>$ot_hrs,'time'=>date('h:i A')));
        exit;
    }

    echo json_encode(array('ok'=>false,'msg'=>'Unknown clock action: '.htmlspecialchars($action)));
    exit;
}

// ── Direct access: redirect to my_attendance.php ────────────────
header('Location: my_attendance.php');
exit;