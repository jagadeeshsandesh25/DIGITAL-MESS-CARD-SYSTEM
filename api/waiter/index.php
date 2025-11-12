<?php
// api/waiter/index.php

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

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401); // Unauthorized
    $response['message'] = 'Access denied. Please log in.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

try {
    // Fetch table records based on user role
    if ($user_role === 'admin') {
        // Admin sees all table records (assignments/orders)
        $table_records = $tableOrderModel->findAll(); // Default order by time DESC
    } elseif ($user_role === 'waiter') {
        // Waiter sees only table records assigned to them (where user_id = waiter's user_id)
        $table_records = $tableOrderModel->findByUserId($user_id); // Order by time DESC
    } else {
        // Regular users typically don't access this directly, but might see their own orders elsewhere
        // For this API endpoint, we'll deny access
        http_response_code(403); // Forbidden
        $response['message'] = 'Access denied. Admin or Waiter privileges required.';
        echo json_encode($response);
        exit;
    }

    if ($table_records !== false) {
        $response['success'] = true;
        $response['message'] = 'Table records (assignments/orders) fetched successfully.';
        $response['data'] = $table_records; // Return the array of table record data
    } else {
        $response['message'] = 'Failed to fetch table records.';
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Database error while fetching table records.';
    error_log("API Waiter index query error: " . $e->getMessage()); // Log the actual error
}

// Output the JSON response
echo json_encode($response);
exit;
?>