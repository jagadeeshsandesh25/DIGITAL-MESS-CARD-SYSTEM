<?php
// views/cards/edit.php

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

// Get card ID from query string
$card_id_to_edit = $_GET['id'] ?? null;

if (!$card_id_to_edit) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$card_details = null;
$user_details = null; // Store associated user info
$success_message = '';
$error_message = '';

// Fetch the specific card's details and associated user info
try {
    // Join card with user to get owner name
    $query = "
        SELECT c.id, c.c_status, c.user_id, c.recharge_details, c.created_at, c.expired_at, c.balance_credits, c.total_credits, u.first_name, u.last_name, u.username
        FROM card c
        LEFT JOIN user u ON c.user_id = u.id
        WHERE c.id = :id LIMIT 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $card_id_to_edit);
    $stmt->execute();

    $card_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$card_details) {
        $error_message = "Card not found.";
    } else {
        // Optionally, fetch the user details separately if needed more extensively
        // $user_query = "SELECT id, first_name, last_name, username FROM user WHERE id = :user_id LIMIT 1";
        // $user_stmt = $db->prepare($user_query);
        // $user_stmt->bindParam(':user_id', $card_details['user_id']);
        // $user_stmt->execute();
        // $user_details = $user_stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "Could not load card details. Please try again later.";
    error_log("Edit Card page - fetch query error: " . $e->getMessage());
}

// Process form submission if card details were found
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $card_details) {
    // Note: user_id and recharge_details might not be editable here, or require special handling
    // For simplicity, let's assume we only edit status, balance, total, and expiry
    $status = $_POST['status'] ?? 'Inactive';
    $balance_credits = (int)($_POST['balance_credits'] ?? 0);
    $total_credits = (int)($_POST['total_credits'] ?? 0);
    $expired_at_input = trim($_POST['expired_at'] ?? '');
    $expired_at = !empty($expired_at_input) ? $expired_at_input : null;

    // Basic validation
    $errors = [];
    if ($balance_credits < 0) {
        $errors[] = "Balance credits cannot be negative.";
    }
    if ($total_credits < 0) {
        $errors[] = "Total credits cannot be negative.";
    }
    if ($balance_credits > $total_credits) {
        $errors[] = "Balance credits cannot exceed total credits.";
    }
    if (!in_array($status, ['Active', 'Inactive'])) {
        $errors[] = "Invalid status selected.";
    }
    // Validate expiry date format if provided
    if (!empty($expired_at_input) && DateTime::createFromFormat('Y-m-d', $expired_at_input) === FALSE) {
        $errors[] = "Invalid expiry date format. Please use YYYY-MM-DD.";
    }

    if (empty($errors)) {
        try {
            // Prepare SQL query to update the card
            $update_query = "UPDATE card SET c_status = :c_status, balance_credits = :balance_credits, total_credits = :total_credits, expired_at = :expired_at WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':c_status', $status);
            $update_stmt->bindParam(':balance_credits', $balance_credits, PDO::PARAM_INT);
            $update_stmt->bindParam(':total_credits', $total_credits, PDO::PARAM_INT);
            $update_stmt->bindParam(':expired_at', $expired_at); // This can be NULL
            $update_stmt->bindParam(':id', $card_id_to_edit);

            if ($update_stmt->execute()) {
                $success_message = "Card updated successfully!";
                // Optionally, refetch the card details to show updated info
                 $stmt->execute(); // Re-execute the fetch query
                 $card_details = $stmt->fetch(PDO::FETCH_ASSOC); // Update the local variable
            } else {
                $error_message = "Failed to update card. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again later.";
            error_log("Edit Card error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}


// Prepare the specific content for this page
$content = "
    <h2>Edit Card</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Cards List</a>";
} elseif ($card_details) {
    $content .= "<p>Editing details for Card ID: <strong>" . htmlspecialchars($card_details['id']) . "</strong>, Owner: <strong>" . htmlspecialchars($card_details['first_name'] . ' ' . $card_details['last_name'] . ' (' . $card_details['username'] . ')') . "</strong></p>";

    if ($success_message) {
        $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }

    $status_selected_active = $card_details['c_status'] === 'Active' ? 'selected' : '';
    $status_selected_inactive = $card_details['c_status'] === 'Inactive' ? 'selected' : '';

    $content .= "
    <form method='post' action=''>
        <div class='mb-3'>
            <label for='card_id_display' class='form-label'>Card ID (Read-only):</label>
            <input type='text' class='form-control-plaintext' id='card_id_display' value='" . htmlspecialchars($card_details['id']) . "' readonly>
        </div>
        <div class='mb-3'>
            <label for='owner_info' class='form-label'>Card Owner (Read-only):</label>
            <input type='text' class='form-control-plaintext' id='owner_info' value='" . htmlspecialchars($card_details['first_name'] . ' ' . $card_details['last_name'] . ' (' . $card_details['username'] . ')') . "' readonly>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='balance_credits' class='form-label'>Balance Credits:</label>
                <input type='number' class='form-control' id='balance_credits' name='balance_credits' value='" . htmlspecialchars($card_details['balance_credits']) . "' min='0' step='0.01' required>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='total_credits' class='form-label'>Total Credits:</label>
                <input type='number' class='form-control' id='total_credits' name='total_credits' value='" . htmlspecialchars($card_details['total_credits']) . "' min='0' step='0.01' required>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='status' class='form-label'>Card Status:</label>
                <select class='form-select' id='status' name='status'>
                    <option value='Active' " . $status_selected_active . ">Active</option>
                    <option value='Inactive' " . $status_selected_inactive . ">Inactive</option>
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='expired_at' class='form-label'>Expiry Date (Optional):</label>
                <input type='date' class='form-control' id='expired_at' name='expired_at' value='" . htmlspecialchars($card_details['expired_at'] ?? '') . "'>
            </div>
        </div>
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Update Card</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Cards List</a>
        <a href='view.php?id=" . $card_details['id'] . "' class='btn btn-info'>View Card Details</a>
        <!-- Recharge might be a common next step after editing balance -->
        <a href='../../views/recharge/create.php?card_id=" . $card_details['id'] . "' class='btn btn-success'>Recharge Card</a>
    </div>
    ";
} else {
    // This case handles when the card ID was provided but the card wasn't found (error_message is set above)
    $content .= "<p>Unable to load card information.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Cards List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>