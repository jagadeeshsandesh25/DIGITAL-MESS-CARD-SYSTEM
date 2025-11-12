<?php
// views/users/view.php

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
$user_id_to_view = $_GET['id'] ?? null;

if (!$user_id_to_view) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$user_details = null;
$error_message = '';

// Fetch the specific user's details
try {
    $query = "SELECT id, first_name, last_name, email, ph_no, username, status, role, gender, created_at FROM user WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id_to_view);
    $stmt->execute();

    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_details) {
        $error_message = "User not found.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load user details. Please try again later.";
    error_log("View User page - fetch query error: " . $e->getMessage());
}

// Prepare the specific content for this page
$content = "
    <h2>View User Details</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Users List</a>";
} elseif ($user_details) {
    $status_badge = $user_details['status'] == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';

    $content .= "
    <div class='card'>
        <div class='card-header'>
            <h5>User Information: " . htmlspecialchars($user_details['username']) . "</h5>
        </div>
        <div class='card-body'>
            <table class='table table-borderless'>
                <tr>
                    <th scope='row'>ID:</th>
                    <td>" . htmlspecialchars($user_details['id']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Username:</th>
                    <td>" . htmlspecialchars($user_details['username']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Full Name:</th>
                    <td>" . htmlspecialchars($user_details['first_name']) . " " . htmlspecialchars($user_details['last_name']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Email:</th>
                    <td>" . htmlspecialchars($user_details['email']) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Phone Number:</th>
                    <td>" . htmlspecialchars($user_details['ph_no']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Role:</th>
                    <td>" . htmlspecialchars($user_details['role']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Status:</th>
                    <td>" . $status_badge . "</td>
                </tr>
                <tr>
                    <th scope='row'>Gender:</th>
                    <td>" . htmlspecialchars($user_details['gender']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Member Since:</th>
                    <td>" . htmlspecialchars($user_details['created_at']) . "</td>
                </tr>
            </table>
        </div>
    </div>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Users List</a>
        <a href='edit.php?id=" . $user_details['id'] . "' class='btn btn-warning'>Edit User</a>
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