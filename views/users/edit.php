<?php
// views/users/edit.php

// Include the authentication check and session details
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php"); // Redirect to login if not authenticated as admin
    exit;
}

$user_name = $_SESSION['username'] ?? 'Admin';

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Get user ID from query string
$user_id_to_edit = $_GET['id'] ?? null;

if (!$user_id_to_edit) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$user_details = null;
$success_message = '';
$error_message = '';

// Fetch the specific user's details
try {
    $query = "SELECT id, first_name, last_name, email, ph_no, username, status, role, gender FROM user WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id_to_edit);
    $stmt->execute();

    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_details) {
        $error_message = "User not found.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load user details. Please try again later.";
    error_log("Edit User page - fetch query error: " . $e->getMessage());
}

// Process form submission if user details were found
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_details) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ph_no = trim($_POST['ph_no'] ?? '');
    $username = trim($_POST['username'] ?? '');
    // Note: Password is typically not changed on the edit page unless explicitly requested with old password verification
    $new_password = $_POST['new_password'] ?? ''; // Optional new password
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $status = isset($_POST['status']) ? 1 : 0; // Checkbox for active status

    // Basic validation (similar to create, but password is optional here)
    $errors = [];
    if (empty($first_name) || empty($last_name) || empty($email) || empty($ph_no) || empty($username) || empty($gender)) {
        $errors[] = "All required fields (except password) must be filled.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (strlen($ph_no) !== 10 || !is_numeric($ph_no)) {
        $errors[] = "Phone number must be 10 digits.";
    }

    if (!empty($new_password) || !empty($confirm_new_password)) { // Only validate if a new password was entered
        if ($new_password !== $confirm_new_password) {
            $errors[] = "New passwords do not match.";
        }
        if (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters long.";
        }
    }

    if (!in_array($role, ['admin', 'waiter', 'user'])) {
        $errors[] = "Invalid role selected.";
    }

    if (!in_array($gender, ['Male', 'Female', 'Other'])) {
        $errors[] = "Invalid gender selected.";
    }

    if (empty($errors)) {
        try {
            // Check if username or email already exists for *another* user
            $check_query = "SELECT id FROM user WHERE (username = :username OR email = :email) AND id != :current_id LIMIT 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->bindParam(':current_id', $user_id_to_edit); // Exclude current user ID
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                $error_message = "Username or Email already exists for another user.";
            } else {
                $hashed_password_sql_part = '';
                $params_for_update = [
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':email' => $email,
                    ':ph_no' => $ph_no,
                    ':username' => $username,
                    ':status' => $status,
                    ':role' => $role,
                    ':gender' => $gender,
                    ':id' => $user_id_to_edit
                ];

                // If a new password was provided, hash it and add to the update query
                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $hashed_password_sql_part = ", password = :password";
                    $params_for_update[':password'] = $hashed_password;
                }

                // Prepare SQL query to update the user
                $update_query = "UPDATE user SET first_name = :first_name, last_name = :last_name, email = :email, ph_no = :ph_no, username = :username, status = :status, role = :role, gender = :gender " . $hashed_password_sql_part . " WHERE id = :id";
                $update_stmt = $db->prepare($update_query);

                if ($update_stmt->execute($params_for_update)) {
                    $success_message = "User updated successfully!";
                    // Optionally, refetch the user details to show updated info
                     $stmt->execute(); // Re-execute the fetch query
                     $user_details = $stmt->fetch(PDO::FETCH_ASSOC); // Update the local variable
                } else {
                    $error_message = "Failed to update user. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again later.";
            error_log("Edit User error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}


// Prepare the specific content for this page
$content = "
    <h2>Edit User</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . $error_message . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Users List</a>";
} elseif ($user_details) {
    $content .= "<p>Editing details for user: <strong>" . htmlspecialchars($user_details['username']) . "</strong></p>";

    if ($success_message) {
        $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }

    $status_checked = $user_details['status'] ? 'checked' : '';

    $content .= "
    <form method='post' action=''>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='first_name' class='form-label'>First Name:</label>
                <input type='text' class='form-control' id='first_name' name='first_name' value='" . htmlspecialchars($user_details['first_name']) . "' required>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='last_name' class='form-label'>Last Name:</label>
                <input type='text' class='form-control' id='last_name' name='last_name' value='" . htmlspecialchars($user_details['last_name']) . "' required>
            </div>
        </div>
        <div class='mb-3'>
            <label for='email' class='form-label'>Email:</label>
            <input type='email' class='form-control' id='email' name='email' value='" . htmlspecialchars($user_details['email']) . "' required>
        </div>
         <div class='mb-3'>
            <label for='ph_no' class='form-label'>Phone Number:</label>
            <input type='tel' class='form-control' id='ph_no' name='ph_no' value='" . htmlspecialchars($user_details['ph_no']) . "' maxlength='10' required>
        </div>
        <div class='mb-3'>
            <label for='username' class='form-label'>Username:</label>
            <input type='text' class='form-control' id='username' name='username' value='" . htmlspecialchars($user_details['username']) . "' required>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='new_password' class='form-label'>(Optional) New Password:</label>
                <input type='password' class='form-control' id='new_password' name='new_password'>
                <small class='form-text text-muted'>Leave blank to keep the current password.</small>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='confirm_new_password' class='form-label'>(Optional) Confirm New Password:</label>
                <input type='password' class='form-control' id='confirm_new_password' name='confirm_new_password'>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='gender' class='form-label'>Gender:</label>
                <select class='form-select' id='gender' name='gender' required>
                    <option value='Male' " . ($user_details['gender'] === 'Male' ? 'selected' : '') . ">Male</option>
                    <option value='Female' " . ($user_details['gender'] === 'Female' ? 'selected' : '') . ">Female</option>
                    <option value='Other' " . ($user_details['gender'] === 'Other' ? 'selected' : '') . ">Other</option>
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='role' class='form-label'>Role:</label>
                <select class='form-select' id='role' name='role'>
                    <option value='user' " . ($user_details['role'] === 'user' ? 'selected' : '') . ">User</option>
                    <option value='waiter' " . ($user_details['role'] === 'waiter' ? 'selected' : '') . ">Waiter</option>
                    <option value='admin' " . ($user_details['role'] === 'admin' ? 'selected' : '') . ">Admin</option>
                </select>
            </div>
        </div>
        <div class='mb-3 form-check'>
            <input type='checkbox' class='form-check-input' id='status' name='status' value='1' " . $status_checked . ">
            <label class='form-check-label' for='status'>Active Status</label>
        </div>
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Update User</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Users List</a>
        <a href='view.php?id=" . $user_details['id'] . "' class='btn btn-info'>View User Details</a>
    </div>
    ";
} else {
    // This case handles when the user ID was provided but the user wasn't found (error_message is set above)
    // Or if the initial fetch failed but didn't set an error message (less likely with the try-catch)
    $content .= "<p>Unable to load user information.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Users List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>