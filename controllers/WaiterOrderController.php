<?php
// controllers/WaiterOrderController.php

require_once '../config/database.php'; // Adjust path
require_once '../models/WaiterOrder.php'; // Adjust path
require_once '../models/User.php'; // Adjust path - Needed for waiter context
require_once '../models/Table.php'; // Adjust path - Needed for order/table context (using Table model)
require_once '../includes/auth.php'; // Adjust path - For permission checks

class WaiterOrderController {

    private $database;
    private $db;
    private $waiterOrderModel;
    private $userModel;
    private $tableModel; // Using Table model for order details

    public function __construct() {
        $this->database = new Database();
        $this->db = $this->database->getConnection();
        $this->waiterOrderModel = new WaiterOrder($this->db);
        $this->userModel = new User($this->db); // Pass the same DB connection
        $this->tableModel = new Table($this->db); // Pass the same DB connection (for order details)
    }

    public function index() {
        // Check if user is logged in
        requireLogin();
        $user_role = $_SESSION['role'];
        $user_id = $_SESSION['user_id'];

        $waiter_order_records = [];
        $error_message = '';

        try {
            // Fetch waiter order records based on user role
            if ($user_role === 'admin') {
                // Admin sees all waiter order assignments
                $waiter_order_records = $this->waiterOrderModel->findAll('wo.id', 'DESC'); // Default order by ID DESC
            } elseif ($user_role === 'waiter') {
                // Waiters see only their own assigned orders
                $waiter_order_records = $this->waiterOrderModel->findByWaiterId($user_id, 'wo.status', 'ASC'); // Order by status, then ID
            } else {
                // Regular users don't have access to this list view
                header("Location: ../views/dashboard/index.php");
                exit;
            }
        } catch (PDOException $e) {
            $error_message = "Could not load waiter order records. Please try again later.";
            error_log("WaiterOrderController index query error: " . $e->getMessage());
        }

        // Prepare the content for the index view using a helper
        $content = $this->getIndexContent($waiter_order_records, $error_message, $user_role, $user_id);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function create() {
        // Check if user is logged in and is an admin (Only admins typically assign waiters to orders)
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        // Fetch list of waiters and orders (tabels records)
        $waiters = [];
        $orders = []; // These are tabels records
        $waiters_error = '';
        $orders_error = '';

        try {
            $waiter_query = "SELECT id, first_name, last_name, username FROM user WHERE role = 'waiter' ORDER BY username";
            $waiter_stmt = $this->db->prepare($waiter_query);
            $waiter_stmt->execute();
            $waiters = $waiter_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $waiters_error = "Could not load waiters.";
            error_log("WaiterOrderController create - Waiters query error: " . $e->getMessage());
        }

        try {
            // Fetch recent or unassigned tabels records? For now, fetch recent ones.
             $order_query = "SELECT id, t_name, t_qr, time, menu_ordered, user_id as assigned_waiter_id FROM tabels ORDER BY time DESC LIMIT 100"; // Limit for performance
             $order_stmt = $this->db->prepare($order_query);
             $order_stmt->execute();
             $orders = $order_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $orders_error = "Could not load recent table orders.";
            error_log("WaiterOrderController create - Orders query error: " . $e->getMessage());
        }

        $success_message = '';
        $error_message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($waiters_error) && empty($orders_error)) {
            $waiter_id = $_POST['waiter_id'] ?? null;
            $order_id = $_POST['order_id'] ?? null;
            $status = $_POST['status'] ?? 'preparing'; // Default status
            $served_at = null; // Served at is typically set later when status is updated to 'served'

            // Basic validation
            $errors = [];
            if (empty($waiter_id) || $waiter_id == 0) {
                $errors[] = "Please select a waiter.";
            }
            if (empty($order_id) || $order_id == 0) {
                $errors[] = "Please select an order.";
            }
            if (!in_array($status, ['preparing', 'served', 'cancelled'])) {
                $errors[] = "Please select a valid status.";
            }

            // Check if the order is already assigned to a waiter
            if (empty($errors)) {
                 try {
                     $check_query = "SELECT id FROM waiter_orders WHERE order_id = :order_id LIMIT 1";
                     $check_stmt = $this->db->prepare($check_query);
                     $check_stmt->bindParam(':order_id', $order_id);
                     $check_stmt->execute();
                     if ($check_stmt->rowCount() > 0) {
                         $errors[] = "This order is already assigned to a waiter.";
                     }
                 } catch (PDOException $e) {
                     $errors[] = "Could not check order assignment status.";
                     error_log("WaiterOrderController create - Assignment check error: " . $e->getMessage());
                 }
            }

            if (empty($errors)) {
                // Prepare data array for the model
                $new_waiter_order_data = [
                    'waiter_id' => $waiter_id,
                    'order_id' => $order_id,
                    'status' => $status,
                    'served_at' => $served_at // NULL initially
                ];

                // Attempt to create the waiter order assignment using the model
                $new_assignment_id = $this->waiterOrderModel->create($new_waiter_order_data);

                if ($new_assignment_id) {
                    $success_message = "Waiter assigned to order successfully! Assignment ID: $new_assignment_id";
                    // Optionally, redirect to the waiter orders list page after successful creation
                    // header("Location: index.php");
                    // exit;
                } else {
                    $error_message = "Failed to assign waiter to order. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors); // Join multiple errors with line breaks
            }
        }

        // Load the create waiter order assignment view
        $content = $this->getCreateContent($waiters, $orders, $waiters_error, $orders_error, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function edit() {
        // Check if user is logged in
        requireLogin();
        $user_role = $_SESSION['role'];
        $user_id = $_SESSION['user_id'];

        $assignment_id_to_edit = $_GET['id'] ?? null;
        if (!$assignment_id_to_edit) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $assignment_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific waiter order assignment's details
        try {
             $assignment_details = $this->waiterOrderModel->findById($assignment_id_to_edit);

             if (!$assignment_details) {
                 $error_message = "Waiter order assignment not found.";
             } elseif ($user_role === 'waiter' && $assignment_details['waiter_id'] != $user_id) {
                  // Waiters can only edit their own assignments
                  $error_message = "Access denied. You can only update your own order assignments.";
                  $assignment_details = null; // Clear details to prevent editing
             }
        } catch (PDOException $e) {
            $error_message = "Could not load assignment details. Please try again later.";
            error_log("WaiterOrderController edit fetch query error: " . $e->getMessage());
        }

        // Process form submission if assignment details were found and user has permission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assignment_details) {
            $status = $_POST['status'] ?? 'preparing';
            $served_at_input = trim($_POST['served_at'] ?? '');
            $served_at = !empty($served_at_input) ? $served_at_input : null; // Can be NULL or a timestamp

            // Basic validation
            $errors = [];
            if (!in_array($status, ['preparing', 'served', 'cancelled'])) {
                $errors[] = "Please select a valid status.";
            }
            // Validate served_at format if provided
            if (!empty($served_at_input) && DateTime::createFromFormat('Y-m-d H:i:s', $served_at_input) === FALSE) {
                 $errors[] = "Invalid served at time format. Please use YYYY-MM-DD HH:MM:SS.";
            }

            if (empty($errors)) {
                // Prepare data array for the model, including the ID
                $update_assignment_data = [
                    'id' => $assignment_id_to_edit,
                    'status' => $status,
                    'served_at' => $served_at // Use provided time or NULL
                ];

                // Attempt to update the waiter order assignment using the model
                if ($this->waiterOrderModel->update($update_assignment_data)) {
                    $success_message = "Assignment status updated successfully!";
                    // Optionally, refetch the assignment details to show updated info
                     $assignment_details = $this->waiterOrderModel->findById($assignment_id_to_edit); // Update the local variable
                } else {
                    $error_message = "Failed to update assignment status. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }


        // Load the edit waiter order assignment view
        $content = $this->getEditContent($assignment_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function delete() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $assignment_id_to_delete = $_GET['id'] ?? null;
        if (!$assignment_id_to_delete) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $assignment_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific waiter order assignment's details for confirmation
        try {
             $assignment_details = $this->waiterOrderModel->findById($assignment_id_to_delete);

             if (!$assignment_details) {
                 $error_message = "Waiter order assignment not found.";
             }
        } catch (PDOException $e) {
            $error_message = "Could not load assignment details for deletion. Please try again later.";
            error_log("WaiterOrderController delete fetch query error: " . $e->getMessage());
        }

        // Process deletion if confirmed via POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assignment_details && isset($_POST['confirm_delete'])) {
            // Attempt to delete the waiter order assignment using the model
            if ($this->waiterOrderModel->delete($assignment_id_to_delete)) {
                $success_message = "Waiter order assignment deleted successfully!";
                // Optionally, redirect to the waiter orders list page after successful deletion
                // header("Location: index.php?success=deleted");
                // exit;
            } else {
                // This could mean the row didn't exist or a constraint prevented deletion
                $error_message = "Failed to delete waiter order assignment. It might not exist or be linked to other records that prevent deletion.";
            }
        }


        // Load the delete waiter order assignment confirmation view
        $content = $this->getDeleteContent($assignment_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    // --- Helper Methods to Generate View Content ---

    private function getIndexContent($records, $error_message = '', $user_role, $user_id) {
        $html = "<h2>Manage Waiter Order Assignments</h2><p>" . ($user_role === 'admin' ? 'View all waiter order assignments.' : 'View your assigned orders.') . "</p>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
        } else {
            // Add a button to create a new assignment (admin only)
            if ($user_role === 'admin') {
                $html .= "
                <div class='mb-3'>
                    <a href='WaiterOrderController.php?action=create' class='btn btn-success'>Assign Waiter to Order</a>
                </div>
                ";
            }

            // Check if records exist
            if (!empty($records)) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Waiter</th>
                                <th>Order ID</th>
                                <th>Table Name</th>
                                <th>Table QR</th>
                                <th>Order Time</th>
                                <th>Menu Ordered</th>
                                <th>Status</th>
                                <th>Served At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";

                foreach ($records as $record) {
                    $waiter_name_display = $record['waiter_first_name'] ? htmlspecialchars($record['waiter_first_name'] . ' ' . $record['waiter_last_name']) : htmlspecialchars($record['waiter_username']);
                    $waiter_link = $record['waiter_id'] ? "<a href='../views/users/view.php?id=" . $record['waiter_id'] . "'>" . $waiter_name_display . "</a>" : 'N/A';
                    $order_link = $record['order_id'] ? "<a href='../views/tables/view.php?id=" . $record['order_id'] . "'>#" . $record['order_id'] . "</a>" : 'N/A';
                    $table_name_display = htmlspecialchars($record['table_name']);
                    $table_qr_display = htmlspecialchars($record['table_qr']);
                    $order_time_display = htmlspecialchars($record['order_time']);
                    // Truncate long menu descriptions for display in the table
                    $menu_display = strlen($record['menu_ordered']) > 100 ? substr(htmlspecialchars($record['menu_ordered']), 0, 97) . '...' : htmlspecialchars($record['menu_ordered']);
                    $status_badge = '';
                    switch ($record['status']) {
                        case 'preparing':
                            $status_badge = '<span class="badge bg-warning">Preparing</span>';
                            break;
                        case 'served':
                            $status_badge = '<span class="badge bg-success">Served</span>';
                            break;
                        case 'cancelled':
                            $status_badge = '<span class="badge bg-danger">Cancelled</span>';
                            break;
                        default:
                            $status_badge = '<span class="badge bg-secondary">' . htmlspecialchars($record['status']) . '</span>';
                    }
                    $served_at_display = $record['served_at'] ? htmlspecialchars($record['served_at']) : 'N/A';

                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($record['id']) . "</td>
                                <td>" . ($user_role === 'admin' ? $waiter_link : $waiter_name_display) . "</td> <!-- Show link only to admins -->
                                <td>" . $order_link . "</td>
                                <td>" . $table_name_display . "</td>
                                <td>" . $table_qr_display . "</td>
                                <td>" . $order_time_display . "</td>
                                <td>" . $menu_display . "</td>
                                <td>" . $status_badge . "</td>
                                <td>" . $served_at_display . "</td>
                                <td>
                                    <a href='WaiterOrderController.php?action=edit&id=" . $record['id'] . "' class='btn btn-sm btn-warning'>Update Status</a>
                                    " . ($user_role === 'admin' ? "<a href='WaiterOrderController.php?action=delete&id=" . $record['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete waiter order assignment ID " . $record['id'] . "? This action cannot be undone.\")'>Delete</a>" : "") . "
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
                $html .= "<p>No waiter order assignments found.</p>";
            }
        }
        return $html;
    }

    private function getCreateContent($waiters, $orders, $waiters_error, $orders_error, $success_message = '', $error_message = '') {
        $html = "<h2>Assign Waiter to Order</h2><p>Link a waiter to a specific table order.</p>";

        if ($waiters_error || $orders_error) {
            $html .= "<div class='alert alert-danger' role='alert'>";
            if ($waiters_error) $html .= htmlspecialchars($waiters_error) . "<br>";
            if ($orders_error) $html .= htmlspecialchars($orders_error);
            $html .= "</div>";
        } else {
            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }
            if ($error_message) {
                $html .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>"; // Don't escape HTML for error messages as they might contain <br>
            }

            $html .= "
                <form method='post' action='../controllers/WaiterOrderController.php?action=create'>
                    <div class='row mb-3'>
                        <div class='col-md-6'>
                            <label for='waiter_id' class='form-label'>Select Waiter:</label>
                            <select class='form-select' id='waiter_id' name='waiter_id' required>
                                <option value='0'>Choose Waiter...</option>
            ";

            foreach ($waiters as $waiter) {
                 $selected_attr = ($_POST['waiter_id'] ?? null) == $waiter['id'] ? 'selected' : '';
                 $html .= "<option value='" . $waiter['id'] . "' " . $selected_attr . ">" . htmlspecialchars($waiter['first_name'] . ' ' . $waiter['last_name'] . ' (' . $waiter['username'] . ')') . "</option>";
            }

            $html .= "
                            </select>
                        </div>
                        <div class='col-md-6'>
                            <label for='order_id' class='form-label'>Select Order (Table Record ID):</label>
                            <select class='form-select' id='order_id' name='order_id' required>
                                <option value='0'>Choose Order...</option>
            ";

            foreach ($orders as $order) {
                 $selected_attr = ($_POST['order_id'] ?? null) == $order['id'] ? 'selected' : '';
                 // Truncate long menu descriptions for display in the dropdown
                 $menu_display_dropdown = strlen($order['menu_ordered']) > 50 ? substr(htmlspecialchars($order['menu_ordered']), 0, 47) . '...' : htmlspecialchars($order['menu_ordered']);
                 $html .= "<option value='" . $order['id'] . "' " . $selected_attr . ">#" . $order['id'] . " (Table: " . htmlspecialchars($order['t_name']) . ", Time: " . htmlspecialchars($order['time']) . ", Menu: " . $menu_display_dropdown . ")</option>";
            }

            $html .= "
                            </select>
                        </div>
                    </div>
                    <div class='row mb-3'>
                        <div class='col-md-6'>
                            <label for='status' class='form-label'>Initial Status:</label>
                            <select class='form-select' id='status' name='status' required>
                                <option value='preparing' " . (($_POST['status'] ?? 'preparing') === 'preparing' ? 'selected' : '') . ">Preparing</option>
                                <option value='served' " . (($_POST['status'] ?? 'preparing') === 'served' ? 'selected' : '') . ">Served</option>
                                <option value='cancelled' " . (($_POST['status'] ?? 'preparing') === 'cancelled' ? 'selected' : '') . ">Cancelled</option>
                            </select>
                        </div>
                        <div class='col-md-6'>
                            <!-- Served At is typically set later, so we won't include it in the create form -->
                            <label class='form-label'>Served At:</label>
                            <input type='text' class='form-control-plaintext' value='Will be set when status is updated to Served' readonly>
                        </div>
                    </div>
                    <div class='d-grid'>
                        <button type='submit' class='btn btn-primary'>Assign Waiter to Order</button>
                    </div>
                </form>
                <div class='mt-3'>
                    <a href='WaiterOrderController.php?action=index' class='btn btn-secondary'>Back to Assignments List</a>
                </div>
            ";
        }

        return $html;
    }

     private function getEditContent($assignment_details, $success_message = '', $error_message = '') {
        $html = "<h2>Update Waiter Order Assignment</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='WaiterOrderController.php?action=index' class='btn btn-secondary'>Back to Assignments List</a>";
        } elseif ($assignment_details) {
            $waiter_name_display = $assignment_details['waiter_first_name'] ? htmlspecialchars($assignment_details['waiter_first_name'] . ' ' . $assignment_details['waiter_last_name']) : htmlspecialchars($assignment_details['waiter_username']);
            $waiter_link = "<a href='../views/users/view.php?id=" . $assignment_details['waiter_id'] . "'>" . $waiter_name_display . "</a>";
            $order_link = "<a href='../views/tables/view.php?id=" . $assignment_details['order_id'] . "'>#" . $assignment_details['order_id'] . "</a>";
            $table_name_display = htmlspecialchars($assignment_details['table_name']);
            $table_qr_display = htmlspecialchars($assignment_details['table_qr']);
            $order_time_display = htmlspecialchars($assignment_details['order_time']);
            // Truncate long menu descriptions for display
            $menu_display = strlen($assignment_details['menu_ordered']) > 100 ? substr(htmlspecialchars($assignment_details['menu_ordered']), 0, 97) . '...' : htmlspecialchars($assignment_details['menu_ordered']);

            $html .= "<p>Updating details for Assignment ID: <strong>" . htmlspecialchars($assignment_details['id']) . "</strong>, Waiter: <strong>" . $waiter_link . "</strong>, Order: <strong>" . $order_link . "</strong></p>";

            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $status_selected_preparing = $assignment_details['status'] === 'preparing' ? 'selected' : '';
            $status_selected_served = $assignment_details['status'] === 'served' ? 'selected' : '';
            $status_selected_cancelled = $assignment_details['status'] === 'cancelled' ? 'selected' : '';

            $html .= "
            <form method='post' action='../controllers/WaiterOrderController.php?action=edit&id=" . $assignment_details['id'] . "'>
                <div class='mb-3'>
                    <label for='assignment_id_display' class='form-label'>Assignment ID (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='assignment_id_display' value='" . htmlspecialchars($assignment_details['id']) . "' readonly>
                </div>
                <div class='row mb-3'>
                    <div class='col-md-6'>
                        <label for='waiter_info_display' class='form-label'>Assigned Waiter (Read-only):</label>
                        <input type='text' class='form-control-plaintext' id='waiter_info_display' value='" . $waiter_name_display . "' readonly>
                    </div>
                    <div class='col-md-6'>
                        <label for='order_info_display' class='form-label'>Assigned Order (Read-only):</label>
                        <input type='text' class='form-control-plaintext' id='order_info_display' value='" . $order_link . " (Table: " . $table_name_display . ", QR: " . $table_qr_display . ")' readonly>
                    </div>
                </div>
                <div class='mb-3'>
                    <label for='order_time_display' class='form-label'>Order Time (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='order_time_display' value='" . $order_time_display . "' readonly>
                </div>
                <div class='mb-3'>
                    <label for='menu_ordered_display' class='form-label'>Menu Ordered (Read-only):</label>
                    <textarea class='form-control-plaintext' id='menu_ordered_display' rows='2' readonly>" . htmlspecialchars($assignment_details['menu_ordered']) . "</textarea>
                </div>
                <div class='row mb-3'>
                    <div class='col-md-6'>
                        <label for='status' class='form-label'>Update Status:</label>
                        <select class='form-select' id='status' name='status' required>
                            <option value='preparing' " . $status_selected_preparing . ">Preparing</option>
                            <option value='served' " . $status_selected_served . ">Served</option>
                            <option value='cancelled' " . $status_selected_cancelled . ">Cancelled</option>
                        </select>
                    </div>
                    <div class='col-md-6'>
                        <label for='served_at' class='form-label'>Served At (YYYY-MM-DD HH:MM:SS):</label>
                        <input type='datetime-local' class='form-control' id='served_at' name='served_at' value='" . ($assignment_details['served_at'] ? (new DateTime($assignment_details['served_at']))->format('Y-m-d\TH:i:s') : '') . "'>
                        <small class='form-text text-muted'>Set this when marking the order as 'Served'. Leave blank otherwise.</small>
                    </div>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Update Assignment Status</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='WaiterOrderController.php?action=index' class='btn btn-secondary'>Back to Assignments List</a>
                <!-- View details link -->
                <a href='WaiterOrderController.php?action=view&id=" . $assignment_details['id'] . "' class='btn btn-info'>View Assignment Details</a>
            </div>
            ";
        } else {
            // This case handles when the assignment ID was provided but the record wasn't found (error_message is set above)
            $html .= "<p>Unable to load waiter order assignment information.</p>";
            $html .= "<a href='WaiterOrderController.php?action=index' class='btn btn-secondary'>Back to Assignments List</a>";
        }

        return $html;
    }

    private function getDeleteContent($assignment_details, $success_message = '', $error_message = '') {
        $html = "<h2>Delete Waiter Order Assignment</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='WaiterOrderController.php?action=index' class='btn btn-secondary'>Back to Assignments List</a>";
        } elseif ($assignment_details) {
            if ($success_message) {
                $html .= "<div class='alert alert-success' role='alert'>" . htmlspecialchars($success_message) . "</div>";
                $html .= "<a href='WaiterOrderController.php?action=index' class='btn btn-secondary'>Back to Assignments List</a>";
            } else {
                $waiter_name_display = $assignment_details['waiter_first_name'] ? htmlspecialchars($assignment_details['waiter_first_name'] . ' ' . $assignment_details['waiter_last_name']) : htmlspecialchars($assignment_details['waiter_username']);
                $waiter_link = "<a href='../views/users/view.php?id=" . $assignment_details['waiter_id'] . "'>" . $waiter_name_display . "</a>";
                $order_link = "<a href='../views/tables/view.php?id=" . $assignment_details['order_id'] . "'>#" . $assignment_details['order_id'] . "</a>";
                $table_name_display = htmlspecialchars($assignment_details['table_name']);
                $table_qr_display = htmlspecialchars($assignment_details['table_qr']);
                $order_time_display = htmlspecialchars($assignment_details['order_time']);
                $menu_display = nl2br(htmlspecialchars($assignment_details['menu_ordered'])); // Preserve line breaks
                $status_badge = '';
                switch ($assignment_details['status']) {
                    case 'preparing':
                        $status_badge = '<span class="badge bg-warning">Preparing</span>';
                        break;
                    case 'served':
                        $status_badge = '<span class="badge bg-success">Served</span>';
                        break;
                    case 'cancelled':
                        $status_badge = '<span class="badge bg-danger">Cancelled</span>';
                        break;
                    default:
                        $status_badge = '<span class="badge bg-secondary">' . htmlspecialchars($assignment_details['status']) . '</span>';
                }
                $served_at_display = $assignment_details['served_at'] ? htmlspecialchars($assignment_details['served_at']) : 'N/A';

                $html .= "
                <div class='alert alert-warning' role='alert'>
                    <h5>Confirm Deletion</h5>
                    <p>Are you sure you want to delete the following waiter order assignment?</p>
                    <p><strong>Assignment ID:</strong> " . htmlspecialchars($assignment_details['id']) . "</p>
                    <p><strong>Waiter:</strong> " . $waiter_link . "</p>
                    <p><strong>Order ID:</strong> " . $order_link . " (Table: " . $table_name_display . ", QR: " . $table_qr_display . ")</p>
                    <p><strong>Order Time:</strong> " . $order_time_display . "</p>
                    <p><strong>Menu Ordered:</strong><br>" . $menu_display . "</p>
                    <p><strong>Status:</strong> " . $status_badge . "</p>
                    <p><strong>Served At:</strong> " . $served_at_display . "</p>
                    <p><strong>This action cannot be undone.</strong></p>
                </div>
                <form method='post' action='../controllers/WaiterOrderController.php?action=delete&id=" . $assignment_details['id'] . "'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($assignment_details['id']) . "'>
                    <input type='hidden' name='confirm_delete' value='1'>
                    <button type='submit' class='btn btn-danger'>Yes, Delete Assignment</button>
                    <a href='WaiterOrderController.php?action=index' class='btn btn-secondary'>Cancel</a>
                </form>
                ";
            }
        } else {
            // This case handles when the assignment ID was provided but the record wasn't found (error_message is set above)
            $html .= "<p>Unable to load waiter order assignment information for deletion.</p>";
            $html .= "<a href='WaiterOrderController.php?action=index' class='btn btn-secondary'>Back to Assignments List</a>";
        }

        return $html;
    }

}

// --- Routing ---
$action = $_GET['action'] ?? '';

$waiterOrderController = new WaiterOrderController();

// Ensure user is logged in before processing actions
requireLogin(); // This check happens *after* controller instantiation so helper functions can use models

// Call the appropriate method based on the action
switch ($action) {
    case 'index':
        $waiterOrderController->index();
        break;
    case 'create':
        $waiterOrderController->create();
        break;
    case 'edit':
        $waiterOrderController->edit();
        break;
    case 'delete':
        $waiterOrderController->delete();
        break;
    default:
        // Default to index if action is invalid or not provided
        $waiterOrderController->index();
        break;
}

?>