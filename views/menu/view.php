<?php
// views/menu/view.php

$script_path = $_SERVER['SCRIPT_NAME'];
$project_root = preg_replace('#/views/.*$#', '', $script_path) . '/';

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

$menu_id_to_view = $_GET['id'] ?? null;
if (!$menu_id_to_view) {
    header("Location: " . $project_root . "views/menu/index.php");
    exit;
}

$menu_details = null;
$error_message = '';

try {
    $query = "SELECT id, name, price, menu_type, description, category, created_at FROM menu WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $menu_id_to_view);
    $stmt->execute();
    $menu_details = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$menu_details) $error_message = "Menu item not found.";
} catch (PDOException $e) {
    $error_message = "Could not load menu item details. Please try again later.";
    error_log("View Menu Item page - fetch query error: " . $e->getMessage());
}

$content = "
    <div class='page-title-box'>
        <h2><i class='bi bi-eye'></i> View Menu Item Details</h2>
        <p class='text-muted mb-0'>Complete information about this menu item</p>
    </div>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='" . $project_root . "views/menu/index.php' class='btn btn-secondary'><i class='bi bi-arrow-left'></i> Back to Menu List</a>";
} elseif ($menu_details) {
    $type_badge = $menu_details['menu_type'] === 'Veg' 
        ? '<span class="badge bg-success fs-5">🥗 Veg</span>' 
        : '<span class="badge bg-danger fs-5">🍖 Non-Veg</span>';
    $category_badge = '<span class="badge bg-info fs-5">' . htmlspecialchars($menu_details['category']) . '</span>';

    $content .= "
    <div class='card shadow-sm'>
        <div class='card-header' style='background: linear-gradient(90deg, var(--brand) 0%, var(--brand-dark) 100%); color: #fff;'>
            <h4 class='mb-0'><i class='bi bi-cup-straw'></i> " . htmlspecialchars($menu_details['name']) . "</h4>
        </div>
        <div class='card-body'>
            <div class='row'>
                <div class='col-md-6 mb-3'>
                    <h6 class='text-muted'><i class='bi bi-hash'></i> Item ID</h6>
                    <p class='fs-5'><strong>" . htmlspecialchars($menu_details['id']) . "</strong></p>
                </div>
                <div class='col-md-6 mb-3'>
                    <h6 class='text-muted'><i class='bi bi-cup-straw'></i> Item Name</h6>
                    <p class='fs-5'><strong>" . htmlspecialchars($menu_details['name']) . "</strong></p>
                </div>
            </div>
            <div class='row'>
                <div class='col-md-6 mb-3'>
                    <h6 class='text-muted'><i class='bi bi-currency-rupee'></i> Price</h6>
                    <h4 class='text-success mb-0'>₹" . number_format($menu_details['price'], 2) . "</h4>
                </div>
                <div class='col-md-6 mb-3'>
                    <h6 class='text-muted'><i class='bi bi-egg'></i> Menu Type</h6>
                    <p>" . $type_badge . "</p>
                </div>
            </div>
            <div class='row'>
                <div class='col-md-6 mb-3'>
                    <h6 class='text-muted'><i class='bi bi-clock'></i> Category</h6>
                    <p>" . $category_badge . "</p>
                </div>
                <div class='col-md-6 mb-3'>
                    <h6 class='text-muted'><i class='bi bi-calendar-check'></i> Created At</h6>
                    <p>" . date('d M Y, h:i A', strtotime($menu_details['created_at'])) . "</p>
                </div>
            </div>
            <hr class='my-3'>
            <div class='mt-3'>
                <h6 class='text-muted'><i class='bi bi-card-text'></i> Description</h6>
                <div class='p-3' style='background: rgba(255,178,107,0.1); border-radius: 8px; border-left: 4px solid var(--brand);'>
                    <p class='mb-0'>" . nl2br(htmlspecialchars($menu_details['description'])) . "</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class='mt-4 d-flex gap-2 flex-wrap'>
        <a href='" . $project_root . "views/menu/index.php' class='btn btn-secondary'><i class='bi bi-arrow-left'></i> Back to Menu List</a>
        <a href='" . $project_root . "views/menu/edit.php?id=" . $menu_details['id'] . "' class='btn btn-warning'><i class='bi bi-pencil'></i> Edit Menu Item</a>
        <a href='" . $project_root . "views/menu/delete.php?id=" . $menu_details['id'] . "' class='btn btn-danger' 
           onclick='return confirm(\"Are you sure you want to delete " . addslashes(htmlspecialchars($menu_details['name'])) . "?\")'><i class='bi bi-trash'></i> Delete</a>
    </div>
    ";
} else {
    $content .= "<div class='card'><div class='card-body text-center py-5'>
        <i class='bi bi-exclamation-triangle' style='font-size: 3rem; color: var(--muted);'></i>
        <h5 class='mt-3'>Unable to load menu item information</h5>
        <a href='" . $project_root . "views/menu/index.php' class='btn btn-secondary mt-3'><i class='bi bi-arrow-left'></i> Back to Menu List</a>
    </div></div>";
}

// Include layout using BASE_PATH
include BASE_PATH . '/views/layouts/app.php';
?>