<?php
// api/users/index.php

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

try {
    // Fetch all users from the model
    $users = $userModel->findAll(); // Default order by created_at DESC

    if ($users !== false) {
        $response['success'] = true;
        $response['message'] = 'Users fetched successfully.';
        $response['data'] = $users; // Return the array of user data
    } else {
        $response['message'] = 'Failed to fetch users.';
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Database error while fetching users.';
    error_log("API Users index error: " . $e->getMessage());
}

// Output the JSON response
echo json_encode($response);
exit;
?>