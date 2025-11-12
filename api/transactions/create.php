<?php
// api/transactions/create.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session
session_start();

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Include the Transaction model
require_once '../../models/Transaction.php'; // Adjust path as needed
$transactionModel = new Transaction($db);

// Include the User model (to validate user_id)
require_once '../../models/User.php'; // Adjust path as needed
$userModel = new User($db);

// Include the Card model (to validate card_id)
require_once '../../models/Card.php'; // Adjust path as needed
$cardModel = new Card($db);

// Include the Recharge model (to validate recharge_id)
require_once '../../models/Recharge.php'; // Adjust path as needed
$rechargeModel = new Recharge($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in and is an admin (only admins should create transactions)
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
    $user_id = (int)($_POST['user_id'] ?? 0);
    $t_time_input = trim($_POST['t_time'] ?? '');
    $t_time = !empty($t_time_input) ? $t_time_input : date('Y-m-d H:i:s'); // Use provided time or current time
    $t_type = $_POST['t_type'] ?? 'Card'; // Default transaction type
    $card_id = (int)($_POST['card_id'] ?? 0);
    $recharge_id = (int)($_POST['recharge_id'] ?? 0); // Can be 0 or a valid recharge ID

    // Basic validation
    $errors = [];
    if (empty($user_id) || $user_id == 0) {
        $errors[] = "Please select a user.";
    }
    if (empty($card_id) || $card_id == 0) {
        $errors[] = "Please select a card.";
    }
    if (empty($t_type) || !in_array($t_type, ['Card', 'Cash', 'UPI'])) {
        $errors[] = "Please select a valid transaction type (Card/Cash/UPI).";
    }
    // Validate t_time format if provided
    if (!empty($t_time_input) && DateTime::createFromFormat('Y-m-d H:i:s', $t_time_input) === FALSE) {
         $errors[] = "Invalid transaction time format. Please use YYYY-MM-DD HH:MM:SS.";
    }
    // Validate recharge_id if provided (check if it exists)
    if (!empty($recharge_id)) {
         try {
             $recharge_check_query = "SELECT id FROM recharge WHERE id = :recharge_id LIMIT 1";
             $recharge_check_stmt = $db->prepare($recharge_check_query);
             $recharge_check_stmt->bindParam(':recharge_id', $recharge_id);
             $recharge_check_stmt->execute();
             if ($recharge_check_stmt->rowCount() === 0) {
                 $errors[] = "Selected recharge ID does not exist.";
             }
         } catch (PDOException $e) {
             $errors[] = "Database error validating recharge ID.";
             error_log("API Transactions create - Recharge ID validation error: " . $e->getMessage());
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
            error_log("API Transactions create - User ID validation error: " . $e->getMessage());
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
            error_log("API Transactions create - Card ID validation error: " . $e->getMessage());
        }
    }


    if (empty($errors)) {
        try {
            // Prepare data array for the model
            $new_transaction_data = [
                'user_id' => $user_id,
                't_time' => $t_time, // Use provided or current time
                't_type' => $t_type,
                'card_id' => $card_id,
                'recharge_id' => $recharge_id // Can be NULL or a provided ID
            ];

            // Attempt to create the transaction using the model
            $new_transaction_id = $transactionModel->create($new_transaction_data);

            if ($new_transaction_id) {
                $response['success'] = true;
                $response['message'] = 'Transaction created successfully!';
                $response['data'] = ['transaction_id' => $new_transaction_id];
            } else {
                $response['message'] = 'Failed to create transaction. Please try again.';
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during transaction creation.';
            error_log("API Transactions create error: " . $e->getMessage());
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