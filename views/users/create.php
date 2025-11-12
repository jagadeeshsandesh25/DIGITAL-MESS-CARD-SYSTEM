<?php
// views/users/create.php

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

$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ph_no = trim($_POST['ph_no'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $role = $_POST['role'] ?? 'user'; // Default role is 'user'
    $status = isset($_POST['status']) ? 1 : 0; // Checkbox for active status

    // Basic validation
    $errors = [];
    if (empty($first_name) || empty($last_name) || empty($email) || empty($ph_no) || empty($username) || empty($password) || empty($confirm_password) || empty($gender)) {
        $errors[] = "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (strlen($ph_no) !== 10 || !is_numeric($ph_no)) {
        $errors[] = "Phone number must be 10 digits.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if (!in_array($role, ['admin', 'waiter', 'user'])) { // Validate role
        $errors[] = "Invalid role selected.";
    }

    if (!in_array($gender, ['Male', 'Female', 'Other'])) { // Validate gender
        $errors[] = "Invalid gender selected.";
    }

    if (empty($errors)) {
        try {
            // Check if username or email already exists
            $check_query = "SELECT id FROM user WHERE username = :username OR email = :email LIMIT 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                $error_message = "Username or Email already exists.";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Prepare SQL query to insert new user
                $insert_query = "INSERT INTO user (first_name, last_name, email, ph_no, username, password, status, role, gender) VALUES (:first_name, :last_name, :email, :ph_no, :username, :password, :status, :role, :gender)";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':first_name', $first_name);
                $insert_stmt->bindParam(':last_name', $last_name);
                $insert_stmt->bindParam(':email', $email);
                $insert_stmt->bindParam(':ph_no', $ph_no);
                $insert_stmt->bindParam(':username', $username);
                $insert_stmt->bindParam(':password', $hashed_password); // Store the hashed password
                $insert_stmt->bindValue(':status', $status); // Use the value from the checkbox
                $insert_stmt->bindParam(':role', $role);
                $insert_stmt->bindParam(':gender', $gender);

                if ($insert_stmt->execute()) {
                    $success_message = "User created successfully!";
                    // Optionally, redirect to the users list page after successful creation
                    // header("Location: index.php");
                    // exit;
                } else {
                    $error_message = "Failed to create user. Please try again.";
                }
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            $error_message = "Database error. Please try again later.";
            error_log("Create User error: " . $e->getMessage()); // Log the actual error
        }
    } else {
        $error_message = implode("<br>", $errors); // Join multiple errors with line breaks
    }
}

// Prepare the specific content for this page
$content = "
    <h2>Create New User</h2>
    <p>Add a new user account to the system.</p>
";

// Display success or error messages if set
if ($success_message) {
    $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
}
if ($error_message) {
    $content .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>"; // Don't escape HTML for error messages as they might contain <br>
}

$content .= "
    <form method='post' action=''>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='first_name' class='form-label'>First Name:</label>
                <input type='text' class='form-control' id='first_name' name='first_name' value='" . htmlspecialchars($_POST['first_name'] ?? '') . "' required>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='last_name' class='form-label'>Last Name:</label>
                <input type='text' class='form-control' id='last_name' name='last_name' value='" . htmlspecialchars($_POST['last_name'] ?? '') . "' required>
            </div>
        </div>
        <div class='mb-3'>
            <label for='email' class='form-label'>Email:</label>
            <input type='email' class='form-control' id='email' name='email' value='" . htmlspecialchars($_POST['email'] ?? '') . "' required>
        </div>
         <div class='mb-3'>
            <label for='ph_no' class='form-label'>Phone Number:</label>
            <input type='tel' class='form-control' id='ph_no' name='ph_no' value='" . htmlspecialchars($_POST['ph_no'] ?? '') . "' maxlength='10' required>
        </div>
        <div class='mb-3'>
            <label for='username' class='form-label'>Username:</label>
            <input type='text' class='form-control' id='username' name='username' value='" . htmlspecialchars($_POST['username'] ?? '') . "' required>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='password' class='form-label'>Password:</label>
                <input type='password' class='form-control' id='password' name='password' required>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='confirm_password' class='form-label'>Confirm Password:</label>
                <input type='password' class='form-control' id='confirm_password' name='confirm_password' required>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='gender' class='form-label'>Gender:</label>
                <select class='form-select' id='gender' name='gender' required>
                    <option value='' " . (($_POST['gender'] ?? '') === '' ? 'selected' : '') . ">Select Gender</option>
                    <option value='Male' " . (($_POST['gender'] ?? '') === 'Male' ? 'selected' : '') . ">Male</option>
                    <option value='Female' " . (($_POST['gender'] ?? '') === 'Female' ? 'selected' : '') . ">Female</option>
                    <option value='Other' " . (($_POST['gender'] ?? '') === 'Other' ? 'selected' : '') . ">Other</option>
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='role' class='form-label'>Role:</label>
                <select class='form-select' id='role' name='role'>
                    <option value='user' " . (($_POST['role'] ?? '') === 'user' ? 'selected' : '') . ">User</option>
                    <option value='waiter' " . (($_POST['role'] ?? '') === 'waiter' ? 'selected' : '') . ">Waiter</option>
                    <option value='admin' " . (($_POST['role'] ?? '') === 'admin' ? 'selected' : '') . ">Admin</option>
                </select>
            </div>
        </div>
        <div class='mb-3 form-check'>
            <input type='checkbox' class='form-check-input' id='status' name='status' value='1' " . ((isset($_POST['status']) && $_POST['status'] == 1) ? 'checked' : '') . ">
            <label class='form-check-label' for='status'>Active Status</label>
        </div>
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Create User</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Users List</a>
    </div>
";

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout