<?php
// api/tables/index.php

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

// Check if the user is logged in and has appropriate permissions (admin or waiter)
// For simplicity, let's restrict to admin for full list, waiters see their own assigned tables
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'waiter')) {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin or Waiter privileges required to list table records.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

try {
    // Fetch table records based on user role
    if ($user_role === 'admin') {
        // Admin sees all table records
        $table_records = $tableOrderModel->findAll(); // Default order by time DESC
    } elseif ($user_role === 'waiter') {
        // Waiter sees only table records assigned to them (where user_id = waiter's user_id)
        $table_records = $tableOrderModel->findByUserId($user_id); // Order by time DESC
    } else {
        // This case shouldn't be reached due to the role check above, but good for safety
        $table_records = [];
    }

    if ($table_records !== false) {
        $response['success'] = true;
        $response['message'] = 'Table records fetched successfully.';
        $response['data'] = $table_records; // Return the array of table record data
    } else {
        $response['message'] = 'Failed to fetch table records.';
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Database error while fetching table records.';
    error_log("API Tables index query error: " . $e->getMessage()); // Log the actual error
}

// Output the JSON response
echo json_encode($response);
exit;
?>