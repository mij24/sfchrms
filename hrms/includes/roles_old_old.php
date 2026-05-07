<?php
// ============================================
// FILE: includes/roles.php — FINAL
// Added: clinic_head, clinic_staff
// Superadmin bypasses ALL page restrictions
// All is_*() helpers defined
// ============================================

define('ROLE_EMPLOYEE',         1);
define('ROLE_MANAGER',          2);
define('ROLE_HR',               3);
define('ROLE_CLINIC_STAFF',     3); // same level as HR — can process sick leave
define('ROLE_NON_ACADEMIC_HEAD',4);
define('ROLE_ACADEMIC_HEAD',    4);
define('ROLE_CLINIC_HEAD',      4); // approves sick leave at clinic level
define('ROLE_PRESIDENT',        5);
define('ROLE_BOARD_DIRECTOR',   5);
define('ROLE_HR_HEAD',          6);
define('ROLE_ADMIN',            7);
define('ROLE_SUPERADMIN',       8);

$ROLE_LEVELS = array(
    'employee'          => ROLE_EMPLOYEE,
    'manager'           => ROLE_MANAGER,
    'hr'                => ROLE_HR,
    'clinic_staff'      => ROLE_CLINIC_STAFF,
    'non_academic_head' => ROLE_NON_ACADEMIC_HEAD,
    'academic_head'     => ROLE_ACADEMIC_HEAD,
    'clinic_head'       => ROLE_CLINIC_HEAD,
    'president'         => ROLE_PRESIDENT,
    'board_director'    => ROLE_BOARD_DIRECTOR,
    'hr_head'           => ROLE_HR_HEAD,
    'admin'             => ROLE_ADMIN,
    'superadmin'        => ROLE_SUPERADMIN,
);

$ROLE_LABELS = array(
    'superadmin'        => 'Super Admin',
    'admin'             => 'Admin',
    'hr_head'           => 'HR Head',
    'board_director'    => 'Board of Directors',
    'president'         => 'President',
    'academic_head'     => 'Academic Head',
    'non_academic_head' => 'Non-Academic Head',
    'clinic_head'       => 'Clinic Head',
    'manager'           => 'Manager',
    'hr'                => 'HR Staff',
    'clinic_staff'      => 'Clinic Staff',
    'employee'          => 'Employee',
);

$ROLE_COLORS = array(
    'superadmin'        => 'danger',
    'admin'             => 'danger',
    'hr_head'           => 'primary',
    'board_director'    => 'dark',
    'president'         => 'dark',
    'academic_head'     => 'info',
    'non_academic_head' => 'info',
    'clinic_head'       => 'success',
    'manager'           => 'warning',
    'hr'                => 'secondary',
    'clinic_staff'      => 'success',
    'employee'          => 'light',
);

// Pages that require an employee record to function
// Superadmin/Admin are EXEMPT from these restrictions
$EMPLOYEE_LINKED_PAGES = array(
    'my_profile.php','my_leave.php','my_payslips.php','my_attendance.php',
    'upload_photo.php','my_schedule.php'
);

// Page minimum role requirements
$PAGE_ROLES = array(
    'dashboard.php'              => ROLE_EMPLOYEE,
    'employees.php'              => ROLE_HR,
    'add_employee.php'           => ROLE_HR_HEAD,
    'edit_employee.php'          => ROLE_HR,
    'delete_employee.php'        => ROLE_ADMIN,
    'view_employee.php'          => ROLE_MANAGER,
    'upload_document.php'        => ROLE_HR,
    'upload_photo.php'           => ROLE_EMPLOYEE,
    'delete_document.php'        => ROLE_HR,
    'departments.php'            => ROLE_HR_HEAD,
    'positions.php'              => ROLE_HR_HEAD,
    'org_chart.php'              => ROLE_EMPLOYEE,
    'job_openings.php'           => ROLE_HR,
    'applicants.php'             => ROLE_HR,
    'attendance.php'             => ROLE_HR,
    'attendance_log.php'         => ROLE_MANAGER,
    'biometrics_import.php'      => ROLE_HR,
    'leave_requests.php'         => ROLE_EMPLOYEE,
    'leave_credits.php'          => ROLE_HR,
    'leave_types.php'            => ROLE_HR_HEAD,
    'schedules.php'              => ROLE_HR,
    'flex_schedule.php'          => ROLE_HR,
    'offsetting.php'             => ROLE_HR,
    'payroll.php'                => ROLE_HR_HEAD,
    'payroll_bulk.php'           => ROLE_HR_HEAD,
    'payroll_release_bulk.php'   => ROLE_HR_HEAD,
    'payslips.php'               => ROLE_EMPLOYEE,
    'payroll_release.php'        => ROLE_HR_HEAD,
    'thirteenth_month.php'       => ROLE_HR_HEAD,
    'remittances.php'            => ROLE_HR_HEAD,
    'appraisals.php'             => ROLE_MANAGER,
    'training.php'               => ROLE_HR,
    'disciplinary.php'           => ROLE_HR,
    'grievance.php'              => ROLE_HR,
    'reports.php'                => ROLE_MANAGER,
    'analytics.php'              => ROLE_MANAGER,
    'announcements.php'          => ROLE_HR,
    'holidays.php'               => ROLE_HR,
    'probationary.php'           => ROLE_HR,
    'classification.php'         => ROLE_HR,
    'coe.php'                    => ROLE_HR,
    'onboarding.php'             => ROLE_HR,
    'profile_requests.php'       => ROLE_HR,
    'separation_pay.php'         => ROLE_HR_HEAD,
    'loans.php'                  => ROLE_HR,
    'allowances.php'             => ROLE_HR,
    'change_password.php'        => ROLE_EMPLOYEE,
    'my_profile.php'             => ROLE_EMPLOYEE,
    'my_payslips.php'            => ROLE_EMPLOYEE,
    'my_leave.php'               => ROLE_EMPLOYEE,
    'my_attendance.php'          => ROLE_EMPLOYEE,
    'my_schedule.php'            => ROLE_EMPLOYEE,
    'teaching_load.php'          => ROLE_MANAGER,

    // ── SETTINGS — what HR Head can access ──
    'hr_setup.php'               => ROLE_HR_HEAD,   // Classifications, Ranks, Types, Statuses
    'leave_types.php'            => ROLE_HR_HEAD,
    'employee_id_generator.php'  => ROLE_HR_HEAD,
    'users.php'                  => ROLE_HR_HEAD,

    // ── SETTINGS — Admin only ──
    'settings.php'               => ROLE_ADMIN,     // System settings (company, payroll, SMTP)
    'audit_log.php'              => ROLE_ADMIN,

    // ── SETTINGS — Superadmin only ──
    'backup.php'                 => ROLE_SUPERADMIN,
);

