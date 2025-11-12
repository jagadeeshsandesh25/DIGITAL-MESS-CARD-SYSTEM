<?php
// api/auth/login.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session
session_start();

// Include database configuration
// Adjust the path based on your actual file structure
// This file should be located at: mess_management_system/api/auth/login.php
// So, config/database.php is 2 levels up and then into config/
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Include the User model
// Adjust the path based on your actual file structure
require_once '../../models/User.php'; // Adjust path as needed
$userModel = new User($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve input data
    $username_or_email = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    // Basic validation
    if (empty($username_or_email) || empty($password)) {
        $response['message'] = 'Username/Email and password are required.';
        echo json_encode($response);
        exit;
    }

    try {
        // --- Critical Fix: Use the correct method from the User model ---
        // The User model's findByUsernameOrEmail method should query the 'user' table
        // with columns: id, username, email, password, role, status
        $user = $userModel->findByUsernameOrEmail($username_or_email);

        // --- Critical Fix: Verify user exists and password is correct ---
        if ($user && password_verify($password, $user['password'])) {
            // --- Critical Fix: Check if the user account is active ---
            if ($user['status'] == 1) { // Assuming 1 means active in the schema
                // Login successful - set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username']; // Or use first_name/last_name
                $_SESSION['role'] = $user['role'];

                // Prepare success response data
                $response['success'] = true;
                $response['message'] = 'Login successful.';
                $response['data'] = [
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ];

                // Log successful login (optional)
                error_log("User logged in successfully: " . $user['username'] . " (ID: " . $user['id'] . ")");
            } else {
                $response['message'] = 'Your account is inactive. Please contact administration.';
            }
        } else {
            $response['message'] = 'Invalid username/email or password.';
            // Log failed login attempt (optional, for security monitoring)
            error_log("Failed login attempt for: " . $username_or_email);
        }


        } catch (PDOException $e) {
            // --- TEMPORARY DEBUGGING: Log the full error message ---
            $response['message'] = 'Database error during login: ' . $e->getMessage(); // TEMPORARY: Show full error
            error_log("Login API error (Detailed): " . $e->getMessage()); // Log the detailed error
            // --- END TEMPORARY DEBUGGING ---
            // In production, revert to a generic message:
            // $response['message'] = 'Database error during login. Please try again later.';
            // error_log("Login API error: " . $e->getMessage());
        }

} else {
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Output the JSON response
echo json_encode($response);
exit;
?>