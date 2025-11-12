<?php
// api/menu/index.php

// Set content type to JSON for API response
header('Content-Type: application/json');

// Start the session
session_start();

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Include the Menu model
require_once '../../models/Menu.php'; // Adjust path as needed
$menuModel = new Menu($db);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if the user is logged in (admins, waiters, and users might view the menu)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401); // Unauthorized
    $response['message'] = 'Access denied. Please log in.';
    echo json_encode($response);
    exit;
}

try {
    // Fetch all menu items from the model, ordered by category, type, creation time
    $menu_items = $menuModel->findAll('category', 'ASC'); // Default order

    if ($menu_items !== false) {
        $response['success'] = true;
        $response['message'] = 'Menu items fetched successfully.';
        $response['data'] = $menu_items; // Return the array of menu item data
    } else {
        $response['message'] = 'Failed to fetch menu items.';
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Database error while fetching menu items.';
    error_log("API Menu index error: " . $e->getMessage());
}

// Output the JSON response
echo json_encode($response);
exit;
?>