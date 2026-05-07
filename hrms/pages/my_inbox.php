<?php
// ============================================
// FILE: pages/my_inbox.php
// Three tabs: Alerts | Notifications | Messages
// ============================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$_tz_row = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('system_timezone','clock_delta_seconds')")->fetchAll(PDO::FETCH_ASSOC);
$_hrms_tz='Asia/Manila'; $_hrms_delta=0;
foreach($_tz_row as $_r){
    if($_r['setting_key']==='system_timezone'&&$_r['setting_value']) $_hrms_tz=$_r['setting_value'];
    if($_r['setting_key']==='clock_delta_seconds') $_hrms_delta=(int)$_r['setting_value'];
}
try{ date_default_timezone_set($_hrms_tz); }catch(Exception $e){ date_default_timezone_set('Asia/Manila'); }

$my_uid    = (int)$_SESSION['user_id'];
$my_role   = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';
$my_user   = db_fetch($pdo,"SELECT * FROM users WHERE id=?",array($my_uid));
$my_emp_id = $my_user ? (int)$my_user['employee_id'] : 0;
$today     = date('Y-m-d', time()+$_hrms_delta);

// ── POST: Faculty confirm / decline teaching load ─────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $act     = clean_string(isset($_POST['post_action']) ? $_POST['post_action'] : '');
    $load_id = (int)(isset($_POST['load_id']) ? $_POST['load_id'] : 0);
    $remarks = clean_string(isset($_POST['remarks']) ? $_POST['remarks'] : '', 500);

    if ($act==='faculty_confirm') {
        $load = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($load_id));
        if ($load && $load['status']==='For Faculty Review' && $load['employee_id']==$my_emp_id) {
            $signer = db_value($pdo,"SELECT e.full_name FROM users u LEFT JOIN employees e ON u.employee_id=e.id WHERE u.id=?",array($my_uid)) ?: $_SESSION['username'];
            db_run($pdo,"UPDATE teaching_loads SET status='For Dept Head Review',current_step=3 WHERE id=?",array($load_id));
            db_run($pdo,"INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks) VALUES (?,2,?,?,?,'Confirmed — Digitally Signed',?)",
                array($load_id,'Faculty Confirmation',$my_uid,$signer,$remarks));
            audit_log($pdo,'DIGITAL_SIGN','teaching_loads',$load_id,array('action'=>'faculty_confirmed','signer'=>$signer));
            prg_redirect('my_inbox.php','Teaching load confirmed. Forwarded to Department Head.');
        }
    }
    if ($act==='faculty_decline') {
        $load = db_fetch($pdo,"SELECT * FROM teaching_loads WHERE id=?",array($load_id));
        if ($load && $load['status']==='For Faculty Review' && $load['employee_id']==$my_emp_id && $remarks) {
            db_run($pdo,"UPDATE teaching_loads SET status='Returned',rejection_reason=?,current_step=1 WHERE id=?",array($remarks,$load_id));
            db_run($pdo,"INSERT INTO teaching_load_approvals (load_id,step,step_label,actor_user_id,actor_name,action,remarks) VALUES (?,2,?,?,?,'Declined',?)",
                array($load_id,'Faculty Review',$my_uid,$_SESSION['username'],$remarks));
            audit_log($pdo,'RETURN','teaching_loads',$load_id,array('action'=>'faculty_declined'));
            prg_redirect('my_inbox.php','Teaching load declined and returned to Department Secretary.');
        }
    }
    // Send message
    if ($act==='send_message') {
        $to_uid  = (int)(isset($_POST['to_user_id']) ? $_POST['to_user_id'] : 0);
        $subject = clean_string(isset($_POST['msg_subject']) ? $_POST['msg_subject'] : '', 200);
        $body    = clean_string(isset($_POST['msg_body']) ? $_POST['msg_body'] : '', 2000);
        if ($to_uid && $body) {
            try {
                db_run($pdo,"INSERT INTO messages (sender_id,recipient_id,subject,body,is_read,created_at) VALUES (?,?,?,?,0,NOW())",
                    array($my_uid,$to_uid,$subject,$body));
            } catch(Exception $e) {}

        }
        prg_redirect('my_inbox.php?tab=messages','Message sent.');
    }
}

