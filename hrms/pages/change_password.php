<?php
// ============================================
// FILE: pages/change_password.php
// Any logged-in user can change their password
// Forced on first login for temporary accounts
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';

$error = ''; $success = '';
$user = db_fetch($pdo, "SELECT * FROM users WHERE id=?", array((int)$_SESSION['user_id']));
$forced = ($user && $user['must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $current  = $_POST['current_password'];
    $new_pw   = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    if (!password_verify($current, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new_pw) < 8) {
        $error = "New password must be at least 8 characters.";
    } elseif ($new_pw !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        db_run($pdo,
            "UPDATE users SET password=?, temp_password=NULL, must_change_password=0 WHERE id=?",
            array($hashed, (int)$_SESSION['user_id'])
        );
        prg_redirect('dashboard.php', 'Password changed successfully.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Change Password | HRMS</title>
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="../assets/dist/css/custom.css">
</head>
<body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0">Change Password</h1></div></div>
  <div class="content"><div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-md-5">
        <?php if ($forced): ?>
        <div class="alert alert-warning">
          <i class="fas fa-exclamation-triangle mr-1"></i>
          <strong>Action required.</strong> You must change your temporary password before continuing.
        </div>
        <?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="card card-primary card-outline">
          <div class="card-header"><h3 class="card-title">Change your password</h3></div>
          <div class="card-body">
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <div class="form-group">
                <label>Current password</label>
                <input type="password" name="current_password" class="form-control" required>
              </div>
              <div class="form-group">
                <label>New password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
                <small class="text-muted">Minimum 8 characters</small>
              </div>
              <div class="form-group">
                <label>Confirm new password</label>
                <input type="password" name="confirm_password" class="form-control" required>
              </div>
              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-lock mr-1"></i> Change password
              </button>
              <?php if (!$forced): ?>
              <a href="dashboard.php" class="btn btn-secondary btn-block mt-1">Cancel</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
</body></html>