// ── Core helpers ──────────────────────────────────────────────────
function get_user_level() {
    global $ROLE_LEVELS;
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';
    return isset($ROLE_LEVELS[$role]) ? $ROLE_LEVELS[$role] : ROLE_EMPLOYEE;
}

function is_superadmin() { return get_user_level() >= ROLE_SUPERADMIN; }
function is_admin()      { return get_user_level() >= ROLE_ADMIN; }
function is_hr_head()    { return get_user_level() >= ROLE_HR_HEAD; }
function is_hr()         { return get_user_level() >= ROLE_HR; }
function is_clinic_head(){ return get_user_level() >= ROLE_CLINIC_HEAD; }
function is_manager()    { return get_user_level() >= ROLE_MANAGER; }
function is_employee()   { return get_user_level() >= ROLE_EMPLOYEE; }
function has_role($min)  { return get_user_level() >= $min; }

// ── Page enforcement ──────────────────────────────────────────────
function enforce_page_role() {
    global $PAGE_ROLES, $EMPLOYEE_LINKED_PAGES;

    // Superadmin bypasses ALL page restrictions
    if (is_superadmin()) return;

    $page     = basename($_SERVER['PHP_SELF']);
    $required = isset($PAGE_ROLES[$page]) ? $PAGE_ROLES[$page] : ROLE_HR;

    if (get_user_level() < $required) {
        http_response_code(403);
        $path = file_exists('../includes/403.php') ? '../includes/403.php' : 'includes/403.php';
        include $path;
        exit;
    }

    // Check employee-linked pages for non-admin/non-hr_head users
    // Admin and HR Head can open these pages without an employee record
    if (in_array($page, $EMPLOYEE_LINKED_PAGES) && !is_admin()) {
        // Checked in the page itself using get_linked_employee()
    }
}

function require_role($min_role) {
    if (is_superadmin()) return; // superadmin bypasses
    if (get_user_level() < $min_role) {
        http_response_code(403);
        $path = file_exists('../includes/403.php') ? '../includes/403.php' : 'includes/403.php';
        include $path;
        exit;
    }
}

// ── Employee record resolver ──────────────────────────────────────
/**
 * Get the employee record linked to the current session user.
 * Superadmin/Admin: returns null (they don't need an employee record).
 * Others: returns the employee row or false if not linked.
 *
 * Usage in self-service pages:
 *   $emp_data = get_linked_employee($pdo);
 *   if ($emp_data === false) {
 *       echo not_linked_error(); exit;
 *   }
 *   // if null = admin/superadmin viewing without employee record — show admin view
 */
function get_linked_employee(PDO $pdo) {
    // Superadmin and Admin: not required to have employee record
    if (is_admin()) return null;

    $user = db_fetch($pdo,
        "SELECT employee_id FROM users WHERE id=?",
        array((int)$_SESSION['user_id'])
    );
    if (!$user || !$user['employee_id']) return false;

    return db_fetch($pdo,
        "SELECT e.*, d.name AS dept, p.title AS position
         FROM employees e
         LEFT JOIN departments d ON e.department_id=d.id
         LEFT JOIN positions   p ON e.position_id=p.id
         WHERE e.id=?",
        array((int)$user['employee_id'])
    );
}

function not_linked_error() {
    return '<div class="alert alert-warning m-4">
              <i class="fas fa-exclamation-triangle mr-2"></i>

              Your account is not linked to an employee record.
              Please contact HR to link your account.
            </div>';
}

// ── Roles HR Head can create ──────────────────────────────────────
function creatable_roles() {
    if (is_superadmin()) {
        return array('admin','hr_head','board_director','president','academic_head',
                     'non_academic_head','clinic_head','clinic_staff','manager','hr','employee');
    }
    if (is_admin()) {
        return array('hr_head','board_director','president','academic_head',
                     'non_academic_head','clinic_head','clinic_staff','manager','hr','employee');
    }
    if (is_hr_head()) {
        return array('board_director','president','academic_head','non_academic_head',
                     'clinic_head','clinic_staff','manager','hr','employee');
    }
    return array();
}
?>