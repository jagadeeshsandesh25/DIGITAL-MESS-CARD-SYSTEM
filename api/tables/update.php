<?php
// api/tables/update.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session
session_start();

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Include the TableOrder model (for tabels table)
require_once '../../models/TableOrder.php'; // Adjust path as needed
$tableOrderModel = new TableOrder($db);

// Include the User model (to validate user_id if changed)
require_once '../../models/User.php'; // Adjust path as needed
$userModel = new User($db);

// Include the Card model (to validate card_id if changed)
require_once '../../models/Card.php'; // Adjust path as needed
$cardModel = new Card($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in and is an admin or waiter
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'waiter')) {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin or Waiter privileges required.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Check if the request method is POST (or PUT/PATCH, but using POST for simplicity)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve input data
    $table_id_to_update = (int)($_POST['id'] ?? 0);
    $t_qr = (int)($_POST['t_qr'] ?? 0);
    $t_name = $_POST['t_name'] ?? 'A'; // Default to Table A
    $t_s_w = (int)($_POST['t_s_w'] ?? 0);
    $time_input = trim($_POST['time'] ?? '');
    $time = !empty($time_input) ? $time_input : date('Y-m-d H:i:s'); // Use provided time or current time
    $transaction_mode = $_POST['transaction_mode'] ?? 'Card'; // Default to Card
    $user_id_assigned = $_POST['user_id'] ?? null; // The waiter or customer assigned
    $card_id = $_POST['card_id'] ?? null; // The card used
    $menu_ordered = trim($_POST['menu_ordered'] ?? ''); // The menu items ordered

    // Basic validation
    $errors = [];
    if ($table_id_to_update <= 0) {
        $errors[] = "Invalid table record ID.";
    }
    if ($t_qr <= 0) {
        $errors[] = "QR Code ID must be a positive integer.";
    }
    if (!in_array($t_name, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'])) {
        $errors[] = "Please select a valid table name (A-J).";
    }
    if ($t_s_w < 0) {
        $errors[] = "Seat/Switch number cannot be negative.";
    }
    // Validate time format if provided
    if (!empty($time_input) && DateTime::createFromFormat('Y-m-d H:i:s', $time_input) === FALSE) {
         $errors[] = "Invalid time format. Please use YYYY-MM-DD HH:MM:SS.";
    }
    if (!in_array($transaction_mode, ['Cash', 'Card', 'UPI'])) {
        $errors[] = "Please select a valid transaction mode.";
    }
    if (empty($user_id_assigned) || $user_id_assigned == 0) {
        $errors[] = "Please select a user (waiter/customer).";
    }
    if (empty($card_id) || $card_id == 0) {
        $errors[] = "Please select a valid card.";
    }
    if (empty($menu_ordered)) {
        $errors[] = "Menu ordered cannot be empty.";
    }

    // Fetch the existing table record to check ownership/permissions
    $existing_table_record = null;
    if (empty($errors) && $table_id_to_update > 0) {
        try {
             $existing_table_record = $tableOrderModel->findById($table_id_to_update);
             if (!$existing_table_record) {
                 $errors[] = "Table record not found.";
             } elseif ($user_role === 'waiter' && $existing_table_record['user_id'] != $user_id) {
                  // Waiters can only update records assigned to them
                  $errors[] = "Access denied. You can only update records assigned to you.";
             }
        } catch (PDOException $e) {
            $errors[] = "Database error fetching existing table record.";
            error_log("API Tables update - Existing record fetch error: " . $e->getMessage());
        }
    }

    // Validate user_id exists and role (admin assigns any user, waiter assigns customer?)
    if (empty($errors) && $user_id_assigned > 0 && $existing_table_record) {
         try {
             $user_check_query = "SELECT id, role FROM user WHERE id = :user_id LIMIT 1";
             $user_check_stmt = $db->prepare($user_check_query);
             $user_check_stmt->bindParam(':user_id', $user_id_assigned, PDO::PARAM_INT);
             $user_check_stmt->execute();
             $user_result = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
             if (!$user_result) {
                 $errors[] = "Selected user ID does not exist.";
             } elseif ($user_role === 'waiter' && $user_result['role'] !== 'user') {
                  // Waiter can only assign to customers (users with role 'user')
                  $errors[] = "Waiters can only assign tables to customers (users with role 'user').";
             }
         } catch (PDOException $e) {
             $errors[] = "Database error validating user ID.";
             error_log("API Tables update - User ID validation error: " . $e->getMessage());
         }
    }

    // Validate card_id exists and belongs to the assigned user
    if (empty($errors) && $card_id > 0 && $existing_table_record) {
         try {
             $card_check_query = "SELECT id, user_id, c_status, balance_credits FROM card WHERE id = :card_id LIMIT 1";
             $card_check_stmt = $db->prepare($card_check_query);
             $card_check_stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
             $card_check_stmt->execute();
             $card_result = $card_check_stmt->fetch(PDO::FETCH_ASSOC);
             if (!$card_result) {
                 $errors[] = "Selected card ID does not exist.";
             } elseif ($card_result['user_id'] != $user_id_assigned) {
                  $errors[] = "Selected card does not belong to the assigned user.";
             } elseif ($card_result['c_status'] !== 'Active') {
                  $errors[] = "Selected card is not active.";
             } elseif ($card_result['balance_credits'] <= 0) {
                  $errors[] = "Selected card has insufficient balance.";
             }
         } catch (PDOException $e) {
             $errors[] = "Database error validating card ID.";
             error_log("API Tables update - Card ID validation error: " . $e->getMessage());
         }
    }


    if (empty($errors) && $existing_table_record) {
        try {
            // Prepare data array for the model, including the ID
            $update_table_data = [
                'id' => $table_id_to_update,
                't_qr' => $t_qr,
                't_name' => $t_name,
                't_s_w' => $t_s_w,
                'time' => $time, // Use provided or existing time
                'transaction_mode' => $transaction_mode,
                'user_id' => $user_id_assigned, // Updated user (waiter/customer) assigned
                'card_id' => $card_id, // Updated card used
                'menu_ordered' => $menu_ordered // Updated list of items ordered
            ];

            // Attempt to update the table record using the model
            if ($tableOrderModel->update($update_table_data)) {
                $response['success'] = true;
                $response['message'] = 'Table record updated successfully!';
                $response['data'] = ['table_id' => $table_id_to_update];
            } else {
                $response['message'] = 'Failed to update table record. It might not exist or no changes were made.';
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during table record update.';
            error_log("API Tables update error: " . $e->getMessage()); // Log the actual error
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