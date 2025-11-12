<?php
// controllers/TableOrderController.php

require_once '../config/database.php'; // Adjust path
require_once '../models/TableOrder.php'; // Adjust path
require_once '../models/User.php'; // Adjust path - Needed for user context
require_once '../models/Card.php'; // Adjust path - Needed for card context
require_once '../includes/auth.php'; // Adjust path - For permission checks

class TableOrderController {

    private $database;
    private $db;
    private $tableOrderModel;
    private $userModel;
    private $cardModel;

    public function __construct() {
        $this->database = new Database();
        $this->db = $this->database->getConnection();
        $this->tableOrderModel = new TableOrder($this->db);
        $this->userModel = new User($this->db); // Pass the same DB connection
        $this->cardModel = new Card($this->db); // Pass the same DB connection
    }

    public function index() {
        // Check if user is logged in
        requireLogin();
        $user_role = $_SESSION['role'];
        $user_id = $_SESSION['user_id'];

        $table_order_records = [];
        $error_message = '';

        try {
            // Fetch table order records based on user role
            if ($user_role === 'admin') {
                // Admin sees all table order records
                $table_order_records = $this->tableOrderModel->findAll(); // Default order by time DESC
            } elseif ($user_role === 'waiter') {
                // Waiter sees only table order records assigned to them (where user_id = waiter's user_id)
                $table_order_records = $this->tableOrderModel->findByUserId($user_id); // Order by time DESC
            } else {
                // Regular user sees only table order records associated with their card(s)
                // This requires finding the user's card(s) first.
                $user_cards_query = "SELECT id FROM card WHERE user_id = :user_id";
                $user_cards_stmt = $this->db->prepare($user_cards_query);
                $user_cards_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $user_cards_stmt->execute();
                $user_card_ids = $user_cards_stmt->fetchAll(PDO::FETCH_COLUMN, 0); // Get an array of card IDs

                if (!empty($user_card_ids)) {
                     $placeholders = str_repeat('?,', count($user_card_ids) - 1) . '?';
                     $orders_query = "
                         SELECT t.id, t.t_qr, t.t_name, t.t_s_w, t.time, t.transaction_mode, t.user_id, t.card_id, t.menu_ordered,
                                w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name,
                                c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                                cu.username as customer_username, cu.first_name as customer_first_name, cu.last_name as customer_last_name
                         FROM tabels t
                         LEFT JOIN user w ON t.user_id = w.id
                         LEFT JOIN card c ON t.card_id = c.id
                         LEFT JOIN user cu ON c.user_id = cu.id
                         WHERE t.card_id IN ($placeholders)
                         ORDER BY t.time DESC
                     ";
                     $orders_stmt = $this->db->prepare($orders_query);
                     $orders_stmt->execute($user_card_ids);
                     $table_order_records = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $table_order_records = []; // User has no cards, hence no orders
                }
            }
        } catch (PDOException $e) {
            $error_message = "Could not load table order records. Please try again later.";
            error_log("TableOrderController index query error: " . $e->getMessage());
        }

        // Prepare the content for the index view using a helper
        $content = $this->getIndexContent($table_order_records, $error_message, $user_role, $user_id);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function create() {
        // Check if user is logged in and is an admin or waiter
        requireLogin();
        $user_role = $_SESSION['role'];
        if (!isAdmin() && !isWaiter()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        // Fetch list of users (potential waiters/customers) and cards for the form
        $users = []; // Both waiters and customers might be listed
        $cards = []; // Cards available for assignment/order
        $users_error = '';
        $cards_error = '';

        try {
            // Fetch all users (admin might assign any waiter, waiter might select customer?)
            // For simplicity, let's fetch all users for admin, active cards for waiter context
            if (isAdmin()) {
                 $user_query = "SELECT id, first_name, last_name, username, role FROM user ORDER BY username";
            } else { // Waiter
                 $user_query = "SELECT id, first_name, last_name, username, role FROM user WHERE role = 'user' ORDER BY username"; // Only customers for waiter
            }
            $user_stmt = $this->db->prepare($user_query);
            $user_stmt->execute();
            $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $users_error = "Could not load users for table order.";
            error_log("TableOrderController create - Users query error: " . $e->getMessage());
        }

        try {
            // Fetch active cards
            $card_query = "SELECT id, user_id, balance_credits, total_credits, c_status FROM card WHERE c_status = 'Active' ORDER BY user_id, id";
            $card_stmt = $this->db->prepare($card_query);
            $card_stmt->execute();
            $cards = $card_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $cards_error = "Could not load active cards.";
            error_log("TableOrderController create - Cards query error: " . $e->getMessage());
        }

        $success_message = '';
        $error_message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($users_error) && empty($cards_error)) {
            $t_qr = (int)($_POST['t_qr'] ?? 0);
            $t_name = $_POST['t_name'] ?? 'A'; // Default to Table A
            $t_s_w = (int)($_POST['t_s_w'] ?? 0);
            $time_input = trim($_POST['time'] ?? '');
            $time = !empty($time_input) ? $time_input : date('Y-m-d H:i:s'); // Use provided time or current time
            $transaction_mode = $_POST['transaction_mode'] ?? 'Card'; // Default to Card
            $user_id_assigned = $_POST['user_id'] ?? null; // The waiter or customer assigned
            $card_id = $_POST['card_id'] ?? null; // The card used
            $menu_ordered = trim($_POST['menu_ordered'] ?? ''); // The menu items ordered

            // Basic validation
            $errors = [];
            if ($t_qr <= 0) {
                $errors[] = "QR Code ID must be a positive integer.";
            }
            if (!in_array($t_name, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'])) {
                $errors[] = "Please select a valid table name (A-J).";
            }
            if ($t_s_w < 0) {
                $errors[] = "Seat/Switch number cannot be negative.";
            }
            // Validate time format if provided
            if (!empty($time_input) && DateTime::createFromFormat('Y-m-d H:i:s', $time_input) === FALSE) {
                 $errors[] = "Invalid time format. Please use YYYY-MM-DD HH:MM:SS.";
            }
            if (!in_array($transaction_mode, ['Cash', 'Card', 'UPI'])) {
                $errors[] = "Please select a valid transaction mode.";
            }
            if (empty($user_id_assigned) || $user_id_assigned == 0) {
                $errors[] = "Please select a user (waiter/customer).";
            }
            if (empty($card_id) || $card_id == 0) {
                $errors[] = "Please select a valid card.";
            }
            if (empty($menu_ordered)) {
                $errors[] = "Menu ordered cannot be empty.";
            }

            // Additional validation: Ensure the assigned user_id is a waiter if the creator is admin,
            // or the card belongs to a customer if the creator is a waiter.
            // This is simplified logic.
            if (empty($errors)) {
                 // Placeholder for role-specific validation logic
                 // e.g., if admin, $user_id_assigned should be a waiter
                 // if waiter, $user_id_assigned might be implicit (the waiter themselves) or the customer linked to the card
                 // For now, we'll assume the form handles this correctly or validation happens elsewhere.
            }

            if (empty($errors)) {
                // Prepare data array for the model
                $new_table_order_data = [
                    't_qr' => $t_qr,
                    't_name' => $t_name,
                    't_s_w' => $t_s_w,
                    'time' => $time, // Use provided or current time
                    'transaction_mode' => $transaction_mode,
                    'user_id' => $user_id_assigned, // The user (waiter/customer) assigned to this record
                    'card_id' => $card_id, // The card used for this record
                    'menu_ordered' => $menu_ordered // The list of items ordered
                ];

                // Attempt to create the table order record using the model
                $new_table_order_id = $this->tableOrderModel->create($new_table_order_data);

                if ($new_table_order_id) {
                    $success_message = "Table order record created successfully! Record ID: $new_table_order_id";
                    // Optionally, redirect to the table orders list page after successful creation
                    // header("Location: index.php");
                    // exit;
                } else {
                    $error_message = "Failed to create table order record. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }

        // Load the create table order record view
        $content = $this->getCreateContent($users, $cards, $users_error, $cards_error, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function edit() {
        // Check if user is logged in and is an admin or waiter
        requireLogin();
        $user_role = $_SESSION['role'];
        if (!isAdmin() && !isWaiter()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $table_order_id_to_edit = $_GET['id'] ?? null;
        if (!$table_order_id_to_edit) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $table_order_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific table order record's details
        try {
             $table_order_details = $this->tableOrderModel->findById($table_order_id_to_edit);

             if (!$table_order_details) {
                 $error_message = "Table order record not found.";
             } elseif ($user_role === 'waiter' && $table_order_details['user_id'] != $_SESSION['user_id']) {
                  // Waiters can only edit records assigned to them
                  $error_message = "Access denied. You can only edit records assigned to you.";
                  $table_order_details = null; // Clear details to prevent editing
             }
        } catch (PDOException $e) {
            $error_message = "Could not load table order record details. Please try again later.";
            error_log("TableOrderController edit fetch query error: " . $e->getMessage());
        }

        // Process form submission if table order record details were found and user has permission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_order_details) {
            $t_qr = (int)($_POST['t_qr'] ?? 0);
            $t_name = $_POST['t_name'] ?? 'A';
            $t_s_w = (int)($_POST['t_s_w'] ?? 0);
            $time_input = trim($_POST['time'] ?? '');
            $time = !empty($time_input) ? $time_input : $table_order_details['time']; // Use provided time or keep existing
            $transaction_mode = $_POST['transaction_mode'] ?? 'Card';
            $user_id_assigned = $_POST['user_id'] ?? $table_order_details['user_id']; // Default to existing
            $card_id = $_POST['card_id'] ?? $table_order_details['card_id']; // Default to existing
            $menu_ordered = trim($_POST['menu_ordered'] ?? $table_order_details['menu_ordered']); // Default to existing

            // Basic validation
            $errors = [];
            if ($t_qr <= 0) {
                $errors[] = "QR Code ID must be a positive integer.";
            }
            if (!in_array($t_name, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'])) {
                $errors[] = "Please select a valid table name (A-J).";
            }
            if ($t_s_w < 0) {
                $errors[] = "Seat/Switch number cannot be negative.";
            }
            // Validate time format if provided
            if (!empty($time_input) && DateTime::createFromFormat('Y-m-d H:i:s', $time_input) === FALSE) {
                 $errors[] = "Invalid time format. Please use YYYY-MM-DD HH:MM:SS.";
            }
            if (!in_array($transaction_mode, ['Cash', 'Card', 'UPI'])) {
                $errors[] = "Please select a valid transaction mode.";
            }
            if (empty($user_id_assigned) || $user_id_assigned == 0) {
                $errors[] = "Please select a user (waiter/customer).";
            }
            if (empty($card_id) || $card_id == 0) {
                $errors[] = "Please select a valid card.";
            }
            if (empty($menu_ordered)) {
                $errors[] = "Menu ordered cannot be empty.";
            }

            if (empty($errors)) {
                // Prepare data array for the model, including the ID
                $update_table_order_data = [
                    'id' => $table_order_id_to_edit,
                    't_qr' => $t_qr,
                    't_name' => $t_name,
                    't_s_w' => $t_s_w,
                    'time' => $time, // Use provided or existing time
                    'transaction_mode' => $transaction_mode,
                    'user_id' => $user_id_assigned, // Updated user (waiter/customer) assigned
                    'card_id' => $card_id, // Updated card used
                    'menu_ordered' => $menu_ordered // Updated list of items ordered
                ];

                // Attempt to update the table order record using the model
                if ($this->tableOrderModel->update($update_table_order_data)) {
                    $success_message = "Table order record updated successfully!";
                    // Optionally, refetch the table order record details to show updated info
                     $table_order_details = $this->tableOrderModel->findById($table_order_id_to_edit); // Update the local variable
                } else {
                    $error_message = "Failed to update table order record. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }


        // Load the edit table order record view
        $content = $this->getEditContent($table_order_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function delete() {
        // Check if user is logged in and is an admin or waiter
        requireLogin();
        $user_role = $_SESSION['role'];
        if (!isAdmin() && !isWaiter()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $table_order_id_to_delete = $_GET['id'] ?? null;
        if (!$table_order_id_to_delete) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $table_order_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific table order record's details for confirmation
        try {
             $table_order_details = $this->tableOrderModel->findById($table_order_id_to_delete);

             if (!$table_order_details) {
                 $error_message = "Table order record not found.";
             } elseif ($user_role === 'waiter' && $table_order_details['user_id'] != $_SESSION['user_id']) {
                  // Waiters can only delete records assigned to them
                  $error_message = "Access denied. You can only delete records assigned to you.";
                  $table_order_details = null; // Clear details to prevent deletion
             }
        } catch (PDOException $e) {
            $error_message = "Could not load table order record details for deletion. Please try again later.";
            error_log("TableOrderController delete fetch query error: " . $e->getMessage());
        }

        // Process deletion if confirmed via POST and user has permission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_order_details && isset($_POST['confirm_delete'])) {
            // Attempt to delete the table order record using the model
            if ($this->tableOrderModel->delete($table_order_id_to_delete)) {
                $success_message = "Table order record deleted successfully!";
                // Optionally, redirect to the table orders list page after successful deletion
                // header("Location: index.php?success=deleted");
                // exit;
            } else {
                // This could mean the row didn't exist or a constraint prevented deletion
                $error_message = "Failed to delete table order record. It might not exist or be linked to other records that prevent deletion (e.g., waiter assignments, user details).";
            }
        }


        // Load the delete table order record confirmation view
        $content = $this->getDeleteContent($table_order_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    // --- Helper Methods to Generate View Content ---

    private function getIndexContent($table_order_records, $error_message = '', $user_role, $user_id) {
        $html = "<h2>Manage Table Order Records</h2><p>View " . ($user_role === 'admin' ? 'all' : ($user_role === 'waiter' ? 'your assigned' : 'your')) . " table order records.</p>";

        if ($error_message) {
            $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
        } else {
            // Add a button to create a new table order record (admin/waiter)
            if ($user_role === 'admin' || $user_role === 'waiter') {
                $html .= "
                <div class='mb-3'>
                    <a href='TableOrderController.php?action=create' class='btn btn-success'>Record New Table Order</a>
                </div>
                ";
            }

            // Check if table order records exist
            if (!empty($table_order_records)) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Table Name</th>
                                <th>QR Code ID</th>
                                <th>Seat/Switch Number</th>
                                <th>Assigned User (Waiter/Customer)</th>
                                <th>Card Used</th>
                                <th>Card Status</th>
                                <th>Transaction Mode</th>
                                <th>Order Time</th>
                                <th>Menu Ordered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";

                foreach ($table_order_records as $record) {
                    $user_name_display = $record['waiter_first_name'] ? htmlspecialchars($record['waiter_first_name'] . ' ' . $record['waiter_last_name']) : htmlspecialchars($record['waiter_username']);
                    $user_link = $record['user_id'] ? "<a href='../views/users/view.php?id=" . $record['user_id'] . "'>" . $user_name_display . "</a>" : 'N/A';
                    $card_link = $record['card_id'] ? "<a href='../views/cards/view.php?id=" . $record['card_id'] . "'>Card #" . $record['card_id'] . "</a>" : 'N/A';
                    $customer_name_display = $record['customer_first_name'] ? htmlspecialchars($record['customer_first_name'] . ' ' . $record['customer_last_name']) : htmlspecialchars($record['customer_username']);
                    $customer_link = $record['card_id'] ? "<a href='../views/users/view.php?id=" . $record['card_id'] . "'>" . $customer_name_display . " (via Card)</a>" : 'N/A (Customer via card)';
                    // Truncate long menu descriptions for display in the table
                    $menu_display = strlen($record['menu_ordered']) > 100 ? substr(htmlspecialchars($record['menu_ordered']), 0, 97) . '...' : htmlspecialchars($record['menu_ordered']);

                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($record['id']) . "</td>
                                <td>" . htmlspecialchars($record['t_name']) . "</td>
                                <td>" . htmlspecialchars($record['t_qr']) . "</td>
                                <td>" . htmlspecialchars($record['t_s_w']) . "</td>
                                <td>" . ($user_role === 'admin' ? $user_link : $user_name_display) . "</td> <!-- Show link only to admins -->
                                <td>" . $card_link . "</td>
                                <td>" . htmlspecialchars($record['card_status']) . "</td>
                                <td>" . htmlspecialchars($record['transaction_mode']) . "</td>
                                <td>" . htmlspecialchars($record['time']) . "</td>
                                <td>" . $menu_display . "</td>
                                <td>
                                    <a href='TableOrderController.php?action=edit&id=" . $record['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                    <a href='TableOrderController.php?action=delete&id=" . $record['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete table order record ID " . $record['id'] . "? This action cannot be undone and may affect related records.\")'>Delete</a>
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
                $html .= "<p>No table order records found.</p>";
            }
        }
        return $html;
    }

    private function getCreateContent($users, $cards, $users_error, $cards_error, $success_message = '', $error_message = '') {
        $html = "<h2>Record New Table Order</h2><p>Create a new record for a table order or assignment.</p>";

        if ($users_error || $cards_error) {
            $html .= "<div class='alert alert-danger' role='alert'>";
            if ($users_error) $html .= htmlspecialchars($users_error) . "<br>";
            if ($cards_error) $html .= htmlspecialchars($cards_error);
            $html .= "</div>";
        } else {
            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }
            if ($error_message) {
                $html .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>"; // Don't escape HTML for error messages as they might contain <br>
            }

            $html .= "
                <form method='post' action='../controllers/TableOrderController.php?action=create'>
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <label for='t_name' class='form-label'>Select Table Name:</label>
                            <select class='form-select' id='t_name' name='t_name' required>
                                <option value='A' " . (($_POST['t_name'] ?? 'A') === 'A' ? 'selected' : '') . ">Table A</option>
                                <option value='B' " . (($_POST['t_name'] ?? 'A') === 'B' ? 'selected' : '') . ">Table B</option>
                                <option value='C' " . (($_POST['t_name'] ?? 'A') === 'C' ? 'selected' : '') . ">Table C</option>
                                <option value='D' " . (($_POST['t_name'] ?? 'A') === 'D' ? 'selected' : '') . ">Table D</option>
                                <option value='E' " . (($_POST['t_name'] ?? 'A') === 'E' ? 'selected' : '') . ">Table E</option>
                                <option value='F' " . (($_POST['t_name'] ?? 'A') === 'F' ? 'selected' : '') . ">Table F</option>
                                <option value='G' " . (($_POST['t_name'] ?? 'A') === 'G' ? 'selected' : '') . ">Table G</option>
                                <option value='H' " . (($_POST['t_name'] ?? 'A') === 'H' ? 'selected' : '') . ">Table H</option>
                                <option value='I' " . (($_POST['t_name'] ?? 'A') === 'I' ? 'selected' : '') . ">Table I</option>
                                <option value='J' " . (($_POST['t_name'] ?? 'A') === 'J' ? 'selected' : '') . ">Table J</option>
                            </select>
                        </div>
                        <div class='col-md-6 mb-3'>
                            <label for='t_qr' class='form-label'>QR Code ID:</label>
                            <input type='number' class='form-control' id='t_qr' name='t_qr' value='" . htmlspecialchars($_POST['t_qr'] ?? ord(strtoupper($_POST['t_name'] ?? 'A'))) . "' min='0' required>
                            <small class='form-text text-muted'>Typically corresponds to the table name.</small>
                        </div>
                    </div>
                     <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <label for='t_s_w' class='form-label'>Seat/Switch Number:</label>
                            <input type='number' class='form-control' id='t_s_w' name='t_s_w' value='" . htmlspecialchars($_POST['t_s_w'] ?? 0) . "' min='0'>
                            <small class='form-text text-muted'>Purpose of this field is unclear from schema.</small>
                        </div>
                        <div class='col-md-6 mb-3'>
                            <label for='time' class='form-label'>Order Time:</label>
                            <input type='datetime-local' class='form-control' id='time' name='time' value='" . (new DateTime())->format('Y-m-d\TH:i:s') . "' required>
                            <small class='form-text text-muted'>Defaults to current time.</small>
                        </div>
                    </div>
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <label for='transaction_mode' class='form-label'>Transaction Mode:</label>
                            <select class='form-select' id='transaction_mode' name='transaction_mode' required>
                                <option value='Card' " . (($_POST['transaction_mode'] ?? 'Card') === 'Card' ? 'selected' : '') . ">Card</option>
                                <option value='Cash' " . (($_POST['transaction_mode'] ?? 'Card') === 'Cash' ? 'selected' : '') . ">Cash</option>
                                <option value='UPI' " . (($_POST['transaction_mode'] ?? 'Card') === 'UPI' ? 'selected' : '') . ">UPI</option>
                            </select>
                        </div>
                         <div class='col-md-6 mb-3'>
                            <label for='user_id' class='form-label'>Select Assigned User (Waiter/Customer):</label>
                            <select class='form-select' id='user_id' name='user_id' required>
                                <option value='0'>Choose User...</option>
            ";

            foreach ($users as $user) {
                 $selected_attr = ($_POST['user_id'] ?? null) == $user['id'] ? 'selected' : '';
                 $role_badge = $user['role'] === 'admin' ? '<span class="badge bg-primary">Admin</span>' :
                              ($user['role'] === 'waiter' ? '<span class="badge bg-warning">Waiter</span>' : '<span class="badge bg-info">User</span>');
                 $html .= "<option value='" . $user['id'] . "' " . $selected_attr . ">" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')') . " " . $role_badge . "</option>";
            }

            $html .= "
                            </select>
                        </div>
                    </div>
                    <div class='row mb-3'>
                        <div class='col-md-6'>
                            <label for='card_id' class='form-label'>Select Card Used:</label>
                            <select class='form-select' id='card_id' name='card_id' required>
                                <option value='0'>Choose Card...</option>
            ";

            foreach ($cards as $card) {
                 $selected_attr = ($_POST['card_id'] ?? null) == $card['id'] ? 'selected' : '';
                 $customer_name = "User ID: " . $card['user_id']; // Simplified, could join user table here if needed in model
                 $html .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (" . $customer_name . ", Balance: ₹" . number_format($card['balance_credits'], 2) . ", Total: ₹" . number_format($card['total_credits'], 2) . ", Status: " . $card['c_status'] . ")</option>";
            }

            $html .= "
                            </select>
                        </div>
                    </div>

                    <div class='mb-3'>
                        <h5>Menu Ordered:</h5>
                        <textarea class='form-control' id='menu_ordered' name='menu_ordered' rows='4' required>" . htmlspecialchars($_POST['menu_ordered'] ?? '') . "</textarea>
                        <small class='form-text text-muted'>Enter the items ordered, e.g., 'Dosa (x2), Sambar, Filter Coffee (x2)'.</small>
                    </div>

                    <div class='d-grid'>
                        <button type='submit' class='btn btn-primary'>Record Table Order</button>
                    </div>
                </form>
                <div class='mt-3'>
                    <a href='TableOrderController.php?action=index' class='btn btn-secondary'>Back to Table Orders List</a>
                </div>
            ";
        }

        return $html;
    }

     private function getEditContent($table_order_details, $success_message = '', $error_message = '') {
        $html = "<h2>Edit Table Order Record</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='TableOrderController.php?action=index' class='btn btn-secondary'>Back to Table Orders List</a>";
        } elseif ($table_order_details) {
            $user_name_display = $table_order_details['waiter_first_name'] ? htmlspecialchars($table_order_details['waiter_first_name'] . ' ' . $table_order_details['waiter_last_name']) : htmlspecialchars($table_order_details['waiter_username']);
            $user_link = "<a href='../views/users/view.php?id=" . $table_order_details['user_id'] . "'>" . $user_name_display . "</a>";
            $card_link = "<a href='../views/cards/view.php?id=" . $table_order_details['card_id'] . "'>Card #" . $table_order_details['card_id'] . "</a>";
            $customer_name_display = $table_order_details['customer_first_name'] ? htmlspecialchars($table_order_details['customer_first_name'] . ' ' . $table_order_details['customer_last_name']) : htmlspecialchars($table_order_details['customer_username']);
            $customer_link = $table_order_details['card_id'] ? "<a href='../views/users/view.php?id=" . $table_order_details['card_id'] . "'>" . $customer_name_display . " (via Card)</a>" : 'N/A (Customer via card)';

            $html .= "<p>Editing details for Table Order Record ID: <strong>" . htmlspecialchars($table_order_details['id']) . "</strong>, Table: <strong>" . htmlspecialchars($table_order_details['t_name']) . "</strong>, Assigned User: <strong>" . $user_link . "</strong>, Customer: <strong>" . $customer_link . "</strong></p>";

            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $mode_selected_cash = $table_order_details['transaction_mode'] === 'Cash' ? 'selected' : '';
            $mode_selected_card = $table_order_details['transaction_mode'] === 'Card' ? 'selected' : '';
            $mode_selected_upi = $table_order_details['transaction_mode'] === 'UPI' ? 'selected' : '';

            $html .= "
            <form method='post' action='../controllers/TableOrderController.php?action=edit&id=" . $table_order_details['id'] . "'>
                <div class='mb-3'>
                    <label for='table_order_id_display' class='form-label'>Table Order Record ID (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='table_order_id_display' value='" . htmlspecialchars($table_order_details['id']) . "' readonly>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='t_name' class='form-label'>Table Name:</label>
                        <select class='form-select' id='t_name' name='t_name' required>
                            <option value='A' " . ($table_order_details['t_name'] === 'A' ? 'selected' : '') . ">Table A</option>
                            <option value='B' " . ($table_order_details['t_name'] === 'B' ? 'selected' : '') . ">Table B</option>
                            <option value='C' " . ($table_order_details['t_name'] === 'C' ? 'selected' : '') . ">Table C</option>
                            <option value='D' " . ($table_order_details['t_name'] === 'D' ? 'selected' : '') . ">Table D</option>
                            <option value='E' " . ($table_order_details['t_name'] === 'E' ? 'selected' : '') . ">Table E</option>
                            <option value='F' " . ($table_order_details['t_name'] === 'F' ? 'selected' : '') . ">Table F</option>
                            <option value='G' " . ($table_order_details['t_name'] === 'G' ? 'selected' : '') . ">Table G</option>
                            <option value='H' " . ($table_order_details['t_name'] === 'H' ? 'selected' : '') . ">Table H</option>
                            <option value='I' " . ($table_order_details['t_name'] === 'I' ? 'selected' : '') . ">Table I</option>
                            <option value='J' " . ($table_order_details['t_name'] === 'J' ? 'selected' : '') . ">Table J</option>
                        </select>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='t_qr' class='form-label'>QR Code ID:</label>
                        <input type='number' class='form-control' id='t_qr' name='t_qr' value='" . htmlspecialchars($table_order_details['t_qr']) . "' min='0' required>
                    </div>
                </div>
                 <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='t_s_w' class='form-label'>Seat/Switch Number:</label>
                        <input type='number' class='form-control' id='t_s_w' name='t_s_w' value='" . htmlspecialchars($table_order_details['t_s_w']) . "' min='0'>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='time' class='form-label'>Order Time:</label>
                        <input type='datetime-local' class='form-control' id='time' name='time' value='" . (new DateTime($table_order_details['time']))->format('Y-m-d\TH:i:s') . "' required>
                    </div>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='transaction_mode' class='form-label'>Transaction Mode:</label>
                        <select class='form-select' id='transaction_mode' name='transaction_mode' required>
                            <option value='Cash' " . $mode_selected_cash . ">Cash</option>
                            <option value='Card' " . $mode_selected_card . ">Card</option>
                            <option value='UPI' " . $mode_selected_upi . ">UPI</option>
                        </select>
                    </div>
                     <div class='col-md-6 mb-3'>
                        <label for='user_id' class='form-label'>Select Assigned User (Waiter/Customer):</label>
                        <select class='form-select' id='user_id' name='user_id' required>
                            <option value='0'>Choose User...</option>
            ";

            // Fetch users again for the edit form
            try {
                $user_query = "SELECT id, first_name, last_name, username, role FROM user ORDER BY username";
                $user_stmt = $this->db->prepare($user_query);
                $user_stmt->execute();
                $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($users as $user) {
                     $selected_attr = $user['id'] == $table_order_details['user_id'] ? 'selected' : '';
                     $role_badge = $user['role'] === 'admin' ? '<span class="badge bg-primary">Admin</span>' :
                                  ($user['role'] === 'waiter' ? '<span class="badge bg-warning">Waiter</span>' : '<span class="badge bg-info">User</span>');
                     $html .= "<option value='" . $user['id'] . "' " . $selected_attr . ">" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')') . " " . $role_badge . "</option>";
                }
            } catch (PDOException $e) {
                $html .= "<option value=''>Error loading users</option>";
                error_log("TableOrderController edit - Users query error: " . $e->getMessage());
            }

            $html .= "
                        </select>
                    </div>
                </div>
                <div class='row mb-3'>
                    <div class='col-md-6'>
                        <label for='card_id' class='form-label'>Select Card Used:</label>
                        <select class='form-select' id='card_id' name='card_id' required>
                            <option value='0'>Choose Card...</option>
            ";

            // Fetch cards again for the edit form
            try {
                $card_query = "SELECT id, user_id, balance_credits, total_credits, c_status FROM card WHERE c_status = 'Active' ORDER BY user_id, id";
                $card_stmt = $this->db->prepare($card_query);
                $card_stmt->execute();
                $cards = $card_stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($cards as $card) {
                     $selected_attr = $card['id'] == $table_order_details['card_id'] ? 'selected' : '';
                     $customer_name = "User ID: " . $card['user_id']; // Simplified
                     $html .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (" . $customer_name . ", Balance: ₹" . number_format($card['balance_credits'], 2) . ", Total: ₹" . number_format($card['total_credits'], 2) . ", Status: " . $card['c_status'] . ")</option>";
                }
            } catch (PDOException $e) {
                $html .= "<option value=''>Error loading cards</option>";
                error_log("TableOrderController edit - Cards query error: " . $e->getMessage());
            }

            $html .= "
                            </select>
                        </div>
                    </div>

                    <div class='mb-3'>
                        <h5>Menu Ordered:</h5>
                        <textarea class='form-control' id='menu_ordered' name='menu_ordered' rows='4' required>" . htmlspecialchars($table_order_details['menu_ordered']) . "</textarea>
                        <small class='form-text text-muted'>Update the items ordered.</small>
                    </div>

                    <div class='d-grid'>
                        <button type='submit' class='btn btn-primary'>Update Table Order Record</button>
                    </div>
                </form>
                <div class='mt-3'>
                    <a href='TableOrderController.php?action=index' class='btn btn-secondary'>Back to Table Orders List</a>
                    <!-- View details link -->
                    <a href='TableOrderController.php?action=view&id=" . $table_order_details['id'] . "' class='btn btn-info'>View Record Details</a>
                </div>
            ";
        } else {
            // This case handles when the table order record ID was provided but the record wasn't found or access denied (error_message is set above)
            $html .= "<p>Unable to load table order record information.</p>";
            $html .= "<a href='TableOrderController.php?action=index' class='btn btn-secondary'>Back to Table Orders List</a>";
        }

        return $html;
    }

    private function getDeleteContent($table_order_details, $success_message = '', $error_message = '') {
        $html = "<h2>Delete Table Order Record</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='TableOrderController.php?action=index' class='btn btn-secondary'>Back to Table Orders List</a>";
        } elseif ($table_order_details) {
            if ($success_message) {
                $html .= "<div class='alert alert-success' role='alert'>" . htmlspecialchars($success_message) . "</div>";
                $html .= "<a href='TableOrderController.php?action=index' class='btn btn-secondary'>Back to Table Orders List</a>";
            } else {
                $user_name_display = $table_order_details['waiter_first_name'] ? htmlspecialchars($table_order_details['waiter_first_name'] . ' ' . $table_order_details['waiter_last_name']) : htmlspecialchars($table_order_details['waiter_username']);
                $user_link = "<a href='../views/users/view.php?id=" . $table_order_details['user_id'] . "'>" . $user_name_display . "</a>";
                $card_link = "<a href='../views/cards/view.php?id=" . $table_order_details['card_id'] . "'>Card #" . $table_order_details['card_id'] . "</a>";
                $customer_name_display = $table_order_details['customer_first_name'] ? htmlspecialchars($table_order_details['customer_first_name'] . ' ' . $table_order_details['customer_last_name']) : htmlspecialchars($table_order_details['customer_username']);
                $customer_link = $table_order_details['card_id'] ? "<a href='../views/users/view.php?id=" . $table_order_details['card_id'] . "'>" . $customer_name_display . " (via Card)</a>" : 'N/A (Customer via card)';
                $menu_display = nl2br(htmlspecialchars($table_order_details['menu_ordered'])); // Preserve line breaks

                $html .= "
                <div class='alert alert-warning' role='alert'>
                    <h5>Confirm Deletion</h5>
                    <p>Are you sure you want to delete Table Order Record ID '<strong>" . $table_order_details['id'] . "</strong>'?</p>
                    <p><strong>Table:</strong> " . htmlspecialchars($table_order_details['t_name']) . "</p>
                    <p><strong>Assigned User:</strong> " . $user_link . "</p>
                    <p><strong>Customer:</strong> " . $customer_link . "</p>
                    <p><strong>Card:</strong> " . $card_link . "</p>
                    <p><strong>Order Time:</strong> " . htmlspecialchars($table_order_details['time']) . "</p>
                    <p><strong>Transaction Mode:</strong> " . htmlspecialchars($table_order_details['transaction_mode']) . "</p>
                    <p><strong>Menu Ordered:</strong><br>" . $menu_display . "</p>
                    <p><strong>This action cannot be undone and may affect related records (e.g., waiter assignments, user details).</strong></p>
                </div>
                <form method='post' action='../controllers/TableOrderController.php?action=delete&id=" . $table_order_details['id'] . "'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($table_order_details['id']) . "'>
                    <input type='hidden' name='confirm_delete' value='1'>
                    <button type='submit' class='btn btn-danger'>Yes, Delete Table Order Record</button>
                    <a href='TableOrderController.php?action=index' class='btn btn-secondary'>Cancel</a>
                </form>
                ";
            }
        } else {
            // This case handles when the table order record ID was provided but the record wasn't found or access denied (error_message is set above)
            $html .= "<p>Unable to load table order record information for deletion.</p>";
            $html .= "<a href='TableOrderController.php?action=index' class='btn btn-secondary'>Back to Table Orders List</a>";
        }

        return $html;
    }

}

// --- Routing ---
$action = $_GET['action'] ?? '';

$tableOrderController = new TableOrderController();

// Ensure user is logged in before processing actions
requireLogin(); // This check happens *after* controller instantiation so helper functions can use models

// Call the appropriate method based on the action
switch ($action) {
    case 'index':
        $tableOrderController->index();
        break;
    case 'create':
        $tableOrderController->create();
        break;
    case 'edit':
        $tableOrderController->edit();
        break;
    case 'delete':
        $tableOrderController->delete();
        break;
    default:
        // Default to index if action is invalid or not provided
        $tableOrderController->index();
        break;
}

?>