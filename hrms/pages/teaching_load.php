<?php

// ============================================
// FILE: pages/teaching_load.php
// Teaching Load Management — v2
// Flow: Draft → Faculty → Dept Head →
//   Registrar Verification → VP Academic
//   (+ Exec Sec logbook) → President
//   (+ Exec Sec logbook) → HR Copy
// Multi-subject support via teaching_load_subjects
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

// ── Safe migration check: new tables/columns ─────────────────────
$tl_subjects_exists = false;
try {
    $pdo->query("SELECT 1 FROM teaching_load_subjects LIMIT 1");
    $tl_subjects_exists = true;
} catch (Exception $e) { $tl_subjects_exists = false; }

$tl_new_cols = false;
try {
    $pdo->query("SELECT total_units FROM teaching_loads LIMIT 1");
    $tl_new_cols = true;
} catch (Exception $e) { $tl_new_cols = false; }
// ── END migration check ───────────────────────────────────────────


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
    1 => 'Created by Dept. Secretary',
    2 => 'Faculty Confirmation',
    3 => 'Department Head Review',
    4 => 'Registrar Verification',
    5 => 'VP for Academic Affairs',
    6 => 'President Approval',
    7 => 'HR Copy Received',
);

$status_badge = array(
    'Draft'                       => 'secondary',
    'For Faculty Review'          => 'info',
    'Faculty Confirmed'           => 'primary',
    'For Dept Head Review'        => 'warning',
    'Dept Head Approved'          => 'primary',
    'For Registrar Verification'  => 'warning',
    'Registrar Verified'          => 'primary',
    'For VP Academic Review'      => 'warning',
    'VP Academic Recommended'     => 'primary',
    'For President Approval'      => 'warning',
    'President Approved'          => 'primary',
    'Approved'                    => 'success',
    'Rejected'                    => 'danger',
    'Returned'                    => 'warning',
);

// ── Logbook helper: auto-create incoming entry ───────────────────
function _tl_trigger_logbook(PDO $pdo, $load, $office, $uid, $col) {
    if (!empty($load[$col])) return;
    $prefix_map = array('VP Academic'=>'VPAA','President'=>'PRES');
    $prefix = isset($prefix_map[$office]) ? $prefix_map[$office] : 'PRES';
    $year   = (int)date('Y');
    $pdo->prepare("INSERT INTO document_control_counter (office,year,counter) VALUES (?,?,1) ON DUPLICATE KEY UPDATE counter=counter+1")
        ->execute(array($prefix,$year));
    $counter    = (int)db_value($pdo,"SELECT counter FROM document_control_counter WHERE office=? AND year=?",array($prefix,$year));
    $ctrl       = $prefix.'-'.$year.'-'.str_pad($counter,4,'0',STR_PAD_LEFT);
    $emp        = db_fetch($pdo,"SELECT e.full_name,e.employee_id,d.name AS dept FROM employees e LEFT JOIN departments d ON e.department_id=d.id WHERE e.id=?",array($load['employee_id']));
    $tl_reference = 'TL-'.$load['id'];
    $doc_type   = 'Teaching Load';
    db_run($pdo,
        "INSERT INTO document_logbook
         (control_number,request_id,teaching_load_id,reference_no,office,
          document_type,document_category,
          requestor_name,requestor_emp_id,requestor_dept,requestor_employee_id,
          date_received,received_from,incoming_remarks,
          incoming_logged_by,incoming_logged_at,exec_action,status)
         VALUES (?,NULL,?,?,?,?,?,?,?,?,?,NOW(),'System - teaching load workflow',
                 ?,?,NOW(),'Pending','Incoming')",
        array($ctrl,$load['id'],$tl_reference,$office,$doc_type,'Teaching Load',
              $emp?$emp['full_name']:'',$emp?$emp['employee_id']:'',$emp?$emp['dept']:'',$load['employee_id'],
              'Auto-routed from Teaching Load approval',$uid)
    );
    $log_id = db_lastid($pdo);
    db_run($pdo,"UPDATE teaching_loads SET {$col}=? WHERE id=?",array($log_id,$load['id']));
}
// ── Logbook helper: mark outgoing on existing entry ──────────────
function _tl_log_outgoing(PDO $pdo, $load, $office, $uid) {
    $col_map = array('VP Academic'=>'vp_logbook_id','President'=>'pres_logbook_id');
    $col     = isset($col_map[$office]) ? $col_map[$office] : null;
    if (!$col || empty($load[$col])) return;
    $logbook_id = (int)$load[$col];
    if (!$logbook_id) return;
    db_run($pdo,
        "UPDATE document_logbook SET
         date_released=NOW(), release_method='System Notification',
         status='Released', outgoing_logged_by=?, outgoing_logged_at=NOW(),
         outgoing_remarks='Auto-logged: executive signed and forwarded'
         WHERE id=?",
        array($uid, $logbook_id)
    );
}

// ── POST handling ─────────────────────────────────────────────────

