<?php
// api/transactions/index.php

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

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in and is an admin (or potentially user for their own transactions)
// For simplicity, let's restrict to admin for full list, users can fetch their own via a different endpoint
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin privileges required to list all transactions.';
    echo json_encode($response);
    exit;
}

try {
    // Fetch all transactions from the model, ordered by transaction time (newest first)
    $transactions = $transactionModel->findAll(); // Default order by t_time DESC

    if ($transactions !== false) {
        $response['success'] = true;
        $response['message'] = 'Transactions fetched successfully.';
        $response['data'] = $transactions; // Return the array of transaction data
    } else {
        $response['message'] = 'Failed to fetch transactions.';
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Database error while fetching transactions.';
    error_log("API Transactions index error: " . $e->getMessage());
}

// Output the JSON response
echo json_encode($response);
exit;
?>