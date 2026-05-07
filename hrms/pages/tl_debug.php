<?php
// DIAGNOSTIC FILE - deploy to hrms/pages/tl_debug.php
// Visit: http://localhost/hrms/pages/tl_debug.php
// This will show the EXACT error

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;">';
echo "=== HRMS Teaching Load Diagnostic ===\n\n";

// Step 1: PHP Version
echo "PHP Version: " . phpversion() . "\n\n";

// Step 2: Test includes
echo "Step 1: Loading includes...\n";
try {
    require_once '../includes/db.php';
    echo "  db.php: OK\n";
} catch (Exception $e) {
    echo "  db.php ERROR: " . $e->getMessage() . "\n";
    exit;
}

try {
    require_once '../includes/auth.php';
    echo "  auth.php: OK\n";
} catch (Exception $e) {
    echo "  auth.php ERROR: " . $e->getMessage() . "\n";
}

try {
    require_once '../includes/roles.php';
    echo "  roles.php: OK\n";
} catch (Exception $e) {
    echo "  roles.php ERROR: " . $e->getMessage() . "\n";
}

try {
    require_once '../includes/csrf.php';
    echo "  csrf.php: OK\n";
} catch (Exception $e) {
    echo "  csrf.php ERROR: " . $e->getMessage() . "\n";
}

// Step 3: Test DB tables
echo "\nStep 2: Checking tables...\n";
$tables = array(
    'teaching_loads', 
    'teaching_load_subjects',
    'teaching_load_approvals',
    'document_logbook',
    'document_control_counter',
    'employees',
    'departments',
    'employee_classifications',
    'settings',
    'users'
);

foreach ($tables as $table) {
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "  $table: OK ($result rows)\n";
    } catch (Exception $e) {
        echo "  $table: MISSING/ERROR - " . $e->getMessage() . "\n";
    }
}

// Step 4: Test teaching_loads columns
echo "\nStep 3: Checking teaching_loads columns...\n";
$cols_needed = array(
    'id','subject_title','subject_code','school_year','semester',
    'employee_id','student_program','section','level','day_of_week',
    'time_start','time_end','room','units','status','current_step',
    'has_offset','offset_time_start','offset_time_end','offset_notes',
    'rejection_reason','created_by','total_units','total_hours','total_preps',
    'vp_logbook_id','pres_logbook_id','created_at'
);
try {
    $cols = $pdo->query("SHOW COLUMNS FROM teaching_loads")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cols_needed as $col) {
        if (in_array($col, $cols)) {
            echo "  $col: OK\n";
        } else {
            echo "  $col: MISSING\n";
        }
    }
} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

// Step 5: Test employees columns
echo "\nStep 4: Checking employees columns...\n";
$emp_cols = array('id','full_name','employee_id','department_id','employment_status',
                   'classification_id','is_part_time','position_id');
try {
    $cols = $pdo->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($emp_cols as $col) {
        echo "  $col: " . (in_array($col, $cols) ? "OK" : "MISSING") . "\n";
    }
} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== DONE ===\n";
echo '</pre>';