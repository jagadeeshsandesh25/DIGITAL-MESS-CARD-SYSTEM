<?php
// api/feedback/update.php

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

// Include the User model (to validate user_id if changed)
require_once '../../models/User.php'; // Adjust path as needed
$userModel = new User($db);

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

// Check if the request method is POST (or PUT/PATCH, but using POST for simplicity)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve input data
    $feedback_id = (int)($_POST['id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $rating = $_POST['rating'] ?? null; // Can be NULL
    $created_at_input = trim($_POST['created_at'] ?? '');
    $created_at = !empty($created_at_input) ? $created_at_input : date('Y-m-d H:i:s'); // Use provided time or current time

    // Basic validation
    $errors = [];
    if ($feedback_id <= 0) {
        $errors[] = "Invalid feedback ID.";
    }
    if (empty($message)) {
        $errors[] = "Feedback message cannot be empty.";
    }
    // Validate rating if provided
    if ($rating !== null && (!is_numeric($rating) || $rating < 1 || $rating > 5)) {
         $errors[] = "Please select a rating between 1 and 5 stars, or leave it blank.";
    }
    // Validate created_at format if provided
    if (!empty($created_at_input) && DateTime::createFromFormat('Y-m-d H:i:s', $created_at_input) === FALSE) {
         $errors[] = "Invalid creation time format. Please use YYYY-MM-DD HH:MM:SS.";
    }

    // Validate feedback ID exists and belongs to the user (unless admin)
    if (empty($errors) && $feedback_id > 0) {
        try {
             $feedback_check_query = "SELECT id, user_id FROM feedback WHERE id = :feedback_id LIMIT 1";
             $feedback_check_stmt = $db->prepare($feedback_check_query);
             $feedback_check_stmt->bindParam(':feedback_id', $feedback_id, PDO::PARAM_INT);
             $feedback_check_stmt->execute();
             $existing_feedback = $feedback_check_stmt->fetch(PDO::FETCH_ASSOC);

             if (!$existing_feedback) {
                 $errors[] = "Feedback record not found.";
             } elseif ($user_role !== 'admin' && $existing_feedback['user_id'] != $user_id) {
                  // Non-admins can only update their own feedback
                  $errors[] = "Access denied. You can only update your own feedback.";
             }
        } catch (PDOException $e) {
            $errors[] = "Database error validating feedback record.";
            error_log("API Feedback update - Feedback ID validation error: " . $e->getMessage());
        }
    }

    // Validate user_id exists (if changing, though usually not done)
    if (empty($errors) && isset($_POST['user_id']) && $_POST['user_id'] != $user_id) {
         $new_user_id = (int)$_POST['user_id'];
         try {
             $user_check_query = "SELECT id FROM user WHERE id = :user_id LIMIT 1";
             $user_check_stmt = $db->prepare($user_check_query);
             $user_check_stmt->bindParam(':user_id', $new_user_id, PDO::PARAM_INT);
             $user_check_stmt->execute();
             if ($user_check_stmt->rowCount() === 0) {
                 $errors[] = "Selected user ID does not exist.";
             }
         } catch (PDOException $e) {
             $errors[] = "Database error validating user ID.";
             error_log("API Feedback update - User ID validation error: " . $e->getMessage());
         }
    }


    if (empty($errors) && $existing_feedback) {
        try {
            // Prepare data array for the model, including the ID
            // Note: Updating user_id is discouraged for non-admins. Admins can do it.
            $update_feedback_data = [
                'id' => $feedback_id,
                'user_id' => isset($_POST['user_id']) ? (int)$_POST['user_id'] : $existing_feedback['user_id'], // Allow admin to change user_id, otherwise keep existing
                'message' => $message,
                'rating' => $rating, // Can be NULL
                'created_at' => $created_at // Use provided or existing time
            ];

            // Attempt to update the feedback record using the model
            if ($feedbackModel->update($update_feedback_data)) {
                $response['success'] = true;
                $response['message'] = 'Feedback updated successfully!';
                $response['data'] = ['feedback_id' => $feedback_id];
            } else {
                $response['message'] = 'Failed to update feedback. It might not exist or no changes were made.';
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during feedback update.';
            error_log("API Feedback update error: " . $e->getMessage());
        }
    } else {
        $response['message'] = implode("<br>", $errors);
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Output the JSON response
echo json_encode($response);
exit;
?>