<?php

// API endpoint to handle request approvals and rejections

// Import necessary libraries
require 'vendor/autoload.php';

// Initialize database connection
$db = new PDO('mysql:host=localhost;dbname=hrms', 'username', 'password');

// Function to log request actions
function logAction($requestId, $action, $userId) {
    global $db;
    $stmt = $db->prepare("INSERT INTO audit_trail (request_id, action, user_id, timestamp) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$requestId, $action, $userId]);
}

// Function to send notifications
function sendNotification($userId, $message) {
    // Code to send notifications (e.g., email)
}

// Handle incoming request
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestId = $_GET['request_id'];
$userId = 'mij24'; // Assuming the current user's ID is obtained securely

if ($requestMethod === 'POST') {
    $action = $_POST['action']; // Either 'approve' or 'reject'

    if ($action === 'approve' || $action === 'reject') {
        // Update request status in database
        $stmt = $db->prepare("UPDATE requests SET status = ? WHERE id = ?");
        $stmt->execute([$action, $requestId]);

        // Log the action
        logAction($requestId, $action, $userId);

        // Send notification
        sendNotification($userId, "Your request has been $action" );

        echo json_encode(['status' => 'success', 'message' => 'Request successfully processed.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
