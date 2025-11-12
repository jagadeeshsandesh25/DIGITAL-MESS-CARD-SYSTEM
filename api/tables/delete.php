<?php
// api/tables/delete.php

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

// Check if the request method is POST (typically used for delete confirmations)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve table record ID
    $table_id_to_delete = (int)($input['id'] ?? 0);

    // Basic validation
    if ($table_id_to_delete <= 0) {
        $response['message'] = 'Invalid table record ID provided.';
    } else {
        try {
            // Fetch the specific table record's details for confirmation and permission check
            $table_record_details = $tableOrderModel->findById($table_id_to_delete);

            if (!$table_record_details) {
                $response['message'] = 'Table record not found.';
            } elseif ($user_role === 'waiter' && $table_record_details['user_id'] != $user_id) {
                 // Waiters can only delete records assigned to them
                 $response['message'] = 'Access denied. You can only delete records assigned to you.';
            } else {
                // Attempt to delete the table record using the model
                // WARNING: This is likely to fail due to foreign key constraints if other tables depend on it (e.g., waiter_orders, user_details_tabel).
                // Ensure cascading deletes are handled correctly in the database schema or handle dependencies before deletion.
                if ($tableOrderModel->delete($table_id_to_delete)) {
                    $response['success'] = true;
                    $response['message'] = 'Table record deleted successfully!';
                    $response['data'] = ['deleted_table_id' => $table_id_to_delete];
                } else {
                    // This could mean the row didn't exist or a constraint prevented deletion
                    $response['message'] = 'Failed to delete table record. It might not exist or be linked to other records that prevent deletion (e.g., related waiter orders, user details).';
                }
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during table record deletion.';
            error_log("API Tables delete error: " . $e->getMessage()); // Log the actual error
        }
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Output the JSON response
echo json_encode($response);
exit;
?>