<?php
// api/feedback/index.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session
session_start();

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Include the Feedback model
require_once '../../models/Feedback.php'; // Adjust path as needed
$feedbackModel = new Feedback($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in
// Admins see all feedback, users see their own, waiters might see none or assigned feedback
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401); // Unauthorized
    $response['message'] = 'Access denied. Please log in.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

try {
    // Fetch feedback records based on user role
    if ($user_role === 'admin') {
        // Admin sees all feedback records
        $feedback_records = $feedbackModel->findAll(); // Default order by created_at DESC
    } elseif ($user_role === 'user') {
        // Regular users see only their own feedback records
        $feedback_records = $feedbackModel->findByUserId($user_id); // Order by created_at DESC
    } else { // Waiter
        // Waiters might not have access to feedback, or see only feedback for tables assigned to them
        // For now, let's assume waiters don't see feedback directly
        $feedback_records = [];
        $response['message'] = 'Feedback access is restricted for waiters.';
    }

    if ($feedback_records !== false) {
        $response['success'] = true;
        $response['message'] = 'Feedback records fetched successfully.';
        $response['data'] = $feedback_records; // Return the array of feedback data
    } else {
        $response['message'] = 'Failed to fetch feedback records.';
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Database error while fetching feedback records.';
    error_log("API Feedback index query error: " . $e->getMessage()); // Log the actual error
}

// Output the JSON response
echo json_encode($response);
exit;
?>