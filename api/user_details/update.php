<?php
// api/user_details/update.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session
session_start();

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Include the UserDetailsTabel model
require_once '../../models/UserDetailsTabel.php'; // Adjust path as needed
$userDetailsModel = new UserDetailsTabel($db);

// Include the User model (to validate user_id if changed)
require_once '../../models/User.php'; // Adjust path as needed
$userModel = new User($db);

// Include the Card model (to validate card_id if changed)
require_once '../../models/Card.php'; // Adjust path as needed
$cardModel = new Card($db);

// Include the Transaction model (to validate transaction_id if changed)
require_once '../../models/Transaction.php'; // Adjust path as needed
$transactionModel = new Transaction($db);

// Include the Recharge model (to validate recharge_id if changed)
require_once '../../models/Recharge.php'; // Adjust path as needed
$rechargeModel = new Recharge($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in and is an admin (only admins should update user details records)
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
    $user_details_id = (int)($_POST['id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $card_id = (int)($_POST['card_id'] ?? 0);
    $transaction_id = (int)($_POST['transaction_id'] ?? 0);
    $recharge_id = (int)($_POST['recharge_id'] ?? 0);
    $present_pswd = trim($_POST['present_pswd'] ?? '');
    $previous_pswd = trim($_POST['previous_pswd'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $contact_details = trim($_POST['contact_details'] ?? '');
    $created_at_input = trim($_POST['created_at'] ?? '');
    $created_at = !empty($created_at_input) ? $created_at_input : date('Y-m-d H:i:s'); // Use provided time or current time
    $updated_at_input = trim($_POST['updated_at'] ?? '');
    $updated_at = !empty($updated_at_input) ? $updated_at_input : date('Y-m-d H:i:s'); // Use provided time or current time

    // Basic validation
    $errors = [];
    if ($user_details_id <= 0) {
        $errors[] = "Invalid user details record ID.";
    }
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
    if (empty($present_pswd)) {
        $errors[] = "Present password hash is required.";
    }
    if (empty($previous_pswd)) {
        $errors[] = "Previous password hash is required.";
    }
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($contact_details)) {
        $errors[] = "Contact details are required.";
    }
    // Validate created_at format if provided
    if (!empty($created_at_input) && DateTime::createFromFormat('Y-m-d H:i:s', $created_at_input) === FALSE) {
         $errors[] = "Invalid creation time format. Please use YYYY-MM-DD HH:MM:SS.";
    }
    // Validate updated_at format if provided
    if (!empty($updated_at_input) && DateTime::createFromFormat('Y-m-d H:i:s', $updated_at_input) === FALSE) {
         $errors[] = "Invalid update time format. Please use YYYY-MM-DD HH:MM:SS.";
    }

    // Validate user_details record ID exists
    if (empty($errors) && $user_details_id > 0) {
        try {
            $user_details_check_query = "SELECT id FROM user_details_tabel WHERE id = :user_details_id LIMIT 1";
            $user_details_check_stmt = $db->prepare($user_details_check_query);
            $user_details_check_stmt->bindParam(':user_details_id', $user_details_id, PDO::PARAM_INT);
            $user_details_check_stmt->execute();
            if ($user_details_check_stmt->rowCount() === 0) {
                $errors[] = "User details record ID does not exist.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error validating user details record ID.";
            error_log("API User Details update - User Details ID validation error: " . $e->getMessage());
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
            error_log("API User Details update - User ID validation error: " . $e->getMessage());
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
            error_log("API User Details update - Card ID validation error: " . $e->getMessage());
        }
    }

    // Validate transaction_id exists
    if (empty($errors) && $transaction_id > 0) {
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
             error_log("API User Details update - Transaction ID validation error: " . $e->getMessage());
         }
    }

    // Validate recharge_id exists
    if (empty($errors) && $recharge_id > 0) {
         try {
             $recharge_check_query = "SELECT id FROM recharge WHERE id = :recharge_id LIMIT 1";
             $recharge_check_stmt = $db->prepare($recharge_check_query);
             $recharge_check_stmt->bindParam(':recharge_id', $recharge_id, PDO::PARAM_INT);
             $recharge_check_stmt->execute();
             if ($recharge_check_stmt->rowCount() === 0) {
                 $errors[] = "Selected recharge ID does not exist.";
             }
         } catch (PDOException $e) {
             $errors[] = "Database error validating recharge ID.";
             error_log("API User Details update - Recharge ID validation error: " . $e->getMessage());
         }
    }


    if (empty($errors)) {
        try {
            // Prepare data array for the model, including the ID
            // Note: Updating password hashes or linked IDs in a snapshot table is highly discouraged.
            // This is included for completeness but should be used with extreme caution.
            $update_user_details_data = [
                'id' => $user_details_id,
                'user_id' => $user_id,
                'card_id' => $card_id,
                'transaction_id' => $transaction_id,
                'recharge_id' => $recharge_id,
                'present_pswd' => $present_pswd, // Pre-hashed
                'previous_pswd' => $previous_pswd, // Pre-hashed
                'username' => $username,
                'contact_details' => $contact_details,
                'created_at' => $created_at, // Use provided or existing time
                'updated_at' => $updated_at // Use provided or current time
            ];

            // Attempt to update the user details record using the model
            if ($userDetailsModel->update($update_user_details_data)) {
                $response['success'] = true;
                $response['message'] = 'User details record updated successfully!';
                $response['data'] = ['user_details_id' => $user_details_id];
            } else {
                $response['message'] = 'Failed to update user details record. It might not exist or no changes were made.';
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during user details record update.';
            error_log("API User Details update error: " . $e->getMessage()); // Log the actual error
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