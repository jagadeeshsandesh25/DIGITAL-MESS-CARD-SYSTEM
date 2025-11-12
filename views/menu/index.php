<?php
// views/menu/index.php

// Determine project base URL
$script_path = $_SERVER['SCRIPT_NAME'];
$project_root = preg_replace('#/views/.*$#', '', $script_path) . '/';

// Define BASE_PATH using __DIR__
define('BASE_PATH', dirname(__DIR__, 2)); // Goes up 2 levels from /menu/ → to /jai/

// Include database
require_once BASE_PATH . '/config/database.php';
$database = new Database();
$db = $database->getConnection();

$menu_items = [];
$error_message = '';

try {
    $query = "SELECT id, name, price, menu_type, description, category, created_at 
              FROM menu ORDER BY category, menu_type, name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Could not load menu items. Please try again later.";
    error_log("Menu index page query error: " . $e->getMessage());
}

$content = "
    <div class='page-title-box'>
        <h2><i class='bi bi-list-ul'></i> Manage Menu</h2>
        <p class='text-muted mb-0'>View, edit, and manage all menu items</p>
    </div>
";

if ($error_message) {
    $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
} else {
    $content .= "
    <div class='mb-3'>
        <a href='" . $project_root . "views/menu/create.php' class='btn btn-brand'><i class='bi bi-plus-circle'></i> Add New Menu Item</a>
    </div>
    ";

    if (!empty($menu_items)) {
        $content .= "
        <div class='card'>
            <div class='card-body'>
                <div class='table-responsive'>
                    <table class='table table-hover align-middle'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Item Name</th>
                                <th>Price</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
        ";

        foreach ($menu_items as $item) {
            $type_badge = $item['menu_type'] === 'Veg' 
                ? '<span class="badge bg-success">🥗 Veg</span>' 
                : '<span class="badge bg-danger">🍖 Non-Veg</span>';
            $category_badge = '<span class="badge bg-info">' . htmlspecialchars($item['category']) . '</span>';
            $description_display = strlen($item['description']) > 50 
                ? substr(htmlspecialchars($item['description']), 0, 47) . '...' 
                : htmlspecialchars($item['description']);

            $content .= "
                    <tr>
                        <td>" . htmlspecialchars($item['id']) . "</td>
                        <td><strong>" . htmlspecialchars($item['name']) . "</strong></td>
                        <td><strong class='text-success'>₹" . number_format($item['price'], 2) . "</strong></td>
                        <td>" . $type_badge . "</td>
                        <td>" . $category_badge . "</td>
                        <td><small>" . $description_display . "</small></td>
                        <td><small>" . date('d M Y', strtotime($item['created_at'])) . "</small></td>
                        <td>
                            <div class='btn-group btn-group-sm' role='group'>
                                <a href='" . $project_root . "views/menu/view.php?id=" . $item['id'] . "' class='btn btn-info' title='View'><i class='bi bi-eye'></i></a>
                                <a href='" . $project_root . "views/menu/edit.php?id=" . $item['id'] . "' class='btn btn-warning' title='Edit'><i class='bi bi-pencil'></i></a>
                                <a href='" . $project_root . "views/menu/delete.php?id=" . $item['id'] . "' class='btn btn-danger' title='Delete' 
                                   onclick='return confirm(\"Are you sure you want to delete " . addslashes(htmlspecialchars($item['name'])) . "?\")'><i class='bi bi-trash'></i></a>
                            </div>
                        </td>
                    </tr>
            ";
        }

        $content .= "
                        </tbody>
                    </table>
                </div>
                <div class='mt-3'>
                    <p class='text-muted mb-0'><i class='bi bi-info-circle'></i> Total Items: <strong>" . count($menu_items) . "</strong></p>
                </div>
            </div>
        </div>
        ";
    } else {
        $content .= "
        <div class='card'>
            <div class='card-body text-center py-5'>
                <i class='bi bi-inbox' style='font-size: 3rem; color: var(--muted);'></i>
                <h5 class='mt-3'>No menu items found!</h5>
                <p class='text-muted'>Click 'Add New Menu Item' button to create your first menu item.</p>
                <a href='" . $project_root . "views/menu/create.php' class='btn btn-brand mt-2'><i class='bi bi-plus-circle'></i> Add First Item</a>
            </div>
        </div>";
    }
}

// Include layout using BASE_PATH
include BASE_PATH . '/views/layouts/app.php';
?>