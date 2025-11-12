<?php
// views/transactions/edit.php

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

// Get transaction ID from query string
$transaction_id_to_edit = $_GET['id'] ?? null;

if (!$transaction_id_to_edit) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$transaction_details = null;
$success_message = '';
$error_message = '';

// Fetch the specific transaction record's details along with related user, card, and recharge info
try {
    $query = "
        SELECT t.id, t.user_id, t.card_id, t.t_time, t.t_type, t.recharge_id,
               u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
               c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
               r.r_time as recharge_time, r.r_type as recharge_type
        FROM transactions t
        LEFT JOIN user u ON t.user_id = u.id
        LEFT JOIN card c ON t.card_id = c.id
        LEFT JOIN recharge r ON t.recharge_id = r.id
        WHERE t.id = :id LIMIT 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $transaction_id_to_edit);
    $stmt->execute();

    $transaction_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction_details) {
        $error_message = "Transaction record not found.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load transaction details. Please try again later.";
    error_log("Edit Transaction page - fetch query error: " . $e->getMessage());
}

// Process form submission if transaction details were found
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $transaction_details) {
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
            // Prepare SQL query to update the transaction record
            // Note: Updating user_id, card_id, or recharge_id might have implications for related data consistency.
            // Be careful when allowing these to be changed, especially for financial records.
            $update_query = "
                UPDATE transactions SET user_id = :user_id, t_time = :t_time, t_type = :t_type, card_id = :card_id, recharge_id = :recharge_id WHERE id = :id
            ";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':user_id', $user_id);
            $update_stmt->bindParam(':t_time', $transaction_time);
            $update_stmt->bindParam(':t_type', $transaction_type);
            $update_stmt->bindParam(':card_id', $card_id);
            $update_stmt->bindParam(':recharge_id', $recharge_id); // Can be NULL
            $update_stmt->bindParam(':id', $transaction_id_to_edit);

            if ($update_stmt->execute()) {
                $success_message = "Transaction record updated successfully!";
                // Optionally, refetch the transaction details to show updated info
                 $stmt->execute(); // Re-execute the fetch query
                 $transaction_details = $stmt->fetch(PDO::FETCH_ASSOC); // Update the local variable
            } else {
                $error_message = "Failed to update transaction record. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again later.";
            error_log("Edit Transaction error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch lists of users, cards (for the selected user), and recharges for the edit form (only if initial fetch was successful)
$users = [];
$cards = [];
$recharges = [];

if ($transaction_details) {
    try {
        $user_query = "SELECT id, first_name, last_name, username FROM user ORDER BY username";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->execute();
        $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch cards for the *currently selected user* in the form, or the user associated with the transaction initially
        $cards_query_user_id = $_POST['user_id'] ?? $transaction_details['user_id']; // Use POST value if available, else initial value
        $card_query = "SELECT id, c_status, balance_credits, total_credits FROM card WHERE user_id = :user_id ORDER BY id";
        $card_stmt = $db->prepare($card_query);
        $card_stmt->bindParam(':user_id', $cards_query_user_id);
        $card_stmt->execute();
        $cards = $card_stmt->fetchAll(PDO::FETCH_ASSOC);

        $recharge_query = "SELECT id, r_type, r_time, user_id FROM recharge ORDER BY r_time DESC LIMIT 50"; // Limit for performance
        $recharge_stmt = $db->prepare($recharge_query);
        $recharge_stmt->execute();
        $recharges = $recharge_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = "Could not load data for the form. Please try again later.";
        error_log("Edit Transaction page - Form data queries error: " . $e->getMessage());
    }
}


// Prepare the specific content for this page
$content = "
    <h2>Edit Transaction Record</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Transactions List</a>";
} elseif ($transaction_details) {
    $user_name_display = $transaction_details['user_first_name'] ? htmlspecialchars($transaction_details['user_first_name'] . ' ' . $transaction_details['user_last_name']) : htmlspecialchars($transaction_details['user_username']);
    $user_link = "<a href='../../views/users/view.php?id=" . $transaction_details['user_id'] . "'>" . $user_name_display . "</a>";
    $card_link = "<a href='../../views/cards/view.php?id=" . $transaction_details['card_id'] . "'>Card #" . $transaction_details['card_id'] . "</a>";
    $recharge_link = $transaction_details['recharge_id'] ? "<a href='../../views/recharge/view.php?id=" . $transaction_details['recharge_id'] . "'>#" . $transaction_details['recharge_id'] . "</a>" : 'N/A (Not Linked Yet)';

    $content .= "<p>Editing details for Transaction ID: <strong>" . htmlspecialchars($transaction_details['id']) . "</strong>, User: <strong>" . $user_link . "</strong>, Card: <strong>" . $card_link . "</strong></p>";

    if ($success_message) {
        $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }

    $type_selected_cash = $transaction_details['t_type'] === 'Cash' ? 'selected' : '';
    $type_selected_card = $transaction_details['t_type'] === 'Card' ? 'selected' : '';
    $type_selected_upi = $transaction_details['t_type'] === 'UPI' ? 'selected' : '';

    $content .= "
    <form method='post' action=''>
        <div class='mb-3'>
            <label for='transaction_id_display' class='form-label'>Transaction ID (Read-only):</label>
            <input type='text' class='form-control-plaintext' id='transaction_id_display' value='" . htmlspecialchars($transaction_details['id']) . "' readonly>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='user_id' class='form-label'>Select User:</label>
                <select class='form-select' id='user_id' name='user_id' onchange='fetchCards(this.value)' required>
                    <option value='0'>Choose User...</option>
        ";

        foreach ($users as $user) {
             $selected_attr = $user['id'] == $transaction_details['user_id'] ? 'selected' : '';
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
             $selected_attr = $card['id'] == $transaction_details['card_id'] ? 'selected' : '';
             $content .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (Balance: ₹" . number_format($card['balance_credits'], 2) . ", Total: ₹" . number_format($card['total_credits'], 2) . ", Status: " . $card['c_status'] . ")</option>";
        }

        $content .= "
                </select>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='transaction_type' class='form-label'>Transaction Type:</label>
                <select class='form-select' id='transaction_type' name='transaction_type' required>
                    <option value='Cash' " . $type_selected_cash . ">Cash</option>
                    <option value='Card' " . $type_selected_card . ">Card</option>
                    <option value='UPI' " . $type_selected_upi . ">UPI</option>
                </select>
            </div>
             <div class='col-md-6 mb-3'>
                <label for='transaction_time' class='form-label'>Transaction Time:</label>
                <input type='datetime-local' class='form-control' id='transaction_time' name='transaction_time' value='" . (new DateTime($transaction_details['t_time']))->format('Y-m-d\TH:i:s') . "' required>
            </div>
        </div>
        <div class='mb-3'>
            <label for='recharge_id' class='form-label'>Linked Recharge ID (Optional):</label>
            <select class='form-select' id='recharge_id' name='recharge_id'>
                <option value=''>Select Recharge (if applicable)...</option>
        ";

        foreach ($recharges as $recharge) {
             $selected_attr = $recharge['id'] == $transaction_details['recharge_id'] ? 'selected' : '';
             $recharge_user_name = htmlspecialchars($recharge['first_name'] ?? $recharge['username'] ?? 'N/A'); // Assuming first_name/username might be fetched with recharge, otherwise just show ID
             $content .= "<option value='" . $recharge['id'] . "' " . $selected_attr . ">#" . $recharge['id'] . " (" . $recharge['r_type'] . " on " . $recharge['r_time'] . " for User ID: " . $recharge['user_id'] . ")</option>";
        }

        $content .= "
            </select>
        </div>
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Update Transaction Record</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Transactions List</a>
        <a href='view.php?id=" . $transaction_details['id'] . "' class='btn btn-info'>View Transaction Details</a>
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
} else {
    // This case handles when the transaction ID was provided but the record wasn't found (error_message is set above)
    $content .= "<p>Unable to load transaction information.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Transactions List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>