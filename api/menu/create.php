<?php
// api/menu/create.php

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

// Check if the user is logged in and is an admin (only admins should create menu items)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Admin privileges required.';
    echo json_encode($response);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to $_POST if JSON input is empty (for form submissions)
    if (!$input) {
        $input = $_POST;
    }

    // Sanitize and retrieve input data
    $menu_type = $_POST['menu_type'] ?? 'Veg'; // Default to Veg
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'Breakfast'; // Default to Breakfast
    $created_at = date('Y-m-d H:i:s'); // Use current time

    // Basic validation
    $errors = [];
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    if (!in_array($menu_type, ['Veg', 'Non-veg'])) {
        $errors[] = "Please select a valid menu type (Veg/Non-veg).";
    }
    if (!in_array($category, ['Breakfast', 'Lunch', 'Dinner'])) {
        $errors[] = "Please select a valid category (Breakfast/Lunch/Dinner).";
    }

    if (empty($errors)) {
        try {
            // Prepare data array for the model
            $new_menu_data = [
                'menu_type' => $menu_type,
                'description' => $description,
                'category' => $category,
                'created_at' => $created_at // Use current time
            ];

            // Attempt to create the menu item using the model
            $new_menu_id = $menuModel->create($new_menu_data);

            if ($new_menu_id) {
                $response['success'] = true;
                $response['message'] = 'Menu item created successfully.';
                $response['data'] = ['menu_id' => $new_menu_id];
            } else {
                $response['message'] = 'Failed to create menu item. Please try again.';
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            $response['message'] = 'Database error during menu item creation.';
            error_log("API Menu create error: " . $e->getMessage());
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