
<?php
// ============================================
// FILE: includes/db.php — FINAL VERSION
// Handles both old $conn (mysqli) callers and new $pdo callers
// during migration so nothing breaks
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // change to true if HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ));
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hrms');

// ── PDO connection (new — use this going forward) ──────────────────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        )
    );
} catch (PDOException $e) {
    error_log('PDO connection failed: ' . $e->getMessage());
    die('<div style="font-family:sans-serif;padding:40px;color:#c00;">
            <h2>Database connection error</h2>
            <p>Cannot connect to the database. Please check your XAMPP MySQL is running.</p>
         </div>');
}

// ── mysqli connection (kept for backward compatibility during migration) ──
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log('mysqli connection failed: ' . $conn->connect_error);
    die('<div style="font-family:sans-serif;padding:40px;color:#c00;">
            <h2>Database connection error</h2>
            <p>Cannot connect to the database. Please check your XAMPP MySQL is running.</p>
         </div>');
}
$conn->set_charset('utf8');

// ── PDO helper functions ───────────────────────────────────────────

function db_fetchall(PDO $pdo, $sql, $params = array()) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_fetch(PDO $pdo, $sql, $params = array()) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function db_run(PDO $pdo, $sql, $params = array()) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_lastid(PDO $pdo) {
    return (int)$pdo->lastInsertId();
}

function db_value(PDO $pdo, $sql, $params = array()) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

// ── Sanitize helpers ───────────────────────────────────────────────
function clean_string($val, $max = 255) {
    return substr(strip_tags(trim($val)), 0, $max);
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

// ── Audit log ─────────────────────────────────────────────────────
function audit_log(PDO $pdo, $action, $table, $record_id, $changes = array()) {
    $user_id  = isset($_SESSION['user_id'])  ? (int)$_SESSION['user_id']  : null;
    $username = isset($_SESSION['username']) ? $_SESSION['username']       : 'system';
    $ip       = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR']  : '';
    $page     = basename($_SERVER['PHP_SELF']);
    $changes_s= json_encode($changes);

    try {
        $pdo->prepare(
            "INSERT INTO audit_logs
             (user_id,username,action,table_name,record_id,changes,ip_address,page,created_at)
             VALUES (?,?,?,?,?,?,?,?,NOW())"
        )->execute(array($user_id,$username,$action,$table,$record_id,$changes_s,$ip,$page));
    } catch (Exception $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
?>