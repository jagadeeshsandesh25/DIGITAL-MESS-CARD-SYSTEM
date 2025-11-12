<?php
// views/cards/create.php

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

// Fetch list of users to link the card to
$users = [];
try {
    $user_query = "SELECT id, first_name, last_name, username FROM user ORDER BY username";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute();
    $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Could not load users. Please try again later.";
    error_log("Create Card page - Users query error: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $status = $_POST['status'] ?? 'Inactive'; // Default to Inactive
    $balance_credits = (int)($_POST['balance_credits'] ?? 0); // Ensure it's an integer
    $total_credits = (int)($_POST['total_credits'] ?? 0); // Ensure it's an integer
    $created_at = date('Y-m-d H:i:s'); // Use current time
    $expired_at_input = trim($_POST['expired_at'] ?? ''); // Optional expiry date
    $expired_at = !empty($expired_at_input) ? $expired_at_input : null;

    // Basic validation
    $errors = [];
    if (empty($user_id) || $user_id == 0) {
        $errors[] = "Please select a user.";
    }
    if ($balance_credits < 0) {
        $errors[] = "Balance credits cannot be negative.";
    }
    if ($total_credits < 0) {
        $errors[] = "Total credits cannot be negative.";
    }
    if ($balance_credits > $total_credits) {
        $errors[] = "Balance credits cannot exceed total credits.";
    }
    // Validate expiry date format if provided
    if (!empty($expired_at_input) && DateTime::createFromFormat('Y-m-d', $expired_at_input) === FALSE) {
        $errors[] = "Invalid expiry date format. Please use YYYY-MM-DD.";
    }

    if (empty($errors)) {
        try {
            // Prepare SQL query to insert new card
            // Note: 'recharge_details' is a foreign key in the 'card' table schema, but it's unclear what initial value it should have when creating a card before a recharge.
            // It might be set to NULL initially if allowed, or perhaps a default recharge ID is used, or it's updated later during a recharge process.
            // For now, let's assume it can be NULL initially (check your database schema constraints).
            // If it cannot be NULL, you might need to create a corresponding entry in 'recharge' first, or link an existing one.
            // Let's proceed assuming it can be NULL initially for a new, empty card.
            $insert_query = "INSERT INTO card (c_status, user_id, recharge_details, created_at, expired_at, balance_credits, total_credits) VALUES (:c_status, :user_id, NULL, :created_at, :expired_at, :balance_credits, :total_credits)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':c_status', $status);
            $insert_stmt->bindParam(':user_id', $user_id);
            $insert_stmt->bindParam(':created_at', $created_at);
            $insert_stmt->bindParam(':expired_at', $expired_at); // This can be NULL
            $insert_stmt->bindParam(':balance_credits', $balance_credits, PDO::PARAM_INT);
            $insert_stmt->bindParam(':total_credits', $total_credits, PDO::PARAM_INT);

            if ($insert_stmt->execute()) {
                $new_card_id = $db->lastInsertId();
                $success_message = "Card created successfully! Card ID: $new_card_id";
                // Optionally, redirect to the cards list page after successful creation
                // header("Location: index.php");
                // exit;
            } else {
                $error_message = "Failed to create card. Please try again.";
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            $error_message = "Database error. Please try again later.";
            error_log("Create Card error: " . $e->getMessage()); // Log the actual error
        }
    } else {
        $error_message = implode("<br>", $errors); // Join multiple errors with line breaks
    }
}

// Prepare the specific content for this page
$content = "
    <h2>Create New Card</h2>
    <p>Link a new card to an existing user.</p>
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
        <div class='mb-3'>
            <label for='user_id' class='form-label'>Select User:</label>
            <select class='form-select' id='user_id' name='user_id' required>
                <option value='0'>Choose User...</option>
        ";

        foreach ($users as $user) {
             $selected_attr = ($_POST['user_id'] ?? null) == $user['id'] ? 'selected' : '';
             $content .= "<option value='" . $user['id'] . "' " . $selected_attr . ">" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')') . "</option>";
        }

        $content .= "
            </select>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='balance_credits' class='form-label'>Initial Balance Credits:</label>
                <input type='number' class='form-control' id='balance_credits' name='balance_credits' value='" . htmlspecialchars($_POST['balance_credits'] ?? 0) . "' min='0' step='0.01' required>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='total_credits' class='form-label'>Initial Total Credits:</label>
                <input type='number' class='form-control' id='total_credits' name='total_credits' value='" . htmlspecialchars($_POST['total_credits'] ?? 0) . "' min='0' step='0.01' required>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='status' class='form-label'>Card Status:</label>
                <select class='form-select' id='status' name='status'>
                    <option value='Active' " . (($_POST['status'] ?? '') === 'Active' ? 'selected' : '') . ">Active</option>
                    <option value='Inactive' " . (($_POST['status'] ?? 'Inactive') === 'Inactive' ? 'selected' : '') . ">Inactive</option>
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='expired_at' class='form-label'>Expiry Date (Optional):</label>
                <input type='date' class='form-control' id='expired_at' name='expired_at' value='" . htmlspecialchars($_POST['expired_at'] ?? '') . "'>
            </div>
        </div>
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Create Card</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Cards List</a>
    </div>
";

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>