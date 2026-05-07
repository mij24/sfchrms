<?php
// tl_debug2.php - Step by step crash finder
// Place in hrms/pages/ and visit it

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<pre>';

echo "STEP 1: Starting...\n";
require_once '../includes/db.php';
echo "STEP 2: db.php loaded\n";
require_once '../includes/auth.php';
echo "STEP 3: auth.php loaded\n";
require_once '../includes/roles.php';
echo "STEP 4: roles.php loaded\n";
require_once '../includes/csrf.php';
echo "STEP 5: csrf.php loaded\n";

echo "STEP 6: Session user_id = " . (isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : "NOT SET") . "\n";
echo "STEP 7: Role = " . (isset($_SESSION["role"]) ? $_SESSION["role"] : "NOT SET") . "\n";

$my_uid  = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;
$my_role = isset($_SESSION["role"]) ? $_SESSION["role"] : "employee";
$my_emp_id = 0;

echo "STEP 8: Testing basic queries...\n";
$user = db_fetch($pdo, "SELECT * FROM users WHERE id=?", array($my_uid));
echo "  users query: OK\n";

$cur_sy  = db_value($pdo,"SELECT setting_value FROM settings WHERE setting_key=\'current_school_year\'") ?: "2025-2026";
echo "  settings query: OK, cur_sy=$cur_sy\n";

$tl_subjects_exists = false;
try { $pdo->query("SELECT 1 FROM teaching_load_subjects LIMIT 1"); $tl_subjects_exists = true; } catch(Exception $e){}
echo "  tl_subjects_exists: " . ($tl_subjects_exists ? "YES" : "NO") . "\n";

if ($user && $user["employee_id"]) {
    $my_emp_id = (int)$user["employee_id"];
}
echo "STEP 9: my_emp_id=$my_emp_id\n";

$sec_dept_id = 0;
if ($my_emp_id && function_exists("is_secretary") && is_secretary()) {
    $sec_dept_id = (int)db_value($pdo,"SELECT department_id FROM employees WHERE id=?",array($my_emp_id));
}
echo "STEP 10: sec_dept_id=$sec_dept_id\n";

$where = array("1=1");
$params = array();
if (function_exists("is_secretary") && is_secretary() && $sec_dept_id) {
    $where[] = "e.department_id = ?";
    $params[] = $sec_dept_id;
}
echo "STEP 11: WHERE built: " . implode(" AND ", $where) . "\n";

echo "STEP 12: Testing loads query...\n";
try {
    $loads = db_fetchall($pdo,
        "SELECT tl.*, e.full_name, e.employee_id AS emp_code, d.name AS dept
         FROM teaching_loads tl
         JOIN employees e ON tl.employee_id=e.id
         LEFT JOIN departments d ON e.department_id=d.id
         WHERE " . implode(" AND ", $where) . "
         ORDER BY tl.created_at DESC",
        $params
    );
    echo "  loads query: OK (" . count($loads) . " rows)\n";
} catch (Exception $e) {
    echo "  loads query FAILED: " . $e->getMessage() . "\n";
}

echo "STEP 13: Testing emp_in_dept query...\n";
if ($sec_dept_id) {
    try {
        $emp_in_dept = db_fetchall($pdo,
            "SELECT e.id, e.full_name, e.employment_status, e.is_part_time
             FROM employees e
             WHERE e.employment_status=\'Active\' AND e.department_id=?
             ORDER BY e.full_name",
            array($sec_dept_id)
        );
        echo "  emp_in_dept: OK (" . count($emp_in_dept) . " rows)\n";
    } catch (Exception $e) {
        echo "  emp_in_dept FAILED: " . $e->getMessage() . "\n";
    }
} else {
    echo "  emp_in_dept: SKIPPED (no sec_dept_id)\n";
}

echo "STEP 14: All diagnostic steps completed successfully!\n";
echo "The crash must be in the HTML rendering section.\n";
echo "</pre>";