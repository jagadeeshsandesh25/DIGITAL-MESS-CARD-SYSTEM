<?php
// api/cards/index.php

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

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in and has appropriate permissions (admin or user for their own cards)
// For simplicity, let's restrict to admin for full list, users can fetch their own via a different endpoint
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin privileges required to list all cards.';
    echo json_encode($response);
    exit;
}

try {
    // Fetch all cards from the model, ordered by creation time (newest first)
    $cards = $cardModel->findAll(); // Default order by created_at DESC

    if ($cards !== false) {
        $response['success'] = true;
        $response['message'] = 'Cards fetched successfully.';
        $response['data'] = $cards; // Return the array of card data
    } else {
        $response['message'] = 'Failed to fetch cards.';
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Database error while fetching cards.';
    error_log("API Cards index error: " . $e->getMessage());
}

// Output the JSON response
echo json_encode($response);
exit;
?>