// ── DATA: ALERTS — Teaching loads awaiting faculty review ─────
$tl_pending = array();
if ($my_emp_id) {
    try {
        $tl_pending = db_fetchall($pdo,
            "SELECT tl.*, tla.actor_name AS assigned_by
             FROM teaching_loads tl
             LEFT JOIN teaching_load_approvals tla ON tla.load_id=tl.id AND tla.step=1
             WHERE tl.employee_id=? AND tl.status='For Faculty Review'
             ORDER BY tl.created_at DESC",
            array($my_emp_id)) ?: array();
    } catch(Exception $e) {}
}

// Approver alerts (for managers, HR, VPs, etc.)
$approver_alerts = array();
$role_tl_status = array(
    'manager'=>'For Dept Head Review','academic_head'=>'For Dept Head Review',
    'non_academic_head'=>'For Dept Head Review','registrar_head'=>'For Registrar Verification',
    'vp_academic'=>'For VP Academic Review','vp_admin'=>'For VP Academic Review',
    'president'=>'For President Approval','hr'=>'Approved','hr_head'=>'Approved',
);
if (isset($role_tl_status[$my_role])) {
    try {
        $approver_alerts = db_fetchall($pdo,
            "SELECT tl.*, e.full_name FROM teaching_loads tl JOIN employees e ON tl.employee_id=e.id
             WHERE tl.status=? ORDER BY tl.created_at DESC LIMIT 10",
            array($role_tl_status[$my_role])) ?: array();
    } catch(Exception $e) {}
}
$total_alerts = count($tl_pending) + count($approver_alerts);

// ── DATA: NOTIFICATIONS — FYI items ──────────────────────────
$notifications = array();
// Leave approved/rejected
if ($my_emp_id) {
    try {
        $notif_requests = db_fetchall($pdo,
            "SELECT er.reference_no, er.status, er.updated_at, er.rejection_reason,
                    rt.name AS type_name
             FROM employee_requests er JOIN request_types rt ON er.request_type_id=rt.id
             WHERE er.employee_id=? AND er.status IN ('Approved','Denied','Rejected','Pending Clarification')
             AND er.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY er.updated_at DESC LIMIT 10",
            array($my_emp_id)) ?: array();
        foreach ($notif_requests as $nr) {
            $is_clarif  = ($nr['status'] === 'Pending Clarification');
            $is_returned= ($nr['status'] === 'Returned');
            if ($is_returned) {
                $notif_icon  = 'fa-undo';
                $notif_color = 'warning';
                $notif_title = 'Request returned by approver — '.$nr['type_name'];
                $notif_msg   = $nr['rejection_reason']
                    ? 'Note: "'.htmlspecialchars(substr($nr['rejection_reason'],0,80)).'"'
                    : 'Ref: '.$nr['reference_no'].' — check your Approvals for details.';
                $notif_link  = 'my_approvals.php?tab=inprogress';
            } elseif ($is_clarif) {
                $notif_icon  = 'fa-exclamation-triangle';
                $notif_color = 'warning';
                $notif_title = 'Clarification needed — '.$nr['type_name'];
                $notif_msg   = $nr['rejection_reason']
                    ? 'Note: "'.htmlspecialchars(substr($nr['rejection_reason'],0,80)).'"'
                    : 'Ref: '.$nr['reference_no'];
                $notif_link  = 'my_approvals.php?tab=inprogress';
            } elseif ($nr['status'] === 'Approved') {
                $notif_icon  = 'fa-check-circle';
                $notif_color = 'success';
                $notif_title = $nr['type_name'].' — Approved';
                $notif_msg   = 'Ref: '.$nr['reference_no'];
                $notif_link  = 'my_approvals.php?tab=approved';
            } else {
                $notif_icon  = 'fa-times-circle';
                $notif_color = 'danger';
                $notif_title = $nr['type_name'].' — '.$nr['status'];
                $notif_msg   = 'Ref: '.$nr['reference_no'];
                $notif_link  = 'my_approvals.php?tab=denied';
            }
            $notifications[] = array(
                'icon'  => $notif_icon,
                'color' => $notif_color,
                'title' => $notif_title,
                'msg'   => $notif_msg,
                'time'  => $nr['updated_at'],
                'link'  => $notif_link,
            );
        }
    } catch(Exception $e) {}
}
// Announcements as notifications
try {
    $announcements_notif = db_fetchall($pdo,
        "SELECT title, created_at FROM announcements WHERE is_active=1 ORDER BY created_at DESC LIMIT 5") ?: array();
    foreach ($announcements_notif as $an) {
        $notifications[] = array(
            'icon'=>'fa-bullhorn','color'=>'warning',
            'title'=>'Announcement: '.htmlspecialchars($an['title']),
            'msg'=>'','time'=>$an['created_at'],'link'=>'#',
        );
    }
} catch(Exception $e) {}
// Sort by time
usort($notifications, function($a,$b){ return strcmp($b['time'],$a['time']); });
$total_notif = count($notifications);