// ── Conflict detection helpers (defined outside POST block) ────────
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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $post_action = isset($_POST['post_action']) ? trim($_POST['post_action']) : '';

    // ── CREATE / EDIT teaching load ──────────────────────────────
    if ($post_action === 'create' || $post_action === 'edit') {
        if (!is_secretary()) { prg_redirect('teaching_load.php','Access denied.'); }

        $edit_id  = ($post_action === 'edit') ? (int)$_POST['load_id'] : 0;
        $emp_id   = (int)$_POST['employee_id'];

        if ($emp_id < 1) {
            prg_redirect_error('teaching_load.php', 'Please select an employee from the search field.');
        }

        // If new multi-subject table not yet created, block create and tell admin
        if (!$tl_subjects_exists) {
            prg_redirect_error('teaching_load.php',
                'Database not yet updated. Please run tl_schema_v2.sql in phpMyAdmin first.');
        }

        // Multi-subject: parse subjects[] array
        $subjects_raw = isset($_POST['subjects']) && is_array($_POST['subjects']) ? $_POST['subjects'] : array();
        $subjects = array();
        foreach ($subjects_raw as $si => $subj) {
            $desc = clean_string((isset($subj['description']) ? $subj['description'] : ''), 200);
            if (empty($desc)) continue;
            $days_val = clean_string((isset($subj['days']) ? $subj['days'] : ''), 50);
            $ts = clean_string((isset($subj['time_start']) ? $subj['time_start'] : ''), 10);
            $te = clean_string((isset($subj['time_end']) ? $subj['time_end'] : ''), 10);
            if (!$days_val || !$ts || !$te) continue;
            $subjects[] = array(
                'course_no'   => clean_string((isset($subj['course_no']) ? $subj['course_no'] : ''), 30),
                'description' => $desc,
                'units'       => (float)((isset($subj['units']) ? $subj['units'] : 3)),
                'hours'       => (float)((isset($subj['hours']) ? $subj['hours'] : 3)),
                'students'    => (int)((isset($subj['students']) ? $subj['students'] : 0)),
                'days'        => $days_val,
                'time_start'  => $ts,
                'time_end'    => $te,
                'room'        => clean_string((isset($subj['room']) ? $subj['room'] : ''), 50),
            );
        }

        if (empty($subjects)) {
            prg_redirect_error('teaching_load.php', 'Please add at least one subject to the teaching load.');
        }

        // Shared fields
        $program  = clean_string((isset($_POST['student_program']) ? $_POST['student_program'] : ''), 150);
        $section  = clean_string((isset($_POST['section']) ? $_POST['section'] : ''), 50);
        $level    = clean_string((isset($_POST['level']) ? $_POST['level'] : 'College'), 50);
        $sy       = clean_string((isset($_POST['school_year']) ? $_POST['school_year'] : $cur_sy));
        $sem      = clean_string((isset($_POST['semester']) ? $_POST['semester'] : $cur_sem));
        $has_offset = !empty($_POST['has_offset']) ? 1 : 0;
        $off_start  = $has_offset ? clean_string((isset($_POST['offset_time_start']) ? $_POST['offset_time_start'] : ''), 10) : null;
        $off_end    = $has_offset ? clean_string((isset($_POST['offset_time_end']) ? $_POST['offset_time_end'] : ''), 10)   : null;
        $off_notes  = $has_offset ? clean_string((isset($_POST['offset_notes']) ? $_POST['offset_notes'] : ''), 500)     : null;

        // Conflict detection across all subjects
        $existing_loads = db_fetchall($pdo,
            "SELECT tl.*, s.day_of_week AS s_days, s.time_start AS s_ts, s.time_end AS s_te, s.room AS s_room
             FROM teaching_loads tl
             JOIN teaching_load_subjects s ON s.load_id=tl.id
             WHERE tl.school_year=? AND tl.semester=?
               AND tl.status NOT IN ('Rejected','Returned')
               AND tl.id != ?",
            array($sy, $sem, $edit_id ?: 0)
        );

        $conflict_msgs = array();
        foreach ($subjects as $subj) {
            foreach ($existing_loads as $ex) {
                if (!days_overlap($subj['days'], $ex['s_days'])) continue;
                if (!time_overlap($subj['time_start'],$subj['time_end'],$ex['s_ts'],$ex['s_te'])) continue;
                if ($subj['room'] && $ex['s_room'] && strtolower(trim($subj['room'])) === strtolower(trim($ex['s_room']))) {
                    $conflict_msgs[] = 'Room conflict: Room "'.$subj['room'].'" is already booked for "'
                        .htmlspecialchars((isset($ex['description']) ? $ex['description'] : $ex)['subject_title'])
                        .'" on '.$ex['s_days'].' '
                        .date('h:i A',strtotime($ex['s_ts'])).'–'.date('h:i A',strtotime($ex['s_te'])).' ('.$ex['status'].')';
                }
                if ($ex['employee_id'] == $emp_id) {
                    $tname = db_value($pdo,"SELECT full_name FROM employees WHERE id=?",array($emp_id));
                    $conflict_msgs[] = 'Teacher conflict: '.htmlspecialchars($tname ?: 'This instructor')
                        .' is already scheduled for "'
                        .htmlspecialchars((isset($ex['description']) ? $ex['description'] : $ex)['subject_title'])
                        .'" on '.$ex['s_days'].' '
                        .date('h:i A',strtotime($ex['s_ts'])).'–'.date('h:i A',strtotime($ex['s_te'])).' ('.$ex['status'].')';
                }
            }
        }
        if (!empty($conflict_msgs)) {
            prg_redirect_error('teaching_load.php', implode(' | ', $conflict_msgs));
        }

        // Calculate totals
        $total_units = array_sum(array_column($subjects,'units'));
        $total_hours = array_sum(array_column($subjects,'hours'));
        $total_preps = count($subjects);

        // Use first subject for backward-compat columns on teaching_loads header
        $first = $subjects[0];

        if ($edit_id) {
            // Capture before-state for audit trail
            $before = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($edit_id));
            $before_subjects = $tl_subjects_exists
                ? db_fetchall($pdo,"SELECT * FROM teaching_load_subjects WHERE load_id=? ORDER BY sort_order",array($edit_id))
                : array();

            db_run($pdo,
                "UPDATE teaching_loads SET
                 employee_id=?,student_program=?,section=?,level=?,school_year=?,semester=?,
                 has_offset=?,offset_time_start=?,offset_time_end=?,offset_notes=?,
                 total_units=?,total_hours=?,total_preps=?,
                 subject_title=?,subject_code=?,day_of_week=?,time_start=?,time_end=?,room=?,units=?
                 WHERE id=? AND status IN ('Draft','Returned')",
                array($emp_id,$program,$section,$level,$sy,$sem,
                      $has_offset,$off_start,$off_end,$off_notes,
                      $total_units,$total_hours,$total_preps,
                      $first['description'],$first['course_no'],$first['days'],$first['time_start'],$first['time_end'],$first['room'],$first['units'],
                      $edit_id)
            );
            // Replace subjects
            db_run($pdo,"DELETE FROM teaching_load_subjects WHERE load_id=?",array($edit_id));
            foreach ($subjects as $si => $subj) {
                db_run($pdo,
                    "INSERT INTO teaching_load_subjects (load_id,course_no,description,units,hours,no_of_students,day_of_week,time_start,time_end,room,program,section,level,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    array($edit_id,$subj['course_no'],$subj['description'],$subj['units'],$subj['hours'],$subj['students'],$subj['days'],$subj['time_start'],$subj['time_end'],$subj['room'],$program,$section,$level,$si)
                );
            }
            // Audit trail: log before/after
            audit_log($pdo,'UPDATE','teaching_loads',$edit_id,array(
                'edited_by'       => $_SESSION['username'],
                'subjects_count'  => $total_preps,
                'before_employee' => (isset($before['employee_id']) ? $before['employee_id'] : ''),
                'after_employee'  => $emp_id,
                'before_sy'       => (isset($before['school_year']) ? $before['school_year'] : ''),
                'after_sy'        => $sy,
                'before_sem'      => (isset($before['semester']) ? $before['semester'] : ''),
                'after_sem'       => $sem,
                'before_subjects' => count($before_subjects),
                'after_subjects'  => $total_preps,
                'before_units'    => (isset($before['total_units']) ? $before['total_units'] : (isset($before['units']) ? $before['units'] : 0)),
                'after_units'     => $total_units,
            ));
            prg_redirect('teaching_load.php?view='.$edit_id,'Teaching load updated successfully.');
        } else {
            db_run($pdo,
                "INSERT INTO teaching_loads
                 (school_year,semester,employee_id,student_program,section,level,
                  has_offset,offset_time_start,offset_time_end,offset_notes,
                  total_units,total_hours,total_preps,
                  subject_title,subject_code,day_of_week,time_start,time_end,room,units,
                  status,current_step,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Draft',1,?)",
                array($sy,$sem,$emp_id,$program,$section,$level,
                      $has_offset,$off_start,$off_end,$off_notes,
                      $total_units,$total_hours,$total_preps,
                      $first['description'],$first['course_no'],$first['days'],$first['time_start'],$first['time_end'],$first['room'],$first['units'],
                      $my_uid)
            );
            $new_id = db_lastid($pdo);
            foreach ($subjects as $si => $subj) {
                db_run($pdo,
                    "INSERT INTO teaching_load_subjects (load_id,course_no,description,units,hours,no_of_students,day_of_week,time_start,time_end,room,program,section,level,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    array($new_id,$subj['course_no'],$subj['description'],$subj['units'],$subj['hours'],$subj['students'],$subj['days'],$subj['time_start'],$subj['time_end'],$subj['room'],$program,$section,$level,$si)
                );
            }
            audit_log($pdo,'INSERT','teaching_loads',$new_id,array('action'=>'create','subjects'=>$total_preps));
            prg_redirect('teaching_load.php','Teaching load created with '.$total_preps.' subject(s). Submit for faculty review when ready.');
        }
    } // ── END create/edit handler ──────────────────────────────────

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

        if (!$load || $load['status'] !== 'For Faculty Review' || $load['employee_id'] != $my_emp_id) {
            prg_redirect('teaching_load.php','Access denied or invalid state.');
        }
        db_run($pdo,
            "UPDATE teaching_loads SET status='For Dept Head Review',current_step=3 WHERE id=?",
            array($lid)
        );
        db_run($pdo,
            "INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks)
             VALUES (?,2,?,?,?,'Confirmed — Digitally Signed',?)",
            array($lid,'Faculty Confirmation',$my_uid,$_SESSION['username'],$remarks)
        );
        audit_log($pdo,'DIGITAL_SIGN','teaching_loads',$lid,
            array('action'=>'faculty_confirmed','step'=>'Faculty Confirmation','user'=>$_SESSION['username']));
        prg_redirect('my_inbox.php','Teaching load confirmed and digitally signed. Forwarded to Department Head.');
    }

    // ── DEPT HEAD APPROVE ────────────────────────────────────────
    if ($post_action === 'dept_head_approve') {
        $lid     = (int)$_POST['load_id'];
        $remarks = clean_string((isset($_POST['remarks']) ? $_POST['remarks'] : ''), 500);
        $load    = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        $dh_roles = array('manager','academic_head','non_academic_head');
        if (!$load || $load['status'] !== 'For Dept Head Review'
            || !in_array($my_role, $dh_roles)) {
            prg_redirect('teaching_load.php','Access denied or invalid state.');
        }
        $signer = db_value($pdo,"SELECT e.full_name FROM users u LEFT JOIN employees e ON u.employee_id=e.id WHERE u.id=?",array($my_uid)) ?: $_SESSION['username'];
        db_run($pdo,"UPDATE teaching_loads SET status='For Registrar Verification',current_step=4 WHERE id=?",array($lid));
        db_run($pdo,"INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks) VALUES (?,3,?,?,?,'Noted & Signed',?)",
            array($lid,'Department Head Review',$my_uid,$signer,$remarks));
        audit_log($pdo,'DIGITAL_SIGN','teaching_loads',$lid,array('action'=>'dept_head_noted','signer'=>$signer));
        prg_redirect('teaching_load.php?view='.$lid,'Noted and forwarded to Registrar for verification.');
    }

    // ── REGISTRAR VERIFY ─────────────────────────────────────────
    if ($post_action === 'registrar_verify') {
        $lid     = (int)$_POST['load_id'];
        $remarks = clean_string((isset($_POST['remarks']) ? $_POST['remarks'] : ''), 500);
        $load    = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load || $load['status'] !== 'For Registrar Verification'
            || !in_array($my_role, array('registrar_head','non_academic_head','manager'))) {
            prg_redirect('teaching_load.php','Access denied or invalid state.');
        }
        $signer = db_value($pdo,"SELECT e.full_name FROM users u LEFT JOIN employees e ON u.employee_id=e.id WHERE u.id=?",array($my_uid)) ?: $_SESSION['username'];
        db_run($pdo,"UPDATE teaching_loads SET status='For VP Academic Review',current_step=5 WHERE id=?",array($lid));
        db_run($pdo,"INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks) VALUES (?,4,?,?,?,'Verified & Signed',?)",
            array($lid,'Registrar Verification',$my_uid,$signer,$remarks));
        // Auto-trigger VP Academic logbook incoming entry
        _tl_trigger_logbook($pdo,$load,'VP Academic',$my_uid,'vp_logbook_id');
        audit_log($pdo,'DIGITAL_SIGN','teaching_loads',$lid,array('action'=>'registrar_verified','signer'=>$signer));
        prg_redirect('teaching_load.php?view='.$lid,'Registrar verified. Forwarded to VP for Academic Affairs.');
    }

    // ── VP ACADEMIC RECOMMEND ────────────────────────────────────
    if ($post_action === 'vp_recommend') {
        $lid     = (int)$_POST['load_id'];
        $remarks = clean_string((isset($_POST['remarks']) ? $_POST['remarks'] : ''), 500);
        $load    = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load || $load['status'] !== 'For VP Academic Review' || !in_array($my_role, array('academic_head','vp_academic'))) {
            prg_redirect('teaching_load.php','Access denied or invalid state.');
        }
        $signer = db_value($pdo,"SELECT e.full_name FROM users u LEFT JOIN employees e ON u.employee_id=e.id WHERE u.id=?",array($my_uid)) ?: $_SESSION['username'];
        db_run($pdo,"UPDATE teaching_loads SET status='For President Approval',current_step=6 WHERE id=?",array($lid));
        db_run($pdo,"INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks) VALUES (?,5,?,?,?,'Recommending Approval',?)",
            array($lid,'VP for Academic Affairs',$my_uid,$signer,$remarks));
        // Log outgoing from VP office, trigger President office incoming
        // Re-fetch load to get updated vp_logbook_id after trigger
        $load = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        _tl_log_outgoing($pdo,$load,'VP Academic',$my_uid);
        _tl_trigger_logbook($pdo,$load,'President',$my_uid,'pres_logbook_id');
        audit_log($pdo,'DIGITAL_SIGN','teaching_loads',$lid,array('action'=>'vp_recommended','signer'=>$signer));
        prg_redirect('teaching_load.php?view='.$lid,'VP recommended. Forwarded to President.');
    }

    // ── PRESIDENT APPROVE ────────────────────────────────────────
    if ($post_action === 'president_approve') {
        $lid     = (int)$_POST['load_id'];
        $remarks = clean_string((isset($_POST['remarks']) ? $_POST['remarks'] : ''), 500);
        $load    = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load || $load['status'] !== 'For President Approval' || !has_role(ROLE_PRESIDENT)) {
            prg_redirect('teaching_load.php','Access denied or invalid state.');
        }
        $signer = db_value($pdo,"SELECT e.full_name FROM users u LEFT JOIN employees e ON u.employee_id=e.id WHERE u.id=?",array($my_uid)) ?: $_SESSION['username'];
        db_run($pdo,"UPDATE teaching_loads SET status='Approved',current_step=7 WHERE id=?",array($lid));
        db_run($pdo,"INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks) VALUES (?,6,?,?,?,'Approved',?)",
            array($lid,'President Approval',$my_uid,$signer,$remarks));
        // Re-fetch to get updated pres_logbook_id
        $load = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        _tl_log_outgoing($pdo,$load,'President',$my_uid);
        db_run($pdo,"UPDATE employees SET has_teaching_load=1 WHERE id=?",array($load['employee_id']));
        audit_log($pdo,'DIGITAL_SIGN','teaching_loads',$lid,array('action'=>'president_approved','signer'=>$signer));
        prg_redirect('teaching_load.php?view='.$lid,'Teaching load APPROVED. HR has been notified.');
    }

    // ── HR RECEIVE ───────────────────────────────────────────────
    if ($post_action === 'hr_receive') {
        $lid  = (int)$_POST['load_id'];
        $load = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load || $load['status'] !== 'Approved' || !is_hr()) {
            prg_redirect('teaching_load.php','Access denied or invalid state.');
        }
        db_run($pdo,"INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks) VALUES (?,7,?,?,?,'HR Received','Copy received and filed.')",
            array($lid,'HR Copy',$my_uid,$_SESSION['username']));
        audit_log($pdo,'UPDATE','teaching_loads',$lid,array('action'=>'hr_received'));
        prg_redirect('teaching_load.php?view='.$lid,'HR copy acknowledged.');
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

    // ── DELETE (Draft or Returned — Secretary only) ──────────────
    if ($post_action === 'delete') {
        $lid  = (int)$_POST['load_id'];
        $load = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($lid));
        if (!$load || !in_array($load['status'],array('Draft','Returned')) || !is_secretary()) {
            prg_redirect('teaching_load.php','Cannot delete — only Draft or Returned loads can be deleted.');
        }
        $emp_name = db_value($pdo,"SELECT full_name FROM employees WHERE id=?",array($load['employee_id']));
        audit_log($pdo,'DELETE','teaching_loads',$lid,array(
            'status_at_deletion' => $load['status'],
            'employee'           => $emp_name,
            'subject'            => $load['subject_title'],
            'school_year'        => $load['school_year'],
            'deleted_by'         => $_SESSION['username'],
        ));
        if ($tl_subjects_exists) {
            db_run($pdo,"DELETE FROM teaching_load_subjects WHERE load_id=?",array($lid));
        }
        db_run($pdo,"DELETE FROM teaching_loads WHERE id=?",array($lid));
        prg_redirect('teaching_load.php','Teaching load deleted.');
    }
}

