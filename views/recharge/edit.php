<?php
// views/recharge/edit.php

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

// Get recharge ID from query string
$recharge_id_to_edit = $_GET['id'] ?? null;

if (!$recharge_id_to_edit) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$recharge_details = null;
$success_message = '';
$error_message = '';

// Fetch the specific recharge record's details along with related user, card, and transaction info
try {
    $query = "
        SELECT r.id, r.r_type, r.user_id, r.card_id, r.transaction_id, r.r_time,
               u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
               c.balance_credits as card_balance, c.total_credits as card_total,
               t.t_time as transaction_time
        FROM recharge r
        LEFT JOIN user u ON r.user_id = u.id
        LEFT JOIN card c ON r.card_id = c.id
        LEFT JOIN transactions t ON r.transaction_id = t.id
        WHERE r.id = :id LIMIT 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $recharge_id_to_edit);
    $stmt->execute();

    $recharge_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recharge_details) {
        $error_message = "Recharge record not found.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load recharge details. Please try again later.";
    error_log("Edit Recharge page - fetch query error: " . $e->getMessage());
}

// Process form submission if recharge details were found
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $recharge_details) {
    $recharge_type = $_POST['recharge_type'] ?? 'Cash';
    $user_id = $_POST['user_id'] ?? null;
    $card_id = $_POST['card_id'] ?? null;
    $transaction_id = $_POST['transaction_id'] ?? null; // This might be editable if needed, but often linked automatically
    $recharge_time_input = trim($_POST['recharge_time'] ?? '');
    $recharge_time = !empty($recharge_time_input) ? $recharge_time_input : date('Y-m-d H:i:s'); // Use current time if field is empty

    // Basic validation
    $errors = [];
    if (empty($user_id) || $user_id == 0) {
        $errors[] = "Please select a user.";
    }
    if (empty($card_id) || $card_id == 0) {
        $errors[] = "Please select a card.";
    }
    // Note: transaction_id validation depends on your logic - maybe it's optional or must exist
    // if (empty($transaction_id) || $transaction_id == 0) {
    //     $errors[] = "Please select a transaction.";
    // }
    if (!in_array($recharge_type, ['Cash', 'Card', 'UPI'])) {
        $errors[] = "Please select a valid recharge type.";
    }
    // Validate recharge time format if provided
    if (!empty($recharge_time_input) && DateTime::createFromFormat('Y-m-d H:i:s', $recharge_time_input) === FALSE) {
        $errors[] = "Invalid recharge time format. Please use YYYY-MM-DD HH:MM:SS.";
    }

    if (empty($errors)) {
        try {
            // Prepare SQL query to update the recharge record
            // Note: Updating user_id, card_id, or transaction_id might have implications for related data consistency.
            // Be careful when allowing these to be changed.
            $update_query = "UPDATE recharge SET r_type = :r_type, user_id = :user_id, card_id = :card_id, transaction_id = :transaction_id, r_time = :r_time WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':r_type', $recharge_type);
            $update_stmt->bindParam(':user_id', $user_id);
            $update_stmt->bindParam(':card_id', $card_id);
            $update_stmt->bindParam(':transaction_id', $transaction_id); // Can be NULL
            $update_stmt->bindParam(':r_time', $recharge_time);
            $update_stmt->bindParam(':id', $recharge_id_to_edit);

            if ($update_stmt->execute()) {
                $success_message = "Recharge record updated successfully!";
                // Optionally, refetch the recharge details to show updated info
                 $stmt->execute(); // Re-execute the fetch query
                 $recharge_details = $stmt->fetch(PDO::FETCH_ASSOC); // Update the local variable
            } else {
                $error_message = "Failed to update recharge record. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again later.";
            error_log("Edit Recharge error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch list of users and cards for the edit form (only if initial fetch was successful)
$users = [];
$cards = [];
if ($recharge_details) {
    try {
        $user_query = "SELECT id, first_name, last_name, username FROM user ORDER BY username";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->execute();
        $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

         $card_query = "SELECT id, user_id, balance_credits, total_credits FROM card ORDER BY user_id, id";
         $card_stmt = $db->prepare($card_query);
         $card_stmt->execute();
         $cards = $card_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Could not load users or cards for the form. Please try again later.";
        error_log("Edit Recharge page - Users/Cards query error: " . $e->getMessage());
    }
}


// Prepare the specific content for this page
$content = "
    <h2>Edit Recharge Record</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Recharges List</a>";
} elseif ($recharge_details) {
    $user_name_display = $recharge_details['user_first_name'] ? htmlspecialchars($recharge_details['user_first_name'] . ' ' . $recharge_details['user_last_name']) : htmlspecialchars($recharge_details['user_username']);
    $card_link = "<a href='../../views/cards/view.php?id=" . $recharge_details['card_id'] . "'>Card #" . $recharge_details['card_id'] . "</a>";
    $transaction_link = $recharge_details['transaction_id'] ? "<a href='../../views/transactions/view.php?id=" . $recharge_details['transaction_id'] . "'>#" . $recharge_details['transaction_id'] . "</a>" : 'N/A (Not Linked Yet)';

    $content .= "<p>Editing details for Recharge ID: <strong>" . htmlspecialchars($recharge_details['id']) . "</strong>, User: <strong>" . $user_name_display . "</strong>, Card: <strong>" . $card_link . "</strong></p>";

    if ($success_message) {
        $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }

    $type_selected_cash = $recharge_details['r_type'] === 'Cash' ? 'selected' : '';
    $type_selected_card = $recharge_details['r_type'] === 'Card' ? 'selected' : '';
    $type_selected_upi = $recharge_details['r_type'] === 'UPI' ? 'selected' : '';

    $content .= "
    <form method='post' action=''>
        <div class='mb-3'>
            <label for='recharge_id_display' class='form-label'>Recharge ID (Read-only):</label>
            <input type='text' class='form-control-plaintext' id='recharge_id_display' value='" . htmlspecialchars($recharge_details['id']) . "' readonly>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='recharge_type' class='form-label'>Recharge Type:</label>
                <select class='form-select' id='recharge_type' name='recharge_type' required>
                    <option value='Cash' " . $type_selected_cash . ">Cash</option>
                    <option value='Card' " . $type_selected_card . ">Card</option>
                    <option value='UPI' " . $type_selected_upi . ">UPI</option>
                </select>
            </div>
             <div class='col-md-6 mb-3'>
                <label for='recharge_time' class='form-label'>Recharge Time:</label>
                <input type='datetime-local' class='form-control' id='recharge_time' name='recharge_time' value='" . (new DateTime($recharge_details['r_time']))->format('Y-m-d\TH:i:s') . "' required>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='user_id' class='form-label'>Select User:</label>
                <select class='form-select' id='user_id' name='user_id' required>
                    <option value='0'>Choose User...</option>
        ";

        foreach ($users as $user) {
             $selected_attr = $user['id'] == $recharge_details['user_id'] ? 'selected' : '';
             $content .= "<option value='" . $user['id'] . "' " . $selected_attr . ">" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')') . "</option>";
        }

        $content .= "
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='card_id' class='form-label'>Select Card:</label>
                <select class='form-select' id='card_id' name='card_id' required>
                    <option value='0'>Choose Card...</option>
        ";

        foreach ($cards as $card) {
             $selected_attr = $card['id'] == $recharge_details['card_id'] ? 'selected' : '';
             $content .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (User ID: " . $card['user_id'] . ", Balance: ₹" . number_format($card['balance_credits'], 2) . ", Total: ₹" . number_format($card['total_credits'], 2) . ")</option>";
        }

        $content .= "
                </select>
            </div>
        </div>
        <div class='mb-3'>
            <label for='transaction_id_display' class='form-label'>Linked Transaction ID (Read-only):</label>
            <input type='text' class='form-control-plaintext' id='transaction_id_display' value='" . $transaction_link . "' readonly>
            <small class='form-text text-muted'>The transaction ID is typically linked automatically when the recharge is processed.</small>
        </div>
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Update Recharge Record</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Recharges List</a>
        <a href='view.php?id=" . $recharge_details['id'] . "' class='btn btn-info'>View Recharge Details</a>
    </div>
    ";
} else {
    // This case handles when the recharge ID was provided but the record wasn't found (error_message is set above)
    $content .= "<p>Unable to load recharge information.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Recharges List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>