// ── DATA: MESSAGES ────────────────────────────────────────────
$messages_inbox = array(); $messages_sent = array(); $msg_unread = 0;
$messages_exist = false;
try {
    $pdo->query("SELECT 1 FROM messages LIMIT 1");
    $messages_exist = true;
    $messages_inbox = db_fetchall($pdo,
        "SELECT m.*, u.username AS sender_name, e.full_name AS sender_fullname
         FROM messages m JOIN users u ON m.sender_id=u.id
         LEFT JOIN employees e ON u.employee_id=e.id
         WHERE m.recipient_id=? ORDER BY m.created_at DESC LIMIT 20",
        array($my_uid)) ?: array();
    $msg_unread = (int)db_value($pdo,"SELECT COUNT(*) FROM messages WHERE recipient_id=? AND is_read=0",array($my_uid));
    // Mark as read
    if (!empty($messages_inbox)) {
        db_run($pdo,"UPDATE messages SET is_read=1 WHERE recipient_id=?",array($my_uid));
    }
    $messages_sent = db_fetchall($pdo,
        "SELECT m.*, u.username AS recipient_name, e.full_name AS recipient_fullname
         FROM messages m JOIN users u ON m.recipient_id=u.id
         LEFT JOIN employees e ON u.employee_id=e.id
         WHERE m.sender_id=? ORDER BY m.created_at DESC LIMIT 20",
        array($my_uid)) ?: array();
} catch(Exception $e) {}

// Users list for New Message To: dropdown
$all_users_for_msg = array();
try {
    $all_users_for_msg = db_fetchall($pdo,
        "SELECT u.id, u.username, e.full_name, d.name AS dept
         FROM users u LEFT JOIN employees e ON u.employee_id=e.id
         LEFT JOIN departments d ON e.department_id=d.id
         WHERE u.id != ? AND u.is_active=1 ORDER BY e.full_name, u.username",
        array($my_uid)) ?: array();
} catch(Exception $e) {}

