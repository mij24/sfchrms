<?php
// ============================================
// FILE: includes/header.php — FIXED
// ============================================
$current_user   = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$current_role   = isset($_SESSION['role'])     ? $_SESSION['role']     : '';
$role_initials  = strtoupper(substr($current_user, 0, 2));
?>
<nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom">

  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button">
        <i class="fas fa-bars"></i>
      </a>
    </li>
    <li class="nav-item d-none d-sm-inline-block">
      <a href="dashboard.php" class="nav-link">Home</a>
    </li>
  </ul>

  <ul class="navbar-nav ml-auto">
    <li class="nav-item">
      <span class="nav-link">
        <i class="fas fa-user-circle mr-1"></i>
        <strong><?= htmlspecialchars($current_user) ?></strong>
        <span class="badge badge-secondary ml-1"><?= ucfirst($current_role) ?></span>
      </span>
    </li>
    <li class="nav-item">
      <a href="../logout.php" class="nav-link text-danger" title="Logout">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </li>
  </ul>

</nav>