<?php
// api/feedback/delete.php

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
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401); // Unauthorized
    $response['message'] = 'Access denied. Please log in.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Check if the request method is POST (typically used for delete confirmations)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve feedback ID
    $feedback_id = (int)($input['id'] ?? 0);

    // Basic validation
    if ($feedback_id <= 0) {
        $response['message'] = 'Invalid feedback ID provided.';
    } else {
        try {
            // Fetch the specific feedback record's details for confirmation and permission check
            $feedback_check_query = "SELECT id, user_id FROM feedback WHERE id = :feedback_id LIMIT 1";
            $feedback_check_stmt = $db->prepare($feedback_check_query);
            $feedback_check_stmt->bindParam(':feedback_id', $feedback_id, PDO::PARAM_INT);
            $feedback_check_stmt->execute();
            $feedback_details = $feedback_check_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$feedback_details) {
                $response['message'] = 'Feedback record not found.';
            } elseif ($user_role !== 'admin' && $feedback_details['user_id'] != $user_id) {
                 // Non-admins can only delete their own feedback
                 $response['message'] = 'Access denied. You can only delete your own feedback.';
            } else {
                // Attempt to delete the feedback record using the model
                // WARNING: Deleting feedback might break referential integrity if other tables depend on it.
                // Ensure cascading deletes are handled correctly in the database schema or handle dependencies before deletion.
                if ($feedbackModel->delete($feedback_id)) {
                    $response['success'] = true;
                    $response['message'] = 'Feedback record deleted successfully!';
                    $response['data'] = ['deleted_feedback_id' => $feedback_id];
                } else {
                    // This could mean the row didn't exist or a constraint prevented deletion
                    $response['message'] = 'Failed to delete feedback record. It might not exist or be linked to other records that prevent deletion.';
                }
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during feedback deletion.';
            error_log("API Feedback delete error: " . $e->getMessage());
        }
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Output the JSON response
echo json_encode($response);
exit;
?>