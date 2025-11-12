<?php
// api/recharge/index.php

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

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in and is an admin (or potentially user for their own recharges)
// For simplicity, let's restrict to admin for full list, users might see theirs via a different endpoint or filtered list
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin privileges required to list all recharges.';
    echo json_encode($response);
    exit;
}

try {
    // Fetch all recharge records from the model, ordered by recharge time (newest first)
    $recharges = $rechargeModel->findAll(); // Default order by r_time DESC

    if ($recharges !== false) {
        $response['success'] = true;
        $response['message'] = 'Recharge records fetched successfully.';
        $response['data'] = $recharges; // Return the array of recharge data
    } else {
        $response['message'] = 'Failed to fetch recharge records.';
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Database error while fetching recharge records.';
    error_log("API Recharge index error: " . $e->getMessage()); // Log the actual error
}

// Output the JSON response
echo json_encode($response);
exit;
?>