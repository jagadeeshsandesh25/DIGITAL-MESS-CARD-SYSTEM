<?php
// views/user_details/edit.php

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

// Get user details record ID from query string
$record_id_to_edit = $_GET['id'] ?? null;

if (!$record_id_to_edit) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$record_details = null;
$success_message = '';
$error_message = '';

// Fetch the specific user details record's information along with related user, card, transaction, and recharge info
try {
    $query = "
        SELECT udt.id, udt.user_id, udt.card_id, udt.transaction_id, udt.recharge_id,
               u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
               c.id as card_id_main, c.c_status as card_status,
               t.id as transaction_id_main, t.t_type as transaction_type,
               r.id as recharge_id_main, r.r_type as recharge_type,
               udt.present_pswd, udt.previous_pswd, udt.username as udt_username, udt.contact_details, udt.created_at, udt.updated_at
        FROM user_details_tabel udt
        LEFT JOIN user u ON udt.user_id = u.id
        LEFT JOIN card c ON udt.card_id = c.id
        LEFT JOIN transactions t ON udt.transaction_id = t.id
        LEFT JOIN recharge r ON udt.recharge_id = r.id
        WHERE udt.id = :id LIMIT 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $record_id_to_edit);
    $stmt->execute();

    $record_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record_details) {
        $error_message = "User details record not found.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load user details record. Please try again later.";
    error_log("Edit User Details page - fetch query error: " . $e->getMessage());
}

