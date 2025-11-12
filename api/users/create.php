<?php
// api/users/create.php

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

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve input data
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $ph_no = trim($input['ph_no'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $gender = $input['gender'] ?? '';
    $role = $input['role'] ?? 'user'; // Default role
    $status = isset($input['status']) ? 1 : 0; // Checkbox for active status

    // Basic validation
    $errors = [];
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($ph_no)) {
        $errors[] = "Phone number is required.";
    } elseif (strlen($ph_no) !== 10 || !ctype_digit($ph_no)) {
        $errors[] = "Phone number must be 10 digits.";
    }
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    if (empty($gender)) {
        $errors[] = "Gender is required.";
    } elseif (!in_array($gender, ['Male', 'Female', 'Other'])) {
        $errors[] = "Invalid gender selected.";
    }
    if (empty($role)) {
        $errors[] = "Role is required.";
    } elseif (!in_array($role, ['admin', 'waiter', 'user'])) {
        $errors[] = "Invalid role selected.";
    }

    if (empty($errors)) {
        // Check for existing username/email
        try {
            if ($userModel->usernameExists($username)) {
                $errors[] = "Username already exists.";
            } elseif ($userModel->emailExists($email)) {
                $errors[] = "Email already exists.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during validation.";
            error_log("API Users create validation error: " . $e->getMessage());
        }
    }

    if (empty($errors)) {
        try {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Prepare data array for the model
            $new_user_data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'ph_no' => $ph_no,
                'username' => $username,
                'password' => $hashed_password, // Store the hashed password
                'status' => $status,
                'role' => $role,
                'gender' => $gender
            ];

            // Attempt to create the user using the model
            $new_user_id = $userModel->create($new_user_data);

            if ($new_user_id) {
                $response['success'] = true;
                $response['message'] = 'User created successfully.';
                $response['data'] = ['user_id' => $new_user_id];
            } else {
                $response['message'] = 'Failed to create user.';
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during user creation.';
            error_log("API Users create error: " . $e->getMessage());
        }
    } else {
        $response['message'] = implode(' ', $errors);
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Output the JSON response
echo json_encode($response);
exit;
?>