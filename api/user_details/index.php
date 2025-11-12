<?php
// api/user_details/index.php

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

// Check if the user is logged in and is an admin (or potentially user for their own details)
// For simplicity, let's restrict to admin for full list, users might see theirs via a different endpoint or filtered list
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin privileges required to list all user details records.';
    echo json_encode($response);
    exit;
}

try {
    // Fetch all user details records from the model, ordered by creation time (newest first)
    $user_details_records = $userDetailsModel->findAll(); // Default order by created_at DESC

    if ($user_details_records !== false) {
        $response['success'] = true;
        $response['message'] = 'User details records fetched successfully.';
        $response['data'] = $user_details_records; // Return the array of user details data
    } else {
        $response['message'] = 'Failed to fetch user details records.';
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Database error while fetching user details records.';
    error_log("API User Details index error: " . $e->getMessage()); // Log the actual error
}

// Output the JSON response
echo json_encode($response);
exit;
?>