<?php
// views/menu/edit.php

$script_path = $_SERVER['SCRIPT_NAME'];
$project_root = preg_replace('#/views/.*$#', '', $script_path) . '/';

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

$menu_id_to_edit = $_GET['id'] ?? null;
if (!$menu_id_to_edit) {
    header("Location: " . $project_root . "views/menu/index.php");
    exit;
}

$menu_details = null;
$success_message = '';
$error_message = '';

try {
    $query = "SELECT id, name, price, menu_type, description, category, created_at FROM menu WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $menu_id_to_edit);
    $stmt->execute();
    $menu_details = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$menu_details) $error_message = "Menu item not found.";
} catch (PDOException $e) {
    $error_message = "Could not load menu item details. Please try again later.";
    error_log("Edit Menu Item page - fetch query error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $menu_details) {
    $name = trim($_POST['name'] ?? '');
    $price = $_POST['price'] ?? '';
    $menu_type = $_POST['menu_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';

    $errors = [];
    if (empty($name)) $errors[] = "Item name is required.";
    if (empty($price) || !is_numeric($price) || $price < 0) $errors[] = "Please enter a valid price.";
    if (empty($menu_type) || !in_array($menu_type, ['Veg', 'Non-veg'])) $errors[] = "Please select a valid menu type (Veg/Non-veg).";
    if (empty($description)) $errors[] = "Description is required.";
    if (empty($category) || !in_array($category, ['Breakfast', 'Lunch', 'Dinner'])) $errors[] = "Please select a valid category (Breakfast/Lunch/Dinner).";

    if (empty($errors)) {
        try {
            $update_query = "UPDATE menu SET name = :name, price = :price, menu_type = :menu_type, 
                            description = :description, category = :category WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':name', $name);
            $update_stmt->bindParam(':price', $price);
            $update_stmt->bindParam(':menu_type', $menu_type);
            $update_stmt->bindParam(':description', $description);
            $update_stmt->bindParam(':category', $category);
            $update_stmt->bindParam(':id', $menu_id_to_edit);

            if ($update_stmt->execute()) {
                $success_message = "Menu item updated successfully!";
                $stmt->execute();
                $menu_details = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error_message = "Failed to update menu item. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again later.";
            error_log("Edit Menu Item error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

$content = "
    <div class='page-title-box'>
        <h2><i class='bi bi-pencil-square'></i> Edit Menu Item</h2>
        <p class='text-muted mb-0'>Update menu item details</p>
    </div>
";

if ($error_message && !$menu_details) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='" . $project_root . "views/menu/index.php' class='btn btn-secondary'><i class='bi bi-arrow-left'></i> Back to Menu List</a>";
} elseif ($menu_details) {
    $content .= "<p class='mb-3'>Editing: <strong>" . htmlspecialchars($menu_details['name']) . "</strong> <span class='badge bg-secondary'>ID: " . htmlspecialchars($menu_details['id']) . "</span></p>";

    if ($success_message) {
        $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }
    if ($error_message) {
        $content .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }

    $type_selected_veg = $menu_details['menu_type'] === 'Veg' ? 'selected' : '';
    $type_selected_nonveg = $menu_details['menu_type'] === 'Non-veg' ? 'selected' : '';
    $cat_selected_breakfast = $menu_details['category'] === 'Breakfast' ? 'selected' : '';
    $cat_selected_lunch = $menu_details['category'] === 'Lunch' ? 'selected' : '';
    $cat_selected_dinner = $menu_details['category'] === 'Dinner' ? 'selected' : '';

    $content .= "
    <div class='card'>
        <div class='card-body'>
            <form method='post' action=''>
                <div class='mb-3'>
                    <label for='name' class='form-label'><i class='bi bi-cup-straw'></i> Item Name:</label>
                    <input type='text' class='form-control' id='name' name='name' value='" . htmlspecialchars($menu_details['name']) . "' required>
                </div>
                <div class='mb-3'>
                    <label for='price' class='form-label'><i class='bi bi-currency-rupee'></i> Price (₹):</label>
                    <input type='number' step='0.01' class='form-control' id='price' name='price' value='" . htmlspecialchars($menu_details['price']) . "' required min='0'>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='menu_type' class='form-label'><i class='bi bi-egg'></i> Menu Type:</label>
                        <select class='form-select' id='menu_type' name='menu_type' required>
                            <option value='Veg' " . $type_selected_veg . ">🥗 Veg</option>
                            <option value='Non-veg' " . $type_selected_nonveg . ">🍖 Non-Veg</option>
                        </select>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='category' class='form-label'><i class='bi bi-clock'></i> Category:</label>
                        <select class='form-select' id='category' name='category' required>
                            <option value='Breakfast' " . $cat_selected_breakfast . ">🌅 Breakfast</option>
                            <option value='Lunch' " . $cat_selected_lunch . ">☀️ Lunch</option>
                            <option value='Dinner' " . $cat_selected_dinner . ">🌙 Dinner</option>
                        </select>
                    </div>
                </div>
                <div class='mb-3'>
                    <label for='description' class='form-label'><i class='bi bi-card-text'></i> Description:</label>
                    <textarea class='form-control' id='description' name='description' rows='3' required>" . htmlspecialchars($menu_details['description']) . "</textarea>
                </div>
                <div class='d-grid gap-2'>
                    <button type='submit' class='btn btn-brand'><i class='bi bi-check-circle'></i> Update Menu Item</button>
                    <a href='" . $project_root . "views/menu/view.php?id=" . $menu_details['id'] . "' class='btn btn-info'><i class='bi bi-eye'></i> View Details</a>
                    <a href='" . $project_root . "views/menu/index.php' class='btn btn-secondary'><i class='bi bi-arrow-left'></i> Back to Menu List</a>
                </div>
            </form>
        </div>
    </div>
    ";
} else {
    $content .= "<p>Unable to load menu item information.</p>";
    $content .= "<a href='" . $project_root . "views/menu/index.php' class='btn btn-secondary'><i class='bi bi-arrow-left'></i> Back to Menu List</a>";
}

// Include layout using BASE_PATH
include BASE_PATH . '/views/layouts/app.php';
?>