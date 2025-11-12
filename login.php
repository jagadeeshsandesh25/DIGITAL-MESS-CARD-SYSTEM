<?php
// login.php (Login Page Entry Point)

// Start the session
session_start();

// --- Check if the user is already logged in ---
// If the user is already logged in, redirect them to their appropriate dashboard
// to prevent them from accessing the login page again.
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // Get the user's role from the session
    $user_role = $_SESSION['role'];

    // Redirect based on the user's role
    switch ($user_role) {
        case 'admin':
            // Redirect admin users to the admin dashboard index
            header("Location: views/dashboard/admin/index.php");
            break;
        case 'waiter':
            // Redirect waiter users to the waiter dashboard index
            header("Location: views/dashboard/waiter/index.php");
            break;
        case 'user':
        default:
            // Redirect regular users (or any other role) to the user dashboard index
            header("Location: views/dashboard/user/index.php");
            break;
    }
    // Important: Always call exit() after a header() redirect to stop further script execution
    exit;
}

// --- Include the AuthController ---
// If the user is not logged in, include the AuthController to handle the login process.
// Adjust the path based on your actual file structure
require_once 'controllers/AuthController.php'; // Make sure this path is correct

// --- Instantiate and Call the Login Method ---
// Create an instance of the AuthController
$authController = new AuthController();

// Call the login() method to display the login form and handle form submission
$authController->login();

// The AuthController's login() method will handle displaying the form,
// processing the submission, and redirecting upon success/failure.
// No further code is needed here.
?>