// Active tab from URL
$active_tab = isset($_GET['tab']) ? clean_string($_GET['tab'],'20') : 'alerts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Inbox | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../assets/dist/css/custom.css">
  <style>
    .inbox-badge{position:absolute;top:-4px;right:-4px;font-size:9px;padding:2px 5px;border-radius:10px;}
    .alert-card{border-left:4px solid #dc3545;border-radius:0 8px 8px 0;padding:14px 16px;
      background:#fff;box-shadow:0 1px 6px rgba(0,0,0,.07);margin-bottom:12px;}
    .notif-card{border-left:4px solid;border-radius:0 8px 8px 0;padding:12px 16px;
      background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:10px;display:flex;align-items:flex-start;gap:12px;}
    .msg-card{border:1px solid #dee2e6;border-radius:8px;padding:12px 16px;
      background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:10px;cursor:pointer;transition:.15s;}
    .msg-card:hover{box-shadow:0 3px 10px rgba(0,0,0,.1);}
    .msg-card.unread{background:#f0f7ff;border-color:#007bff44;font-weight:600;}
    .msg-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;
      justify-content:center;font-weight:700;font-size:15px;flex-shrink:0;color:#fff;}
    .tab-count{font-size:9px;padding:2px 6px;border-radius:10px;margin-left:4px;vertical-align:middle;}
    .step-dot{width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;
      justify-content:center;font-size:10px;font-weight:700;flex-shrink:0;}
    .step-dot.done{background:#28a745;color:#fff;}
    .step-dot.active{background:#ffc107;color:#333;border:2px solid #e0a800;}
    .step-dot.pending{background:#dee2e6;color:#6c757d;}
    .step-dot.rejected{background:#dc3545;color:#fff;}
  </style>
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
<div class="content"><div class="container-fluid" style="padding-top:20px;">
  <?= prg_flash() ?>

  <!-- ══ THREE MAIN TABS ══ -->
  <div class="card">
    <div class="card-header p-0">
      <ul class="nav nav-tabs" id="inboxMainTabs" role="tablist">

        <!-- TAB 1: ALERTS 🚨 -->
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='alerts'?'active':'' ?>"
             data-toggle="tab" href="#tab-alerts" role="tab">
            <i class="fas fa-exclamation-circle text-danger mr-1"></i>
            <strong>Alerts</strong>
            <?php if ($total_alerts > 0): ?>
            <span class="badge badge-danger tab-count"><?= $total_alerts ?></span>
            <?php endif; ?>
          </a>
        </li>

        <!-- TAB 2: NOTIFICATIONS 🔔 -->
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='notifications'?'active':'' ?>"
             data-toggle="tab" href="#tab-notifications" role="tab">
            <i class="fas fa-bell text-warning mr-1"></i>
            <strong>Notifications</strong>
            <?php if ($total_notif > 0): ?>
            <span class="badge badge-warning tab-count"><?= $total_notif ?></span>
            <?php endif; ?>
          </a>
        </li>

        <!-- TAB 3: MESSAGES 💬 -->
        <li class="nav-item">
          <a class="nav-link <?= $active_tab==='messages'?'active':'' ?>"
             data-toggle="tab" href="#tab-messages" role="tab">
            <i class="fas fa-comments text-info mr-1"></i>
            <strong>Messages</strong>
            <?php if ($msg_unread > 0): ?>
            <span class="badge badge-info tab-count"><?= $msg_unread ?></span>
            <?php endif; ?>
          </a>
        </li>

      </ul>
    </div><!-- /.card-header -->

    <div class="card-body tab-content p-0">

      <!-- ══════════════════════════════════════════════ -->
      <!-- TAB 1: ALERTS — Urgent action required 🚨     -->
      <!-- ══════════════════════════════════════════════ -->
      <div class="tab-pane fade <?= $active_tab==='alerts'?'show active':'' ?>" id="tab-alerts" role="tabpanel">
        <div class="p-3">

          <?php if (empty($tl_pending) && empty($approver_alerts)): ?>
          <div class="text-center text-muted py-5">
            <i class="fas fa-check-circle fa-3x d-block mb-3 text-success" style="opacity:.5;"></i>
            <div style="font-size:15px;font-weight:600;">No urgent alerts</div>
            <div style="font-size:13px;margin-top:6px;">You're all caught up!</div>
          </div>

          <?php else: ?>

          <!-- Faculty: Teaching loads awaiting YOUR signature -->
          <?php if (!empty($tl_pending)): ?>
          <div class="mb-3">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#dc3545;margin-bottom:10px;">
              <i class="fas fa-signature mr-1"></i>Teaching Loads — Awaiting Your Digital Signature
            </div>
            <?php foreach ($tl_pending as $tl): ?>
            <div class="alert-card">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div style="font-weight:700;font-size:14px;">
                    <?= htmlspecialchars($tl['subject_title'] ?: 'Multiple Subjects') ?>
                    <?php if ($tl['subject_code']): ?>
                    <small class="text-muted">(<?= htmlspecialchars($tl['subject_code']) ?>)</small>
                    <?php endif; ?>
                  </div>
                  <div style="font-size:12px;color:#6c757d;margin-top:4px;">
                    <?= htmlspecialchars($tl['semester']) ?> &middot; <?= htmlspecialchars($tl['school_year']) ?>
                    &middot; <?= htmlspecialchars($tl['day_of_week'] ?: '—') ?>
                    &middot; <?= $tl['time_start'] ? date('h:i A',strtotime($tl['time_start'])) : '' ?>
                    <?= $tl['time_end'] ? '– '.date('h:i A',strtotime($tl['time_end'])) : '' ?>
                    &middot; <?= $tl['units'] ?> units
                  </div>
                  <div style="font-size:11px;margin-top:6px;">
                    <span class="badge badge-warning">Awaiting Your Signature</span>
                    <span class="text-muted ml-2"><?= date('M d, Y', strtotime($tl['created_at'])) ?></span>
                  </div>
                </div>
                <div class="d-flex flex-column" style="gap:6px;min-width:120px;align-items:flex-end;">
                  <button type="button" class="btn btn-success btn-sm"
                          data-toggle="modal" data-target="#modalSign<?= $tl['id'] ?>">
                    <i class="fas fa-signature mr-1"></i>Review & Sign
                  </button>
                  <a href="teaching_load.php?view=<?= $tl['id'] ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-eye mr-1"></i>View Full
                  </a>
                </div>
              </div>
            </div>

            <!-- Sign Modal for this TL -->
            <div class="modal fade" id="modalSign<?= $tl['id'] ?>" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-signature mr-2"></i>Review & Digitally Sign</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                  </div>
                  <div class="modal-body">
                    <table class="table table-sm table-borderless" style="font-size:13px;">
                      <tr><td class="text-muted" width="100">Subject</td><td><strong><?= htmlspecialchars($tl['subject_title'] ?: '—') ?></strong></td></tr>
                      <tr><td class="text-muted">Schedule</td><td><?= htmlspecialchars($tl['day_of_week'].' '.(  $tl['time_start']?date('h:i A',strtotime($tl['time_start'])):'').' – '.($tl['time_end']?date('h:i A',strtotime($tl['time_end'])):'')) ?></td></tr>
                      <tr><td class="text-muted">Room</td><td><?= htmlspecialchars($tl['room'] ?: '—') ?></td></tr>
                      <tr><td class="text-muted">Units</td><td><?= $tl['units'] ?></td></tr>
                      <tr><td class="text-muted">Period</td><td><?= htmlspecialchars($tl['semester'].' '.$tl['school_year']) ?></td></tr>
                    </table>
                    <form method="POST" action="" id="signForm<?= $tl['id'] ?>">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="post_action" value="faculty_confirm">
                      <input type="hidden" name="load_id" value="<?= $tl['id'] ?>">
                      <div class="form-group">
                        <label style="font-size:12px;">Remarks <small class="text-muted">(optional)</small></label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="Any notes..."></textarea>
                      </div>
                      <div class="custom-control custom-checkbox mb-3">
                        <input type="checkbox" class="custom-control-input" id="chkSign<?= $tl['id'] ?>" required>
                        <label class="custom-control-label" for="chkSign<?= $tl['id'] ?>" style="font-size:12px;">
                          I have reviewed my assigned schedule and affix my <strong>digital signature</strong> as confirmation.
                        </label>
                      </div>
                    </form>
                  </div>
                  <div class="modal-footer d-flex justify-content-between">
                    <!-- Decline -->
                    <form method="POST" action="">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="post_action" value="faculty_decline">
                      <input type="hidden" name="load_id" value="<?= $tl['id'] ?>">
                      <div class="input-group input-group-sm">
                        <input type="text" name="remarks" class="form-control" placeholder="Reason for decline (required)" required>
                        <div class="input-group-append">
                          <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Decline and return to Secretary?')">
                            <i class="fas fa-times mr-1"></i>Decline
                          </button>
                        </div>
                      </div>
                    </form>
                    <button type="submit" form="signForm<?= $tl['id'] ?>" class="btn btn-success">
                      <i class="fas fa-check mr-1"></i>Confirm & Sign
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Approver: items waiting for this role -->
          <?php if (!empty($approver_alerts)): ?>
          <div class="mb-3">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#dc3545;margin-bottom:10px;">
              <i class="fas fa-tasks mr-1"></i>Teaching Loads — Awaiting Your Action
            </div>
            <?php foreach ($approver_alerts as $al): ?>
            <div class="alert-card" style="border-color:#ff9800;">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($al['full_name']) ?></div>
                  <div style="font-size:12px;color:#6c757d;"><?= htmlspecialchars($al['subject_title'] ?: 'Multiple subjects') ?> &middot; <?= htmlspecialchars($al['semester'].' '.$al['school_year']) ?></div>
                  <span class="badge badge-warning mt-1" style="font-size:10px;"><?= htmlspecialchars($al['status']) ?></span>
                </div>
                <a href="teaching_load.php?view=<?= $al['id'] ?>" class="btn btn-warning btn-sm">
                  <i class="fas fa-tasks mr-1"></i>Act Now
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php endif; ?>
        </div>
      </div><!-- /#tab-alerts -->

      <!-- ══════════════════════════════════════════════ -->
      <!-- TAB 2: NOTIFICATIONS — Passive FYI 🔔         -->
      <!-- ══════════════════════════════════════════════ -->
      <div class="tab-pane fade <?= $active_tab==='notifications'?'show active':'' ?>" id="tab-notifications" role="tabpanel">
        <div class="p-3">
          <?php if (empty($notifications)): ?>
          <div class="text-center text-muted py-5">
            <i class="fas fa-bell-slash fa-3x d-block mb-3" style="opacity:.3;"></i>
            <div style="font-size:14px;">No notifications</div>
          </div>
          <?php else: ?>
          <?php foreach ($notifications as $n): ?>
          <div class="notif-card" style="border-color:<?= $n['color']==='success'?'#28a745':($n['color']==='danger'?'#dc3545':($n['color']==='warning'?'#ffc107':'#17a2b8')) ?>;">
            <div style="width:36px;height:36px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;
                        background:<?= $n['color']==='success'?'#d4edda':($n['color']==='danger'?'#f8d7da':($n['color']==='warning'?'#fff3cd':'#d1ecf1')) ?>;">
              <i class="fas <?= $n['icon'] ?> text-<?= $n['color'] ?>" style="font-size:14px;"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:13px;font-weight:600;"><?= $n['title'] ?></div>
              <?php if ($n['msg']): ?><div style="font-size:12px;color:#6c757d;"><?= $n['msg'] ?></div><?php endif; ?>
              <div style="font-size:10px;color:#adb5bd;margin-top:3px;"><?= date('M d, Y g:i A',strtotime($n['time'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div><!-- /#tab-notifications -->

      <!-- ══════════════════════════════════════════════ -->
      <!-- TAB 3: MESSAGES — Two-way communication 💬    -->
      <!-- ══════════════════════════════════════════════ -->
      <div class="tab-pane fade <?= $active_tab==='messages'?'show active':'' ?>" id="tab-messages" role="tabpanel">
        <div class="p-3">

          <!-- New Message button -->
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <span class="text-muted" style="font-size:13px;">
                <?= $msg_unread > 0 ? "<strong class='text-info'>{$msg_unread} unread</strong>" : 'All messages read' ?>
              </span>
            </div>
            <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#modalNewMessage">
              <i class="fas fa-pen mr-1"></i>New Message
            </button>
          </div>

          <?php if (!$messages_exist): ?>
          <div class="alert alert-info" style="font-size:13px;">
            <i class="fas fa-info-circle mr-2"></i>
            The messaging system is not yet set up. Please run the messages table SQL to enable this feature.
          </div>
          <?php elseif (empty($messages_inbox) && empty($messages_sent)): ?>
          <div class="text-center text-muted py-5">
            <i class="fas fa-envelope-open fa-3x d-block mb-3" style="opacity:.3;"></i>
            <div style="font-size:14px;">No messages yet</div>
            <button type="button" class="btn btn-info mt-3" data-toggle="modal" data-target="#modalNewMessage">
              <i class="fas fa-pen mr-1"></i>Send First Message
            </button>
          </div>
          <?php else: ?>

          <!-- Inbox/Sent toggle -->
          <ul class="nav nav-pills mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#msg-inbox">
                <i class="fas fa-inbox mr-1"></i>Inbox
                <span class="badge badge-info ml-1"><?= count($messages_inbox) ?></span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#msg-sent">
                <i class="fas fa-paper-plane mr-1"></i>Sent
                <span class="badge badge-secondary ml-1"><?= count($messages_sent) ?></span>
              </a>
            </li>
          </ul>

          <div class="tab-content">
            <!-- Inbox messages -->
            <div class="tab-pane fade show active" id="msg-inbox">
              <?php foreach ($messages_inbox as $msg):
                $sender_name = $msg['sender_fullname'] ?: $msg['sender_name'];
                $initial = strtoupper(substr($sender_name,0,1));
                $colors = array('#007bff','#28a745','#dc3545','#fd7e14','#6f42c1','#17a2b8');
                $bg = $colors[crc32($sender_name) % count($colors)];
              ?>
              <div class="msg-card <?= !$msg['is_read']?'unread':'' ?>"
                   onclick="showMessage(<?= $msg['id'] ?>,'<?= htmlspecialchars(addslashes($sender_name)) ?>','<?= htmlspecialchars(addslashes($msg['subject'])) ?>','<?= htmlspecialchars(addslashes($msg['body'])) ?>','<?= date('M d, Y g:i A',strtotime($msg['created_at'])) ?>')">
                <div class="d-flex align-items-start" style="gap:12px;">
                  <div class="msg-avatar" style="background:<?= $bg ?>;"><?= $initial ?></div>
                  <div style="flex:1;min-width:0;">
                    <div class="d-flex justify-content-between">
                      <span style="font-size:13px;"><?= htmlspecialchars($sender_name) ?></span>
                      <span style="font-size:11px;color:#6c757d;"><?= date('M d, g:i A',strtotime($msg['created_at'])) ?></span>
                    </div>
                    <?php if ($msg['subject']): ?>
                    <div style="font-size:12px;font-weight:600;margin-top:2px;"><?= htmlspecialchars($msg['subject']) ?></div>
                    <?php endif; ?>
                    <div style="font-size:12px;color:#6c757d;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                      <?= htmlspecialchars(substr($msg['body'],0,100)) ?>
                    </div>
                    <?php if (!$msg['is_read']): ?>
                    <span class="badge badge-info" style="font-size:9px;margin-top:3px;">New</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Sent messages -->
            <div class="tab-pane fade" id="msg-sent">
              <?php if (empty($messages_sent)): ?>
              <div class="text-center text-muted py-4" style="font-size:13px;">No sent messages.</div>
              <?php else: ?>
              <?php foreach ($messages_sent as $msg):
                $recipient_name = $msg['recipient_fullname'] ?: $msg['recipient_name'];
                $initial = strtoupper(substr($recipient_name,0,1));
                $colors = array('#007bff','#28a745','#dc3545','#fd7e14','#6f42c1','#17a2b8');
                $bg = $colors[crc32($recipient_name) % count($colors)];
              ?>
              <div class="msg-card"
                   onclick="showMessage(<?= $msg['id'] ?>,'To: <?= htmlspecialchars(addslashes($recipient_name)) ?>','<?= htmlspecialchars(addslashes($msg['subject'])) ?>','<?= htmlspecialchars(addslashes($msg['body'])) ?>','<?= date('M d, Y g:i A',strtotime($msg['created_at'])) ?>')">
                <div class="d-flex align-items-start" style="gap:12px;">
                  <div class="msg-avatar" style="background:<?= $bg ?>;"><?= $initial ?></div>
                  <div style="flex:1;min-width:0;">
                    <div class="d-flex justify-content-between">
                      <span style="font-size:13px;">To: <?= htmlspecialchars($recipient_name) ?></span>
                      <span style="font-size:11px;color:#6c757d;"><?= date('M d, g:i A',strtotime($msg['created_at'])) ?></span>
                    </div>
                    <?php if ($msg['subject']): ?>
                    <div style="font-size:12px;font-weight:600;margin-top:2px;"><?= htmlspecialchars($msg['subject']) ?></div>
                    <?php endif; ?>
                    <div style="font-size:12px;color:#6c757d;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                      <?= htmlspecialchars(substr($msg['body'],0,100)) ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div><!-- /.tab-content messages -->
          <?php endif; ?>
        </div>
      </div><!-- /#tab-messages -->

    </div><!-- /.card-body tab-content -->
  </div><!-- /.card -->

</div></div>
</div>

<!-- ══ Modal: View Message ══ -->
<div class="modal fade" id="modalViewMessage" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-info text-white py-2">
        <h6 class="modal-title mb-0"><i class="fas fa-envelope-open mr-2"></i><span id="msgViewFrom"></span></h6>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div style="font-size:12px;font-weight:700;color:#6c757d;margin-bottom:4px;" id="msgViewSubject"></div>
        <div style="font-size:13px;line-height:1.6;white-space:pre-wrap;" id="msgViewBody"></div>
        <div style="font-size:10px;color:#adb5bd;margin-top:10px;" id="msgViewTime"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-sm btn-info" onclick="replyToMessage()">
          <i class="fas fa-reply mr-1"></i>Reply
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══ Modal: New Message ══ -->
<div class="modal fade" id="modalNewMessage" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-info text-white py-2">
        <h6 class="modal-title mb-0"><i class="fas fa-pen mr-2"></i>New Message</h6>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="post_action" value="send_message">
        <div class="modal-body">
          <div class="form-group mb-2">
            <label style="font-size:12px;font-weight:600;">To <span class="text-danger">*</span></label>
            <select name="to_user_id" id="msgToUser" class="form-control form-control-sm" required>
              <option value="">— Select recipient —</option>
              <?php foreach ($all_users_for_msg as $u): ?>
              <option value="<?= $u['id'] ?>">
                <?= htmlspecialchars($u['full_name'] ?: $u['username']) ?>
                <?php if ($u['dept']): ?>(<?= htmlspecialchars($u['dept']) ?>)<?php endif; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-2">
            <label style="font-size:12px;font-weight:600;">Subject</label>
            <input type="text" name="msg_subject" id="msgSubject" class="form-control form-control-sm" placeholder="Optional subject...">
          </div>
          <div class="form-group mb-0">
            <label style="font-size:12px;font-weight:600;">Message <span class="text-danger">*</span></label>
            <textarea name="msg_body" class="form-control form-control-sm" rows="5"
                      placeholder="Type your message here..." required></textarea>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-info">
            <i class="fas fa-paper-plane mr-1"></i>Send Message
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
function showMessage(id, from, subject, body, time) {
    document.getElementById('msgViewFrom').textContent    = from;
    document.getElementById('msgViewSubject').textContent = subject || '(No subject)';
    document.getElementById('msgViewBody').textContent    = body;
    document.getElementById('msgViewTime').textContent    = time;
    window._replyTo = from;
    window._replySubject = subject ? 'Re: '+subject : '';
    $('#modalViewMessage').modal('show');
}
function replyToMessage() {
    $('#modalViewMessage').modal('hide');
    setTimeout(function(){
        document.getElementById('msgSubject').value = window._replySubject || '';
        $('#modalNewMessage').modal('show');
    }, 400);
}
// Set active tab from URL on load
$(document).ready(function(){
    var hash = '<?= addslashes($active_tab) ?>';
    if (hash && hash !== 'alerts') {
        $('#inboxMainTabs a[href="#tab-'+hash+'"]').tab('show');
    }
});
</script>
</body>
</html>