<?php
// views/user_details/create.php

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

// Fetch list of users
$users = [];
try {
    $user_query = "SELECT id, first_name, last_name, username FROM user ORDER BY username";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute();
    $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Could not load users. Please try again later.";
    error_log("Create User Details Record page - Users query error: " . $e->getMessage());
}

// Fetch list of cards
$cards = [];
try {
    $card_query = "SELECT id, user_id, c_status FROM card ORDER BY user_id, id";
    $card_stmt = $db->prepare($card_query);
    $card_stmt->execute();
    $cards = $card_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= " Could not load cards. ";
    error_log("Create User Details Record page - Cards query error: " . $e->getMessage());
}

// Fetch list of transactions
$transactions = [];
try {
    $transaction_query = "SELECT id, user_id, card_id, t_type FROM transactions ORDER BY t_time DESC LIMIT 50"; // Limit for performance
    $transaction_stmt = $db->prepare($transaction_query);
    $transaction_stmt->execute();
    $transactions = $transaction_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= " Could not load transactions. ";
    error_log("Create User Details Record page - Transactions query error: " . $e->getMessage());
}

// Fetch list of recharges
$recharges = [];
try {
    $recharge_query = "SELECT id, user_id, card_id, r_type FROM recharge ORDER BY r_time DESC LIMIT 50"; // Limit for performance
    $recharge_stmt = $db->prepare($recharge_query);
    $recharge_stmt->execute();
    $recharges = $recharge_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= " Could not load recharges. ";
    error_log("Create User Details Record page - Recharges query error: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $card_id = $_POST['card_id'] ?? null;
    $transaction_id = $_POST['transaction_id'] ?? null;
    $recharge_id = $_POST['recharge_id'] ?? null;
    $udt_username = trim($_POST['udt_username'] ?? '');
    $contact_details = trim($_POST['contact_details'] ?? '');
    // Note: Creating new password hashes here is unusual. This table likely logs *changes*.
    // $present_pswd = trim($_POST['present_pswd'] ?? '');
    // $previous_pswd = trim($_POST['previous_pswd'] ?? '');
    // Let's omit password fields for creation unless specifically needed for an initial state log.
    $created_at = date('Y-m-d H:i:s');
    $updated_at = date('Y-m-d H:i:s');

    // Basic validation
    $errors = [];
    if (empty($user_id) || $user_id == 0) {
        $errors[] = "Please select a user.";
    }
    if (empty($card_id) || $card_id == 0) {
        $errors[] = "Please select a card.";
    }
    if (empty($transaction_id) || $transaction_id == 0) {
        $errors[] = "Please select a transaction.";
    }
    if (empty($recharge_id) || $recharge_id == 0) {
        $errors[] = "Please select a recharge.";
    }
    if (empty($udt_username)) {
        $errors[] = "Username in record cannot be empty.";
    }
    if (empty($contact_details)) {
        $errors[] = "Contact details in record cannot be empty.";
    }

    // Validate IDs exist in their respective tables
    $id_checks = [
        'user_id' => ['table' => 'user', 'id' => $user_id],
        'card_id' => ['table' => 'card', 'id' => $card_id],
        'transaction_id' => ['table' => 'transactions', 'id' => $transaction_id],
        'recharge_id' => ['table' => 'recharge', 'id' => $recharge_id],
    ];

    foreach ($id_checks as $field => $check) {
        if (!empty($check['id'])) {
             $check_query = "SELECT id FROM {$check['table']} WHERE id = :id LIMIT 1";
             $check_stmt = $db->prepare($check_query);
             $check_stmt->bindParam(':id', $check['id']);
             $check_stmt->execute();
             if ($check_stmt->rowCount() === 0) {
                 $errors[] = "Selected {$field} (ID: {$check['id']}) does not exist.";
             }
        }
    }


    if (empty($errors)) {
        try {
            // Prepare SQL query to insert new user details record
            // Note: This table seems to track specific states/changes linked to other actions.
            // Creating a 'new' one manually might not always make sense depending on your business logic.
            $insert_query = "
                INSERT INTO user_details_tabel (user_id, card_id, transaction_id, recharge_id, present_pswd, previous_pswd, username, contact_details, created_at, updated_at)
                VALUES (:user_id, :card_id, :transaction_id, :recharge_id, :present_pswd, :previous_pswd, :udt_username, :contact_details, :created_at, :updated_at)
            ";
            // For a new record, present/previous passwords might be empty or set to a default/hash of the current user password if known.
            // Let's initialize them as empty strings for now, assuming they are populated during specific password change events.
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':user_id', $user_id);
            $insert_stmt->bindParam(':card_id', $card_id);
            $insert_stmt->bindParam(':transaction_id', $transaction_id);
            $insert_stmt->bindParam(':recharge_id', $recharge_id);
            $insert_stmt->bindValue(':present_pswd', ''); // Initialize as empty or hash if applicable
            $insert_stmt->bindValue(':previous_pswd', ''); // Initialize as empty or hash if applicable
            $insert_stmt->bindParam(':udt_username', $udt_username);
            $insert_stmt->bindParam(':contact_details', $contact_details);
            $insert_stmt->bindParam(':created_at', $created_at);
            $insert_stmt->bindParam(':updated_at', $updated_at);

            if ($insert_stmt->execute()) {
                $new_record_id = $db->lastInsertId();
                $success_message = "User details record created successfully! Record ID: $new_record_id";
                // Optionally, redirect to the user details list page after successful creation
                // header("Location: index.php");
                // exit;
            } else {
                $error_message = "Failed to create user details record. Please try again.";
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            $error_message = "Database error. Please try again later.";
            error_log("Create User Details Record error: " . $e->getMessage()); // Log the actual error
        }
    } else {
        $error_message = implode("<br>", $errors); // Join multiple errors with line breaks
    }
}

// Prepare the specific content for this page
$content = "
    <h2>Create New User Details Record</h2>
    <p>Log a new user-specific detail record (e.g., for tracking changes linked to specific actions). <strong>Use with caution.</strong></p>
";

// Display success or error messages if set
if ($success_message) {
    $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
}
if ($error_message) {
    $content .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>"; // Don't escape HTML for error messages as they might contain <br>
}

$content .= "
    <div class='alert alert-info' role='alert'>
        <strong>Note:</strong> This table typically stores details related to specific user actions (like password changes, contact updates linked to transactions/recharges).
        Creating a record manually should align with a specific event or state change.
    </div>

    <form method='post' action=''>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='user_id' class='form-label'>Associated User:</label>
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
            <div class='col-md-6 mb-3'>
                <label for='card_id' class='form-label'>Associated Card:</label>
                <select class='form-select' id='card_id' name='card_id' required>
                    <option value='0'>Choose Card...</option>
        ";

        foreach ($cards as $card) {
             $selected_attr = ($_POST['card_id'] ?? null) == $card['id'] ? 'selected' : '';
             $content .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (User ID: " . $card['user_id'] . ", Status: " . $card['c_status'] . ")</option>";
        }

        $content .= "
                </select>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='transaction_id' class='form-label'>Associated Transaction:</label>
                <select class='form-select' id='transaction_id' name='transaction_id' required>
                    <option value='0'>Choose Transaction...</option>
        ";

        foreach ($transactions as $transaction) {
             $selected_attr = ($_POST['transaction_id'] ?? null) == $transaction['id'] ? 'selected' : '';
             $content .= "<option value='" . $transaction['id'] . "' " . $selected_attr . ">#" . $transaction['id'] . " (User: " . $transaction['user_id'] . ", Card: " . $transaction['card_id'] . ", Type: " . $transaction['t_type'] . ")</option>";
        }

        $content .= "
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='recharge_id' class='form-label'>Associated Recharge:</label>
                <select class='form-select' id='recharge_id' name='recharge_id' required>
                    <option value='0'>Choose Recharge...</option>
        ";

        foreach ($recharges as $recharge) {
             $selected_attr = ($_POST['recharge_id'] ?? null) == $recharge['id'] ? 'selected' : '';
             $content .= "<option value='" . $recharge['id'] . "' " . $selected_attr . ">#" . $recharge['id'] . " (User: " . $recharge['user_id'] . ", Card: " . $recharge['card_id'] . ", Type: " . $recharge['r_type'] . ")</option>";
        }

        $content .= "
                </select>
            </div>
        </div>
        <div class='mb-3'>
            <label for='udt_username' class='form-label'>Username (to store in this record):</label>
            <input type='text' class='form-control' id='udt_username' name='udt_username' value='" . htmlspecialchars($_POST['udt_username'] ?? '') . "' required>
        </div>
        <div class='mb-3'>
            <label for='contact_details' class='form-label'>Contact Details (to store in this record):</label>
            <input type='text' class='form-control' id='contact_details' name='contact_details' value='" . htmlspecialchars($_POST['contact_details'] ?? '') . "' required>
        </div>
        <!-- Password fields are omitted for creation for safety -->
        <!-- <div class='mb-3'>
            <label for='present_pswd' class='form-label'>Present Password Hash (for this record state) (Optional):</label>
            <input type='text' class='form-control' id='present_pswd' name='present_pswd' value='" . htmlspecialchars($_POST['present_pswd'] ?? '') . "'>
            <small class='form-text text-muted'>Usually updated automatically during password changes.</small>
        </div>
        <div class='mb-3'>
            <label for='previous_pswd' class='form-label'>Previous Password Hash (for this record state) (Optional):</label>
            <input type='text' class='form-control' id='previous_pswd' name='previous_pswd' value='" . htmlspecialchars($_POST['previous_pswd'] ?? '') . "'>
            <small class='form-text text-muted'>Usually updated automatically during password changes.</small>
        </div> -->
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Create User Details Record</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to User Details List</a>
    </div>
";

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>