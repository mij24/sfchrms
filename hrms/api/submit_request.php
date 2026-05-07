<?php
// hrms/api/submit_request.php

// API endpoint to handle employee request submission

// Include necessary files for validation, workflow, and logging
include('validation.php');
include('workflow.php');
include('audit_logging.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate request data
    $validationResult = validateRequest($_POST);
    if (!$validationResult['success']) {
        echo json_encode(['success' => false, 'message' => $validationResult['message']]);
        exit;
    }

    // Initialize workflow
    $workflowId = initializeWorkflow($_POST);

    // Log the audit
    logAudit($_POST, $workflowId);

    // Respond with success
    echo json_encode(['success' => true, 'workflowId' => $workflowId]);
} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
