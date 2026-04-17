<?php
// ============================================
// FILE: index.php  (place in C:\xampp\htdocs\hrms\)
// Redirects http://localhost/hrms to login or dashboard
// ============================================
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>