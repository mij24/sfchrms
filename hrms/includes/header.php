<?php
// ============================================
// FILE: includes/header.php
// Shows: first name, single user dropdown,
//   notification bell + message icon
// ============================================
$_h_username  = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$_h_role      = isset($_SESSION['role'])     ? $_SESSION['role']     : '';
$_h_uid       = isset($_SESSION['user_id'])  ? (int)$_SESSION['user_id'] : 0;

// ── Get employee first name ───────────────────────────────────────
$_h_firstname = $_h_username;
$_h_emp_id    = 0;
if ($_h_uid) {
    try {
        $_h_urow = db_fetch($pdo,
            "SELECT u.employee_id, e.full_name
             FROM users u LEFT JOIN employees e ON u.employee_id=e.id
             WHERE u.id=?", array($_h_uid));
        if ($_h_urow) {
            $_h_emp_id = (int)(isset($_h_urow['employee_id']) ? $_h_urow['employee_id'] : 0);
            if (!empty($_h_urow['full_name'])) {
                $_h_parts     = explode(' ', trim($_h_urow['full_name']));
                $_h_firstname = $_h_parts[0];
            }
        }
    } catch(Exception $e) {}
}

// ── Notification count ────────────────────────────────────────────
$_h_notif_count = 0;
$_h_notifications = array();
try {
    // 1. Teaching loads awaiting THIS employee's review/signature
    if ($_h_emp_id) {
        $_h_tl_items = db_fetchall($pdo,
            "SELECT id, school_year, semester, created_at
             FROM teaching_loads
             WHERE employee_id=? AND status='For Faculty Review'
             ORDER BY created_at DESC LIMIT 3",
            array($_h_emp_id)) ?: array();
        foreach ($_h_tl_items as $tl) {
            $_h_notif_count++;
            $_h_notifications[] = array(
                'icon' => 'fa-chalkboard-teacher',
                'color'=> 'warning',
                'msg'  => 'Teaching load pending your review — '.$tl['school_year'].' '.$tl['semester'],
                'link' => 'my_inbox.php',
                'time' => $tl['created_at'],
            );
        }
    }

    // 2. Requester: MY OWN requests returned (Pending Clarification OR Returned)
    if ($_h_emp_id) {
        $_h_clarif_items = db_fetchall($pdo,
            "SELECT er.id, er.reference_no, er.rejection_reason, er.updated_at,
                    er.status, rt.name AS type_name
             FROM employee_requests er
             JOIN request_types rt ON er.request_type_id=rt.id
             WHERE er.employee_id=? AND er.status='Pending Clarification'
             ORDER BY er.updated_at DESC LIMIT 5",
            array($_h_emp_id)) ?: array();
        foreach ($_h_clarif_items as $ci) {
            $_h_notif_count++;
            $_h_label = $ci['status'] === 'Returned' ? 'Returned with note' : 'Clarification needed';
            $_h_notifications[] = array(
                'icon' => 'fa-exclamation-triangle',
                'color'=> 'warning',
                'msg'  => $_h_label.': '
                          .htmlspecialchars($ci['type_name'])
                          .' ('.$ci['reference_no'].')'
                          .($ci['rejection_reason']
                              ? ' — "'.htmlspecialchars(substr($ci['rejection_reason'],0,60)).'"'
                              : ''),
                'link' => 'my_approvals.php?tab=inprogress',
                'time' => $ci['updated_at'],
            );
        }
    }

    // 3. Approver roles: requests pending YOUR action
    $approver_roles = array('dept_secretary','exec_secretary','manager','academic_head',
                            'non_academic_head','registrar_head','vp_academic','vp_admin',
                            'president','hr','hr_head','admin','superadmin');
    if (in_array($_h_role, $approver_roles)) {
        $_h_req_items = db_fetchall($pdo,
            "SELECT er.id, er.reference_no, e.full_name, er.updated_at
             FROM employee_requests er
             JOIN employees e ON er.employee_id=e.id
             WHERE er.status='Pending'
             ORDER BY er.updated_at DESC LIMIT 3") ?: array();
        foreach ($_h_req_items as $ri) {
            $_h_notif_count++;
            $_h_notifications[] = array(
                'icon' => 'fa-file-alt',
                'color'=> 'info',
                'msg'  => $ri['reference_no'].' — '.htmlspecialchars($ri['full_name']).' awaiting approval',
                'link' => 'employee_requests.php',
                'time' => $ri['updated_at'],
            );
        }
        // Teaching loads awaiting this role
        $tl_status_map = array(
            'dept_secretary'   => 'For Faculty Review',
            'manager'          => 'For Dept Head Review',
            'academic_head'    => 'For Dept Head Review',
            'non_academic_head'=> 'For Dept Head Review',
            'registrar_head'   => 'For Registrar Verification',
            'vp_academic'      => 'For VP Academic Review',
            'vp_admin'         => 'For VP Academic Review',
            'president'        => 'For President Approval',
            'hr'               => 'Approved',
            'hr_head'          => 'Approved',
        );
        if (isset($tl_status_map[$_h_role])) {
            $_h_tl_pending = (int)db_value($pdo,
                "SELECT COUNT(*) FROM teaching_loads WHERE status=?",
                array($tl_status_map[$_h_role]));
            if ($_h_tl_pending > 0) {
                $_h_notif_count += $_h_tl_pending;
                $_h_notifications[] = array(
                    'icon' => 'fa-chalkboard-teacher',
                    'color'=> 'warning',
                    'msg'  => $_h_tl_pending.' teaching load(s) awaiting your action',
                    'link' => 'teaching_load.php',
                    'time' => date('Y-m-d H:i:s'),
                );
            }
        }
    }

    usort($_h_notifications, function($a,$b){ return strcmp($b['time'],$a['time']); });
    $_h_notifications = array_slice($_h_notifications, 0, 5);
} catch(Exception $e) {}

// ── Message count ─────────────────────────────────────────────────
$_h_msg_count = 0;
try {
    $pdo->query("SELECT 1 FROM messages LIMIT 1");
    $_h_msg_count = (int)db_value($pdo,
        "SELECT COUNT(*) FROM messages WHERE recipient_id=? AND is_read=0",
        array($_h_uid));
} catch(Exception $e) {}
?>
<nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom">

  <!-- Left: sidebar toggle + Home -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button">
        <i class="fas fa-bars"></i>
      </a>
    </li>
    <li class="nav-item d-none d-sm-inline-block">
      <a href="dashboard.php" class="nav-link">Home</a>
    </li>
  </ul>

  <!-- Right icons -->
  <ul class="navbar-nav ml-auto">

    <!-- Notification bell -->
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#" title="Notifications"
         style="position:relative;padding:8px 12px;">
        <i class="fas fa-bell" style="font-size:17px;color:#495057;"></i>
        <?php if ($_h_notif_count > 0): ?>
        <span style="position:absolute;top:5px;right:5px;background:#dc3545;color:#fff;
                     font-size:9px;font-weight:700;border-radius:10px;padding:1px 5px;
                     min-width:16px;text-align:center;line-height:14px;">
          <?= $_h_notif_count > 99 ? '99+' : $_h_notif_count ?>
        </span>
        <?php endif; ?>
      </a>
      <div class="dropdown-menu dropdown-menu-right"
           style="min-width:310px;max-width:350px;padding:0;border-radius:8px;
                  overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12);">
        <!-- Header -->
        <div style="background:#f8f9fa;padding:10px 16px;border-bottom:1px solid #dee2e6;
                    display:flex;align-items:center;justify-content:space-between;">
          <span style="font-weight:700;font-size:13px;">
            <i class="fas fa-bell mr-1 text-warning"></i>Notifications
          </span>
          <?php if ($_h_notif_count > 0): ?>
          <span class="badge badge-warning"><?= $_h_notif_count ?> new</span>
          <?php endif; ?>
        </div>
        <!-- Items -->
        <?php if (empty($_h_notifications)): ?>
        <div class="text-center text-muted py-4" style="font-size:12px;">
          <i class="fas fa-check-circle text-success fa-2x d-block mb-2"></i>
          No new notifications
        </div>
        <?php else: ?>
        <?php foreach($_h_notifications as $n): ?>
        <a href="<?= $n['link'] ?>" class="dropdown-item"
           style="padding:10px 16px;border-bottom:1px solid #f5f5f5;
                  display:flex;align-items:flex-start;gap:10px;white-space:normal;">
          <span style="width:32px;height:32px;border-radius:50%;flex-shrink:0;margin-top:1px;
                       display:flex;align-items:center;justify-content:center;
                       background:<?= $n['color']==='warning'?'#fff3cd':
                                     ($n['color']==='info'?'#d1ecf1':
                                     ($n['color']==='danger'?'#f8d7da':'#e2e3e5')) ?>;">
            <i class="fas <?= $n['icon'] ?> text-<?= $n['color'] ?>"
               style="font-size:12px;"></i>
          </span>
          <div style="min-width:0;flex:1;">
            <div style="font-size:12px;color:#212529;line-height:1.35;word-break:break-word;">
              <?= $n['msg'] ?>
            </div>
            <div style="font-size:10px;color:#adb5bd;margin-top:3px;">
              <?= date('M d, g:i A', strtotime($n['time'])) ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
        <div style="padding:8px 14px;border-top:1px solid #dee2e6;background:#f8f9fa;">
          <a href="my_inbox.php" style="font-size:11px;font-weight:600;color:#007bff;">
            View all notifications &rarr;
          </a>
        </div>
      </div>
    </li>

    <!-- Messages -->
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#" title="Messages"
         style="position:relative;padding:8px 12px;">
        <i class="fas fa-envelope" style="font-size:17px;color:#495057;"></i>
        <?php if ($_h_msg_count > 0): ?>
        <span style="position:absolute;top:5px;right:5px;background:#007bff;color:#fff;
                     font-size:9px;font-weight:700;border-radius:10px;padding:1px 5px;
                     min-width:16px;text-align:center;line-height:14px;">
          <?= $_h_msg_count > 99 ? '99+' : $_h_msg_count ?>
        </span>
        <?php endif; ?>
      </a>
      <div class="dropdown-menu dropdown-menu-right"
           style="min-width:270px;padding:0;border-radius:8px;
                  overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12);">
        <div style="background:#f8f9fa;padding:10px 16px;border-bottom:1px solid #dee2e6;
                    display:flex;align-items:center;justify-content:space-between;">
          <span style="font-weight:700;font-size:13px;">
            <i class="fas fa-envelope mr-1 text-primary"></i>Messages
          </span>
          <?php if ($_h_msg_count > 0): ?>
          <span class="badge badge-primary"><?= $_h_msg_count ?> unread</span>
          <?php endif; ?>
        </div>
        <div class="text-center py-4" style="font-size:12px;color:#6c757d;">
          <i class="fas fa-envelope-open-text fa-2x d-block mb-2" style="opacity:.3;"></i>
          <?= $_h_msg_count > 0
              ? '<strong style="color:#007bff;">'.$_h_msg_count.'</strong> unread message(s)'
              : 'No new messages' ?>
        </div>
        <div style="padding:8px 14px;border-top:1px solid #dee2e6;background:#f8f9fa;">
          <a href="messages.php" style="font-size:11px;font-weight:600;color:#007bff;">
            Open messages &rarr;
          </a>
        </div>
      </div>
    </li>

    <!-- Separator -->
    <li class="nav-item d-flex align-items-center px-1">
      <div style="width:1px;height:22px;background:#dee2e6;"></div>
    </li>

    <!-- User dropdown — SINGLE, no duplicates -->
    <li class="nav-item dropdown">
      <a class="nav-link d-flex align-items-center" data-toggle="dropdown" href="#"
         style="gap:8px;padding:6px 12px;cursor:pointer;">
        <div style="width:34px;height:34px;border-radius:50%;
                    background:linear-gradient(135deg,#007bff,#0056b3);
                    display:flex;align-items:center;justify-content:center;
                    color:#fff;font-weight:700;font-size:14px;flex-shrink:0;
                    box-shadow:0 2px 6px rgba(0,123,255,.4);">
          <?= strtoupper(substr($_h_firstname, 0, 1)) ?>
        </div>
        <span style="font-weight:600;font-size:14px;color:#343a40;max-width:120px;
                     overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          <?= htmlspecialchars($_h_firstname) ?>
        </span>
        <i class="fas fa-caret-down" style="font-size:11px;opacity:.5;"></i>
      </a>
      <div class="dropdown-menu dropdown-menu-right"
           style="min-width:220px;padding:0;border-radius:8px;
                  overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.13);">
        <!-- User info -->
        <div style="background:linear-gradient(135deg,#007bff,#0056b3);
                    padding:16px;color:#fff;">
          <div style="width:42px;height:42px;border-radius:50%;
                      background:rgba(255,255,255,.22);
                      display:flex;align-items:center;justify-content:center;
                      font-weight:700;font-size:18px;margin-bottom:8px;">
            <?= strtoupper(substr($_h_firstname, 0, 1)) ?>
          </div>
          <div style="font-weight:700;font-size:15px;line-height:1.2;">
            <?= htmlspecialchars($_h_firstname) ?>
          </div>
          <div style="font-size:11px;opacity:.7;margin-top:2px;">
            <?= htmlspecialchars($_h_username) ?>
          </div>
        </div>
        <!-- Menu -->
        <div style="padding:4px 0;">
          <a class="dropdown-item" href="my_profile.php"
             style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-user-circle text-primary" style="width:16px;font-size:14px;"></i>
            My Profile
          </a>
          <a class="dropdown-item" href="change_password.php"
             style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-key text-warning" style="width:16px;font-size:14px;"></i>
            Change Password
          </a>
          <a class="dropdown-item" href="my_requests.php"
             style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-file-alt text-info" style="width:16px;font-size:14px;"></i>
            New Request
          </a>
          <?php if (function_exists('is_hr') && is_hr()): ?>
          <a class="dropdown-item" href="settings.php"
             style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-cog text-secondary" style="width:16px;font-size:14px;"></i>
            Settings
          </a>
          <?php endif; ?>
          <div style="height:1px;background:#f0f0f0;margin:4px 0;"></div>
          <a class="dropdown-item" href="../logout.php"
             style="padding:10px 16px;font-size:13px;color:#dc3545;font-weight:600;
                    display:flex;align-items:center;gap:10px;">
            <i class="fas fa-sign-out-alt" style="width:16px;font-size:14px;"></i>
            Logout
          </a>
        </div>
      </div>
    </li>

  </ul>
</nav>