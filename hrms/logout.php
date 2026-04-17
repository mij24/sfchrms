<?php
// ============================================
// FILE: logout.php  (root of project)
// ============================================
session_start();
session_destroy();
header("Location: login.php");
exit;
?>