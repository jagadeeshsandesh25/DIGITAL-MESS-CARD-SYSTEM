<?php
// api/cards/update.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session
session_start();

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Include the Card model
require_once '../../models/Card.php'; // Adjust path as needed
$cardModel = new Card($db);

// Include the User model (to validate user_id if changed)
require_once '../../models/User.php'; // Adjust path as needed
$userModel = new User($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in and is an admin (only admins should update cards)
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
    $card_id = (int)($input['id'] ?? 0);
    $c_status = $input['c_status'] ?? 'Inactive'; // Default to Inactive
    $user_id = (int)($input['user_id'] ?? 0);
    $recharge_details = (int)($input['recharge_details'] ?? 0); // Can be 0 or a valid recharge ID
    $balance_credits = (int)($input['balance_credits'] ?? 0);
    $total_credits = (int)($input['total_credits'] ?? 0);
    $expired_at_input = trim($input['expired_at'] ?? '');
    $expired_at = !empty($expired_at_input) ? $expired_at_input : null; // Can be NULL

    // Basic validation
    $errors = [];
    if ($card_id <= 0) {
        $errors[] = "Invalid card ID.";
    }
    if (!in_array($c_status, ['Active', 'Inactive'])) {
        $errors[] = "Please select a valid card status (Active/Inactive).";
    }
    if (empty($user_id) || $user_id == 0) {
        $errors[] = "Please select a user.";
    }
    // Note: recharge_details might be optional or require validation against existing recharge records.
    // For now, we'll allow it to be 0 or a provided ID.
    // if (!empty($recharge_details) && $recharge_details != 0) {
    //      // Validate recharge ID exists
    // }
    if ($balance_credits < 0) {
        $errors[] = "Balance credits cannot be negative.";
    }
    if ($total_credits < 0) {
        $errors[] = "Total credits cannot be negative.";
    }
    if ($balance_credits > $total_credits) {
        $errors[] = "Balance credits cannot exceed total credits.";
    }
    // Validate expiry date format if provided
    if (!empty($expired_at_input) && DateTime::createFromFormat('Y-m-d', $expired_at_input) === FALSE) {
         $errors[] = "Invalid expiry date format. Please use YYYY-MM-DD.";
    }

    // Validate card ID exists
    if (empty($errors) && $card_id > 0) {
        try {
            $card_check_query = "SELECT id FROM card WHERE id = :card_id LIMIT 1";
            $card_check_stmt = $db->prepare($card_check_query);
            $card_check_stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
            $card_check_stmt->execute();
            if ($card_check_stmt->rowCount() === 0) {
                $errors[] = "Card ID does not exist.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error validating card ID.";
            error_log("API Cards update - Card ID validation error: " . $e->getMessage());
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
            error_log("API Cards update - User ID validation error: " . $e->getMessage());
        }
    }

    // Validate recharge_details ID if provided
    if (empty($errors) && !empty($recharge_details) && $recharge_details != 0) {
        try {
            $recharge_check_query = "SELECT id FROM recharge WHERE id = :recharge_id LIMIT 1";
            $recharge_check_stmt = $db->prepare($recharge_check_query);
            $recharge_check_stmt->bindParam(':recharge_id', $recharge_details, PDO::PARAM_INT);
            $recharge_check_stmt->execute();
            if ($recharge_check_stmt->rowCount() === 0) {
                $errors[] = "Selected recharge ID does not exist.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error validating recharge ID.";
            error_log("API Cards update - Recharge ID validation error: " . $e->getMessage());
        }
    }


    if (empty($errors)) {
        try {
            // Prepare data array for the model, including the ID
            $update_card_data = [
                'id' => $card_id,
                'c_status' => $c_status,
                'user_id' => $user_id,
                'recharge_details' => $recharge_details, // Can be 0 or a valid ID
                'balance_credits' => $balance_credits,
                'total_credits' => $total_credits,
                'expired_at' => $expired_at, // Can be NULL
                'created_at' => date('Y-m-d H:i:s') // Update creation time? Or keep original? Schema uses ON UPDATE CURRENT_TIMESTAMP
            ];

            // Attempt to update the card using the model
            if ($cardModel->update($update_card_data)) {
                $response['success'] = true;
                $response['message'] = 'Card updated successfully.';
                $response['data'] = ['card_id' => $card_id];
            } else {
                $response['message'] = 'Failed to update card. It might not exist or no changes were made.';
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during card update.';
            error_log("API Cards update error: " . $e->getMessage());
        }
    } else {
        $response['message'] = implode(' ', $errors); // Join multiple errors with space
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Output the JSON response
echo json_encode($response);
exit;
?>