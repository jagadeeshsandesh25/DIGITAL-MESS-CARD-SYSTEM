<?php
// api/recharge/update.php

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

// Include the User model (to validate user_id if changed)
require_once '../../models/User.php'; // Adjust path as needed
$userModel = new User($db);

// Include the Card model (to validate card_id if changed)
require_once '../../models/Card.php'; // Adjust path as needed
$cardModel = new Card($db);

// Include the Transaction model (to validate transaction_id if changed)
require_once '../../models/Transaction.php'; // Adjust path as needed
$transactionModel = new Transaction($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in and is an admin (only admins should update recharges)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin privileges required.';
    echo json_encode($response);
    exit;
}

// Check if the request method is POST (or PUT/PATCH, but using POST for simplicity)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve input data
    $recharge_id = (int)($_POST['id'] ?? 0);
    $r_type = $_POST['r_type'] ?? 'Cash'; // Default to Cash
    $user_id = (int)($_POST['user_id'] ?? 0);
    $card_id = (int)($_POST['card_id'] ?? 0);
    $transaction_id = (int)($_POST['transaction_id'] ?? 0); // Optional, might be updated automatically
    $r_time_input = trim($_POST['r_time'] ?? '');
    $r_time = !empty($r_time_input) ? $r_time_input : date('Y-m-d H:i:s'); // Use provided time or current time

    // Basic validation
    $errors = [];
    if ($recharge_id <= 0) {
        $errors[] = "Invalid recharge ID.";
    }
    if (empty($user_id) || $user_id == 0) {
        $errors[] = "Please select a user.";
    }
    if (empty($card_id) || $card_id == 0) {
        $errors[] = "Please select a card.";
    }
    if (!in_array($r_type, ['Cash', 'Card', 'UPI'])) {
        $errors[] = "Please select a valid recharge type.";
    }
    // Validate r_time format if provided
    if (!empty($r_time_input) && DateTime::createFromFormat('Y-m-d H:i:s', $r_time_input) === FALSE) {
         $errors[] = "Invalid recharge time format. Please use YYYY-MM-DD HH:MM:SS.";
    }

    // Validate recharge ID exists
    if (empty($errors) && $recharge_id > 0) {
        try {
            $recharge_check_query = "SELECT id FROM recharge WHERE id = :recharge_id LIMIT 1";
            $recharge_check_stmt = $db->prepare($recharge_check_query);
            $recharge_check_stmt->bindParam(':recharge_id', $recharge_id, PDO::PARAM_INT);
            $recharge_check_stmt->execute();
            if ($recharge_check_stmt->rowCount() === 0) {
                $errors[] = "Recharge ID does not exist.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error validating recharge ID.";
            error_log("API Recharge update - Recharge ID validation error: " . $e->getMessage());
        }
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
            error_log("API Recharge update - User ID validation error: " . $e->getMessage());
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
            error_log("API Recharge update - Card ID validation error: " . $e->getMessage());
        }
    }

    // Validate transaction_id if provided (check if it exists)
    if (empty($errors) && !empty($transaction_id) && $transaction_id != 0) {
         try {
             $transaction_check_query = "SELECT id FROM transactions WHERE id = :transaction_id LIMIT 1";
             $transaction_check_stmt = $db->prepare($transaction_check_query);
             $transaction_check_stmt->bindParam(':transaction_id', $transaction_id, PDO::PARAM_INT);
             $transaction_check_stmt->execute();
             if ($transaction_check_stmt->rowCount() === 0) {
                 $errors[] = "Selected transaction ID does not exist.";
             }
         } catch (PDOException $e) {
             $errors[] = "Database error validating transaction ID.";
             error_log("API Recharge update - Transaction ID validation error: " . $e->getMessage());
         }
    }


    if (empty($errors)) {
        try {
            // Prepare data array for the model, including the ID
            $update_recharge_data = [
                'id' => $recharge_id,
                'r_type' => $r_type,
                'user_id' => $user_id,
                'card_id' => $card_id,
                'transaction_id' => $transaction_id, // Can be NULL or a provided ID
                'r_time' => $r_time // Use provided or existing time
            ];

            // Attempt to update the recharge record using the model
            if ($rechargeModel->update($update_recharge_data)) {
                $response['success'] = true;
                $response['message'] = 'Recharge record updated successfully!';
                $response['data'] = ['recharge_id' => $recharge_id];
            } else {
                $response['message'] = 'Failed to update recharge record. It might not exist or no changes were made.';
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during recharge update.';
            error_log("API Recharge update error: " . $e->getMessage());
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