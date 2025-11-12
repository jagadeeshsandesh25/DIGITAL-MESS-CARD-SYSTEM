<?php
// api/menu/delete.php

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

// Check if the user is logged in and is an admin (only admins should delete menu items)
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

    // Sanitize and retrieve menu item ID
    $menu_id = (int)($input['id'] ?? 0);

    // Basic validation
    if ($menu_id <= 0) {
        $response['message'] = 'Invalid menu item ID provided.';
    } else {
        try {
            // Check if the menu item exists before attempting deletion
            $existing_menu_item = $menuModel->findById($menu_id);
            if (!$existing_menu_item) {
                $response['message'] = 'Menu item not found.';
            } else {
                // Attempt to delete the menu item using the model
                // WARNING: This is likely to fail due to foreign key constraints if items are ordered (in tabels.menu_ordered).
                // Ensure cascading deletes are handled correctly in the database schema or handle dependencies before deletion.
                if ($menuModel->delete($menu_id)) {
                    $response['success'] = true;
                    $response['message'] = 'Menu item deleted successfully.';
                    $response['data'] = ['deleted_menu_id' => $menu_id];
                } else {
                    // This could mean the row didn't exist or a constraint prevented deletion
                    $response['message'] = 'Failed to delete menu item. It might not exist or be linked to other records that prevent deletion (e.g., orders placed using this item).';
                }
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during menu item deletion.';
            error_log("API Menu delete error: " . $e->getMessage());
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