<?php
// ============================================
// FILE: pages/delete_document.php
// Quick delete for a single 201 document
// ============================================
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/roles.php';
require_once '../includes/csrf.php';
enforce_page_role();

$doc_id = isset($_GET['id'])  ? (int)$_GET['id']  : 0;
$emp_id = isset($_GET['emp']) ? (int)$_GET['emp'] : 0;

if ($doc_id) {
    $doc = $conn->query("SELECT * FROM employee_documents WHERE id = $doc_id")->fetch_assoc();
    if ($doc) {
        $file = '../uploads/' . $doc['file_path'];
        if (file_exists($file)) unlink($file);
        $conn->query("DELETE FROM employee_documents WHERE id = $doc_id");
    }
}

header("Location: upload_document.php?id=" . $emp_id);
exit;
?>