// ── VIEW single record ────────────────────────────────────────────
$view_id   = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1' && is_secretary();
$view_load = null;
$view_trail = array();
if ($view_id) {
    $view_load = db_fetch($pdo,
        "SELECT tl.*, e.full_name, e.employee_id AS emp_code,
                IFNULL(e.is_part_time,0) AS is_part_time,
                d.name AS dept, IFNULL(p.title,'') AS position, '' AS cat_name
         FROM teaching_loads tl
         JOIN employees e ON tl.employee_id=e.id
         LEFT JOIN departments d ON e.department_id=d.id
         LEFT JOIN positions p ON e.position_id=p.id
         WHERE tl.id=?",
        array($view_id)
    );
    try {
        $view_trail = db_fetchall($pdo,
            "SELECT * FROM teaching_load_approvals WHERE load_id=? ORDER BY step,acted_at",
            array($view_id)
        );
    } catch (Exception $e) {
        $view_trail = array(); // table may not exist yet
    }
}

// ── Filter / list ─────────────────────────────────────────────────
$filter_sy    = isset($_GET['sy'])     ? trim($_GET['sy'])     : $cur_sy;
$filter_sem   = isset($_GET['sem'])    ? trim($_GET['sem'])    : '';
$filter_status= isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_emp   = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : 0;

// ── Secretary's department ────────────────────────────────────
$sec_dept_id = 0;
if ($my_emp_id && is_secretary()) {
    $sec_dept_id = (int)db_value($pdo,"SELECT department_id FROM employees WHERE id=?",array($my_emp_id));
}

// Role-based filter
$where  = array("1=1");
$params = array();

if (is_secretary() && $sec_dept_id) {
    // Secretary sees ALL loads for employees in their department
    $where[]  = "e.department_id = ?";
    $params[] = $sec_dept_id;
} elseif (!is_manager() && !is_secretary()) {
    // Plain employee: only sees their own loads
    $where[]  = "tl.employee_id = ?";
    $params[] = $my_emp_id;
}

if ($filter_sy) { $where[] = "tl.school_year=?"; $params[] = $filter_sy; }
if ($filter_sem){ $where[] = "tl.semester=?";     $params[] = $filter_sem; }
if ($filter_status){ $where[] = "tl.status=?";    $params[] = $filter_status; }
if ($filter_emp && (is_secretary() || is_manager())) { $where[] = "tl.employee_id=?"; $params[] = $filter_emp; }

try {
    $loads = db_fetchall($pdo,
        "SELECT tl.*, e.full_name, e.employee_id AS emp_code, d.name AS dept
         FROM teaching_loads tl
         JOIN employees e ON tl.employee_id=e.id
         LEFT JOIN departments d ON e.department_id=d.id
         WHERE ".implode(" AND ",$where)."
         ORDER BY tl.created_at DESC",
        $params
    );
} catch (Exception $e) {
    $loads = array();
    // Show error in flash
    $_SESSION['flash_error'] = 'Database error: '.$e->getMessage();
}



// ── Smart employee list for "Assign To" ───────────────────────
// Loads:
// 1. All teaching-classification employees in secretary's dept
// 2. Part-time employees in the dept
// 3. Employees from OTHER depts who already have a load in this dept
//    for the selected SY/Semester (cross-dept instructors)
// The filter SY/sem values are used here for cross-dept lookup
$_form_sy  = $filter_sy  ?: $cur_sy;
$_form_sem = $filter_sem ?: $cur_sem;

if ($sec_dept_id) {
    try {
    // Employees in the secretary's own department (teaching/faculty)
    $emp_in_dept = db_fetchall($pdo,
        "SELECT DISTINCT e.id, e.full_name, e.employee_id AS emp_code,
                d.name AS dept, '' AS cat_name,
                IF(e.employment_type='Part-Time' OR e.employment_type='Part Time',1,0) AS is_part_time,
                'dept' AS source
         FROM employees e
         LEFT JOIN departments d ON e.department_id=d.id
         WHERE e.employment_status='Active'
           AND e.department_id=?
         ORDER BY e.full_name",
        array($sec_dept_id)
    );
    // Cross-dept instructors: employees from OTHER depts who have loads in this dept's SY/Sem
    $emp_cross = db_fetchall($pdo,
        "SELECT DISTINCT e.id, e.full_name, e.employee_id AS emp_code,
                d.name AS dept, '' AS cat_name,
                IF(e.employment_type='Part-Time' OR e.employment_type='Part Time',1,0) AS is_part_time,
                'cross' AS source
         FROM teaching_loads tl
         JOIN employees e ON tl.employee_id=e.id
         LEFT JOIN departments d ON e.department_id=d.id
         WHERE e.employment_status='Active'
           AND e.department_id != ?
           AND tl.school_year=? AND tl.semester=?
           AND tl.status NOT IN ('Rejected')
         ORDER BY e.full_name",
        array($sec_dept_id, $_form_sy, $_form_sem)
    );
    } catch (Exception $e) { $emp_in_dept = array(); $emp_cross = array(); }
    // Merge and deduplicate by employee id
    $seen_ids = array();
    $all_employees = array();
    foreach (array_merge($emp_in_dept, $emp_cross) as $e) {
        if (!isset($seen_ids[$e['id']])) {
            $seen_ids[$e['id']] = true;
            $all_employees[] = $e;
        }
    }
    usort($all_employees, function($a,$b){ return strcmp($a['full_name'],$b['full_name']); });
} else {
    // Non-secretary or no dept: show all active employees
    $all_employees = db_fetchall($pdo,
        "SELECT e.id, e.full_name, e.employee_id AS emp_code, d.name AS dept,
                '' AS cat_name,
                IF(e.employment_type='Part-Time' OR e.employment_type='Part Time',1,0) AS is_part_time,
                'all' AS source
         FROM employees e
         LEFT JOIN departments d ON e.department_id=d.id
         WHERE e.employment_status='Active'
         ORDER BY e.full_name"
    );
}

$semesters   = array('1st Semester','2nd Semester','Summer');
$levels      = array('Nursery','Elementary','Junior High','Senior High','College','Graduate School');
$sem_options = array('1st Semester','2nd Semester','Summer');

// School years dropdown (last 3 + next)
$sy_options = array();
$cur_year   = (int)date('Y', time()+$_hrms_delta);
for ($y=$cur_year+1; $y>=$cur_year-2; $y--) {
    $sy_options[] = $y.'-'.($y+1);
}

