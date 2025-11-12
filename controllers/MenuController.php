<?php
// controllers/MenuController.php

require_once '../config/database.php'; // Adjust path
require_once '../models/Menu.php'; // Adjust path
require_once '../includes/auth.php'; // Adjust path - For permission checks

class MenuController {

    private $database;
    private $db;
    private $menuModel;

    public function __construct() {
        $this->database = new Database();
        $this->db = $this->database->getConnection();
        $this->menuModel = new Menu($this->db);
    }

    public function index() {
        // Check if user is logged in (typically admin or waiter would manage menu, users view it)
        // For simplicity, let's restrict to admin for management. Users might see it on the dashboard or order page.
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php"); // Redirect non-admins
            exit;
        }

        $menu_items = [];
        $error_message = '';

        try {
            // Fetch all menu items from the model, ordered by category
            $menu_items = $this->menuModel->findAll('category', 'ASC'); // Default order
        } catch (PDOException $e) {
            $error_message = "Could not load menu items. Please try again later.";
            error_log("MenuController index query error: " . $e->getMessage());
        }

        // Prepare the content for the index view using a helper
        $content = $this->getIndexContent($menu_items, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function create() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $success_message = '';
        $error_message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $menu_type = $_POST['menu_type'] ?? 'Veg'; // Default to Veg
            $description = trim($_POST['description'] ?? '');
            $category = $_POST['category'] ?? 'Breakfast'; // Default to Breakfast

            // Basic validation
            $errors = [];
            if (empty($description)) {
                $errors[] = "Description cannot be empty.";
            }
            if (!in_array($menu_type, ['Veg', 'Non-veg'])) {
                $errors[] = "Please select a valid menu type (Veg/Non-veg).";
            }
            if (!in_array($category, ['Breakfast', 'Lunch', 'Dinner'])) {
                $errors[] = "Please select a valid category (Breakfast/Lunch/Dinner).";
            }

            if (empty($errors)) {
                // Prepare data array for the model
                $new_menu_data = [
                    'menu_type' => $menu_type,
                    'description' => $description,
                    'category' => $category
                ];

                // Attempt to create the menu item using the model
                $new_menu_id = $this->menuModel->create($new_menu_data);

                if ($new_menu_id) {
                    $success_message = "Menu item created successfully! Item ID: $new_menu_id";
                    // Optionally, redirect to the menu list page after successful creation
                    // header("Location: index.php");
                    // exit;
                } else {
                    $error_message = "Failed to create menu item. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }

        // Load the create menu item view
        $content = $this->getCreateContent($success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function edit() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $menu_id_to_edit = $_GET['id'] ?? null;
        if (!$menu_id_to_edit) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $menu_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific menu item's details
        try {
             $menu_details = $this->menuModel->findById($menu_id_to_edit);

             if (!$menu_details) {
                 $error_message = "Menu item not found.";
             }
        } catch (PDOException $e) {
            $error_message = "Could not load menu item details. Please try again later.";
            error_log("MenuController edit fetch query error: " . $e->getMessage());
        }

        // Process form submission if menu details were found
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $menu_details) {
            $menu_type = $_POST['menu_type'] ?? 'Veg';
            $description = trim($_POST['description'] ?? '');
            $category = $_POST['category'] ?? 'Breakfast';

            // Basic validation
            $errors = [];
            if (empty($description)) {
                $errors[] = "Description cannot be empty.";
            }
            if (!in_array($menu_type, ['Veg', 'Non-veg'])) {
                $errors[] = "Please select a valid menu type (Veg/Non-veg).";
            }
            if (!in_array($category, ['Breakfast', 'Lunch', 'Dinner'])) {
                $errors[] = "Please select a valid category (Breakfast/Lunch/Dinner).";
            }

            if (empty($errors)) {
                // Prepare data array for the model, including the ID
                $update_menu_data = [
                    'id' => $menu_id_to_edit,
                    'menu_type' => $menu_type,
                    'description' => $description,
                    'category' => $category
                ];

                // Attempt to update the menu item using the model
                if ($this->menuModel->update($update_menu_data)) {
                    $success_message = "Menu item updated successfully!";
                    // Optionally, refetch the menu item details to show updated info
                     $menu_details = $this->menuModel->findById($menu_id_to_edit); // Update the local variable
                } else {
                    $error_message = "Failed to update menu item. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }


        // Load the edit menu item view
        $content = $this->getEditContent($menu_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function delete() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $menu_id_to_delete = $_GET['id'] ?? null;
        if (!$menu_id_to_delete) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $menu_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific menu item's details for confirmation
        try {
             $menu_details = $this->menuModel->findById($menu_id_to_delete);

             if (!$menu_details) {
                 $error_message = "Menu item not found.";
             }
        } catch (PDOException $e) {
            $error_message = "Could not load menu item details for deletion. Please try again later.";
            error_log("MenuController delete fetch query error: " . $e->getMessage());
        }

        // Process deletion if confirmed via POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $menu_details && isset($_POST['confirm_delete'])) {
            // Attempt to delete the menu item using the model
            if ($this->menuModel->delete($menu_id_to_delete)) {
                $success_message = "Menu item deleted successfully!";
                // Optionally, redirect to the menu list page after successful deletion
                // header("Location: index.php?success=deleted");
                // exit;
            } else {
                // This could mean the row didn't exist or a constraint prevented deletion
                $error_message = "Failed to delete menu item. It might not exist or be linked to other records (e.g., orders) that prevent deletion.";
            }
        }


        // Load the delete menu item confirmation view
        $content = $this->getDeleteContent($menu_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    // --- Helper Methods to Generate View Content ---

    private function getIndexContent($menu_items, $error_message = '') {
        $html = "<h2>Manage Menu</h2><p>View, edit, and manage menu items.</p>";

        if ($error_message) {
            $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
        } else {
            // Add a button to create a new menu item
            $html .= "
            <div class='mb-3'>
                <a href='MenuController.php?action=create' class='btn btn-success'>Add New Menu Item</a>
            </div>
            ";

            // Check if menu items exist
            if (!empty($menu_items)) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";

                foreach ($menu_items as $item) {
                    $type_badge = $item['menu_type'] === 'Veg' ? '<span class="badge bg-success">Veg</span>' : '<span class="badge bg-danger">Non-Veg</span>';
                    $category_badge = '<span class="badge bg-info">' . htmlspecialchars($item['category']) . '</span>';
                    // Truncate long descriptions for display in the table
                    $description_display = strlen($item['description']) > 100 ? substr(htmlspecialchars($item['description']), 0, 97) . '...' : htmlspecialchars($item['description']);

                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($item['id']) . "</td>
                                <td>" . $type_badge . "</td>
                                <td>" . $category_badge . "</td>
                                <td>" . $description_display . "</td>
                                <td>" . htmlspecialchars($item['created_at']) . "</td>
                                <td>
                                    <!-- View action might be less common for menu items, but could link to a detailed view if needed -->
                                    <!-- <a href='MenuController.php?action=view&id=" . $item['id'] . "' class='btn btn-sm btn-info'>View</a> -->
                                    <a href='MenuController.php?action=edit&id=" . $item['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                    <a href='MenuController.php?action=delete&id=" . $item['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete menu item \\\"" . addslashes(htmlspecialchars(substr($item['description'], 0, 50))) . "\\\" (ID: " . $item['id'] . ")? This action cannot be undone and might affect orders.\")'>Delete</a>
                                </td>
                            </tr>
                    ";
                }

                $html .= "
                        </tbody>
                    </table>
                </div>
                ";
            } else {
                $html .= "<p>No menu items found.</p>";
            }
        }
        return $html;
    }

    private function getCreateContent($success_message = '', $error_message = '') {
        $html = "<h2>Create New Menu Item</h2><p>Add a new item to the menu.</p>";

        if ($success_message) {
            $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        }
        if ($error_message) {
            $html .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>"; // Don't escape HTML for error messages as they might contain <br>
        }

        $html .= "
            <form method='post' action='../controllers/MenuController.php?action=create'>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='menu_type' class='form-label'>Menu Type:</label>
                        <select class='form-select' id='menu_type' name='menu_type' required>
                            <option value='Veg' " . (($_POST['menu_type'] ?? 'Veg') === 'Veg' ? 'selected' : '') . ">Veg</option>
                            <option value='Non-veg' " . (($_POST['menu_type'] ?? 'Veg') === 'Non-veg' ? 'selected' : '') . ">Non-Veg</option>
                        </select>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='category' class='form-label'>Category:</label>
                        <select class='form-select' id='category' name='category' required>
                            <option value='Breakfast' " . (($_POST['category'] ?? 'Breakfast') === 'Breakfast' ? 'selected' : '') . ">Breakfast</option>
                            <option value='Lunch' " . (($_POST['category'] ?? 'Breakfast') === 'Lunch' ? 'selected' : '') . ">Lunch</option>
                            <option value='Dinner' " . (($_POST['category'] ?? 'Breakfast') === 'Dinner' ? 'selected' : '') . ">Dinner</option>
                        </select>
                    </div>
                </div>
                <div class='mb-3'>
                    <label for='description' class='form-label'>Description:</label>
                    <textarea class='form-control' id='description' name='description' rows='3' required>" . htmlspecialchars($_POST['description'] ?? '') . "</textarea>
                    <small class='form-text text-muted'>Enter a detailed description of the menu item.</small>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Create Menu Item</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='MenuController.php?action=index' class='btn btn-secondary'>Back to Menu List</a>
            </div>
        ";

        return $html;
    }

     private function getEditContent($menu_details, $success_message = '', $error_message = '') {
        $html = "<h2>Edit Menu Item</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='MenuController.php?action=index' class='btn btn-secondary'>Back to Menu List</a>";
        } elseif ($menu_details) {
            $html .= "<p>Editing details for Menu Item ID: <strong>" . htmlspecialchars($menu_details['id']) . "</strong></p>";

            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $type_selected_veg = $menu_details['menu_type'] === 'Veg' ? 'selected' : '';
            $type_selected_nonveg = $menu_details['menu_type'] === 'Non-veg' ? 'selected' : '';
            $cat_selected_breakfast = $menu_details['category'] === 'Breakfast' ? 'selected' : '';
            $cat_selected_lunch = $menu_details['category'] === 'Lunch' ? 'selected' : '';
            $cat_selected_dinner = $menu_details['category'] === 'Dinner' ? 'selected' : '';

            $html .= "
            <form method='post' action='../controllers/MenuController.php?action=edit&id=" . $menu_details['id'] . "'>
                <div class='mb-3'>
                    <label for='menu_id_display' class='form-label'>Menu Item ID (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='menu_id_display' value='" . htmlspecialchars($menu_details['id']) . "' readonly>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='menu_type' class='form-label'>Menu Type:</label>
                        <select class='form-select' id='menu_type' name='menu_type' required>
                            <option value='Veg' " . $type_selected_veg . ">Veg</option>
                            <option value='Non-veg' " . $type_selected_nonveg . ">Non-Veg</option>
                        </select>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='category' class='form-label'>Category:</label>
                        <select class='form-select' id='category' name='category' required>
                            <option value='Breakfast' " . $cat_selected_breakfast . ">Breakfast</option>
                            <option value='Lunch' " . $cat_selected_lunch . ">Lunch</option>
                            <option value='Dinner' " . $cat_selected_dinner . ">Dinner</option>
                        </select>
                    </div>
                </div>
                <div class='mb-3'>
                    <label for='description' class='form-label'>Description:</label>
                    <textarea class='form-control' id='description' name='description' rows='3' required>" . htmlspecialchars($menu_details['description']) . "</textarea>
                    <small class='form-text text-muted'>Update the detailed description of the menu item.</small>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Update Menu Item</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='MenuController.php?action=index' class='btn btn-secondary'>Back to Menu List</a>
                <!-- View details link if a dedicated view page exists -->
                <!-- <a href='MenuController.php?action=view&id=" . $menu_details['id'] . "' class='btn btn-info'>View Menu Item Details</a> -->
            </div>
            ";
        } else {
            // This case handles when the menu item ID was provided but the item wasn't found (error_message is set above)
            $html .= "<p>Unable to load menu item information.</p>";
            $html .= "<a href='MenuController.php?action=index' class='btn btn-secondary'>Back to Menu List</a>";
        }

        return $html;
    }

    private function getDeleteContent($menu_details, $success_message = '', $error_message = '') {
        $html = "<h2>Delete Menu Item</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='MenuController.php?action=index' class='btn btn-secondary'>Back to Menu List</a>";
        } elseif ($menu_details) {
            if ($success_message) {
                $html .= "<div class='alert alert-success' role='alert'>" . htmlspecialchars($success_message) . "</div>";
                $html .= "<a href='MenuController.php?action=index' class='btn btn-secondary'>Back to Menu List</a>";
            } else {
                $type_badge = $menu_details['menu_type'] === 'Veg' ? '<span class="badge bg-success">Veg</span>' : '<span class="badge bg-danger">Non-Veg</span>';
                $category_badge = '<span class="badge bg-info">' . htmlspecialchars($menu_details['category']) . '</span>';
                $description_display = strlen($menu_details['description']) > 100 ? substr(htmlspecialchars($menu_details['description']), 0, 97) . '...' : htmlspecialchars($menu_details['description']);

                $html .= "
                <div class='alert alert-warning' role='alert'>
                    <h5>Confirm Deletion</h5>
                    <p>Are you sure you want to delete the following menu item?</p>
                    <p><strong>ID:</strong> " . htmlspecialchars($menu_details['id']) . "</p>
                    <p><strong>Type:</strong> " . $type_badge . "</p>
                    <p><strong>Category:</strong> " . $category_badge . "</p>
                    <p><strong>Description:</strong> " . $description_display . "</p>
                    <p><strong>Created At:</strong> " . htmlspecialchars($menu_details['created_at']) . "</p>
                    <p><strong>WARNING: This action cannot be undone and might affect existing orders that reference this item.</strong></p>
                </div>
                <form method='post' action='../controllers/MenuController.php?action=delete&id=" . $menu_details['id'] . "'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($menu_details['id']) . "'>
                    <input type='hidden' name='confirm_delete' value='1'>
                    <button type='submit' class='btn btn-danger'>Yes, Delete Menu Item</button>
                    <a href='MenuController.php?action=index' class='btn btn-secondary'>Cancel</a>
                </form>
                ";
            }
        } else {
            // This case handles when the menu item ID was provided but the item wasn't found (error_message is set above)
            $html .= "<p>Unable to load menu item information for deletion.</p>";
            $html .= "<a href='MenuController.php?action=index' class='btn btn-secondary'>Back to Menu List</a>";
        }

        return $html;
    }

}

// --- Routing ---
$action = $_GET['action'] ?? '';

$menuController = new MenuController();

// Ensure only admin can access menu management (for now)
// This check happens *after* controller instantiation so helper functions can use models
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../views/auth/login.php"); // Redirect to login if not authenticated as admin
    exit;
}

// Call the appropriate method based on the action
switch ($action) {
    case 'index':
        $menuController->index();
        break;
    case 'create':
        $menuController->create();
        break;
    case 'edit':
        $menuController->edit();
        break;
    case 'delete':
        $menuController->delete();
        break;
    default:
        // Default to index if action is invalid or not provided
        $menuController->index();
        break;
}

?>  