// Process form submission if record details were found
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $record_details) {
    // Note: Updating user_id, card_id, transaction_id, recharge_id might have significant implications
    // and should be done with extreme caution for a record like this.
    // Often, these linking IDs are set during creation and are immutable.
    // We'll include them in the form but add a warning.
    $user_id = $_POST['user_id'] ?? null;
    $card_id = $_POST['card_id'] ?? null;
    $transaction_id = $_POST['transaction_id'] ?? null;
    $recharge_id = $_POST['recharge_id'] ?? null;
    $udt_username = trim($_POST['udt_username'] ?? '');
    $contact_details = trim($_POST['contact_details'] ?? '');
    // Note: Updating password hashes directly is generally not recommended.
    // $present_pswd = trim($_POST['present_pswd'] ?? '');
    // $previous_pswd = trim($_POST['previous_pswd'] ?? '');
    // Let's exclude password fields from this form for safety.
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

    // Validate IDs exist in their respective tables (optional but good practice)
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
            // Prepare SQL query to update the user details record
            // WARNING: Updating foreign keys (user_id, card_id, etc.) can break referential integrity or data logic.
            $update_query = "
                UPDATE user_details_tabel
                SET user_id = :user_id, card_id = :card_id, transaction_id = :transaction_id, recharge_id = :recharge_id,
                    username = :udt_username, contact_details = :contact_details, updated_at = :updated_at
                WHERE id = :id
            ";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':user_id', $user_id);
            $update_stmt->bindParam(':card_id', $card_id);
            $update_stmt->bindParam(':transaction_id', $transaction_id);
            $update_stmt->bindParam(':recharge_id', $recharge_id);
            $update_stmt->bindParam(':udt_username', $udt_username);
            $update_stmt->bindParam(':contact_details', $contact_details);
            $update_stmt->bindParam(':updated_at', $updated_at);
            $update_stmt->bindParam(':id', $record_id_to_edit);

            if ($update_stmt->execute()) {
                $success_message = "User details record updated successfully!";
                // Optionally, refetch the record details to show updated info
                 $stmt->execute(); // Re-execute the fetch query
                 $record_details = $stmt->fetch(PDO::FETCH_ASSOC); // Update the local variable
            } else {
                $error_message = "Failed to update user details record. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again later.";
            error_log("Edit User Details error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch lists of users, cards, transactions, and recharges for the edit form (only if initial fetch was successful)
$users = [];
$cards = [];
$transactions = [];
$recharges = [];

if ($record_details) {
    try {
        $user_query = "SELECT id, first_name, last_name, username FROM user ORDER BY username";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->execute();
        $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

        $card_query = "SELECT id, user_id, c_status FROM card ORDER BY user_id, id";
        $card_stmt = $db->prepare($card_query);
        $card_stmt->execute();
        $cards = $card_stmt->fetchAll(PDO::FETCH_ASSOC);

        $transaction_query = "SELECT id, user_id, card_id, t_type FROM transactions ORDER BY t_time DESC LIMIT 50"; // Limit for performance
        $transaction_stmt = $db->prepare($transaction_query);
        $transaction_stmt->execute();
        $transactions = $transaction_stmt->fetchAll(PDO::FETCH_ASSOC);

        $recharge_query = "SELECT id, user_id, card_id, r_type FROM recharge ORDER BY r_time DESC LIMIT 50"; // Limit for performance
        $recharge_stmt = $db->prepare($recharge_query);
        $recharge_stmt->execute();
        $recharges = $recharge_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = "Could not load data for the form. Please try again later.";
        error_log("Edit User Details page - Form data queries error: " . $e->getMessage());
    }
}


// Prepare the specific content for this page
$content = "
    <h2>Edit User Details Record</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to User Details List</a>";
} elseif ($record_details) {
    $user_name_display = $record_details['user_first_name'] ? htmlspecialchars($record_details['user_first_name'] . ' ' . $record_details['user_last_name']) : htmlspecialchars($record_details['user_username']);
    $user_link = "<a href='../../views/users/view.php?id=" . $record_details['user_id'] . "'>" . $user_name_display . "</a>";
    $card_link = "<a href='../../views/cards/view.php?id=" . $record_details['card_id_main'] . "'>Card #" . $record_details['card_id_main'] . "</a>";
    $transaction_link = "<a href='../../views/transactions/view.php?id=" . $record_details['transaction_id_main'] . "'>#" . $record_details['transaction_id_main'] . "</a>";
    $recharge_link = "<a href='../../views/recharge/view.php?id=" . $record_details['recharge_id_main'] . "'>#" . $record_details['recharge_id_main'] . "</a>";

    $content .= "<p>Editing details for User Details Record ID: <strong>" . htmlspecialchars($record_details['id']) . "</strong>, Associated User: <strong>" . $user_link . "</strong></p>";

    if ($success_message) {
        $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }

    $content .= "
    <div class='alert alert-warning' role='alert'>
        <strong>Warning:</strong> Be extremely careful when editing linking IDs (User, Card, Transaction, Recharge).
        Changing these might break data integrity or link the record to incorrect entities.
    </div>

    <form method='post' action=''>
        <div class='mb-3'>
            <label for='record_id_display' class='form-label'>Record ID (Read-only):</label>
            <input type='text' class='form-control-plaintext' id='record_id_display' value='" . htmlspecialchars($record_details['id']) . "' readonly>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='user_id' class='form-label'>Associated User (ID):</label>
                <select class='form-select' id='user_id' name='user_id' required>
                    <option value='0'>Choose User...</option>
        ";

        foreach ($users as $user) {
             $selected_attr = $user['id'] == $record_details['user_id'] ? 'selected' : '';
             $content .= "<option value='" . $user['id'] . "' " . $selected_attr . ">" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')') . "</option>";
        }

        $content .= "
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='card_id' class='form-label'>Associated Card (ID):</label>
                <select class='form-select' id='card_id' name='card_id' required>
                    <option value='0'>Choose Card...</option>
        ";

        foreach ($cards as $card) {
             $selected_attr = $card['id'] == $record_details['card_id_main'] ? 'selected' : '';
             $content .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (User ID: " . $card['user_id'] . ", Status: " . $card['c_status'] . ")</option>";
        }

        $content .= "
                </select>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='transaction_id' class='form-label'>Associated Transaction (ID):</label>
                <select class='form-select' id='transaction_id' name='transaction_id' required>
                    <option value='0'>Choose Transaction...</option>
        ";

        foreach ($transactions as $transaction) {
             $selected_attr = $transaction['id'] == $record_details['transaction_id_main'] ? 'selected' : '';
             $content .= "<option value='" . $transaction['id'] . "' " . $selected_attr . ">#" . $transaction['id'] . " (User: " . $transaction['user_id'] . ", Card: " . $transaction['card_id'] . ", Type: " . $transaction['t_type'] . ")</option>";
        }

        $content .= "
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='recharge_id' class='form-label'>Associated Recharge (ID):</label>
                <select class='form-select' id='recharge_id' name='recharge_id' required>
                    <option value='0'>Choose Recharge...</option>
        ";

        foreach ($recharges as $recharge) {
             $selected_attr = $recharge['id'] == $record_details['recharge_id_main'] ? 'selected' : '';
             $content .= "<option value='" . $recharge['id'] . "' " . $selected_attr . ">#" . $recharge['id'] . " (User: " . $recharge['user_id'] . ", Card: " . $recharge['card_id'] . ", Type: " . $recharge['r_type'] . ")</option>";
        }

        $content .= "
                </select>
            </div>
        </div>
        <div class='mb-3'>
            <label for='udt_username' class='form-label'>Username (Stored in this Record):</label>
            <input type='text' class='form-control' id='udt_username' name='udt_username' value='" . htmlspecialchars($record_details['udt_username']) . "' required>
        </div>
        <div class='mb-3'>
            <label for='contact_details' class='form-label'>Contact Details (Stored in this Record):</label>
            <input type='text' class='form-control' id='contact_details' name='contact_details' value='" . htmlspecialchars($record_details['contact_details']) . "' required>
        </div>
        <!-- Password fields are omitted for safety -->
        <!-- <div class='mb-3'>
            <label for='present_pswd' class='form-label'>Present Password Hash (Stored in this Record):</label>
            <input type='text' class='form-control' id='present_pswd' name='present_pswd' value='" . htmlspecialchars($record_details['present_pswd']) . "' readonly> <!-- Or hide entirely -->
            <small class='form-text text-muted'>Password hashes should not be manually edited.</small>
        </div>
        <div class='mb-3'>
            <label for='previous_pswd' class='form-label'>Previous Password Hash (Stored in this Record):</label>
            <input type='text' class='form-control' id='previous_pswd' name='previous_pswd' value='" . htmlspecialchars($record_details['previous_pswd']) . "' readonly> <!-- Or hide entirely -->
            <small class='form-text text-muted'>Password hashes should not be manually edited.</small>
        </div> -->
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Update User Details Record</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to User Details List</a>
        <a href='view.php?id=" . $record_details['id'] . "' class='btn btn-info'>View Record Details</a>
    </div>
    ";
} else {
    // This case handles when the record ID was provided but the record wasn't found (error_message is set above)
    $content .= "<p>Unable to load user details record information.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to User Details List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>