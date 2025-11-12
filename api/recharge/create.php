<?php
// api/recharge/create.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session
session_start();

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Include the Recharge model
require_once '../../models/Recharge.php'; // Adjust path as needed
$rechargeModel = new Recharge($db);

// Include the User model (to validate user_id)
require_once '../../models/User.php'; // Adjust path as needed
$userModel = new User($db);

// Include the Card model (to validate card_id and update balance)
require_once '../../models/Card.php'; // Adjust path as needed
$cardModel = new Card($db);

// Include the Transaction model (to create linked transaction)
require_once '../../models/Transaction.php'; // Adjust path as needed
$transactionModel = new Transaction($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in and is an admin (only admins should process recharges)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin privileges required.';
    echo json_encode($response);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve input data
    $r_type = $_POST['r_type'] ?? 'Cash'; // Default recharge type
    $user_id = (int)($_POST['user_id'] ?? 0);
    $card_id = (int)($_POST['card_id'] ?? 0);
    $recharge_amount = (float)($_POST['recharge_amount'] ?? 0); // Amount to add
    $r_time_input = trim($_POST['r_time'] ?? '');
    $r_time = !empty($r_time_input) ? $r_time_input : date('Y-m-d H:i:s'); // Use provided time or current time

    // Basic validation
    $errors = [];
    if (empty($user_id) || $user_id == 0) {
        $errors[] = "Please select a user.";
    }
    if (empty($card_id) || $card_id == 0) {
        $errors[] = "Please select a card.";
    }
    if ($recharge_amount <= 0) {
        $errors[] = "Recharge amount must be greater than zero.";
    }
    if (!in_array($r_type, ['Cash', 'Card', 'UPI'])) {
        $errors[] = "Please select a valid recharge type.";
    }
    // Validate r_time format if provided
    if (!empty($r_time_input) && DateTime::createFromFormat('Y-m-d H:i:s', $r_time_input) === FALSE) {
         $errors[] = "Invalid recharge time format. Please use YYYY-MM-DD HH:MM:SS.";
    }

    // Validate user_id exists
    if (empty($errors) && $user_id > 0) {
        try {
            $user_check_query = "SELECT id FROM user WHERE id = :user_id LIMIT 1";
            $user_check_stmt = $db->prepare($user_check_query);
            $user_check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $user_check_stmt->execute();
            if ($user_check_stmt->rowCount() === 0) {
                $errors[] = "Selected user ID does not exist.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error validating user ID.";
            error_log("API Recharge create - User ID validation error: " . $e->getMessage());
        }
    }

    // Validate card_id exists and belongs to the user
    if (empty($errors) && $card_id > 0) {
        try {
            $card_check_query = "SELECT id, user_id FROM card WHERE id = :card_id LIMIT 1";
            $card_check_stmt = $db->prepare($card_check_query);
            $card_check_stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
            $card_check_stmt->execute();
            $card_result = $card_check_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$card_result) {
                $errors[] = "Selected card ID does not exist.";
            } elseif ($card_result['user_id'] != $user_id) {
                 $errors[] = "Selected card does not belong to the selected user.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error validating card ID.";
            error_log("API Recharge create - Card ID validation error: " . $e->getMessage());
        }
    }


    if (empty($errors)) {
        try {
            // Start a database transaction to ensure atomicity
            $db->beginTransaction();

            // 1. Fetch current card details
            $card_details = $cardModel->findById($card_id);
            if (!$card_details) {
                 throw new Exception("Card details could not be retrieved.");
            }

            // 2. Calculate new balances
            $new_balance_credits = $card_details['balance_credits'] + $recharge_amount;
            $new_total_credits = $card_details['total_credits'] + $recharge_amount;

            // 3. Update the card's balance_credits and total_credits
            $card_update_data = [
                'id' => $card_id,
                'c_status' => $card_details['c_status'], // Keep existing status
                'user_id' => $card_details['user_id'], // Keep existing user_id
                'recharge_details' => $card_details['recharge_details'], // Keep existing recharge_details
                'balance_credits' => $new_balance_credits,
                'total_credits' => $new_total_credits,
                'expired_at' => $card_details['expired_at'], // Keep existing expired_at
                'created_at' => $card_details['created_at'] // Keep existing created_at
            ];
            if (!$cardModel->update($card_update_data)) {
                 throw new Exception("Failed to update card balance.");
            }

            // 4. Create the recharge record
            $new_recharge_data = [
                'r_type' => $r_type,
                'user_id' => $user_id,
                'card_id' => $card_id,
                'r_time' => $r_time, // Use provided or current time
                'transaction_id' => null // Will be set after creating transaction
            ];
            $new_recharge_id = $rechargeModel->create($new_recharge_data);
            if (!$new_recharge_id) {
                 throw new Exception("Failed to create recharge record.");
            }

            // 5. Create the transaction record linked to the new recharge
            $new_transaction_data = [
                'user_id' => $user_id,
                't_time' => $r_time, // Use recharge time
                't_type' => $r_type, // Match recharge type
                'card_id' => $card_id,
                'recharge_id' => $new_recharge_id // Link to the newly created recharge
            ];
            $new_transaction_id = $transactionModel->create($new_transaction_data);
            if (!$new_transaction_id) {
                 throw new Exception("Failed to create transaction record.");
            }

            // 6. Update the recharge record with the transaction ID
            $recharge_update_data = [
                'id' => $new_recharge_id,
                'r_type' => $r_type, // Keep existing type
                'user_id' => $user_id, // Keep existing user_id
                'card_id' => $card_id, // Keep existing card_id
                'r_time' => $r_time, // Keep existing time
                'transaction_id' => $new_transaction_id // Set the transaction ID
            ];
            if (!$rechargeModel->update($recharge_update_data)) {
                 throw new Exception("Failed to link recharge to transaction.");
            }

            // Commit the transaction if all steps succeeded
            $db->commit();

            $response['success'] = true;
            $response['message'] = 'Recharge processed successfully!';
            $response['data'] = [
                'recharge_id' => $new_recharge_id,
                'transaction_id' => $new_transaction_id,
                'card_id' => $card_id,
                'user_id' => $user_id,
                'new_balance_credits' => $new_balance_credits,
                'new_total_credits' => $new_total_credits
            ];
        } catch (Exception $e) {
            // Rollback the transaction on any error
            $db->rollback();
            $response['message'] = 'Recharge failed: ' . $e->getMessage();
            error_log("API Recharge create error: " . $e->getMessage());
        }
    } else {
        $response['message'] = implode("<br>", $errors); // Join multiple errors with line breaks
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Output the JSON response
echo json_encode($response);
exit;
?>