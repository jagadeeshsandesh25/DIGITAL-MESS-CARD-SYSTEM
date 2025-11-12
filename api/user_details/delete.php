<?php
// api/user_details/delete.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session
session_start();

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Include the UserDetailsTabel model
require_once '../../models/UserDetailsTabel.php'; // Adjust path as needed
$userDetailsModel = new UserDetailsTabel($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in and is an admin (only admins should delete user details records)
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

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve user details record ID
    $user_details_id_to_delete = (int)($input['id'] ?? 0);

    // Basic validation
    if ($user_details_id_to_delete <= 0) {
        $response['message'] = 'Invalid user details record ID provided.';
    } else {
        try {
            // Check if the user details record exists before attempting deletion
            $existing_user_details = $userDetailsModel->findById($user_details_id_to_delete);
            if (!$existing_user_details) {
                $response['message'] = 'User details record not found.';
            } else {
                // Attempt to delete the user details record using the model
                // WARNING: This is likely to fail due to foreign key constraints if other tables depend on it (e.g., tabels).
                // Ensure cascading deletes are handled correctly in the database schema or handle dependencies before deletion.
                if ($userDetailsModel->delete($user_details_id_to_delete)) {
                    $response['success'] = true;
                    $response['message'] = 'User details record deleted successfully!';
                    $response['data'] = ['deleted_user_details_id' => $user_details_id_to_delete];
                } else {
                    // This could mean the row didn't exist or a constraint prevented deletion
                    $response['message'] = 'Failed to delete user details record. It might not exist or be linked to other records that prevent deletion (e.g., related table orders).';
                }
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during user details record deletion.';
            error_log("API User Details delete error: " . $e->getMessage()); // Log the actual error
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