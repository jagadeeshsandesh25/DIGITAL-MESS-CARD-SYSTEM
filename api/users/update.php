<?php
// api/users/update.php

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

// Check if the request method is POST (or PUT/PATCH, but using POST for simplicity)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve input data
    $user_id = (int)($input['id'] ?? 0);
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $ph_no = trim($input['ph_no'] ?? '');
    $username = trim($input['username'] ?? '');
    $new_password = $input['new_password'] ?? ''; // Optional new password
    $gender = $input['gender'] ?? '';
    $role = $input['role'] ?? 'user'; // Default role
    $status = isset($input['status']) ? 1 : 0; // Checkbox for active status

    // Basic validation
    $errors = [];
    if ($user_id <= 0) {
        $errors[] = "Invalid user ID.";
    }
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
    if (!empty($new_password) && strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long.";
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
        try {
            // Check if the user exists
            $existing_user = $userModel->findById($user_id);
            if (!$existing_user) {
                $errors[] = "User not found.";
            } else {
                // Check for uniqueness of username/email (excluding current user)
                if ($username !== $existing_user['username'] && $userModel->usernameExists($username)) {
                    $errors[] = "Username already exists.";
                } elseif ($email !== $existing_user['email'] && $userModel->emailExists($email)) {
                    $errors[] = "Email already exists.";
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during validation.";
            error_log("API Users update validation error: " . $e->getMessage());
        }
    }

    if (empty($errors)) {
        try {
            // Prepare data array for the model, including the ID
            $update_user_data = [
                'id' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'ph_no' => $ph_no,
                'username' => $username,
                'gender' => $gender,
                'role' => $role,
                'status' => $status
            ];

            // Handle password update if a new one is provided
            if (!empty($new_password)) {
                $update_user_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            // Attempt to update the user using the model
            if ($userModel->update($update_user_data)) {
                $response['success'] = true;
                $response['message'] = 'User updated successfully.';
                $response['data'] = ['user_id' => $user_id];
            } else {
                $response['message'] = 'Failed to update user. No changes made or user not found.';
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during user update.';
            error_log("API Users update error: " . $e->getMessage());
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