// Actions available to current user for a given load
function can_act($load, $my_emp_id, $my_role) {
    $s = $load['status'];
    $dh_roles = array('manager','academic_head','non_academic_head');
    if ($s === 'For Faculty Review'          && $load['employee_id'] == $my_emp_id) return 'faculty_confirm';
    if ($s === 'For Dept Head Review'        && in_array($my_role,$dh_roles))       return 'dept_head_approve';
    if ($s === 'For Registrar Verification'  && in_array($my_role,array('registrar_head','non_academic_head','manager'))) return 'registrar_verify';
    if ($s === 'For VP Academic Review'      && in_array($my_role, array('vp_academic','academic_head')))         return 'vp_recommend';
    if ($s === 'For President Approval'      && has_role(ROLE_PRESIDENT))           return 'president_approve';
    if ($s === 'Approved'                    && is_hr())                            return 'hr_receive';
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
    @media print {
      .wrapper > .main-header, .main-sidebar, .col-md-4,
      .breadcrumb, .btn, .content-header { display:none !important; }
      .content-wrapper { margin-left:0 !important; }
      .col-md-8 { flex:0 0 100%; max-width:100%; }
      .print-header { display:block !important; }
      .card { box-shadow:none !important; border:1px solid #dee2e6 !important; }
      .badge { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
      .no-print { display:none !important; }
    }
    @media screen { .print-header { display:none; } }
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
    <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible">
      <button type="button" class="close" data-dismiss="alert">&times;</button>
      <strong>Database Error:</strong> <?= htmlspecialchars($_SESSION['flash_error']) ?>
      <br><small>Please run <code>teaching_load_setup.sql</code> in phpMyAdmin to create missing tables/columns.</small>
    </div>
    <?php unset($_SESSION['flash_error']); endif; ?>

    <?php if ($view_load): ?>
    <!-- ══ DETAIL VIEW ══ -->
    <!-- Print header -->
    <div class="print-header" style="text-align:center;padding:16px 0 10px;border-bottom:2px solid #343a40;margin-bottom:16px;">
      <h4 style="margin:0;font-size:16px;font-weight:700;">SFC|HRMS — Teaching Load / Instructor's Load</h4>
      <p style="margin:4px 0 0;font-size:12px;color:#666;">
        Printed by: <?= htmlspecialchars($_SESSION['username']) ?>
        &nbsp;|&nbsp; <?= date('F d, Y g:i A') ?>
        &nbsp;|&nbsp; Status at time of printing: <?= htmlspecialchars($view_load['status']) ?>
      </p>
    </div>
    <div class="mb-3 d-flex justify-content-between align-items-center no-print">
      <a href="teaching_load.php" class="btn btn-sm btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to list
      </a>
      <div class="d-flex" style="gap:8px;">
        <?php if (is_secretary() && in_array($view_load['status'],array('Draft','Returned'))): ?>
        <?php if (!$edit_mode): ?>
        <a href="teaching_load.php?view=<?= $view_id ?>&edit=1"
           class="btn btn-sm btn-primary">
          <i class="fas fa-pencil-alt mr-1"></i> Edit Load
        </a>
        <form method="POST" action="" style="display:inline;"
              onsubmit="return confirm('Delete this teaching load? This cannot be undone.')">
          <?php csrf_field(); ?>
          <input type="hidden" name="post_action" value="delete">
          <input type="hidden" name="load_id" value="<?= $view_id ?>">
          <button type="submit" class="btn btn-sm btn-danger">
            <i class="fas fa-trash-alt mr-1"></i> Delete
          </button>
        </form>
        <?php else: ?>
        <span class="badge badge-warning" style="font-size:13px;padding:7px 12px;">
          <i class="fas fa-pencil-alt mr-1"></i> Edit Mode
        </span>
        <a href="teaching_load.php?view=<?= $view_id ?>"
           class="btn btn-sm btn-secondary">
          <i class="fas fa-times mr-1"></i> Cancel Edit
        </a>
        <?php endif; ?>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-sm btn-outline-dark">
          <i class="fas fa-print mr-1"></i> Print / Save as PDF
        </button>
      </div>
    </div>

    <!-- Step progress bar -->
    <?php
    $step_defs = array(
      1 => 'Created',
      2 => 'Faculty Confirmed',
      3 => 'Dept Head Noted',
      4 => 'Registrar Verified',
      5 => 'VP Recommended',  // VP Academic
      6 => 'President Approved',
      7 => 'HR Received',
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
        <?php if ($edit_mode): ?>
        <!-- ══ EDIT MODE FORM ══ -->
        <div class="card card-outline card-primary">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
              <i class="fas fa-pencil-alt mr-2 text-primary"></i>
              Editing: Instructor's Teaching Load
            </h3>
            <span class="badge badge-primary">Edit Mode</span>
          </div>
          <div class="card-body">
            <form method="POST" action="teaching_load.php" id="editForm">
              <?php csrf_field(); ?>
              <input type="hidden" name="post_action" value="edit">
              <input type="hidden" name="load_id" value="<?= $view_id ?>">

              <!-- Assign To (read-only in edit — employee doesn't change) -->
              <div class="row mb-2">
                <div class="col-md-6">
                  <div class="form-group mb-2">
                    <label style="font-size:12px;">Assigned Faculty / Staff</label>
                    <input type="hidden" name="employee_id" value="<?= $view_load['employee_id'] ?>">
                    <input type="text" class="form-control form-control-sm"
                           value="<?= htmlspecialchars((isset($view_load['full_name']) ? $view_load['full_name'] : '')) ?>" readonly
                           style="background:#f8f9fa;">
                    <small class="text-muted">Employee cannot be changed during edit</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group mb-2">
                    <label style="font-size:12px;">School Year</label>
                    <select name="school_year" class="form-control form-control-sm">
                      <?php foreach ($sy_options as $sy): ?>
                        <option <?= $sy===$view_load['school_year']?'selected':'' ?>><?= $sy ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group mb-2">
                    <label style="font-size:12px;">Semester</label>
                    <select name="semester" class="form-control form-control-sm">
                      <?php foreach ($sem_options as $so): ?>
                        <option <?= $so===$view_load['semester']?'selected':'' ?>><?= $so ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Subjects table (editable) -->
              <?php
              $edit_subjects = $tl_subjects_exists ? db_fetchall($pdo,
                  "SELECT * FROM teaching_load_subjects WHERE load_id=? ORDER BY sort_order,id",
                  array($view_id)
              ) : array();
              if (empty($edit_subjects) && !empty($view_load['subject_title'])) {
                  $edit_subjects = array(array(
                      'course_no'   => (isset($view_load['subject_code']) ? $view_load['subject_code'] : ''),
                      'description' => $view_load['subject_title'],
                      'units'       => (isset($view_load['units']) ? $view_load['units'] : 3),
                      'hours'       => (isset($view_load['units']) ? $view_load['units'] : 3),
                      'no_of_students' => 0,
                      'day_of_week' => (isset($view_load['day_of_week']) ? $view_load['day_of_week'] : ''),
                      'time_start'  => (isset($view_load['time_start']) ? $view_load['time_start'] : ''),
                      'time_end'    => (isset($view_load['time_end']) ? $view_load['time_end'] : ''),
                      'room'        => (isset($view_load['room']) ? $view_load['room'] : ''),
                  ));
              }
              ?>
              <div class="table-responsive">
              <table class="table table-sm table-bordered" style="font-size:12px;min-width:900px;">
                <thead class="thead-dark">
                  <tr>
                    <th width="20">#</th><th>Course No.</th>
                    <th>Description <span class="text-danger">*</span></th>
                    <th width="55">Units</th><th width="55">Hours</th>
                    <th width="70">Students</th>
                    <th width="200">Days <span class="text-danger">*</span></th>
                    <th width="130">Time <span class="text-danger">*</span></th>
                    <th width="80">Room</th><th width="30"></th>
                  </tr>
                </thead>
                <tbody id="editSubjectRows">
                  <?php foreach ($edit_subjects as $ei => $es): ?>
                  <tr class="subj-row" data-idx="<?= $ei ?>">
                    <td class="text-center text-muted" style="vertical-align:middle;"><?= $ei+1 ?></td>
                    <td><input type="text" name="subjects[<?= $ei ?>][course_no]" class="form-control form-control-sm" value="<?= htmlspecialchars((isset($es['course_no']) ? $es['course_no'] : '')) ?>"></td>
                    <td><input type="text" name="subjects[<?= $ei ?>][description]" class="form-control form-control-sm" value="<?= htmlspecialchars($es['description']) ?>" required></td>
                    <td><input type="number" name="subjects[<?= $ei ?>][units]" class="form-control form-control-sm" step="0.5" min="0" value="<?= $es['units'] ?>" required></td>
                    <td><input type="number" name="subjects[<?= $ei ?>][hours]" class="form-control form-control-sm" step="0.5" min="0" value="<?= $es['hours'] ?>"></td>
                    <td><input type="number" name="subjects[<?= $ei ?>][students]" class="form-control form-control-sm" min="0" value="<?= (isset($es['no_of_students']) ? $es['no_of_students'] : 0) ?>"></td>
                    <td style="min-width:190px;">
                      <?php
                      $existing_days = array_map('trim', explode('/',str_replace(array(',','-'),'/',(isset($es['day_of_week']) ? $es['day_of_week'] : ''))));
                      ?>
                      <input type="hidden" name="subjects[<?= $ei ?>][days]" class="days-val" value="<?= htmlspecialchars((isset($es['day_of_week']) ? $es['day_of_week'] : '')) ?>">
                      <div class="days-cb-group" style="font-size:11px;line-height:1.8;">
                        <?php foreach(array('Mon','Tue','Wed','Thu','Fri','Sat','Sun') as $_d):
                          $chk = in_array($_d,$existing_days) ? 'checked' : '';
                        ?>
                        <label style="margin-right:6px;cursor:pointer;">
                          <input type="checkbox" class="day-cb" value="<?= $_d ?>" <?= $chk ?>
                                 style="margin-right:2px;"><?= $_d ?>
                        </label>
                        <?php endforeach; ?>
                      </div>
                      <div style="margin-top:3px;">
                        <button type="button" class="btn btn-xs btn-outline-primary mr-1" onclick="qdays(this,'Mon,Wed,Fri')">MWF</button>
                        <button type="button" class="btn btn-xs btn-outline-primary mr-1" onclick="qdays(this,'Tue,Thu')">TTh</button>
                        <button type="button" class="btn btn-xs btn-outline-primary mr-1" onclick="qdays(this,'Mon,Tue,Wed,Thu,Fri')">M-F</button>
                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="qdays(this,'')">✕</button>
                      </div>
                    </td>
                    <td>
                      <div class="d-flex" style="gap:2px;">
                        <input type="time" name="subjects[<?= $ei ?>][time_start]" class="form-control form-control-sm" value="<?= substr((isset($es['time_start']) ? $es['time_start'] : ''),0,5) ?>" required>
                        <input type="time" name="subjects[<?= $ei ?>][time_end]" class="form-control form-control-sm" value="<?= substr((isset($es['time_end']) ? $es['time_end'] : ''),0,5) ?>" required>
                      </div>
                    </td>
                    <td><input type="text" name="subjects[<?= $ei ?>][room]" class="form-control form-control-sm" value="<?= htmlspecialchars((isset($es['room']) ? $es['room'] : '')) ?>"></td>
                    <td class="text-center" style="vertical-align:middle;">
                      <?php if ($ei > 0): ?>
                      <button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest('tr').remove();updateEditTotals();">
                        <i class="fas fa-times"></i>
                      </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="3" class="text-right text-muted font-weight-bold" style="font-size:11px;">Totals:</td>
                    <td id="editTotalUnits"><?= array_sum(array_column($edit_subjects,'units')) ?></td>
                    <td id="editTotalHours"><?= array_sum(array_column($edit_subjects,'hours')) ?></td>
                    <td id="editTotalStudents"><?= array_sum(array_column($edit_subjects,'no_of_students')) ?></td>
                    <td colspan="4">
                      <span id="editTotalPreps" style="font-size:11px;color:#6c757d;"><?= count($edit_subjects) ?> preparation<?= count($edit_subjects)!==1?'s':'' ?></span>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="10">
                      <button type="button" class="btn btn-sm btn-outline-primary" onclick="addEditSubjRow()">
                        <i class="fas fa-plus mr-1"></i> Add Subject
                      </button>
                    </td>
                  </tr>
                </tfoot>
              </table>
              </div>

              <!-- Shared fields -->
              <div class="row mt-2">
                <div class="col-md-5">
                  <div class="form-group">
                    <label style="font-size:12px;">Student Program / Course</label>
                    <input type="text" name="student_program" class="form-control form-control-sm"
                           value="<?= htmlspecialchars((isset($view_load['student_program']) ? $view_load['student_program'] : '')) ?>">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label style="font-size:12px;">Section</label>
                    <input type="text" name="section" class="form-control form-control-sm"
                           value="<?= htmlspecialchars((isset($view_load['section']) ? $view_load['section'] : '')) ?>">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label style="font-size:12px;">Level</label>
                    <select name="level" class="form-control form-control-sm">
                      <?php foreach ($levels as $lv): ?>
                        <option <?= $lv===$view_load['level']?'selected':'' ?>><?= $lv ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>

              <div class="mt-3 d-flex" style="gap:10px;">
                <button type="submit" class="btn btn-success" form="editForm"
                        onclick="document.querySelectorAll('#editSubjectRows .subj-row').forEach(function(r){syncDays(r);})">
                  <i class="fas fa-save mr-1"></i> Save Changes
                </button>
                <a href="teaching_load.php?view=<?= $view_id ?>" class="btn btn-secondary">
                  <i class="fas fa-times mr-1"></i> Cancel
                </a>
              </div>
            </form>
          </div>
        </div>

        <script>
        // Edit form: add subject row
        var _editIdx = <?= count($edit_subjects) ?>;
        function addEditSubjRow() {
            var tmpl = document.querySelector('#editSubjectRows .subj-row');
            var row  = tmpl.cloneNode(true);
            row.setAttribute('data-idx', _editIdx);
            row.querySelectorAll('[name]').forEach(function(el){
                el.name = el.name.replace(/subjects\[\d+\]/,'subjects['+_editIdx+']');
            });
            row.querySelectorAll('input[type="text"],input[type="time"]').forEach(function(el){ el.value=''; });
            row.querySelectorAll('input[type="number"]').forEach(function(el){
                el.value = (el.name.indexOf('units')!==-1||el.name.indexOf('hours')!==-1)?'3':'0';
            });
            row.querySelectorAll('.day-cb').forEach(function(cb){ cb.checked=false; });
            var hidden = row.querySelector('.days-val');
            if (hidden) hidden.value='';
            // Ensure remove button exists
            var lastCell = row.cells[row.cells.length-1];
            lastCell.innerHTML='<button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest(\'tr\').remove();updateEditTotals();"><i class="fas fa-times"></i></button>';
            // Row number
            row.cells[0].textContent = document.querySelectorAll('#editSubjectRows tr').length + 1;
            document.getElementById('editSubjectRows').appendChild(row);
            attachDayListeners(row);
            _editIdx++;
            updateEditTotals();
        }
        function updateEditTotals(){
            var rows=document.querySelectorAll('#editSubjectRows .subj-row');
            var u=0,h=0,s=0;
            rows.forEach(function(r,i){
                r.cells[0].textContent=i+1;
                u+=parseFloat(r.querySelector('[name*="[units]"]').value)||0;
                h+=parseFloat(r.querySelector('[name*="[hours]"]').value)||0;
                s+=parseInt(r.querySelector('[name*="[students]"]').value)||0;
            });
            document.getElementById('editTotalUnits').textContent=u.toFixed(1);
            document.getElementById('editTotalHours').textContent=h.toFixed(1);
            document.getElementById('editTotalStudents').textContent=s;
            document.getElementById('editTotalPreps').textContent=rows.length+' preparation'+(rows.length!==1?'s':'');
        }
        // Attach day listeners to existing edit rows
        document.querySelectorAll('#editSubjectRows .subj-row').forEach(function(row){
            attachDayListeners(row);
            row.querySelectorAll('input[type="number"]').forEach(function(el){
                el.addEventListener('input',updateEditTotals);
            });
        });
        </script>

        <?php else: ?>
        <!-- ══ READ-ONLY VIEW MODE ══ -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
              Instructor's Teaching Load
              <?php
              $view_subjects = $tl_subjects_exists ? db_fetchall($pdo,
                  "SELECT * FROM teaching_load_subjects WHERE load_id=? ORDER BY sort_order,id",
                  array($view_load['id'])
              ) : array();
              // Fallback: if no subjects table entries, use legacy single-subject fields
              if (empty($view_subjects) && !empty($view_load['subject_title'])) {
                  $view_subjects = array(array(
                      'course_no'=>(isset($view_load['subject_code']) ? $view_load['subject_code'] : ''),
                      'description'=>$view_load['subject_title'],
                      'units'=>(isset($view_load['units']) ? $view_load['units'] : 0),
                      'hours'=>(isset($view_load['units']) ? $view_load['units'] : 0),
                      'no_of_students'=>0,
                      'day_of_week'=>$view_load['day_of_week'],
                      'time_start'=>$view_load['time_start'],
                      'time_end'=>$view_load['time_end'],
                      'room'=>(isset($view_load['room']) ? $view_load['room'] : ''),
                  ));
              }
              ?>
              <small class="text-muted ml-2">(<?= count($view_subjects) ?> subject<?= count($view_subjects)!==1?'s':'' ?>)</small>
            </h3>
            <span class="badge badge-<?= isset($status_badge[$view_load['status']]) ? $status_badge[$view_load['status']] : 'secondary' ?> ml-2">
              <?= $view_load['status'] ?>
            </span>
          </div>
          <div class="card-body">
            <div class="row mb-3">
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
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-sm table-borderless" style="font-size:13px;">
                  <tr><td class="text-muted" width="140">School Year</td>
                      <td><?= htmlspecialchars($view_load['school_year']) ?></td></tr>
                  <tr><td class="text-muted">Semester</td>
                      <td><?= htmlspecialchars($view_load['semester']) ?></td></tr>
                  <tr><td class="text-muted">Total Units</td>
                      <td><strong><?= $view_load['total_units'] ?: $view_load['units'] ?></strong></td></tr>
                  <tr><td class="text-muted">Total Hours</td>
                      <td><?= $view_load['total_hours'] ?: $view_load['units'] ?></td></tr>
                  <tr><td class="text-muted">No. of Preps</td>
                      <td><?= $view_load['total_preps'] ?: 1 ?></td></tr>
                </table>
              </div>
            </div>

            <!-- Subjects table -->
            <div class="table-responsive mt-2">
              <table class="table table-sm table-bordered" style="font-size:12px;">
                <thead class="thead-dark">
                  <tr>
                    <th>#</th><th>Course No.</th><th>Description</th>
                    <th>Units</th><th>Hours</th><th>Students</th>
                    <th>Days</th><th>Time</th><th>Room</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($view_subjects as $si => $subj): ?>
                  <tr>
                    <td class="text-muted"><?= $si+1 ?></td>
                    <td><?= htmlspecialchars((isset($subj['course_no']) ? $subj['course_no'] : '')) ?></td>
                    <td><strong><?= htmlspecialchars($subj['description']) ?></strong></td>
                    <td><?= $subj['units'] ?></td>
                    <td><?= $subj['hours'] ?></td>
                    <td><?= (isset($subj['no_of_students']) ? $subj['no_of_students'] : 0) ?></td>
                    <td><?= htmlspecialchars($subj['day_of_week']) ?></td>
                    <td><?= date('h:i A',strtotime($subj['time_start'])) ?> &mdash; <?= date('h:i A',strtotime($subj['time_end'])) ?></td>
                    <td><?= htmlspecialchars((isset($subj['room']) ? $subj['room'] : '—')) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (count($view_subjects) > 1): ?>
                  <tr class="table-light">
                    <td colspan="3" class="text-right text-muted font-weight-bold">Total</td>
                    <td class="font-weight-bold"><?= array_sum(array_column($view_subjects,'units')) ?></td>
                    <td class="font-weight-bold"><?= array_sum(array_column($view_subjects,'hours')) ?></td>
                    <td class="font-weight-bold"><?= array_sum(array_column($view_subjects,'no_of_students')) ?></td>
                    <td colspan="3"></td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
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

        <?php if ($view_load['status'] === 'Draft' && (is_secretary() || is_manager())): ?>
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
        <div class="card card-outline card-<?= $action_key === 'faculty_confirm' ? 'info' : 'success' ?>">
          <div class="card-header">
            <h3 class="card-title">
              <?php if ($action_key === 'faculty_confirm'): ?>
                <i class="fas fa-chalkboard-teacher mr-1"></i> Faculty Review Required
              <?php else: ?>
                <i class="fas fa-pen-nib mr-1"></i> Your Action Required
              <?php endif; ?>
            </h3>
          </div>
          <div class="card-body">
            <?php
            $btn_labels = array(
              'faculty_confirm'   => array('Digital Sign & Confirm','btn-success'),
              'dept_head_approve' => array('Noted & Digitally Signed','btn-info'),
              'registrar_verify'  => array('Verified & Digitally Signed','btn-teal'),
              'vp_recommend'      => array('Recommending Approval','btn-primary'),
              'president_approve' => array('Approve Teaching Load','btn-success'),
              'hr_receive'        => array('Acknowledge HR Copy','btn-secondary'),
            );
            $btn = $btn_labels[$action_key];
            ?>

            <?php if ($action_key === 'faculty_confirm'): ?>
            <!-- Faculty: view load details prompt -->
            <div class="alert alert-info p-2 mb-3" style="font-size:12px;">
              <i class="fas fa-info-circle mr-1"></i>
              Please review all assigned subjects carefully before signing.
              If there are issues with your schedule, offset time, or subject assignment — use Decline below.
            </div>
            <?php endif; ?>

            <!-- APPROVE / DIGITAL SIGN form -->
            <form method="POST" action="" id="tlSignForm">
              <?php csrf_field(); ?>
              <input type="hidden" name="post_action" value="<?= $action_key ?>">
              <input type="hidden" name="load_id" value="<?= $view_load['id'] ?>">
              <?php if ($action_key !== 'hr_receive'): ?>
              <div class="form-group">
                <label style="font-size:12px;">Remarks <small class="text-muted">(optional)</small></label>
                <textarea name="remarks" class="form-control form-control-sm" rows="2"
                          placeholder="Add notes or comments..."></textarea>
              </div>
              <?php endif; ?>

              <!-- Digital signature confirmation checkbox -->
              <div class="custom-control custom-checkbox mb-3">
                <input type="checkbox" class="custom-control-input" id="tlConfirmSign"
                       <?= $action_key === 'hr_receive' ? 'checked' : '' ?> <?= $action_key === 'hr_receive' ? 'disabled' : 'required' ?>>
                <label class="custom-control-label" for="tlConfirmSign" style="font-size:12px;">
                  <?php if ($action_key === 'faculty_confirm'): ?>
                    I have reviewed all my assigned subjects and I affix my <strong>digital signature</strong> as confirmation.
                  <?php elseif ($action_key === 'dept_head_approve'): ?>
                    I have reviewed all faculty loads and I affix my <strong>digital signature</strong> as Department Head.
                  <?php elseif ($action_key === 'vp_recommend'): ?>
                    I recommend this teaching load for approval and affix my <strong>digital signature</strong>.
                  <?php elseif ($action_key === 'president_approve'): ?>
                    I approve this teaching load and affix my <strong>digital signature</strong> as President.
                  <?php else: ?>
                    I confirm receipt of this teaching load copy.
                  <?php endif; ?>
                </label>
              </div>

              <button type="submit" class="btn <?= $btn[1] ?> btn-block mb-2"
                      id="tlSignBtn" <?= $action_key !== 'hr_receive' ? 'disabled' : '' ?>>
                <i class="fas fa-signature mr-1"></i> <?= $btn[0] ?>
              </button>
            </form>

            <!-- DECLINE / RETURN form (all roles can decline except hr_receive) -->
            <?php if ($action_key !== 'hr_receive'): ?>
            <hr style="margin:14px 0;">
            <form method="POST" action="" id="tlDeclineForm">
              <?php csrf_field(); ?>
              <input type="hidden" name="post_action" value="return">
              <input type="hidden" name="load_id" value="<?= $view_load['id'] ?>">
              <div class="form-group mb-2">
                <label style="font-size:12px;font-weight:600;" class="text-warning">
                  <i class="fas fa-exclamation-triangle mr-1"></i>
                  <?= $action_key === 'faculty_confirm' ? 'Decline & Return to Secretary' : 'Return for Correction' ?>
                  <span class="text-danger">*</span>
                </label>
                <textarea name="rejection_reason" class="form-control form-control-sm" rows="3"
                          placeholder="<?= $action_key === 'faculty_confirm'
                            ? 'State the reason for declining (e.g. conflict with offset time, wrong subject code, room issue...)...'
                            : 'State the reason for returning for revision...' ?>"
                          required></textarea>
                <small class="text-muted">This message will be visible to the Department Secretary.</small>
              </div>
              <button type="submit" class="btn btn-outline-warning btn-block btn-sm"
                      onclick="return confirm('<?= $action_key === 'faculty_confirm'
                        ? 'Decline and return this load to the Department Secretary?'
                        : 'Return this teaching load for revision?' ?>')">
                <i class="fas fa-undo mr-1"></i>
                <?= $action_key === 'faculty_confirm' ? 'Decline — Return to Secretary' : 'Return for Revision' ?>
              </button>
            </form>
            <?php endif; ?>

            <?php if (has_role(ROLE_PRESIDENT) && $action_key !== 'hr_receive'): ?>
            <hr style="margin:10px 0;">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <input type="hidden" name="post_action" value="reject">
              <input type="hidden" name="load_id" value="<?= $view_load['id'] ?>">
              <div class="form-group mb-2">
                <label style="font-size:12px;" class="text-danger">Rejection Reason <span class="text-danger">*</span></label>
                <textarea name="rejection_reason" class="form-control form-control-sm" rows="2"
                          placeholder="State reason for permanent rejection..." required></textarea>
              </div>
              <button type="submit" class="btn btn-outline-danger btn-block btn-sm">
                <i class="fas fa-times-circle mr-1"></i> Permanently Reject
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php endif; // edit_mode ?>
    <?php else: ?>
    <!-- ══ LIST VIEW ══ -->

    <!-- Create Teaching Load button -->
    <?php if (is_secretary()): ?>
    <div class="mb-3 d-flex justify-content-between align-items-center">
      <h5 class="mb-0">
        <i class="fas fa-chalkboard-teacher mr-2"></i>Teaching Load Management
      </h5>
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalCreateLoad">
        <i class="fas fa-plus-circle mr-1"></i> New Teaching Load
      </button>
    </div>
    <?php endif; ?>

    <!-- ══ MODAL: Create Teaching Load ══ -->
    <?php if (is_secretary()): ?>
    <div class="modal fade" id="modalCreateLoad" tabindex="-1" data-backdrop="static" data-keyboard="false">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header" style="background:#007bff;color:#fff;">
            <h5 class="modal-title">
              <i class="fas fa-plus-circle mr-2"></i>New Teaching / Instructor's Load
            </h5>
            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
        <form method="POST" action="" id="createForm">
          <?php csrf_field(); ?>
          <input type="hidden" name="post_action" value="create">
          <div class="row">

            <!-- Column 1: Who, What -->
            <div class="col-md-4">
              <div class="form-group">
                <label>Assign To <span class="text-danger">*</span></label>
                <input type="hidden" name="employee_id" id="empHiddenId">
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                  </div>
                  <input type="text" id="empSearchBox" class="form-control"
                         placeholder="Type name, employee ID, or department..."
                         autocomplete="off"
                         oninput="empSearchFilter(this.value)"
                         onblur="empSearchBlur()"
                         onkeydown="empSearchKey(event)">
                </div>
                <div id="empDropdown" style="display:none;position:absolute;z-index:9999;
                     background:#fff;border:1px solid #ced4da;border-top:none;
                     border-radius:0 0 6px 6px;max-height:220px;overflow-y:auto;
                     min-width:300px;box-shadow:0 4px 12px rgba(0,0,0,.12);">
                </div>
                <div id="empSelectedBadge" style="display:none;margin-top:5px;">
                  <span class="badge badge-success" style="font-size:12px;padding:5px 10px;">
                    <i class="fas fa-user-check mr-1"></i>
                    <span id="empSelectedName"></span>
                    <a href="#" onclick="empClear();return false;"
                       style="color:rgba(255,255,255,.7);margin-left:8px;">
                      <i class="fas fa-times"></i>
                    </a>
                  </span>
                </div>
                <script>
                var _empData=[<?php foreach($all_employees as $e):
                ?>{id:<?=(int)$e['id']?>,name:<?=json_encode($e['full_name'])?>,
                   code:<?=json_encode((isset($e['emp_code']) ? $e['emp_code'] : ''))?>,dept:<?=json_encode((isset($e['dept']) ? $e['dept'] : ''))?>,
                   parttime:<?=$e['is_part_time']?'true':'false'?>,
                   cat:<?=json_encode((isset($e['cat_name']) ? $e['cat_name'] : ''))?>,
                   source:<?=json_encode((isset($e['source']) ? $e['source'] : 'dept'))?>,
                   search:<?=json_encode(strtolower($e['full_name'].' '.((isset($e['emp_code']) ? $e['emp_code'] : '')).' '.((isset($e['dept']) ? $e['dept'] : ''))))?>},
                <?php endforeach;?>];
                var _empHL=-1;
                function empSearchFilter(q){
                  var dd=document.getElementById('empDropdown');
                  document.getElementById('empHiddenId').value='';
                  document.getElementById('empSelectedBadge').style.display='none';
                  q=q.toLowerCase().trim();
                  if(!q){dd.style.display='none';return;}
                  var m=_empData.filter(function(e){return e.search.indexOf(q)!==-1;}).slice(0,10);
                  if(!m.length){
                    dd.innerHTML='<div class="eo-none">No match found</div>';
                    dd.style.display='block';return;
                  }
                  // Build items using DOM to avoid all quote-escaping issues
                  dd.innerHTML='';
                  m.forEach(function(e){
                    var div=document.createElement('div');
                    div.className='eo';
                    div.style.cssText='padding:8px 14px;cursor:pointer;font-size:12px;border-bottom:1px solid #f5f5f5;';
                    var inner='<strong>'+e.name+'</strong>';
                    if(e.code) inner+=' <span style="color:#888;">('+e.code+')</span>';
                    if(e.dept) inner+=' <span style="color:#adb5bd;font-size:11px;"> &mdash; '+e.dept+'</span>';
                    if(e.parttime) inner+=' <span class="badge badge-warning ml-1" style="font-size:9px;">Part-time</span>';
                    if(e.source==='cross') inner+=' <span class="badge badge-info ml-1" style="font-size:9px;">Cross-dept</span>';
                    div.innerHTML=inner;
                    div.addEventListener('mousedown',function(){empPick(e.id,e.name,e.parttime,e.cat);});
                    div.addEventListener('mouseover',function(){this.style.background='#f0f7ff';});
                    div.addEventListener('mouseout', function(){this.style.background='';});
                    dd.appendChild(div);
                  });
                  _empHL=-1;dd.style.display='block';
                }
                function empSearchKey(ev){
                  var dd=document.getElementById('empDropdown'),opts=dd.querySelectorAll('.eo');
                  if(ev.key==='ArrowDown'){_empHL=Math.min(_empHL+1,opts.length-1);opts.forEach(function(o,i){o.style.background=i===_empHL?'#f0f7ff':'';});ev.preventDefault();}
                  else if(ev.key==='ArrowUp'){_empHL=Math.max(_empHL-1,0);opts.forEach(function(o,i){o.style.background=i===_empHL?'#f0f7ff':'';});ev.preventDefault();}
                  else if(ev.key==='Enter'&&_empHL>=0&&opts[_empHL]){opts[_empHL].dispatchEvent(new MouseEvent('mousedown'));ev.preventDefault();}
                  else if(ev.key==='Escape'){dd.style.display='none';}
                }
                function empPick(id,name,pt,cat){
                  document.getElementById('empHiddenId').value=id;
                  document.getElementById('empSearchBox').value='';
                  document.getElementById('empDropdown').style.display='none';
                  document.getElementById('empSelectedName').textContent=name;
                  document.getElementById('empSelectedBadge').style.display='block';
                  var os=document.getElementById('offsetSection');
                  if(os){var isNT=cat.toLowerCase().indexOf('non')!==-1||cat==='';os.style.display=(pt||isNT)?'block':'none';}
                }
                function empSearchBlur(){setTimeout(function(){document.getElementById('empDropdown').style.display='none';},200);}
                function empClear(){
                  document.getElementById('empHiddenId').value='';
                  document.getElementById('empSearchBox').value='';
                  document.getElementById('empSelectedBadge').style.display='none';
                  document.getElementById('empDropdown').style.display='none';
                  var os=document.getElementById('offsetSection');if(os)os.style.display='none';
                }
                </script>
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
              <!-- ── SUBJECTS TABLE (multi-subject) ── -->
              <div class="form-group mb-0">
                <label class="font-weight-bold">
                  Subject Loads
                  <span class="text-danger">*</span>
                  <small class="text-muted ml-2">(Add all subjects assigned to this instructor)</small>
                </label>
                <div style="overflow-x:auto;">
                <table class="table table-sm table-bordered" id="subjectsTable" style="font-size:12px;min-width:900px;">
                  <thead class="thead-light">
                    <tr>
                      <th width="20">#</th>
                      <th>Course No.</th>
                      <th>Description <span class="text-danger">*</span></th>
                      <th width="55">Units</th>
                      <th width="55">Hours</th>
                      <th width="70">Students</th>
                      <th width="200">Days <span class="text-danger">*</span></th>
                      <th width="130">Time <span class="text-danger">*</span></th>
                      <th width="80">Room</th>
                      <th width="30"></th>
                    </tr>
                  </thead>
                  <tbody id="subjectRows">
                    <!-- Row 1 (always visible) -->
                    <tr class="subj-row" data-idx="0">
                      <td class="text-center text-muted" style="vertical-align:middle;">1</td>
                      <td><input type="text" name="subjects[0][course_no]" class="form-control form-control-sm" placeholder="CS101"></td>
                      <td><input type="text" name="subjects[0][description]" class="form-control form-control-sm" placeholder="Introduction to Computing" required></td>
                      <td><input type="number" name="subjects[0][units]" class="form-control form-control-sm" step="0.5" min="0" value="3" required></td>
                      <td><input type="number" name="subjects[0][hours]" class="form-control form-control-sm" step="0.5" min="0" value="3"></td>
                      <td><input type="number" name="subjects[0][students]" class="form-control form-control-sm" min="0" placeholder="0"></td>
                      <td style="min-width:190px;">
                        <input type="hidden" name="subjects[0][days]" class="days-val" value="">
                        <div class="days-cb-group" style="font-size:11px;line-height:1.8;">
                          <?php foreach(array('Mon','Tue','Wed','Thu','Fri','Sat','Sun') as $_d): ?>
                          <label style="margin-right:6px;cursor:pointer;">
                            <input type="checkbox" class="day-cb" value="<?= $_d ?>"
                                   style="margin-right:2px;">
                            <?= $_d ?>
                          </label>
                          <?php endforeach; ?>
                        </div>
                        <div style="margin-top:3px;">
                          <button type="button" class="btn btn-xs btn-outline-primary mr-1" onclick="qdays(this,'Mon,Wed,Fri')">MWF</button>
                          <button type="button" class="btn btn-xs btn-outline-primary mr-1" onclick="qdays(this,'Tue,Thu')">TTh</button>
                          <button type="button" class="btn btn-xs btn-outline-primary mr-1" onclick="qdays(this,'Mon,Tue,Wed,Thu,Fri')">M-F</button>
                          <button type="button" class="btn btn-xs btn-outline-danger" onclick="qdays(this,'')">✕</button>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex" style="gap:2px;">
                          <input type="time" name="subjects[0][time_start]" class="form-control form-control-sm" required>
                          <input type="time" name="subjects[0][time_end]" class="form-control form-control-sm" required>
                        </div>
                      </td>
                      <td><input type="text" name="subjects[0][room]" class="form-control form-control-sm" placeholder="L101"></td>
                      <td class="text-center" style="vertical-align:middle;">
                        <button type="button" class="btn btn-xs btn-outline-danger btn-remove-subj" style="display:none;" onclick="removeSubjRow(this)">
                          <i class="fas fa-times"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="3" class="text-right text-muted" style="font-size:11px;"><strong>Totals:</strong></td>
                      <td><strong id="totalUnits">3</strong></td>
                      <td><strong id="totalHours">3</strong></td>
                      <td><strong id="totalStudents">0</strong></td>
                      <td colspan="4">
                        <span id="totalPreps" style="font-size:11px;color:#6c757d;">1 preparation</span>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="10">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSubjRow()">
                          <i class="fas fa-plus mr-1"></i> Add Another Subject
                        </button>
                      </td>
                    </tr>
                  </tfoot>
                </table>
                </div>
              </div>

              <!-- Program / Section / Level (shared per load document) -->
              <div class="row mt-2">
                <div class="col-md-5">
                  <div class="form-group">
                    <label style="font-size:12px;">Student Program / Course</label>
                    <input type="text" name="student_program" class="form-control form-control-sm"
                           placeholder="e.g. BS Information Technology">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label style="font-size:12px;">Section</label>
                    <input type="text" name="section" class="form-control form-control-sm"
                           placeholder="e.g. CS3A">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label style="font-size:12px;">Level</label>
                    <select name="level" class="form-control form-control-sm">
                      <?php foreach ($levels as $lv): ?>
                        <option <?= $lv==='College'?'selected':'' ?>><?= $lv ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>

            </div><!-- /.col subjects -->

            <!-- Column 2: Offset schedule only now (schedule is per-subject) -->
            <div class="col-md-4">
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


          </div><!-- /.row -->
        </form>
          </div><!-- modal-body -->
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-1"></i> Cancel
            </button>
            <button type="submit" form="createForm" class="btn btn-primary">
              <i class="fas fa-save mr-1"></i> Create Teaching Load
            </button>
          </div>
        </div><!-- modal-content -->
      </div><!-- modal-dialog -->
    </div><!-- modal -->
    <?php endif; ?>

    <!-- Filters + List: full width -->
    <div class="card card-outline card-secondary mb-3">
      <div class="card-body py-2">
        <form method="GET" action="">
          <div class="row align-items-end">
            <div class="col-6 col-md-2 mb-2">
              <label style="font-size:11px;color:#6c757d;margin-bottom:2px;">School Year</label>
              <select name="sy" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php foreach ($sy_options as $sy): ?>
                  <option <?= $sy===$filter_sy?'selected':'' ?>><?= $sy ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-2 mb-2">
              <label style="font-size:11px;color:#6c757d;margin-bottom:2px;">Semester</label>
              <select name="sem" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">All Semesters</option>
                <?php foreach ($semesters as $s): ?>
                  <option <?= $s===$filter_sem?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (is_secretary() || is_manager()): ?>
            <div class="col-6 col-md-3 mb-2">
              <label style="font-size:11px;color:#6c757d;margin-bottom:2px;">
                Faculty / Instructor
                <small class="text-muted" id="empFilterCount"></small>
              </label>
              <select name="emp_id" class="form-control form-control-sm" onchange="this.form.submit()" id="empFilterSelect">
                <option value="0">All Faculty / Instructors</option>
                <?php
                // Only show employees who have a teaching load in the selected SY/Semester
                // within this secretary's department (or cross-dept loads assigned to this dept)
                $emp_filter_query_params = array($filter_sy ?: $cur_sy, $filter_sem ?: $cur_sem);
                $dept_cond = $sec_dept_id ? "AND e.department_id=?" : "";
                if ($sec_dept_id) $emp_filter_query_params[] = $sec_dept_id;
                try {
                    $emp_with_loads = db_fetchall($pdo,
                        "SELECT DISTINCT e.id, e.full_name, e.employee_id AS emp_code,
                                d.name AS dept, e.is_part_time,
                                e.department_id=".((int)$sec_dept_id)." AS in_dept
                         FROM teaching_loads tl
                         JOIN employees e ON tl.employee_id=e.id
                         LEFT JOIN departments d ON e.department_id=d.id
                         WHERE tl.school_year=? AND tl.semester=?
                           AND tl.status NOT IN ('Rejected')
                           $dept_cond
                         ORDER BY e.full_name",
                        $emp_filter_query_params
                    );
                } catch (Exception $e) {
                    $emp_with_loads = array();
                }
                foreach ($emp_with_loads as $e):
                ?>
                  <option value="<?= $e['id'] ?>" <?= $filter_emp==$e['id']?'selected':'' ?>>
                    <?= htmlspecialchars($e['full_name']) ?>
                    <?php if ($e['emp_code']): ?>(<?= htmlspecialchars($e['emp_code']) ?>)<?php endif; ?>
                    <?php if (!empty($e['is_part_time'])): ?>[Part-time]<?php endif; ?>
                    <?php if (!$e['in_dept']): ?>[Cross-dept]<?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="col-auto mb-2">
              <label style="font-size:11px;color:transparent;margin-bottom:2px;">.</label><br>
              <a href="teaching_load.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-undo mr-1"></i> Reset
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Load list: tabbed by status -->
    <?php
    // Bucket loads by category
    $loads_pending  = array_filter($loads, function($l){ return in_array($l['status'],array('Draft','For Faculty Review','Faculty Confirmed','For Dept Head Review','Dept Head Approved','For Registrar Verification','Registrar Verified','For VP Academic Review','VP Academic Recommended','For President Approval','President Approved')); });
    $loads_approved = array_filter($loads, function($l){ return $l['status']==='Approved'; });
    $loads_rejected = array_filter($loads, function($l){ return in_array($l['status'],array('Rejected','Returned')); });
    ?>
    <div class="card">
      <div class="card-header p-0 pt-1">
        <ul class="nav nav-tabs px-3" id="tlTabs">
          <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#tl-all">
              All <span class="badge badge-secondary ml-1"><?= count($loads) ?></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tl-pending">
              <i class="fas fa-clock mr-1 text-warning"></i>In Progress
              <?php if (!empty($loads_pending)): ?>
                <span class="badge badge-warning ml-1"><?= count($loads_pending) ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tl-approved">
              <i class="fas fa-check mr-1 text-success"></i>Approved
              <?php if (!empty($loads_approved)): ?>
                <span class="badge badge-success ml-1"><?= count($loads_approved) ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tl-rejected">
              <i class="fas fa-undo mr-1 text-danger"></i>Returned/Rejected
              <?php if (!empty($loads_rejected)): ?>
                <span class="badge badge-danger ml-1"><?= count($loads_rejected) ?></span>
              <?php endif; ?>
            </a>
          </li>
        </ul>
      </div>
      <div class="card-body tab-content p-0">
        <?php
        // Process flow steps for display
        $flow_steps = array(
            'Draft'                      => array(1, 'secondary'),
            'For Faculty Review'         => array(2, 'info'),
            'Faculty Confirmed'          => array(2, 'primary'),
            'For Dept Head Review'       => array(3, 'warning'),
            'Dept Head Approved'         => array(3, 'primary'),
            'For Registrar Verification' => array(4, 'warning'),
            'Registrar Verified'         => array(4, 'primary'),
            'For VP Academic Review'     => array(5, 'warning'),
            'VP Academic Recommended'    => array(5, 'primary'),
            'For President Approval'     => array(6, 'warning'),
            'President Approved'         => array(6, 'primary'),
            'Approved'                   => array(7, 'success'),
            'Rejected'                   => array(0, 'danger'),
            'Returned'                   => array(0, 'warning'),
        );
        $flow_labels = array(
            'Draft','For Faculty Review','Faculty Confirmed','For Dept Head Review',
            'Dept Head Approved','For Registrar Verification','Registrar Verified',
            'For VP Academic Review','VP Academic Recommended','For President Approval','President Approved','Approved'
        );

        // Reusable table renderer
        function tl_table($rows, $table_id, $status_badge, $my_emp_id, $my_role, $flow_steps, $flow_labels, $pdo, $tl_subjects_exists) {
        // Step short labels for the mini flow
        $step_short = array(
            'Draft'                       => 'Draft',
            'For Faculty Review'          => 'Faculty',
            'Faculty Confirmed'           => 'Faculty ✓',
            'For Dept Head Review'        => 'Dept Head',
            'Dept Head Approved'          => 'Dept Head ✓',
            'For Registrar Verification'  => 'Registrar',
            'Registrar Verified'          => 'Registrar ✓',
            'For VP Academic Review'      => 'VP Academic',
            'VP Academic Recommended'     => 'VP Academic ✓',
            'For President Approval'      => 'President',
            'President Approved'          => 'President ✓',
            'Approved'                    => 'Approved ✓',
            'Rejected'                    => 'Rejected',
            'Returned'                    => 'Returned',
        );
        ?>
        <table class="table table-sm table-bordered table-hover mb-0" id="<?= $table_id ?>">
          <thead class="thead-light">
            <tr>
              <th>Faculty / Staff</th>
              <th>Subject</th>
              <th>Day &amp; Time</th>
              <th>Room</th>
              <th>SY / Sem</th>
              <th>Process Flow</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $l):
              $sb  = isset($status_badge[$l['status']]) ? $status_badge[$l['status']] : 'secondary';
              $act = can_act($l, $my_emp_id, $my_role);
              $cur_step = isset($flow_steps[$l['status']]) ? $flow_steps[$l['status']][0] : 0;
              $total_steps = 9;
              $pct = $cur_step > 0 ? min(100, round($cur_step / $total_steps * 100)) : 0;
            ?>
            <?php
              // Subject count for this load
              $row_subj_count = $tl_subjects_exists
                ? (int)db_value($pdo,"SELECT COUNT(*) FROM teaching_load_subjects WHERE load_id=?",array($l['id']))
                : ((!empty($l['subject_title'])) ? 1 : 0);
              $is_multi = ($row_subj_count > 1);
            ?>
            <tr>
              <td>
                <strong style="font-size:12px;"><?= htmlspecialchars($l['full_name']) ?></strong>
                <br><small class="text-muted"><?= htmlspecialchars($l['emp_code']) ?> &mdash; <?= htmlspecialchars($l['dept'] ?: '—') ?></small>
              </td>
              <td style="font-size:12px;">
                <?php if ($is_multi): ?>
                  <span class="badge badge-secondary" style="font-size:10px;">
                    <i class="fas fa-list mr-1"></i><?= $row_subj_count ?> subjects
                  </span><br>
                  <small class="text-muted"><?= $row_subj_count ?> preparation<?= $row_subj_count!==1?'s':'' ?> &middot; <?= $l['total_units'] ?: '—' ?> units</small>
                <?php else: ?>
                  <strong><?= htmlspecialchars($l['subject_title'] ?: '—') ?></strong>
                  <?php if ($l['subject_code']): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($l['subject_code']) ?></small>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;">
                <?php if ($is_multi): ?>
                  <span class="text-muted" style="font-size:11px;">— multiple —</span>
                <?php else: ?>
                  <?= htmlspecialchars($l['day_of_week'] ?: '—') ?><br>
                  <?php if ($l['time_start']): ?>
                    <small><?= date('h:i A',strtotime($l['time_start'])) ?> &mdash; <?= date('h:i A',strtotime($l['time_end'])) ?></small>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;"><?= htmlspecialchars($is_multi ? '—' : ($l['room'] ?: '—')) ?></td>
              <td style="font-size:11px;">
                <?= htmlspecialchars($l['school_year']) ?><br>
                <small><?= htmlspecialchars($l['semester']) ?></small>
              </td>
              <td style="min-width:180px;">
                <?php if (in_array($l['status'],array('Rejected','Returned'))): ?>
                  <span class="badge badge-<?= $l['status']==='Rejected'?'danger':'warning' ?>" style="font-size:9px;">
                    <i class="fas fa-<?= $l['status']==='Rejected'?'times':'undo' ?>-circle mr-1"></i>
                    <?= htmlspecialchars($l['status']) ?>
                  </span>
                  <?php if ($l['rejection_reason']): ?>
                  <div style="font-size:11px;color:#721c24;background:#f8d7da;border-radius:4px;padding:3px 6px;margin-top:4px;">
                    <i class="fas fa-comment-alt mr-1"></i>
                    <em>"<?= htmlspecialchars(substr($l['rejection_reason'],0,80)) ?><?= strlen($l['rejection_reason'])>80?'...':'' ?>"</em>
                  </div>
                  <?php endif; ?>
                <?php else: ?>
                  <?php
                  $is_waiting = strpos($l['status'],'For ') === 0;
                  ?>
                  <div class="progress mb-1" style="height:5px;border-radius:3px;">
                    <div class="progress-bar bg-<?= $sb ?>" style="width:<?= $pct ?>%"></div>
                  </div>
                  <div style="font-size:10px;display:flex;justify-content:space-between;">
                    <span class="badge badge-<?= $sb ?>" style="font-size:9px;">
                      <?= $is_waiting?'<i class="fas fa-clock mr-1"></i>':'' ?>
                      <?= isset($step_short[$l['status']]) ? $step_short[$l['status']] : $l['status'] ?>
                    </span>
                    <span style="color:#adb5bd;"><?= $cur_step ?>/<?= $total_steps ?></span>
                  </div>
                  <div style="display:flex;gap:2px;margin-top:3px;">
                    <?php for ($si=1;$si<=$total_steps;$si++): ?>
                    <div style="flex:1;height:3px;border-radius:2px;background:<?= $si<$cur_step?'#28a745':($si===$cur_step?'#ffc107':'#dee2e6') ?>;"></div>
                    <?php endfor; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge-<?= $sb ?>" style="font-size:10px;"><?= $l['status'] ?></span>
                <?php if ($l['has_offset']): ?>
                  <br><span class="badge badge-warning" style="font-size:9px;">Offset</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap;">
                <?php
                // Determine which buttons to show
                $can_edit_tl   = is_secretary() && in_array($l['status'], array('Draft','Returned'));
                $can_delete_tl = is_secretary() && in_array($l['status'], array('Draft','Returned'));
                ?>
                <div class="btn-group" role="group" style="gap:0;">
                  <!-- VIEW / ACT button — all roles -->
                  <button type="button"
                          class="btn btn-sm <?= $act ? 'btn-warning' : 'btn-info' ?> btn-view-load"
                          style="border-radius:4px 0 0 4px;font-size:12px;padding:4px 10px;"
                          data-id="<?= $l['id'] ?>"
                          data-act="<?= $act ? '1' : '0' ?>"
                          data-toggle="modal" data-target="#modalViewLoad"
                          title="<?= $act ? 'Action required' : 'View details' ?>">
                    <i class="fas fa-<?= $act ? 'tasks' : 'eye' ?>"></i>
                    <span style="font-size:11px;"> <?= $act ? 'Act' : 'View' ?></span>
                  </button>
                  <!-- EDIT button — Secretary only, Draft/Returned status only -->
                  <?php if ($can_edit_tl): ?>
                  <a href="teaching_load.php?view=<?= $l['id'] ?>&edit=1"
                     class="btn btn-sm btn-primary"
                     style="border-radius:0;font-size:12px;padding:4px 10px;border-left:1px solid rgba(255,255,255,.3);"
                     title="Edit this teaching load">
                    <i class="fas fa-pencil-alt"></i>
                    <span style="font-size:11px;"> Edit</span>
                  </a>
                  <?php endif; ?>
                  <!-- DELETE button — Secretary only, Draft/Returned status only -->
                  <?php if ($can_delete_tl): ?>
                  <form method="POST" action="" style="display:inline;margin:0;"
                        onsubmit="return confirm('Permanently delete this teaching load?\nThis cannot be undone.')">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="post_action" value="delete">
                    <input type="hidden" name="load_id" value="<?= $l['id'] ?>">
                    <button type="submit"
                            class="btn btn-sm btn-danger"
                            style="border-radius:0 4px 4px 0;font-size:12px;padding:4px 10px;border-left:1px solid rgba(255,255,255,.3);"
                            title="Delete this teaching load">
                      <i class="fas fa-trash-alt"></i>
                      <span style="font-size:11px;"> Del</span>
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-4">
                <i class="fas fa-chalkboard fa-2x d-block mb-2" style="opacity:.2;"></i>
                No teaching loads found.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
        <?php } ?>

        <!-- ALL tab -->
        <div class="tab-pane fade show active" id="tl-all">
          <?php tl_table($loads,'loadTable',$status_badge,$my_emp_id,$my_role,$flow_steps,$flow_labels,$pdo,$tl_subjects_exists); ?>
        </div>
        <!-- IN PROGRESS tab -->
        <div class="tab-pane fade" id="tl-pending">
          <?php tl_table($loads_pending,'loadTablePending',$status_badge,$my_emp_id,$my_role,$flow_steps,$flow_labels,$pdo,$tl_subjects_exists); ?>
        </div>
        <!-- APPROVED tab -->
        <div class="tab-pane fade" id="tl-approved">
          <?php tl_table($loads_approved,'loadTableApproved',$status_badge,$my_emp_id,$my_role,$flow_steps,$flow_labels,$pdo,$tl_subjects_exists); ?>
        </div>
        <!-- RETURNED/REJECTED tab -->
        <div class="tab-pane fade" id="tl-rejected">
          <?php tl_table($loads_rejected,'loadTableRejected',$status_badge,$my_emp_id,$my_role,$flow_steps,$flow_labels,$pdo,$tl_subjects_exists); ?>
        </div>
      </div>
    </div>

    <?php endif; // view_load ?>

  </div></div>
</div>
<?php include '../includes/footer.php'; ?>

<!-- ══ VIEW LOAD MODAL ══ -->
<div class="modal fade" id="modalViewLoad" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header" style="background:#1a1a2e;color:#fff;">
        <h5 class="modal-title" id="modalViewLoadTitle">
          <i class="fas fa-chalkboard-teacher mr-2"></i>Teaching Load
        </h5>
        <div class="ml-auto d-flex align-items-center" style="gap:8px;">
          <button onclick="window.print()" class="btn btn-sm btn-outline-light no-print">
            <i class="fas fa-print mr-1"></i>Print
          </button>
          <button type="button" class="close text-white ml-2" data-dismiss="modal">&times;</button>
        </div>
      </div>
      <div class="modal-body" id="modalViewLoadBody" style="min-height:300px;">

        <div class="text-center py-5">
          <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
          <div class="text-muted mt-2">Loading...</div>
        </div>
      </div>
      <!-- Action form area (shown only when action required) -->
      <div id="modalViewLoadActions" style="display:none;">
        <div class="modal-footer flex-column align-items-stretch px-4 pb-4">
          <div id="modalActionContent"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
$(function(){
    // Initialize DataTable on all tab tables
    var dtOpts = { pageLength:25, order:[[6,'asc']], dom:'lfrtip' };
    if ($('#loadTable').length        && $.fn.DataTable) $('#loadTable').DataTable(dtOpts);
    if ($('#loadTablePending').length && $.fn.DataTable) $('#loadTablePending').DataTable(dtOpts);
    if ($('#loadTableApproved').length&& $.fn.DataTable) $('#loadTableApproved').DataTable(dtOpts);
    if ($('#loadTableRejected').length&& $.fn.DataTable) $('#loadTableRejected').DataTable(dtOpts);

    // Re-init DataTable when tab shown (fixes column widths)
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e){
        $.fn.DataTable && $.fn.DataTable.tables({visible:true,api:true}).columns.adjust();
    });
});

// ── Employee search filter ────────────────────────────────────
// empSearch handled inline in the form field

function setDays(days) {
    // Legacy - no longer used (kept for safety)
}

// Sync checkboxes → hidden input for a subject row
function syncDays(rowEl) {
    var checked = [];
    rowEl.querySelectorAll('.day-cb:checked').forEach(function(cb){ checked.push(cb.value); });
    var hidden = rowEl.querySelector('.days-val');
    if (hidden) hidden.value = checked.join('/');
    // Validate: mark row invalid if no day checked
    var dayCell = rowEl.querySelector('.days-cb-group');
    if (dayCell) dayCell.style.outline = checked.length ? '' : '1px solid red';
}

// Quick-day preset button
function qdays(btn, preset) {
    var row = btn.closest('tr');
    var days = preset ? preset.split(',') : [];
    row.querySelectorAll('.day-cb').forEach(function(cb){
        cb.checked = days.indexOf(cb.value) !== -1;
    });
    syncDays(row);
}

// Attach day-checkbox listeners to a row
function attachDayListeners(row) {
    row.querySelectorAll('.day-cb').forEach(function(cb){
        cb.addEventListener('change', function(){ syncDays(row); });
    });
}
// Attach to first row on load
$(document).ready(function(){
    var firstRow = document.querySelector('.subj-row[data-idx="0"]');
    if (firstRow) attachDayListeners(firstRow);
});

// ── Multi-subject table JS ───────────────────────────────────
var _subjIdx = 1;

function addSubjRow() {
    var idx = _subjIdx++;
    var tbody = document.getElementById('subjectRows');
    var tmpl = document.querySelector('.subj-row[data-idx="0"]');
    var row = tmpl.cloneNode(true);
    row.setAttribute('data-idx', idx);
    // Update input names
    row.querySelectorAll('[name]').forEach(function(el) {
        el.name = el.name.replace('[0]','['+idx+']');
    });
    // Reset text/number inputs
    row.querySelectorAll('input[type="text"],input[type="time"]').forEach(function(el){ el.value=''; });
    row.querySelectorAll('input[type="number"]').forEach(function(el){
        el.value = (el.name.indexOf('units')!==-1||el.name.indexOf('hours')!==-1) ? '3' : '0';
    });
    // Reset day checkboxes and hidden value
    row.querySelectorAll('.day-cb').forEach(function(cb){ cb.checked = false; });
    var hidden = row.querySelector('.days-val');
    if (hidden) hidden.value = '';
    // Update row number
    row.cells[0].textContent = tbody.rows.length + 1;
    // Show remove button
    row.querySelector('.btn-remove-subj').style.display = '';
    tbody.appendChild(row);
    // Attach listeners
    row.querySelectorAll('input[type="number"]').forEach(function(el) {
        el.addEventListener('input', updateTotals);
    });
    attachDayListeners(row);
    updateTotals();
}

function removeSubjRow(btn) {
    var row = btn.closest('tr');
    row.parentNode.removeChild(row);
    // Renumber
    document.querySelectorAll('#subjectRows tr').forEach(function(r, i) {
        r.cells[0].textContent = i + 1;
    });
    updateTotals();
}

function updateTotals() {
    var rows = document.querySelectorAll('#subjectRows .subj-row');
    var units = 0, hours = 0, students = 0, preps = 0;
    rows.forEach(function(r) {
        var u = parseFloat(r.querySelector('[name*="[units]"]').value) || 0;
        var h = parseFloat(r.querySelector('[name*="[hours]"]').value) || 0;
        var s = parseInt(r.querySelector('[name*="[students]"]').value) || 0;
        if (r.querySelector('[name*="[description]"]').value.trim()) preps++;
        units += u; hours += h; students += s;
    });
    document.getElementById('totalUnits').textContent    = units.toFixed(1);
    document.getElementById('totalHours').textContent    = hours.toFixed(1);
    document.getElementById('totalStudents').textContent = students;
    document.getElementById('totalPreps').textContent    = preps + ' preparation' + (preps !== 1 ? 's' : '');
}

// Attach listeners to first row inputs
$(document).ready(function(){
    document.querySelectorAll('#subjectRows input[type="number"]').forEach(function(el){
        el.addEventListener('input', updateTotals);
    });
    updateTotals();
});

// ── View Load Modal ──────────────────────────────────────────
$(document).on('click','.btn-view-load',function(){
    var lid = $(this).data('id');
    var act = $(this).data('act');
    $('#modalViewLoadBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><div class="text-muted mt-2">Loading...</div></div>');
    $('#modalViewLoadActions').hide();
    $.get('teaching_load_modal.php',{id:lid},function(html){
        $('#modalViewLoadBody').html(html);
        if(act=='1'){
            $('#modalViewLoadActions').show();
        }
    }).fail(function(){
        // Fallback: redirect to full page view
        window.location.href='teaching_load.php?view='+lid;
    });
});

// Enable sign button only when confirmation checkbox is checked
var tlCheck = document.getElementById('tlConfirmSign');
var tlBtn   = document.getElementById('tlSignBtn');
if (tlCheck && tlBtn) {
    tlCheck.addEventListener('change', function(){
        tlBtn.disabled = !this.checked;
    });
}

// Validate employee selected before submit
document.getElementById('createForm') && document.getElementById('createForm').addEventListener('submit', function(e) {
    var empId = document.getElementById('empHiddenId');
    if (!empId || !empId.value || empId.value === '0') {
        e.preventDefault();
        alert('Please select an employee using the search box before creating the teaching load.');
        document.getElementById('empSearchBox') && document.getElementById('empSearchBox').focus();
        return false;
    }
    // Sync all day checkboxes before submit
    document.querySelectorAll('.subj-row').forEach(function(row){ syncDays(row); });
    // Validate each row has at least one day
    var rows = document.querySelectorAll('.subj-row');
    var dayError = false;
    rows.forEach(function(row){
        var hidden = row.querySelector('.days-val');
        if (hidden && !hidden.value) dayError = true;
    });
    if (dayError) {
        e.preventDefault();
        alert('Please select at least one day for each subject row.');
        return false;
    }
});
// checkOffset handled via empSelect() in autocomplete

function toggleOffsetFields() {
    var cb = document.getElementById('hasOffset');
    document.getElementById('offsetFields').style.display = cb.checked ? 'block' : 'none';
}
</script>
</body>
</html>