<?php
// ============================================
// FILE: includes/csrf.php — WITH PRG PATTERN
// Fixes duplicate entry on page refresh
// ============================================

function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Invalid request token. Please go back and try again.');
    }
}

/**
 * PRG — Post/Redirect/Get pattern
 * Call AFTER a successful save to prevent duplicate submission on refresh.
 *
 * Usage:
 *   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 *       csrf_verify();
 *       // ... do the save ...
 *       prg_redirect('employees.php', 'Employee saved successfully.');
 *   }
 *
 * On the destination page, call:
 *   $flash = prg_flash();
 *   if ($flash) echo '<div class="alert alert-success">' . $flash . '</div>';
 */
function prg_redirect($url, $message = '') {
    if ($message) {
        $_SESSION['flash_success'] = $message;
    }
    // Regenerate CSRF token after every successful POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: ' . $url);
    exit;
}

function prg_redirect_error($url, $message) {
    $_SESSION['flash_error'] = $message;
    header('Location: ' . $url);
    exit;
}

function prg_flash() {
    $msg = '';
    if (!empty($_SESSION['flash_success'])) {
        $msg = '<div class="alert alert-success alert-dismissible">
                  <button type="button" class="close" data-dismiss="alert">&times;</button>
                  <i class="fas fa-check-circle mr-1"></i>'
               . htmlspecialchars($_SESSION['flash_success'])
               . '</div>';
        unset($_SESSION['flash_success']);
    }
    if (!empty($_SESSION['flash_error'])) {
        $msg .= '<div class="alert alert-danger alert-dismissible">
                   <button type="button" class="close" data-dismiss="alert">&times;</button>
                   <i class="fas fa-exclamation-triangle mr-1"></i>'
                . htmlspecialchars($_SESSION['flash_error'])
                . '</div>';
        unset($_SESSION['flash_error']);
    }
    return $msg;
}
?>