<?php
// api/users/delete.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session
session_start();

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Include the User model
require_once '../../models/User.php'; // Adjust path as needed
$userModel = new User($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin privileges required.';
    echo json_encode($response);
    exit;
}

// Check if the request method is POST (typically used for delete confirmations)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve user ID
    $user_id = (int)($input['id'] ?? 0);

    // Basic validation
    if ($user_id <= 0) {
        $response['message'] = 'Invalid user ID provided.';
    } else {
        try {
            // Check if the user exists before attempting deletion
            $existing_user = $userModel->findById($user_id);
            if (!$existing_user) {
                $response['message'] = 'User not found.';
            } else {
                // Attempt to delete the user using the model
                // Note: This might fail due to foreign key constraints.
                if ($userModel->delete($user_id)) {
                    $response['success'] = true;
                    $response['message'] = 'User deleted successfully.';
                    $response['data'] = ['deleted_user_id' => $user_id];
                } else {
                    $response['message'] = 'Failed to delete user. It might be linked to other records.';
                }
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during user deletion.';
            error_log("API Users delete error: " . $e->getMessage());
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