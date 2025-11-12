<?php
// api/menu/update.php

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

// Check if the user is logged in and is an admin (only admins should update menu items)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin privileges required.';
    echo json_encode($response);
    exit;
}

// Check if the request method is POST (or PUT/PATCH, but using POST for simplicity)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve input data
    $menu_id = (int)($_POST['id'] ?? 0);
    $menu_type = $_POST['menu_type'] ?? 'Veg'; // Default to Veg
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'Breakfast'; // Default to Breakfast
    $created_at = date('Y-m-d H:i:s'); // Use current time (or keep existing?)

    // Basic validation
    $errors = [];
    if ($menu_id <= 0) {
        $errors[] = "Invalid menu item ID.";
    }
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    if (!in_array($menu_type, ['Veg', 'Non-veg'])) {
        $errors[] = "Please select a valid menu type (Veg/Non-veg).";
    }
    if (!in_array($category, ['Breakfast', 'Lunch', 'Dinner'])) {
        $errors[] = "Please select a valid category (Breakfast/Lunch/Dinner).";
    }

    // Validate menu item ID exists
    if (empty($errors) && $menu_id > 0) {
        try {
            $menu_check_query = "SELECT id FROM menu WHERE id = :menu_id LIMIT 1";
            $menu_check_stmt = $db->prepare($menu_check_query);
            $menu_check_stmt->bindParam(':menu_id', $menu_id, PDO::PARAM_INT);
            $menu_check_stmt->execute();
            if ($menu_check_stmt->rowCount() === 0) {
                $errors[] = "Menu item ID does not exist.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error validating menu item ID.";
            error_log("API Menu update - Menu ID validation error: " . $e->getMessage());
        }
    }

    if (empty($errors)) {
        try {
            // Prepare data array for the model, including the ID
            $update_menu_data = [
                'id' => $menu_id,
                'menu_type' => $menu_type,
                'description' => $description,
                'category' => $category,
                'created_at' => $created_at // Use current time (or keep existing?)
            ];

            // Attempt to update the menu item using the model
            if ($menuModel->update($update_menu_data)) {
                $response['success'] = true;
                $response['message'] = 'Menu item updated successfully.';
                $response['data'] = ['menu_id' => $menu_id];
            } else {
                $response['message'] = 'Failed to update menu item. It might not exist or no changes were made.';
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during menu item update.';
            error_log("API Menu update error: " . $e->getMessage());
        }
    } else {
        $response['message'] = implode(" ", $errors); // Join multiple errors with space
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Output the JSON response
echo json_encode($response);
exit;
?>