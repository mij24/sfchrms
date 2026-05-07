<?php
// ============================================
// FILE: includes/sidebar.php — REORGANIZED
// Changes:
// - Employees nav: removed Classification & Status (moved to HR Setup)
//   kept Profile Requests & COE Generator here
// - Organization: added Work Locations
// - HR Setup: added Salary Grades tab, Work Locations link
// - Requests: consolidated Profile Requests + COE into one nav group
// ============================================
if (session_status() === PHP_SESSION_NONE) session_start();

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


    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent"
          data-widget="treeview" role="menu" data-accordion="false">

        <!-- DASHBOARD -->
        <li class="nav-item">
          <a href="dashboard.php" class="nav-link <?= sa('dashboard.php') ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
          </a>
        </li>
		<!--<li class="nav-item">
          <a href="dashboard.php" class="nav-link <?= sa('self-service.php') ?>">
            <i class="nav-icon fas fa-user-circle"></i><p>Self-Service</p>
          </a>
        </li>-->

        <!-- ══ SELF-SERVICE PORTAL ══ -->
        <!--<li class="nav-header">SELF-SERVICE PORTAL</li>-->

        <li class="nav-item <?= so(array('my_profile.php')) ?>">
          <a href="#" class="nav-link <?= sa('my_profile.php') ?>">
            <i class="nav-icon fas fa-user-circle"></i>
            <p>Self-Service <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="my_profile.php" class="nav-link <?= sa('my_profile.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>My Profile</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="my_schedule.php" class="nav-link <?= sa('my_schedule.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>My Schedule</p>
              </a>
            </li>

        <li class="nav-item">
          <a href="my_leave.php" class="nav-link <?= sa('my_leave.php') ?>">
            <i class="nav-icon fas fa-calendar-minus"></i><p>My Leave</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="my_payslips.php" class="nav-link <?= sa('my_payslips.php') ?>">
            <i class="nav-icon fas fa-file-invoice-dollar"></i><p>My Payslips</p>
          </a>
        </li>

        <li class="nav-item <?= so(array('my_attendance.php','attendance_selfservice.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('my_attendance.php','attendance_selfservice.php')) ?>">
            <i class="nav-icon fas fa-clock"></i>
            <p>My Attendance <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="my_attendance.php" class="nav-link <?= sa('my_attendance.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>View Attendance</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="attendance_selfservice.php" class="nav-link <?= sa('attendance_selfservice.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Clock In/Out</p>
              </a>
            </li>
          </ul>
        </li>

        <!-- ── My Requests (self-service) ── -->
        <li class="nav-item <?= so(array('my_requests.php','my_requests_history.php','my_requests_tracking.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('my_requests.php','my_requests_history.php','my_requests_tracking.php')) ?>">
            <i class="nav-icon fas fa-file-alt"></i>
            <p>My Requests <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="my_requests.php" class="nav-link <?= sa('my_requests.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>New Request</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="my_requests_history.php" class="nav-link <?= sa('my_requests_history.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Request History</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="my_requests_tracking.php" class="nav-link <?= sa('my_requests_tracking.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Status &amp; Tracking</p>
              </a>
            </li>
          </ul>
        </li>
			  
          </ul>
        </li>
		

        <!-- ══ CORE HR OPERATIONS ══ -->
        <?php if (is_hr()): ?>
        <li class="nav-header">CORE HR OPERATIONS</li>

        <!-- EMPLOYEES — no Classification/Status here (those are in HR Setup) -->
        <li class="nav-item <?= so(array('employees.php','add_employee.php','view_employee.php','edit_employee.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('employees.php','add_employee.php','view_employee.php','edit_employee.php')) ?>">
            <i class="nav-icon fas fa-users"></i>
            <p>Employees <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="employees.php" class="nav-link <?= sa('employees.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>All Employees</p>
              </a>
            </li>
            <?php if (is_hr_head()): ?>
            <li class="nav-item">
              <a href="add_employee.php" class="nav-link <?= sa('add_employee.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Add Employee</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>

        <!-- EMPLOYEE LIFECYCLE -->
        <li class="nav-item <?= so(array('onboarding.php','transfers_promotions.php','probationary.php','classification.php','employee_documents.php','employee_exit.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('onboarding.php','transfers_promotions.php','probationary.php','classification.php','employee_documents.php','employee_exit.php')) ?>">
            <i class="nav-icon fas fa-user-tie"></i>
            <p>Employee Lifecycle <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="onboarding.php" class="nav-link <?= sa('onboarding.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Onboarding</p>
              </a>
            </li>
			<li class="nav-item">
              <a href="transfers_promotions.php" class="nav-link <?= sa('transfers_promotions.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Transfer/Promotions</p>
              </a>
            </li>
			<li class="nav-item">
              <a href="employee_exit.php" class="nav-link <?= sa('employee_exit.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Offboarding</p>
              </a>
            </li>
            <!--<li class="nav-item">
              <a href="probationary.php" class="nav-link <?= sa('probationary.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Probationary Status</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="classification.php" class="nav-link <?= sa('classification.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Classification Status</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="employee_documents.php" class="nav-link <?= sa('employee_documents.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Employee Documents</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="employee_exit.php" class="nav-link <?= sa('employee_exit.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Exit / Termination</p>
              </a>
            </li>-->
          </ul>
        </li>

        <!-- ORGANIZATION STRUCTURE — includes Work Locations -->
        <li class="nav-item <?= so(array('departments.php','positions.php','work_locations.php','org_chart.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('departments.php','positions.php','work_locations.php','org_chart.php')) ?>">
            <i class="nav-icon fas fa-sitemap"></i>
            <p>Organization <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="departments.php" class="nav-link <?= sa('departments.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Departments</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="positions.php" class="nav-link <?= sa('positions.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Positions</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="work_locations.php" class="nav-link <?= sa('work_locations.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Work Locations</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="org_chart.php" class="nav-link <?= sa('org_chart.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Org Chart</p>
              </a>
            </li>
          </ul>
        </li>

        <!-- RECRUITMENT -->
        <li class="nav-item <?= so(array('job_openings.php','applicants.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('job_openings.php','applicants.php')) ?>">
            <i class="nav-icon fas fa-user-plus"></i>
            <p>Recruitment <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="job_openings.php" class="nav-link <?= sa('job_openings.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Job Openings</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="applicants.php" class="nav-link <?= sa('applicants.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Applicants</p>
              </a>
            </li>
          </ul>
        </li>

        <!-- ══ TIME & ATTENDANCE ══ -->
        <li class="nav-header">TIME &amp; ATTENDANCE</li>

        <li class="nav-item <?= so(array('attendance.php','attendance_log.php','biometrics_import.php','attendance_biometric.php','qr_scanner.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('attendance.php','attendance_log.php','biometrics_import.php','attendance_biometric.php','qr_scanner.php')) ?>">
            <i class="nav-icon fas fa-clock"></i>
            <p>Attendance <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="attendance.php" class="nav-link <?= sa('attendance.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Log Attendance</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="attendance_log.php" class="nav-link <?= sa('attendance_log.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Full Attendance Log</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="biometrics_import.php" class="nav-link <?= sa('biometrics_import.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Biometrics Import</p>
              </a>
            </li>
            <!--<li class="nav-item">
              <a href="attendance_biometric.php" class="nav-link <?= sa('attendance_biometric.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Biometric &amp; QR Mgmt</p>
              </a>
            </li>-->
            <li class="nav-item">
              <a href="qr_scanner.php" class="nav-link <?= sa('qr_scanner.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>QR Code Scanner</p>
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-item <?= so(array('leave_requests.php','leave_credits.php','leave_types.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('leave_requests.php','leave_credits.php','leave_types.php')) ?>">
            <i class="nav-icon fas fa-calendar-minus"></i>
            <p>Leave Management <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="leave_requests.php" class="nav-link <?= sa('leave_requests.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Leave Requests</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="leave_credits.php" class="nav-link <?= sa('leave_credits.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Leave Credits</p>
              </a>
            </li>
            <?php if (is_hr_head()): ?>
            <li class="nav-item">
              <a href="leave_types.php" class="nav-link <?= sa('leave_types.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Leave Types</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>

        <li class="nav-item <?= so(array('schedules.php','flex_schedule.php','offsetting.php','teaching_load.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('schedules.php','flex_schedule.php','offsetting.php','teaching_load.php')) ?>">
            <i class="nav-icon fas fa-calendar-alt"></i>
            <p>Scheduling <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="schedules.php" class="nav-link <?= sa('schedules.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Shift Schedules</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="flex_schedule.php" class="nav-link <?= sa('flex_schedule.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Flex Schedules</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="offsetting.php" class="nav-link <?= sa('offsetting.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Offsetting</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="teaching_load.php" class="nav-link <?= sa('teaching_load.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Teaching Load</p>
              </a>
            </li>
          </ul>
        </li>

        <!-- ══ PAYROLL ══ -->
        <li class="nav-header">PAYROLL</li>

        <li class="nav-item <?= so(array('payroll_bulk.php','payroll_release_bulk.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('payroll_bulk.php','payroll_release_bulk.php')) ?>">
            <i class="nav-icon fas fa-money-bill-wave"></i>
            <p>Payroll Processing <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="payroll_bulk.php" class="nav-link <?= sa('payroll_bulk.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Bulk Payroll</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="payroll_release_bulk.php" class="nav-link <?= sa('payroll_release_bulk.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Release Payroll</p>
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-item <?= so(array('payslips.php','thirteenth_month.php','separation_pay.php','allowances.php','loans.php','remittances.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('payslips.php','thirteenth_month.php','separation_pay.php','allowances.php','loans.php','remittances.php')) ?>">
            <i class="nav-icon fas fa-calculator"></i>
            <p>Payroll Components <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="payslips.php" class="nav-link <?= sa('payslips.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Payslips (Management)</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="thirteenth_month.php" class="nav-link <?= sa('thirteenth_month.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>13th Month Pay</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="separation_pay.php" class="nav-link <?= sa('separation_pay.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Separation Pay</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="allowances.php" class="nav-link <?= sa('allowances.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Allowances</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="loans.php" class="nav-link <?= sa('loans.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Loans &amp; Advances</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="remittances.php" class="nav-link <?= sa('remittances.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Remittances</p>
              </a>
            </li>
          </ul>
        </li>

        <!-- ══ PERFORMANCE & DEVELOPMENT ══ -->
        <li class="nav-header">PERFORMANCE &amp; DEVELOPMENT</li>

        <li class="nav-item">
          <a href="appraisals.php" class="nav-link <?= sa('appraisals.php') ?>">
            <i class="nav-icon fas fa-star"></i><p>Appraisals</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="training.php" class="nav-link <?= sa('training.php') ?>">
            <i class="nav-icon fas fa-graduation-cap"></i><p>Training &amp; Development</p>
          </a>
        </li>

        <!-- ══ COMPLIANCE & DISCIPLINE ══ -->
        <li class="nav-header">COMPLIANCE &amp; DISCIPLINE</li>

        <li class="nav-item">
          <a href="disciplinary.php" class="nav-link <?= sa('disciplinary.php') ?>">
            <i class="nav-icon fas fa-gavel"></i><p>Disciplinary Records</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="grievance.php" class="nav-link <?= sa('grievance.php') ?>">
            <i class="nav-icon fas fa-comment-alt"></i><p>Grievance Management</p>
          </a>
        </li>

        <!-- ══ REQUEST MANAGEMENT — Profile Requests + COE consolidated ══ -->
        <li class="nav-header">REQUEST MANAGEMENT</li>

        <li class="nav-item <?= so(array('profile_requests.php','coe.php','employee_requests.php')) ?>">
          <a href="#" class="nav-link <?= sa(array('profile_requests.php','coe.php','employee_requests.php')) ?>">
            <i class="nav-icon fas fa-file-invoice"></i>
            <p>Requests <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="profile_requests.php" class="nav-link <?= sa('profile_requests.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Profile Requests</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="coe.php" class="nav-link <?= sa('coe.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>COE Generator</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="employee_requests.php" class="nav-link <?= sa('employee_requests.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>All Requests</p>
              </a>
            </li>
          </ul>
        </li>

        <!-- ══ REPORTS & ANALYTICS ══ -->
        <li class="nav-header">REPORTS &amp; ANALYTICS</li>

        <li class="nav-item">
          <a href="analytics.php" class="nav-link <?= sa('analytics.php') ?>">
            <i class="nav-icon fas fa-chart-pie"></i><p>HR Analytics</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="announcements.php" class="nav-link <?= sa('announcements.php') ?>">
            <i class="nav-icon fas fa-bullhorn"></i><p>Announcements</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="holidays.php" class="nav-link <?= sa('holidays.php') ?>">
            <i class="nav-icon fas fa-calendar-star"></i><p>Holidays</p>
          </a>
        </li>

        <!-- ══ SETTINGS ══ -->
        <?php if (is_hr_head()): ?>
        <li class="nav-header">SETTINGS</li>

        <li class="nav-item">
          <a href="users.php" class="nav-link <?= sa('users.php') ?>">
            <i class="nav-icon fas fa-user-cog"></i><p>User Management</p>
          </a>
        </li>

        <!-- HR Setup: Classifications, Ranks, Types, Statuses, Salary Grades -->
        <li class="nav-item <?= so(array('hr_setup.php','leave_types.php','employee_id_generator.php')) ?>">
          <a href="#" class="nav-link <?= sa('hr_setup.php') ?>">
            <i class="nav-icon fas fa-sliders-h"></i>
            <p>HR Setup <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="hr_setup.php?tab=classifications" class="nav-link <?= ($cp==='hr_setup.php'&&(isset($_GET['tab'])&&$_GET['tab']==='classifications'))?'active':'' ?>">
                <i class="far fa-circle nav-icon"></i><p>Classifications</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="hr_setup.php?tab=ranks" class="nav-link <?= ($cp==='hr_setup.php'&&(isset($_GET['tab'])&&$_GET['tab']==='ranks'))?'active':'' ?>">
                <i class="far fa-circle nav-icon"></i><p>Ranks</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="hr_setup.php?tab=types" class="nav-link <?= ($cp==='hr_setup.php'&&(isset($_GET['tab'])&&$_GET['tab']==='types'))?'active':'' ?>">
                <i class="far fa-circle nav-icon"></i><p>Employment Types</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="hr_setup.php?tab=statuses" class="nav-link <?= ($cp==='hr_setup.php'&&(isset($_GET['tab'])&&$_GET['tab']==='statuses'))?'active':'' ?>">
                <i class="far fa-circle nav-icon"></i><p>Employment Statuses</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="hr_setup.php?tab=salary_grades" class="nav-link <?= ($cp==='hr_setup.php'&&(isset($_GET['tab'])&&$_GET['tab']==='salary_grades'))?'active':'' ?>">
                <i class="far fa-circle nav-icon"></i><p>Salary Grades</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="leave_types.php" class="nav-link <?= sa('leave_types.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Leave Types</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="employee_id_generator.php" class="nav-link <?= sa('employee_id_generator.php') ?>">
                <i class="far fa-circle nav-icon"></i><p>Employee ID Format</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (is_admin()): ?>
        <li class="nav-item <?= so(array('settings.php')) ?>">
          <a href="#" class="nav-link <?= sa('settings.php') ?>">
            <i class="nav-icon fas fa-cog"></i>
            <p>System Settings <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="settings.php?tab=company" class="nav-link"><?php // active per tab ?><i class="far fa-circle nav-icon"></i><p>Company Info</p></a>
            </li>
            <li class="nav-item">
              <a href="settings.php?tab=payroll" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Payroll Config</p></a>
            </li>
            <li class="nav-item">
              <a href="settings.php?tab=security" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Security</p></a>
            </li>
            <li class="nav-item">
              <a href="settings.php?tab=email" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Email (SMTP)</p></a>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="audit_log.php" class="nav-link <?= sa('audit_log.php') ?>">
            <i class="nav-icon fas fa-history"></i><p>Audit Log</p>
          </a>
        </li>
        <?php endif; ?>

        <?php if (is_superadmin()): ?>
        <li class="nav-item">
          <a href="backup.php" class="nav-link <?= sa('backup.php') ?>">
            <i class="nav-icon fas fa-database"></i><p>Backup &amp; Restore</p>
          </a>
        </li>
        <?php endif; ?>

        <?php endif; // is_hr ?>
        <li class="nav-header">&nbsp;</li>
      </ul>
    </nav>
  </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ !== 'undefined') {
        $('[data-toggle="dropdown"]').dropdown();
    }
});
</script>