<?php
// api/cards/delete.php

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

// Check if the user is logged in and is an admin (only admins should delete cards)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin privileges required.';
    echo json_encode($response);
    exit;
}

// Check if the request method is POST (typically used for delete confirmations)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve card ID
    $card_id = (int)($input['id'] ?? 0);

    // Basic validation
    if ($card_id <= 0) {
        $response['message'] = 'Invalid card ID provided.';
    } else {
        try {
            // Check if the card exists before attempting deletion
            $existing_card = $cardModel->findById($card_id);
            if (!$existing_card) {
                $response['message'] = 'Card not found.';
            } else {
                // Attempt to delete the card using the model
                // WARNING: This is likely to fail due to foreign key constraints if other tables depend on it (e.g., tabels, transactions, user_details_tabel).
                // Ensure cascading deletes are handled correctly in the database schema or handle dependencies before deletion.
                if ($cardModel->delete($card_id)) {
                    $response['success'] = true;
                    $response['message'] = 'Card deleted successfully.';
                    $response['data'] = ['deleted_card_id' => $card_id];
                } else {
                    // This could mean the row didn't exist or a constraint prevented deletion
                    $response['message'] = 'Failed to delete card. It might not exist or be linked to other records that prevent deletion (e.g., related transactions, table orders, user details).';
                }
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during card deletion.';
            error_log("API Cards delete error: " . $e->getMessage());
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