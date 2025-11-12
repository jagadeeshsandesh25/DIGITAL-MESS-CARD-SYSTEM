<?php
// views/menu/create.php

$script_path = $_SERVER['SCRIPT_NAME'];
$project_root = preg_replace('#/views/.*$#', '', $script_path) . '/';

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $price = $_POST['price'] ?? '';
    $menu_type = $_POST['menu_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $created_at = date('Y-m-d H:i:s');

    $errors = [];
    if (empty($name)) $errors[] = "Item name is required.";
    if (empty($price) || !is_numeric($price) || $price < 0) $errors[] = "Please enter a valid price.";
    if (empty($menu_type) || !in_array($menu_type, ['Veg', 'Non-veg'])) $errors[] = "Please select a valid menu type (Veg/Non-veg).";
    if (empty($description)) $errors[] = "Description is required.";
    if (empty($category) || !in_array($category, ['Breakfast', 'Lunch', 'Dinner'])) $errors[] = "Please select a valid category (Breakfast/Lunch/Dinner).";

    if (empty($errors)) {
        try {
            $insert_query = "INSERT INTO menu (name, price, menu_type, description, category, created_at) 
                            VALUES (:name, :price, :menu_type, :description, :category, :created_at)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':name', $name);
            $insert_stmt->bindParam(':price', $price);
            $insert_stmt->bindParam(':menu_type', $menu_type);
            $insert_stmt->bindParam(':description', $description);
            $insert_stmt->bindParam(':category', $category);
            $insert_stmt->bindParam(':created_at', $created_at);

            if ($insert_stmt->execute()) {
                $new_item_id = $db->lastInsertId();
                $success_message = "Menu item created successfully! Item ID: $new_item_id";
            } else {
                $error_message = "Failed to create menu item. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again later.";
            error_log("Create Menu Item error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

$content = "
    <div class='page-title-box'>
        <h2><i class='bi bi-plus-circle'></i> Create New Menu Item</h2>
        <p class='text-muted mb-0'>Add a new item to the menu.</p>
    </div>
";

if ($success_message) {
    $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
}
if ($error_message) {
    $content .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
}

$content .= "
    <div class='card'>
        <div class='card-body'>
            <form method='post' action=''>
                <div class='mb-3'>
                    <label for='name' class='form-label'><i class='bi bi-cup-straw'></i> Item Name:</label>
                    <input type='text' class='form-control' id='name' name='name' value='" . htmlspecialchars($_POST['name'] ?? '') . "' required placeholder='e.g., Masala Dosa, Chicken Biryani'>
                </div>
                <div class='mb-3'>
                    <label for='price' class='form-label'><i class='bi bi-currency-rupee'></i> Price (₹):</label>
                    <input type='number' step='0.01' class='form-control' id='price' name='price' value='" . htmlspecialchars($_POST['price'] ?? '') . "' required placeholder='Enter price' min='0'>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='menu_type' class='form-label'><i class='bi bi-egg'></i> Menu Type:</label>
                        <select class='form-select' id='menu_type' name='menu_type' required>
                            <option value='' " . (($_POST['menu_type'] ?? '') === '' ? 'selected' : '') . ">Select Type...</option>
                            <option value='Veg' " . (($_POST['menu_type'] ?? '') === 'Veg' ? 'selected' : '') . ">🥗 Veg</option>
                            <option value='Non-veg' " . (($_POST['menu_type'] ?? '') === 'Non-veg' ? 'selected' : '') . ">🍖 Non-Veg</option>
                        </select>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='category' class='form-label'><i class='bi bi-clock'></i> Category:</label>
                        <select class='form-select' id='category' name='category' required>
                            <option value='' " . (($_POST['category'] ?? '') === '' ? 'selected' : '') . ">Select Category...</option>
                            <option value='Breakfast' " . (($_POST['category'] ?? '') === 'Breakfast' ? 'selected' : '') . ">🌅 Breakfast</option>
                            <option value='Lunch' " . (($_POST['category'] ?? '') === 'Lunch' ? 'selected' : '') . ">☀️ Lunch</option>
                            <option value='Dinner' " . (($_POST['category'] ?? '') === 'Dinner' ? 'selected' : '') . ">🌙 Dinner</option>
                        </select>
                    </div>
                </div>
                <div class='mb-3'>
                    <label for='description' class='form-label'><i class='bi bi-card-text'></i> Description:</label>
                    <textarea class='form-control' id='description' name='description' rows='3' required placeholder='Describe the dish, ingredients, taste, etc.'>" . htmlspecialchars($_POST['description'] ?? '') . "</textarea>
                </div>
                <div class='d-grid gap-2'>
                    <button type='submit' class='btn btn-brand'><i class='bi bi-check-circle'></i> Create Menu Item</button>
                    <a href='" . $project_root . "views/menu/index.php' class='btn btn-secondary'><i class='bi bi-arrow-left'></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
";

// Include layout using BASE_PATH
include BASE_PATH . '/views/layouts/app.php';
?>