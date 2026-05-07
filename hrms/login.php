<?php
// ============================================
// FILE: login.php — SECURED VERSION
// Rate limiting, lockout, CSRF, PDO
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

if (isset($_SESSION['user_id'])) {
    $r = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';
    $rd = array(
        'dept_secretary'    => 'pages/dept_secretary_dashboard.php',
        'exec_secretary'    => 'pages/exec_secretary_dashboard.php',
        'manager'           => 'pages/academic_head_dashboard.php',
        'academic_head'     => 'pages/academic_head_dashboard.php',
        'non_academic_head' => 'pages/non_academic_head_dashboard.php',
        'registrar_head'    => 'pages/registrar_head_dashboard.php',
        'vp_academic'       => 'pages/vp_academic_dashboard.php',
        'vp_admin'          => 'pages/vp_admin_dashboard.php',
        'president'         => 'pages/president_dashboard.php',
        'board_director'    => 'pages/board_director_dashboard.php',
        'clinic_head'       => 'pages/clinic_head_dashboard.php',
        'clinic_staff'      => 'pages/clinic_staff_dashboard.php',
        'employee'          => 'pages/employee_dashboard.php',
    );
    $dest = isset($rd[$r]) ? $rd[$r] : 'pages/dashboard.php';
    header('Location: ' . $dest);
    exit;
}

require_once 'includes/db.php';
require_once 'includes/csrf.php';

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES',    15);

$error   = '';
$locked  = false;
$timeout = isset($_GET['timeout']) ? (int)$_GET['timeout'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    csrf_verify();

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $ip       = $_SERVER['REMOTE_ADDR'];

    // ── Rate limiting check ──
    $lockout_time = date('Y-m-d H:i:s', strtotime('-' . LOCKOUT_MINUTES . ' minutes'));

    $attempts = (int)db_value($pdo,
        "SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = ? AND attempted_at > ? AND success = 0",
        array($ip, $lockout_time)
    );

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $locked = true;
        $error  = "Too many failed attempts. Please wait " . LOCKOUT_MINUTES . " minutes.";
        log_login_attempt($pdo, $username, $ip, false, 'RATE_LIMITED');
    } else {

        // ── Authenticate ──
        $user = db_fetch($pdo,
            "SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1",
            array($username)
        );

        if ($user && password_verify($password, $user['password'])) {

            // Success — regenerate session
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['emp_id']        = $user['employee_id'];
            $_SESSION['last_activity'] = time();
            $_SESSION['created_at']    = time();

            // Clear failed attempts
            db_run($pdo,
                "DELETE FROM login_attempts WHERE ip_address = ?",
                array($ip)
            );

            // Update last login
            db_run($pdo,
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                array($user['id'])
            );

            log_login_attempt($pdo, $username, $ip, true, 'SUCCESS');

            $r = $user['role'];
            $rd = array(
        'dept_secretary'    => 'pages/dept_secretary_dashboard.php',
        'exec_secretary'    => 'pages/exec_secretary_dashboard.php',
        'manager'           => 'pages/academic_head_dashboard.php',
        'academic_head'     => 'pages/academic_head_dashboard.php',
        'non_academic_head' => 'pages/non_academic_head_dashboard.php',
        'registrar_head'    => 'pages/registrar_head_dashboard.php',
        'vp_academic'       => 'pages/vp_academic_dashboard.php',
        'vp_admin'          => 'pages/vp_admin_dashboard.php',
        'president'         => 'pages/president_dashboard.php',
        'board_director'    => 'pages/board_director_dashboard.php',
        'clinic_head'       => 'pages/clinic_head_dashboard.php',
        'clinic_staff'      => 'pages/clinic_staff_dashboard.php',
        'employee'          => 'pages/employee_dashboard.php',
            );
            $dest = isset($rd[$r]) ? $rd[$r] : 'pages/dashboard.php';
    header('Location: ' . $dest);
    exit;

        } else {
            // Failed — log attempt
            log_login_attempt($pdo, $username, $ip, false, 'WRONG_CREDENTIALS');
            $remaining = MAX_LOGIN_ATTEMPTS - $attempts - 1;
            $error = "Invalid username or password." .
                     ($remaining > 0 ? " $remaining attempt(s) remaining." : " Account will be locked.");
        }
    }
}

function log_login_attempt(PDO $pdo, $username, $ip, $success, $reason) {
    try {
        db_run($pdo,
            "INSERT INTO login_attempts (username, ip_address, success, reason, attempted_at)
             VALUES (?, ?, ?, ?, NOW())",
            array($username, $ip, $success ? 1 : 0, $reason)
        );
    } catch (Exception $e) {
        // Table may not exist yet — fail silently
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SFCHRMS | Login</title>
  <link rel="stylesheet" href="assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="assets/dist/css/adminlte.min.css">
  <style>
    body { background: #f4f6f9; }
    .login-box { width: 380px; }
    .login-card-body { border-top: 3px solid #007bff; }
    .brand-logo { font-size: 22px; font-weight: 600; color: #343a40; }
    .brand-logo span { color: #007bff; }
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">

  <div class="text-center mb-3">
    <p class="brand-logo">SFC|<span>HRMS</span></p>
    <p class="text-muted" style="font-size:13px;">Human Resource Management System</p>
  </div>

  <div class="card login-card-body shadow-sm">
    <div class="card-body">

      <?php if ($timeout): ?>
        <div class="alert alert-warning alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <i class="fas fa-clock mr-1"></i> Session expired. Please log in again.
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" <?= $locked ? 'onsubmit="return false;"' : '' ?>>
        <?php csrf_field(); ?>

        <div class="input-group mb-3">
          <input type="text" name="username" class="form-control"
                 placeholder="Username" required autofocus
                 <?= $locked ? 'disabled' : '' ?>>
          <div class="input-group-append">
            <div class="input-group-text"><i class="fas fa-user"></i></div>
          </div>
        </div>

        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control"
                 placeholder="Password" required
                 <?= $locked ? 'disabled' : '' ?>>
          <div class="input-group-append">
            <div class="input-group-text"><i class="fas fa-lock"></i></div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block" <?= $locked ? 'disabled' : '' ?>>
          <i class="fas fa-sign-in-alt mr-1"></i> Sign in
        </button>
      </form>

    </div>
  </div>
</div>
<script src="assets/plugins/jquery/jquery.min.js"></script>
<script src="assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/dist/js/adminlte.min.js"></script>
</body>
</html>