<?php
// api/feedback/create.php

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

// Include the User model (to validate user_id)
require_once '../../models/User.php'; // Adjust path as needed
$userModel = new User($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in (any role can give feedback)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401); // Unauthorized
    $response['message'] = 'Access denied. Please log in to submit feedback.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve input data
    $message = trim($_POST['message'] ?? '');
    $rating = $_POST['rating'] ?? null; // Can be NULL
    $created_at = date('Y-m-d H:i:s'); // Use current time

    // Basic validation
    $errors = [];
    if (empty($message)) {
        $errors[] = "Feedback message cannot be empty.";
    }
    // Validate rating if provided
    if ($rating !== null && (!is_numeric($rating) || $rating < 1 || $rating > 5)) {
         $errors[] = "Please select a rating between 1 and 5 stars, or leave it blank.";
    }

    // Validate user_id exists (should always be true if logged in, but good practice)
    if (empty($errors)) {
         try {
             $user_check_query = "SELECT id FROM user WHERE id = :user_id LIMIT 1";
             $user_check_stmt = $db->prepare($user_check_query);
             $user_check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
             $user_check_stmt->execute();
             if ($user_check_stmt->rowCount() === 0) {
                 $errors[] = "User account not found. Please log in again.";
             }
         } catch (PDOException $e) {
             $errors[] = "Database error validating user account.";
             error_log("API Feedback create - User ID validation error: " . $e->getMessage());
         }
    }


    if (empty($errors)) {
        try {
            // Prepare data array for the model to create the feedback record
            // The user_id is taken from the logged-in session
            $new_feedback_data = [
                'user_id' => $user_id, // The logged-in user gives the feedback
                'message' => $message,
                'rating' => $rating, // Can be NULL
                'created_at' => $created_at // Use current time
            ];

            // Attempt to create the feedback record using the model
            $new_feedback_id = $feedbackModel->create($new_feedback_data);

            if ($new_feedback_id) {
                $response['success'] = true;
                $response['message'] = 'Feedback submitted successfully! Thank you for your input.';
                $response['data'] = ['feedback_id' => $new_feedback_id];
            } else {
                $response['message'] = 'Failed to submit feedback. Please try again.';
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during feedback submission.';
            error_log("API Feedback create error: " . $e->getMessage()); // Log the actual error
        }
    } else {
        $response['message'] = implode("<br>", $errors); // Join multiple errors with line breaks
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Output the JSON response
echo json_encode($response);
exit;
?>