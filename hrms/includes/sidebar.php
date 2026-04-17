<?php
// ============================================
// FILE: includes/sidebar.php — FINAL v3
// All new pages linked + HR Head sees settings
// data-accordion="false" keeps treeviews open
// ============================================
$cp       = basename($_SERVER['PHP_SELF']);
$role     = isset($_SESSION['role'])     ? $_SESSION['role']     : 'employee';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

function sa($pages) {
    $c = basename($_SERVER['PHP_SELF']);
    return in_array($c, is_array($pages) ? $pages : array($pages)) ? 'active' : '';
}
function so($pages) {
    $c = basename($_SERVER['PHP_SELF']);
    return in_array($c, is_array($pages) ? $pages : array($pages)) ? 'menu-open' : '';
}
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <a href="dashboard.php" class="brand-link text-center">
    <span class="brand-text font-weight-bold" style="font-size:18px;letter-spacing:1px;">
      SFC|<span style="color:#74c0fc;">HRMS</span>
    </span>
  </a>

  <div class="sidebar">
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="image">
        <div style="width:34px;height:34px;border-radius:50%;background:#4a90d9;
                    display:flex;align-items:center;justify-content:center;
                    font-weight:600;font-size:13px;color:#fff;">
          <?= strtoupper(substr($username, 0, 2)) ?>
        </div>
      </div>
      <div class="info">
        <a href="my_profile.php" class="d-block"><?= htmlspecialchars($username) ?></a>
        <small style="color:#aaa;font-size:11px;"><?= ucwords(str_replace('_',' ',$role)) ?></small>
      </div>
    </div>

    <nav class="mt-2">
      <!-- data-accordion="false" = multiple menus can stay open -->
      <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent"
          data-widget="treeview" role="menu" data-accordion="false">

        <!-- Dashboard -->
        <li class="nav-item">
          <a href="dashboard.php" class="nav-link <?= sa('dashboard.php') ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
          </a>
        </li>

        <!-- ── MY PORTAL (every logged-in user) ── -->
        <li class="nav-header">Self-Service</li>
        <li class="nav-item <?= so(array('my_profile.php','change_password.php','upload_photo.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('my_profile.php','change_password.php')) ?>">
            <i class="nav-icon fas fa-user-circle"></i>
            <p>My account <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="my_profile.php"      class="nav-link <?= sa('my_profile.php') ?>"><i class="far fa-circle nav-icon"></i><p>My profile</p></a></li>
            <li class="nav-item"><a href="change_password.php" class="nav-link <?= sa('change_password.php') ?>"><i class="far fa-circle nav-icon"></i><p>Change password</p></a></li>
          </ul>
        </li>
        <li class="nav-item"><a href="my_leave.php"       class="nav-link <?= sa('my_leave.php') ?>"><i class="nav-icon fas fa-calendar-minus"></i><p>My leave</p></a></li>
        <li class="nav-item"><a href="my_payslips.php"    class="nav-link <?= sa('my_payslips.php') ?>"><i class="nav-icon fas fa-file-invoice-dollar"></i><p>My payslips</p></a></li>
        <li class="nav-item"><a href="my_attendance.php"  class="nav-link <?= sa('my_attendance.php') ?>"><i class="nav-icon fas fa-clock"></i><p>My attendance</p></a></li>

        <?php if (is_hr()): ?>
        <!-- ══════════════════════════════════════════ -->
        <!-- CORE HR (HR Staff and above)              -->
        <!-- ══════════════════════════════════════════ -->
        <li class="nav-header">CORE HR</li>

        <!-- Employees -->
        <li class="nav-item <?= so(array('employees.php','add_employee.php','view_employee.php','edit_employee.php','delete_employee.php','profile_requests.php','onboarding.php','coe.php','classification.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('employees.php','add_employee.php','view_employee.php','edit_employee.php')) ?>">
            <i class="nav-icon fas fa-users"></i>
            <p>Employees <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="employees.php"       class="nav-link <?= sa('employees.php') ?>"><i class="far fa-circle nav-icon"></i><p>All employees</p></a></li>
            <?php if (is_hr_head()): ?>
            <li class="nav-item"><a href="add_employee.php"    class="nav-link <?= sa('add_employee.php') ?>"><i class="far fa-circle nav-icon"></i><p>Add employee</p></a></li>
            <?php endif; ?>
            <li class="nav-item"><a href="classification.php"  class="nav-link <?= sa('classification.php') ?>"><i class="far fa-circle nav-icon"></i><p>Classification &amp; status</p></a></li>
            <li class="nav-item"><a href="profile_requests.php" class="nav-link <?= sa('profile_requests.php') ?>"><i class="far fa-circle nav-icon"></i><p>Profile requests</p></a></li>
            <li class="nav-item"><a href="onboarding.php"      class="nav-link <?= sa('onboarding.php') ?>"><i class="far fa-circle nav-icon"></i><p>Onboarding</p></a></li>
            <li class="nav-item"><a href="coe.php"             class="nav-link <?= sa('coe.php') ?>"><i class="far fa-circle nav-icon"></i><p>COE generator</p></a></li>
          </ul>
        </li>

        <!-- Org Structure -->
        <li class="nav-item <?= so(array('departments.php','positions.php','org_chart.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('departments.php','positions.php','org_chart.php')) ?>">
            <i class="nav-icon fas fa-sitemap"></i>
            <p>Org structure <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="departments.php" class="nav-link <?= sa('departments.php') ?>"><i class="far fa-circle nav-icon"></i><p>Departments</p></a></li>
            <li class="nav-item"><a href="positions.php"   class="nav-link <?= sa('positions.php') ?>"><i class="far fa-circle nav-icon"></i><p>Positions</p></a></li>
            <li class="nav-item"><a href="org_chart.php"   class="nav-link <?= sa('org_chart.php') ?>"><i class="far fa-circle nav-icon"></i><p>Org chart</p></a></li>
          </ul>
        </li>

        <!-- Recruitment -->
        <li class="nav-item <?= so(array('job_openings.php','applicants.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('job_openings.php','applicants.php')) ?>">
            <i class="nav-icon fas fa-user-plus"></i>
            <p>Recruitment <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="job_openings.php" class="nav-link <?= sa('job_openings.php') ?>"><i class="far fa-circle nav-icon"></i><p>Job openings</p></a></li>
            <li class="nav-item"><a href="applicants.php"   class="nav-link <?= sa('applicants.php') ?>"><i class="far fa-circle nav-icon"></i><p>Applicants</p></a></li>
          </ul>
        </li>

        <!-- Time & Attendance -->
        <li class="nav-header">TIME &amp; ATTENDANCE</li>
        <li class="nav-item <?= so(array('attendance.php','attendance_log.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('attendance.php','attendance_log.php')) ?>">
            <i class="nav-icon fas fa-clock"></i>
            <p>Attendance <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="attendance.php"     class="nav-link <?= sa('attendance.php') ?>"><i class="far fa-circle nav-icon"></i><p>Log attendance</p></a></li>
            <li class="nav-item"><a href="attendance_log.php" class="nav-link <?= sa('attendance_log.php') ?>"><i class="far fa-circle nav-icon"></i><p>Full log</p></a></li>
            <li class="nav-item"><a href="biometrics_import.php" class="nav-link <?= sa('biometrics_import.php') ?>"><i class="far fa-circle nav-icon"></i><p>Biometrics import</p></a></li>
          </ul>
        </li>

        <li class="nav-item <?= so(array('leave_requests.php','leave_credits.php','leave_types.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('leave_requests.php','leave_credits.php','leave_types.php')) ?>">
            <i class="nav-icon fas fa-calendar-minus"></i>
            <p>Leave <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="leave_requests.php" class="nav-link <?= sa('leave_requests.php') ?>"><i class="far fa-circle nav-icon"></i><p>Leave requests</p></a></li>
            <li class="nav-item"><a href="leave_credits.php"  class="nav-link <?= sa('leave_credits.php') ?>"><i class="far fa-circle nav-icon"></i><p>Leave credits</p></a></li>
            <?php if (is_hr_head()): ?>
            <li class="nav-item"><a href="leave_types.php"    class="nav-link <?= sa('leave_types.php') ?>"><i class="far fa-circle nav-icon"></i><p>Leave types</p></a></li>
            <?php endif; ?>
          </ul>
        </li>

        <li class="nav-item <?= so(array('schedules.php','flex_schedule.php','offsetting.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('schedules.php','flex_schedule.php','offsetting.php')) ?>">
            <i class="nav-icon fas fa-calendar-alt"></i>
            <p>Scheduling <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="schedules.php"     class="nav-link <?= sa('schedules.php') ?>"><i class="far fa-circle nav-icon"></i><p>Shift schedules</p></a></li>
            <li class="nav-item"><a href="flex_schedule.php" class="nav-link <?= sa('flex_schedule.php') ?>"><i class="far fa-circle nav-icon"></i><p>Flex schedules</p></a></li>
            <li class="nav-item"><a href="offsetting.php"    class="nav-link <?= sa('offsetting.php') ?>"><i class="far fa-circle nav-icon"></i><p>Offsetting</p></a></li>
          </ul>
        </li>

        <!-- Payroll -->
        <li class="nav-header">PAYROLL</li>
        <li class="nav-item <?= so(array('payroll.php','payroll_bulk.php','payslips.php','thirteenth_month.php','remittances.php','separation_pay.php','loans.php','allowances.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('payroll.php','payroll_bulk.php','payslips.php','thirteenth_month.php','remittances.php')) ?>">
            <i class="nav-icon fas fa-money-bill-wave"></i>
            <p>Payroll <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="payroll.php"          class="nav-link <?= sa('payroll.php') ?>"><i class="far fa-circle nav-icon"></i><p>Single payroll</p></a></li>
            <li class="nav-item"><a href="payroll_bulk.php"     class="nav-link <?= sa('payroll_bulk.php') ?>"><i class="far fa-circle nav-icon"></i><p>Bulk payroll</p></a></li>
            <li class="nav-item"><a href="payslips.php"         class="nav-link <?= sa('payslips.php') ?>"><i class="far fa-circle nav-icon"></i><p>Payslips</p></a></li>
            <li class="nav-item"><a href="thirteenth_month.php" class="nav-link <?= sa('thirteenth_month.php') ?>"><i class="far fa-circle nav-icon"></i><p>13th month</p></a></li>
            <li class="nav-item"><a href="remittances.php"      class="nav-link <?= sa('remittances.php') ?>"><i class="far fa-circle nav-icon"></i><p>Remittances</p></a></li>
            <li class="nav-item"><a href="separation_pay.php"   class="nav-link <?= sa('separation_pay.php') ?>"><i class="far fa-circle nav-icon"></i><p>Separation pay</p></a></li>
            <li class="nav-item"><a href="loans.php"            class="nav-link <?= sa('loans.php') ?>"><i class="far fa-circle nav-icon"></i><p>Loans &amp; advances</p></a></li>
            <li class="nav-item"><a href="allowances.php"       class="nav-link <?= sa('allowances.php') ?>"><i class="far fa-circle nav-icon"></i><p>Allowances</p></a></li>
          </ul>
        </li>

        <!-- Performance -->
        <li class="nav-header">PERFORMANCE</li>
        <li class="nav-item"><a href="appraisals.php" class="nav-link <?= sa('appraisals.php') ?>"><i class="nav-icon fas fa-star"></i><p>Appraisals</p></a></li>
        <li class="nav-item"><a href="training.php"   class="nav-link <?= sa('training.php') ?>"><i class="nav-icon fas fa-graduation-cap"></i><p>Training</p></a></li>

        <!-- Compliance -->
        <li class="nav-header">COMPLIANCE</li>
        <li class="nav-item"><a href="disciplinary.php" class="nav-link <?= sa('disciplinary.php') ?>"><i class="nav-icon fas fa-gavel"></i><p>Disciplinary</p></a></li>
        <li class="nav-item"><a href="grievance.php"    class="nav-link <?= sa('grievance.php') ?>"><i class="nav-icon fas fa-comment-alt"></i><p>Grievance</p></a></li>

        <!-- Reports -->
        <li class="nav-header">REPORTS &amp; ADMIN</li>
        <li class="nav-item"><a href="reports.php"       class="nav-link <?= sa('reports.php') ?>"><i class="nav-icon fas fa-chart-bar"></i><p>Reports</p></a></li>
        <li class="nav-item"><a href="analytics.php"     class="nav-link <?= sa('analytics.php') ?>"><i class="nav-icon fas fa-chart-pie"></i><p>Analytics</p></a></li>
        <li class="nav-item"><a href="announcements.php" class="nav-link <?= sa('announcements.php') ?>"><i class="nav-icon fas fa-bullhorn"></i><p>Announcements</p></a></li>
        <li class="nav-item"><a href="holidays.php"      class="nav-link <?= sa('holidays.php') ?>"><i class="nav-icon fas fa-calendar-star"></i><p>Holidays</p></a></li>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- SETTINGS — HR Head sees HR Setup + Users           -->
        <!--             Admin sees everything above +           -->
        <!--             System Settings + Audit                 -->
        <!--             Superadmin sees everything + Backup     -->
        <!-- ═══════════════════════════════════════════════════ -->
        <?php if (is_hr_head()): ?>
        <li class="nav-header">SETTINGS</li>

        <!-- HR Head: User management + HR Setup -->
        <li class="nav-item"><a href="users.php" class="nav-link <?= sa('users.php') ?>">
          <i class="nav-icon fas fa-user-cog"></i><p>User management</p></a>
        </li>

        <!-- HR Setup — Classifications, Ranks, Types, Statuses -->
        <li class="nav-item <?= so(array('hr_setup.php','leave_types.php','employee_id_generator.php')) ?>">
          <a href="#" class="nav-link <?= sa('hr_setup.php') ?>">
            <i class="nav-icon fas fa-sliders-h"></i>
            <p>HR setup <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="hr_setup.php?tab=classifications" class="nav-link <?= ($cp==='hr_setup.php'&&(isset($_GET['tab'])&&$_GET['tab']==='classifications'))?'active':'' ?>"><i class="far fa-circle nav-icon"></i><p>Classifications</p></a></li>
            <li class="nav-item"><a href="hr_setup.php?tab=ranks"           class="nav-link <?= ($cp==='hr_setup.php'&&(isset($_GET['tab'])&&$_GET['tab']==='ranks'))?'active':'' ?>"><i class="far fa-circle nav-icon"></i><p>Ranks</p></a></li>
            <li class="nav-item"><a href="hr_setup.php?tab=types"           class="nav-link <?= ($cp==='hr_setup.php'&&(isset($_GET['tab'])&&$_GET['tab']==='types'))?'active':'' ?>"><i class="far fa-circle nav-icon"></i><p>Employment types</p></a></li>
            <li class="nav-item"><a href="hr_setup.php?tab=statuses"        class="nav-link <?= ($cp==='hr_setup.php'&&(isset($_GET['tab'])&&$_GET['tab']==='statuses'))?'active':'' ?>"><i class="far fa-circle nav-icon"></i><p>Employment statuses</p></a></li>
            <li class="nav-item"><a href="leave_types.php"                  class="nav-link <?= sa('leave_types.php') ?>"><i class="far fa-circle nav-icon"></i><p>Leave types</p></a></li>
            <li class="nav-item"><a href="employee_id_generator.php"        class="nav-link <?= sa('employee_id_generator.php') ?>"><i class="far fa-circle nav-icon"></i><p>Employee ID format</p></a></li>
          </ul>
        </li>
        <?php endif; // is_hr_head ?>

        <?php if (is_admin()): ?>
        <!-- Admin: everything HR Head has + System Settings + Audit -->
        <li class="nav-item <?= so(array('settings.php')) ?>">
          <a href="#" class="nav-link <?= sa('settings.php') ?>">
            <i class="nav-icon fas fa-cog"></i>
            <p>System settings <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="settings.php?tab=company"  class="nav-link"><i class="far fa-circle nav-icon"></i><p>Company info</p></a></li>
            <li class="nav-item"><a href="settings.php?tab=payroll"  class="nav-link"><i class="far fa-circle nav-icon"></i><p>Payroll config</p></a></li>
            <li class="nav-item"><a href="settings.php?tab=security" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Security</p></a></li>
            <li class="nav-item"><a href="settings.php?tab=email"    class="nav-link"><i class="far fa-circle nav-icon"></i><p>Email (SMTP)</p></a></li>
          </ul>
        </li>
        <li class="nav-item"><a href="audit_log.php" class="nav-link <?= sa('audit_log.php') ?>">
          <i class="nav-icon fas fa-history"></i><p>Audit log</p></a>
        </li>
        <?php endif; // is_admin ?>

        <?php if (is_superadmin()): ?>
        <!-- Superadmin only: Backup -->
        <li class="nav-item"><a href="backup.php" class="nav-link <?= sa('backup.php') ?>">
          <i class="nav-icon fas fa-database"></i><p>Backup &amp; restore</p></a>
        </li>
        <?php endif; // is_superadmin ?>

        <?php endif; // is_hr ?>

        <!-- Logout (always shown) -->
        <li class="nav-header">&nbsp;</li>
        <li class="nav-item">
          <a href="../logout.php" class="nav-link" style="color:#ff6b6b;">
            <i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p>
          </a>
        </li>

      </ul>
    </nav>
  </div>
</aside>