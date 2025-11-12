<?php
// views/recharge/create.php

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

// Fetch list of users to link the recharge to
$users = [];
try {
    $user_query = "SELECT id, first_name, last_name, username FROM user ORDER BY username";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute();
    $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Could not load users. Please try again later.";
    error_log("Create Recharge page - Users query error: " . $e->getMessage());
}

// Fetch list of cards to recharge (optionally filter by user if user ID is pre-selected via GET parameter)
$cards = [];
$preselected_card_id = $_GET['card_id'] ?? null; // Allow pre-selection via URL, e.g., from 'Recharge Card' button on card view
if ($preselected_card_id) {
    try {
        $card_query = "SELECT id, user_id, balance_credits, total_credits FROM card WHERE id = :card_id LIMIT 1";
        $card_stmt = $db->prepare($card_query);
        $card_stmt->bindParam(':card_id', $preselected_card_id);
        $card_stmt->execute();
        $preselected_card = $card_stmt->fetch(PDO::FETCH_ASSOC);
        if ($preselected_card) {
             $cards = [$preselected_card]; // Set the specific card as the only option
        } else {
             $error_message = "Pre-selected card ID not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Could not load pre-selected card. Please try again later.";
        error_log("Create Recharge page - Pre-selected card query error: " . $e->getMessage());
    }
} else {
    // Fetch all cards if no specific one is pre-selected
    try {
        $card_query = "SELECT id, user_id, balance_credits, total_credits FROM card ORDER BY user_id, id";
        $card_stmt = $db->prepare($card_query);
        $card_stmt->execute();
        $cards = $card_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Could not load cards. Please try again later.";
        error_log("Create Recharge page - Cards query error: " . $e->getMessage());
    }
}

// Fetch list of transactions to link the recharge to (optional, might be created simultaneously)
$transactions = [];
try {
    // Example: Fetch recent transactions that might be linked to this recharge
    // In a simple flow, the transaction might be created *as part of* this recharge process,
    // linking the recharge ID back to the transaction. This requires careful handling of foreign keys.
    // For now, let's assume the transaction is created *first* or *simultaneously* within a transaction block.
    // We'll proceed assuming the transaction ID is provided or created internally.
    // Let's fetch a few recent transactions just as an example if needed later.
    // $transaction_query = "SELECT id FROM transactions ORDER BY t_time DESC LIMIT 10";
    // $transaction_stmt = $db->prepare($transaction_query);
    // $transaction_stmt->execute();
    // $transactions = $transaction_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log if needed, but this might not be critical if transactions are created internally
    error_log("Create Recharge page - Transactions query error (optional): " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $card_id = $_POST['card_id'] ?? null;
    $transaction_id = $_POST['transaction_id'] ?? null; // This might be created internally
    $recharge_type = $_POST['recharge_type'] ?? 'Cash';
    $recharge_amount = (float)($_POST['recharge_amount'] ?? 0); // Amount to add
    $recharge_time = date('Y-m-d H:i:s'); // Use current time

    // Basic validation
    $errors = [];
    if (empty($user_id) || $user_id == 0) {
        $errors[] = "Please select a user.";
    }
    if (empty($card_id) || $card_id == 0) {
        $errors[] = "Please select a card.";
    }
    // Note: transaction_id might be optional or created internally, so we won't validate it strictly here unless required.
    // if (empty($transaction_id) || $transaction_id == 0) {
    //     $errors[] = "Please select a transaction.";
    // }
    if ($recharge_amount <= 0) {
        $errors[] = "Recharge amount must be greater than zero.";
    }
    if (!in_array($recharge_type, ['Cash', 'Card', 'UPI'])) {
        $errors[] = "Please select a valid recharge type.";
    }

    if (empty($errors)) {
        try {
            // Start a database transaction to ensure consistency across related tables
            $db->beginTransaction();

            // 1. Fetch current card balance/total (optional, for logging or validation)
            $card_fetch_query = "SELECT balance_credits, total_credits FROM card WHERE id = :card_id LIMIT 1";
            $card_fetch_stmt = $db->prepare($card_fetch_query);
            $card_fetch_stmt->bindParam(':card_id', $card_id);
            $card_fetch_stmt->execute();
            $current_card_info = $card_fetch_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current_card_info) {
                 throw new Exception("Card not found during recharge process.");
            }

            // 2. Update the card's balance and total credits
            $new_balance = $current_card_info['balance_credits'] + $recharge_amount;
            $new_total = $current_card_info['total_credits'] + $recharge_amount;

            $card_update_query = "UPDATE card SET balance_credits = :new_balance, total_credits = :new_total WHERE id = :card_id";
            $card_update_stmt = $db->prepare($card_update_query);
            $card_update_stmt->bindParam(':new_balance', $new_balance, PDO::PARAM_INT); // Assuming balance/total are INT
            $card_update_stmt->bindParam(':new_total', $new_total, PDO::PARAM_INT);
            $card_update_stmt->bindParam(':card_id', $card_id);

            if (!$card_update_stmt->execute()) {
                 throw new Exception("Failed to update card balance.");
            }

            // 3. Create the recharge record (linking to user, card, and potentially transaction)
            // Note: transaction_id might be NULL if created later or not applicable in this direct flow
            $recharge_insert_query = "INSERT INTO recharge (r_type, user_id, transaction_id, r_time, card_id) VALUES (:r_type, :user_id, :transaction_id, :r_time, :card_id)";
            $recharge_insert_stmt = $db->prepare($recharge_insert_query);
            $recharge_insert_stmt->bindParam(':r_type', $recharge_type);
            $recharge_insert_stmt->bindParam(':user_id', $user_id);
            $recharge_insert_stmt->bindParam(':transaction_id', $transaction_id); // Can be NULL if not provided
            $recharge_insert_stmt->bindParam(':r_time', $recharge_time);
            $recharge_insert_stmt->bindParam(':card_id', $card_id);

            if (!$recharge_insert_stmt->execute()) {
                 throw new Exception("Failed to create recharge record.");
            }
            $new_recharge_id = $db->lastInsertId();

            // 4. Create the corresponding transaction record (linking back to the recharge)
            // Assuming t_type reflects the payment method used for the recharge
            $transaction_type_map = [
                'Cash' => 'Cash',
                'Card' => 'Card', // This refers to the payment *method* for the recharge, not the target card
                'UPI' => 'UPI'
            ];
            $transaction_type_for_payment = $transaction_type_map[$recharge_type] ?? 'Cash'; // Default fallback

            $transaction_insert_query = "INSERT INTO transactions (user_id, t_time, t_type, card_id, recharge_id) VALUES (:user_id, :t_time, :t_type, :card_id, :recharge_id)";
            $transaction_insert_stmt = $db->prepare($transaction_insert_query);
            $transaction_insert_stmt->bindParam(':user_id', $user_id);
            $transaction_insert_stmt->bindParam(':t_time', $recharge_time);
            $transaction_insert_stmt->bindParam(':t_type', $transaction_type_for_payment);
            $transaction_insert_stmt->bindParam(':card_id', $card_id);
            $transaction_insert_stmt->bindParam(':recharge_id', $new_recharge_id); // Link the transaction to the recharge

            if (!$transaction_insert_stmt->execute()) {
                 throw new Exception("Failed to create transaction record.");
            }
            $new_transaction_id = $db->lastInsertId();

            // 5. Update the recharge record to include the newly created transaction ID
            $recharge_update_transaction_query = "UPDATE recharge SET transaction_id = :transaction_id WHERE id = :recharge_id";
            $recharge_update_transaction_stmt = $db->prepare($recharge_update_transaction_query);
            $recharge_update_transaction_stmt->bindParam(':transaction_id', $new_transaction_id);
            $recharge_update_transaction_stmt->bindParam(':recharge_id', $new_recharge_id);

            if (!$recharge_update_transaction_stmt->execute()) {
                 throw new Exception("Failed to update recharge record with transaction ID.");
            }


            // Commit the transaction if all steps succeeded
            $db->commit();
            $success_message = "Recharge processed successfully! Recharge ID: $new_recharge_id, Transaction ID: $new_transaction_id. Card balance updated.";
            // Optionally, redirect to the recharge list page or the card's view page after successful creation
            // header("Location: index.php");
            // exit;

        } catch (Exception $e) {
            // Rollback the transaction on any error
            $db->rollback();
            $error_message = "Recharge failed: " . $e->getMessage();
            error_log("Create Recharge error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors); // Join multiple errors with line breaks
    }
}

// Prepare the specific content for this page
$content = "
    <h2>Record New Recharge</h2>
    <p>Process a recharge for a user's card.</p>
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
                <select class='form-select' id='user_id' name='user_id' required onchange='updateCardList(this.value)'>
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

        // Populate cards based on the selected user (from POST or fetched via JS) or pre-selected card
        $selected_user_id = $_POST['user_id'] ?? null;
        foreach ($cards as $card) {
             // If a specific card was pre-selected via GET, only show that one
             if ($preselected_card_id) {
                 if ($card['id'] == $preselected_card_id) {
                     $selected_attr = 'selected'; // Pre-selected card is always selected
                     $content .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (Balance: ₹" . number_format($card['balance_credits'], 2) . ", Total: ₹" . number_format($card['total_credits'], 2) . ")</option>";
                 }
             } else {
                 // If no pre-selection, filter cards by the selected user (or show all if none selected yet)
                 if (!$selected_user_id || $card['user_id'] == $selected_user_id) {
                     $selected_attr = ($_POST['card_id'] ?? null) == $card['id'] ? 'selected' : '';
                     $content .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (User ID: " . $card['user_id'] . ", Balance: ₹" . number_format($card['balance_credits'], 2) . ", Total: ₹" . number_format($card['total_credits'], 2) . ")</option>";
                 }
             }
        }

        $content .= "
                </select>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='recharge_type' class='form-label'>Recharge Type:</label>
                <select class='form-select' id='recharge_type' name='recharge_type' required>
                    <option value='Cash' " . (($_POST['recharge_type'] ?? 'Cash') === 'Cash' ? 'selected' : '') . ">Cash</option>
                    <option value='Card' " . (($_POST['recharge_type'] ?? 'Cash') === 'Card' ? 'selected' : '') . ">Card</option>
                    <option value='UPI' " . (($_POST['recharge_type'] ?? 'Cash') === 'UPI' ? 'selected' : '') . ">UPI</option>
                </select>
            </div>
             <div class='col-md-6 mb-3'>
                <label for='recharge_amount' class='form-label'>Recharge Amount (₹):</label>
                <input type='number' class='form-control' id='recharge_amount' name='recharge_amount' value='" . htmlspecialchars($_POST['recharge_amount'] ?? '') . "' min='0.01' step='0.01' required>
                <small class='form-text text-muted'>Enter the amount to add to the card.</small>
            </div>
        </div>
        <!-- Optional: Link to an existing transaction ID if applicable -->
        <!-- <div class='mb-3'>
            <label for='transaction_id' class='form-label'>Linked Transaction ID (Optional):</label>
            <select class='form-select' id='transaction_id' name='transaction_id'>
                <option value=''>Select Transaction...</option>
                ";
                // foreach ($transactions as $transaction) {
                //      $selected_attr = ($_POST['transaction_id'] ?? null) == $transaction['id'] ? 'selected' : '';
                //      $content .= "<option value='" . $transaction['id'] . "' " . $selected_attr . ">#" . $transaction['id'] . "</option>";
                // }
                $content .= "
            </select>
        </div> -->
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Process Recharge</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Recharges List</a>
        <!-- If a card was pre-selected, offer a link back to its view page -->
        " . ($preselected_card_id ? "<a href='../../views/cards/view.php?id=" . $preselected_card_id . "' class='btn btn-info'>View Card</a>" : "") . "
    </div>

    <script>
        // Function to update card list via AJAX when user is selected (optional, improves UX)
        function updateCardList(userId) {
            if (userId && userId != 0) {
                // In a real app, you would make an AJAX call to a PHP script (e.g., fetch_cards_for_user.php)
                // that returns the cards associated with the selected user.
                // For now, this relies on the page reload/POST mechanism above, or the initial population if user_id is known.
                // console.log('Fetching cards for user ID: ' + userId);
                // Example AJAX call (requires fetch_cards_for_user.php endpoint):
                /*
                fetch('../../api/recharge/fetch_cards_for_user.php', {
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
                        option.textContent = `Card ID: \${card.id} (Balance: ₹\${card.balance_credits}, Total: ₹\${card.total_credits})`;
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