<?php
// api/auth/signup.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session (might be needed if auto-start is off)
// session_start(); // Usually not required for signup, but good to have if interacting with session later

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
    $confirm_password = $input['confirm_password'] ?? '';
    $gender = $input['gender'] ?? '';
    $role = $input['role'] ?? 'user'; // Default role is 'user'

    // Basic validation
    $errors = [];
    if (empty($first_name)) {
        $errors[] = 'First name is required.';
    }
    if (empty($last_name)) {
        $errors[] = 'Last name is required.';
    }
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    if (empty($ph_no)) {
        $errors[] = 'Phone number is required.';
    } elseif (strlen($ph_no) !== 10 || !ctype_digit($ph_no)) {
        $errors[] = 'Phone number must be 10 digits.';
    }
    if (empty($username)) {
        $errors[] = 'Username is required.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    if (empty($gender)) {
        $errors[] = 'Gender is required.';
    } elseif (!in_array($gender, ['Male', 'Female', 'Other'])) {
        $errors[] = 'Invalid gender selected.';
    }
    if (empty($role)) {
        $errors[] = 'Role is required.';
    } elseif (!in_array($role, ['admin', 'waiter', 'user'])) {
        $errors[] = 'Invalid role selected.';
    }

    // If basic validation passes, check for existing username/email
    if (empty($errors)) {
        try {
            if ($userModel->usernameExists($username)) {
                $errors[] = 'Username already exists.';
            }
            if ($userModel->emailExists($email)) {
                $errors[] = 'Email already exists.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error during signup validation. Please try again later.';
            error_log("Signup API validation error: " . $e->getMessage());
        }
    }

    // If all validation passes, proceed with signup
    if (empty($errors)) {
        try {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Prepare user data array for the model
            $new_user_data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'ph_no' => $ph_no,
                'username' => $username,
                'password_hash' => $hashed_password, // Store the hashed password
                'status' => 1, // Default active status
                'role' => $role,
                'gender' => $gender
            ];

            // Attempt to create the user using the model
            $new_user_id = $userModel->create($new_user_data);

            if ($new_user_id) {
                // Signup successful
                $response['success'] = true;
                $response['message'] = 'Signup successful! You can now log in.';
                $response['data'] = [
                    'user_id' => $new_user_id,
                    'username' => $username
                ];

                // Log successful signup (optional)
                error_log("New user signed up: " . $username . " (ID: " . $new_user_id . ")");
            } else {
                $response['message'] = 'Failed to create user account. Please try again.';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error during signup. Please try again later.';
            error_log("Signup API error: " . $e->getMessage());
        }
    } else {
        // Validation failed
        $response['message'] = implode(' ', $errors); // Join errors with space
    }
} else {
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Output the JSON response
echo json_encode($response);
exit;
?>