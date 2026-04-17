<?php
// ============================================
// FILE: includes/security_log.php
// Audit trail — log every create/update/delete
// ============================================

/**
 * Log a user action to the audit_logs table.
 *
 * audit_log($pdo, 'UPDATE', 'employees', $id, ['basic_salary' => [20000, 25000]]);
 */
function audit_log(PDO $pdo, $action, $table, $record_id, $changes = array()) {
    $user_id   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $username  = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';
    $ip        = $_SERVER['REMOTE_ADDR'];
    $page      = basename($_SERVER['PHP_SELF']);
    $changes_j = json_encode($changes);
    $uid_val   = $user_id ? $user_id : 'NULL';

    try {
        $pdo->prepare(
            "INSERT INTO audit_logs
             (user_id, username, action, table_name, record_id, changes, ip_address, page, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        )->execute(array($user_id, $username, $action, $table, $record_id, $changes_j, $ip, $page));
    } catch (Exception $e) {
        // Fail silently — don't break the app if audit fails
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

/**
 * Sanitize and validate common input types — use before insert/update
 */
function clean_string($val, $max = 255) {
    $val = trim($val);
    $val = strip_tags($val);
    return substr($val, 0, $max);
}

function clean_int($val) {
    return (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
}

function clean_float($val) {
    return (float)filter_var($val, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

function clean_date($val) {
    $d = DateTime::createFromFormat('Y-m-d', $val);
    return ($d && $d->format('Y-m-d') === $val) ? $val : null;
}

function clean_email($val) {
    return filter_var(trim($val), FILTER_SANITIZE_EMAIL);
}
?>