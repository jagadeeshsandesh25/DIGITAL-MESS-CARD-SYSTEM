<?php
// views/transactions/create.php

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
    error_log("Create Transaction page - Users query error: " . $e->getMessage());
}

// Fetch list of cards (optionally filter by user if user ID is selected)
$cards = [];
if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
    $selected_user_id = $_POST['user_id'];
    try {
        $card_query = "SELECT id, c_status, balance_credits, total_credits FROM card WHERE user_id = :user_id ORDER BY id";
        $card_stmt = $db->prepare($card_query);
        $card_stmt->bindParam(':user_id', $selected_user_id);
        $card_stmt->execute();
        $cards = $card_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message .= " Could not load cards for the selected user. ";
        error_log("Create Transaction page - Cards query error: " . $e->getMessage());
    }
}

// Fetch list of recharges (to link the transaction to a specific recharge event)
$recharges = [];
try {
    $recharge_query = "SELECT id, r_type, r_time, user_id FROM recharge ORDER BY r_time DESC LIMIT 50"; // Limit for performance
    $recharge_stmt = $db->prepare($recharge_query);
    $recharge_stmt->execute();
    $recharges = $recharge_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= " Could not load recharges. ";
    error_log("Create Transaction page - Recharges query error: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $card_id = $_POST['card_id'] ?? null;
    $transaction_type = $_POST['transaction_type'] ?? 'Cash';
    $recharge_id = $_POST['recharge_id'] ?? null; // Optional: link to a specific recharge
    $transaction_time_input = trim($_POST['transaction_time'] ?? '');
    $transaction_time = !empty($transaction_time_input) ? $transaction_time_input : date('Y-m-d H:i:s'); // Use current time if field is empty

    // Basic validation
    $errors = [];
    if (empty($user_id) || $user_id == 0) {
        $errors[] = "Please select a user.";
    }
    if (empty($card_id) || $card_id == 0) {
        $errors[] = "Please select a card.";
    }
    if (!in_array($transaction_type, ['Cash', 'Card', 'UPI'])) {
        $errors[] = "Please select a valid transaction type.";
    }
    // Validate transaction time format if provided
    if (!empty($transaction_time_input) && DateTime::createFromFormat('Y-m-d H:i:s', $transaction_time_input) === FALSE) {
        $errors[] = "Invalid transaction time format. Please use YYYY-MM-DD HH:MM:SS.";
    }
    // Validate recharge_id if provided (check if it exists)
    if (!empty($recharge_id)) {
         $recharge_check_query = "SELECT id FROM recharge WHERE id = :recharge_id LIMIT 1";
         $recharge_check_stmt = $db->prepare($recharge_check_query);
         $recharge_check_stmt->bindParam(':recharge_id', $recharge_id);
         $recharge_check_stmt->execute();
         if ($recharge_check_stmt->rowCount() === 0) {
             $errors[] = "Selected recharge ID does not exist.";
         }
    }


    if (empty($errors)) {
        try {
            // Prepare SQL query to insert new transaction
            $insert_query = "
                INSERT INTO transactions (user_id, t_time, t_type, card_id, recharge_id)
                VALUES (:user_id, :t_time, :t_type, :card_id, :recharge_id)
            ";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':user_id', $user_id);
            $insert_stmt->bindParam(':t_time', $transaction_time);
            $insert_stmt->bindParam(':t_type', $transaction_type);
            $insert_stmt->bindParam(':card_id', $card_id);
            $insert_stmt->bindParam(':recharge_id', $recharge_id); // Can be NULL

            if ($insert_stmt->execute()) {
                $new_transaction_id = $db->lastInsertId();
                $success_message = "Transaction created successfully! Transaction ID: $new_transaction_id";
                // Optionally, redirect to the transactions list page after successful creation
                // header("Location: index.php");
                // exit;
            } else {
                $error_message = "Failed to create transaction. Please try again.";
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            $error_message = "Database error. Please try again later.";
            error_log("Create Transaction error: " . $e->getMessage()); // Log the actual error
        }
    } else {
        $error_message = implode("<br>", $errors); // Join multiple errors with line breaks
    }
}

// Prepare the specific content for this page
$content = "
    <h2>Record New Transaction</h2>
    <p>Log a new financial transaction.</p>
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
                <label for='user_id' class='form-label'>Select User:</label>
                <select class='form-select' id='user_id' name='user_id' onchange='fetchCards(this.value)' required>
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
                <label for='card_id' class='form-label'>Select Card:</label>
                <select class='form-select' id='card_id' name='card_id' required>
                    <option value='0'>Choose Card...</option>
        ";

        // Populate cards based on the selected user (from POST or fetched via JS)
        $selected_user_id = $_POST['user_id'] ?? null;
        if ($selected_user_id && !empty($cards)) {
             foreach ($cards as $card) {
                 $selected_attr = ($_POST['card_id'] ?? null) == $card['id'] ? 'selected' : '';
                 $content .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (Balance: ₹" . number_format($card['balance_credits'], 2) . ", Total: ₹" . number_format($card['total_credits'], 2) . ", Status: " . $card['c_status'] . ")</option>";
             }
        }

        $content .= "
                </select>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='transaction_type' class='form-label'>Transaction Type:</label>
                <select class='form-select' id='transaction_type' name='transaction_type' required>
                    <option value='Cash' " . (($_POST['transaction_type'] ?? 'Cash') === 'Cash' ? 'selected' : '') . ">Cash</option>
                    <option value='Card' " . (($_POST['transaction_type'] ?? 'Cash') === 'Card' ? 'selected' : '') . ">Card</option>
                    <option value='UPI' " . (($_POST['transaction_type'] ?? 'Cash') === 'UPI' ? 'selected' : '') . ">UPI</option>
                </select>
            </div>
             <div class='col-md-6 mb-3'>
                <label for='transaction_time' class='form-label'>Transaction Time:</label>
                <input type='datetime-local' class='form-control' id='transaction_time' name='transaction_time' value='" . (new DateTime())->format('Y-m-d\TH:i:s') . "' required>
            </div>
        </div>
        <div class='mb-3'>
            <label for='recharge_id' class='form-label'>Linked Recharge ID (Optional):</label>
            <select class='form-select' id='recharge_id' name='recharge_id'>
                <option value=''>Select Recharge (if applicable)...</option>
        ";

        foreach ($recharges as $recharge) {
             $selected_attr = ($_POST['recharge_id'] ?? null) == $recharge['id'] ? 'selected' : '';
             $recharge_user_name = htmlspecialchars($recharge['first_name'] ?? $recharge['username'] ?? 'N/A'); // Assuming first_name/username might be fetched with recharge, otherwise just show ID
             $content .= "<option value='" . $recharge['id'] . "' " . $selected_attr . ">#" . $recharge['id'] . " (" . $recharge['r_type'] . " on " . $recharge['r_time'] . " for User ID: " . $recharge['user_id'] . ")</option>";
        }

        $content .= "
            </select>
        </div>
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Record Transaction</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Transactions List</a>
    </div>

    <script>
        // Function to fetch cards via AJAX when user is selected (optional, improves UX)
        function fetchCards(userId) {
            if (userId && userId != 0) {
                // In a real app, you would make an AJAX call to a PHP script (e.g., fetch_cards_for_user.php)
                // that returns the cards associated with the selected user.
                // For now, this relies on the page reload/POST mechanism above.
                // console.log('Fetching cards for user ID: ' + userId);
                // Example AJAX call (requires fetch_cards_for_user.php endpoint):
                /*
                fetch('../../api/transactions/fetch_cards_for_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: userId }),
                })
                .then(response => response.json())
                .then(data => {
                    const cardSelect = document.getElementById('card_id');
                    cardSelect.innerHTML = '<option value=\"0\">Choose Card...</option>'; // Clear existing options
                    data.forEach(card => {
                        const option = document.createElement('option');
                        option.value = card.id;
                        option.textContent = `Card ID: \${card.id} (Balance: ₹\${card.balance_credits}, Total: ₹\${card.total_credits}, Status: \${card.c_status})`;
                        cardSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error fetching cards:', error));
                */
            }
        }
    </script>
";

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>