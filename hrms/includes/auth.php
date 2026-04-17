<?php
// ============================================
// FILE: includes/auth.php — FIXED
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict'
    ));
    session_start();
}

define('SESSION_TIMEOUT', 1800);

// Not logged in — redirect
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Session timeout
if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ../login.php?timeout=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();

// Session fixation protection
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
} elseif ((time() - $_SESSION['created_at']) > 300) {
    session_regenerate_id(true);
    $_SESSION['created_at'] = time();
}

// Force password change for temporary accounts
// Only runs when $pdo is already available (db.php included before auth.php)
if (isset($pdo)) {
    $cur_page = basename($_SERVER['PHP_SELF']);
    $exempt   = array('change_password.php', 'logout.php');
    if (!in_array($cur_page, $exempt)) {
        try {
            $stmt = $pdo->prepare("SELECT must_change_password FROM users WHERE id=? LIMIT 1");
            $stmt->execute(array((int)$_SESSION['user_id']));
            $u = $stmt->fetch();
            if ($u && $u['must_change_password']) {
                header('Location: change_password.php');
                exit;
            }
        } catch (Exception $e) {
            // Table may not have column yet — skip silently
        }
    }
}
?>