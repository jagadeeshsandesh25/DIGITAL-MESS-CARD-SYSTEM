<?php
// api/auth/logout.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session
session_start();

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // User is logged in, proceed with logout
    // Unset all session variables
    session_unset();

    // Destroy the session
    session_destroy();

    // Regenerate session ID for security (optional but recommended)
    // session_regenerate_id(true); // Not needed after destroy

    // Prepare success response
    $response['success'] = true;
    $response['message'] = 'Logout successful.';
    $response['data'] = [
        'redirect_url' => '../../views/auth/login.php' // Suggest redirect URL to the client
    ];

    // Log successful logout (optional)
    error_log("User logged out successfully: ID " . $_SESSION['user_id'] . " (Role: " . $_SESSION['role'] . ")");
} else {
    // User is not logged in
    $response['message'] = 'No active session found. Already logged out.';
    // Optionally, still suggest redirecting to login
    $response['data'] = [
        'redirect_url' => '../../views/auth/login.php'
    ];
}

// Output the JSON response
echo json_encode($response);
exit;
?>