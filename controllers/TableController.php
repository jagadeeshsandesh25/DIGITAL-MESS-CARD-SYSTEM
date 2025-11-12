<?php
// controllers/TableController.php

require_once '../config/database.php'; // Adjust path
require_once '../models/Table.php'; // Adjust path
require_once '../models/User.php'; // Adjust path - Needed for waiter/customer context
require_once '../models/Card.php'; // Adjust path - Needed for card context
require_once '../includes/auth.php'; // Adjust path - For permission checks

class TableController {

    private $database;
    private $db;
    private $tableModel;
    private $userModel;
    private $cardModel;

    public function __construct() {
        $this->database = new Database();
        $this->db = $this->database->getConnection();
        $this->tableModel = new Table($this->db);
        $this->userModel = new User($this->db); // Pass the same DB connection
        $this->cardModel = new Card($this->db); // Pass the same DB connection
    }

    public function index() {
        // Check if user is logged in
        requireLogin();
        // Restrict access: Admins see all, Waiters see their own assigned tables/orders
        // Users typically wouldn't access this directly, but might see their order history elsewhere.
        $user_role = $_SESSION['role'];
        $user_id = $_SESSION['user_id'];

        $table_records = [];
        $error_message = '';

        try {
            // Fetch table records based on user role
            if ($user_role === 'admin') {
                // Admin sees all table records
                $table_records = $this->tableModel->findAll(); // Default order by time DESC
            } elseif ($user_role === 'waiter') {
                // Waiter sees only table records assigned to them (where user_id = waiter's user_id)
                $query = "
                    SELECT t.id, t.t_name, t.t_qr, t.t_s_w, t.time, t.transaction_mode, t.user_id, t.card_id, t.menu_ordered,
                           w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name,
                           c.balance_credits as card_balance, c.total_credits as card_total, cu.username as customer_username
                    FROM tabels t
                    LEFT JOIN user w ON t.user_id = w.id
                    LEFT JOIN card c ON t.card_id = c.id
                    LEFT JOIN user cu ON c.user_id = cu.id
                    WHERE t.user_id = :waiter_user_id
                    ORDER BY t.time DESC
                ";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':waiter_user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                $table_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Regular user - redirect or show unauthorized
                header("Location: ../views/dashboard/index.php"); // Redirect users
                exit;
            }
        } catch (PDOException $e) {
            $error_message = "Could not load table records. Please try again later.";
            error_log("TableController index query error: " . $e->getMessage());
        }

        // Prepare the content for the index view using a helper
        $content = $this->getIndexContent($table_records, $error_message, $user_role);
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
            $users_error = "Could not load users for table assignment.";
            error_log("TableController create - Users query error: " . $e->getMessage());
        }

        try {
            // Fetch active cards
            $card_query = "SELECT id, user_id, balance_credits, total_credits, c_status FROM card WHERE c_status = 'Active' ORDER BY user_id, id";
            $card_stmt = $this->db->prepare($card_query);
            $card_stmt->execute();
            $cards = $card_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $cards_error = "Could not load active cards.";
            error_log("TableController create - Cards query error: " . $e->getMessage());
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
                $new_table_data = [
                    't_qr' => $t_qr,
                    't_name' => $t_name,
                    't_s_w' => $t_s_w,
                    'time' => $time, // Use provided or current time
                    'transaction_mode' => $transaction_mode,
                    'user_id' => $user_id_assigned, // The user (waiter/customer) assigned to this record
                    'card_id' => $card_id, // The card used for this record
                    'menu_ordered' => $menu_ordered // The list of items ordered
                ];

                // Attempt to create the table record using the model
                $new_table_id = $this->tableModel->create($new_table_data);

                if ($new_table_id) {
                    $success_message = "Table record (order/assignment) created successfully! Record ID: $new_table_id";
                    // Optionally, redirect to the table records list page after successful creation
                    // header("Location: index.php");
                    // exit;
                } else {
                    $error_message = "Failed to create table record. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }

        // Load the create table record view
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

        $table_id_to_edit = $_GET['id'] ?? null;
        if (!$table_id_to_edit) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $table_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific table record's details
        try {
             $table_details = $this->tableModel->findById($table_id_to_edit);

             if (!$table_details) {
                 $error_message = "Table record not found.";
             } elseif (($user_role === 'waiter' && $table_details['user_id'] != $_SESSION['user_id'])) {
                  // Waiters can only edit records assigned to them
                  $error_message = "Access denied. You can only edit records assigned to you.";
                  $table_details = null; // Clear details to prevent editing
             }
        } catch (PDOException $e) {
            $error_message = "Could not load table record details. Please try again later.";
            error_log("TableController edit fetch query error: " . $e->getMessage());
        }

        // Process form submission if table record details were found and user has permission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_details) {
            $t_qr = (int)($_POST['t_qr'] ?? 0);
            $t_name = $_POST['t_name'] ?? 'A';
            $t_s_w = (int)($_POST['t_s_w'] ?? 0);
            $time_input = trim($_POST['time'] ?? '');
            $time = !empty($time_input) ? $time_input : $table_details['time']; // Use provided time or keep existing
            $transaction_mode = $_POST['transaction_mode'] ?? 'Card';
            $user_id_assigned = $_POST['user_id'] ?? $table_details['user_id']; // Default to existing
            $card_id = $_POST['card_id'] ?? $table_details['card_id']; // Default to existing
            $menu_ordered = trim($_POST['menu_ordered'] ?? $table_details['menu_ordered']); // Default to existing

            // Basic validation (similar to create)
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
                $update_table_data = [
                    'id' => $table_id_to_edit,
                    't_qr' => $t_qr,
                    't_name' => $t_name,
                    't_s_w' => $t_s_w,
                    'time' => $time, // Use provided or existing time
                    'transaction_mode' => $transaction_mode,
                    'user_id' => $user_id_assigned, // Updated user (waiter/customer) assigned
                    'card_id' => $card_id, // Updated card used
                    'menu_ordered' => $menu_ordered // Updated list of items ordered
                ];

                // Attempt to update the table record using the model
                if ($this->tableModel->update($update_table_data)) {
                    $success_message = "Table record updated successfully!";
                    // Optionally, refetch the table record details to show updated info
                     $table_details = $this->tableModel->findById($table_id_to_edit); // Update the local variable
                } else {
                    $error_message = "Failed to update table record. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }


        // Load the edit table record view
        $content = $this->getEditContent($table_details, $success_message, $error_message);
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

        $table_id_to_delete = $_GET['id'] ?? null;
        if (!$table_id_to_delete) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $table_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific table record's details for confirmation
        try {
             $table_details = $this->tableModel->findById($table_id_to_delete);

             if (!$table_details) {
                 $error_message = "Table record not found.";
             } elseif (($user_role === 'waiter' && $table_details['user_id'] != $_SESSION['user_id'])) {
                  // Waiters can only delete records assigned to them
                  $error_message = "Access denied. You can only delete records assigned to you.";
                  $table_details = null; // Clear details to prevent deletion
             }
        } catch (PDOException $e) {
            $error_message = "Could not load table record details for deletion. Please try again later.";
            error_log("TableController delete fetch query error: " . $e->getMessage());
        }

        // Process deletion if confirmed via POST and user has permission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_details && isset($_POST['confirm_delete'])) {
            // Attempt to delete the table record using the model
            if ($this->tableModel->delete($table_id_to_delete)) {
                $success_message = "Table record deleted successfully!";
                // Optionally, redirect to the table records list page after successful deletion
                // header("Location: index.php?success=deleted");
                // exit;
            } else {
                // This could mean the row didn't exist or a constraint prevented deletion
                $error_message = "Failed to delete table record. It might not exist or be linked to other records that prevent deletion.";
            }
        }


        // Load the delete table record confirmation view
        $content = $this->getDeleteContent($table_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    // --- Helper Methods to Generate View Content ---

    private function getIndexContent($table_records, $error_message = '', $user_role) {
        $html = "<h2>Manage Table Records (Orders/Assignments)</h2><p>View " . ($user_role === 'admin' ? 'all' : 'your assigned') . " table records and orders.</p>";

        if ($error_message) {
            $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
        } else {
            // Add a button to create a new table record (admin/waiter)
            if ($user_role === 'admin' || $user_role === 'waiter') {
                $html .= "
                <div class='mb-3'>
                    <a href='TableController.php?action=create' class='btn btn-success'>Record New Order/Assignment</a>
                </div>
                ";
            }

            // Check if table records exist
            if (!empty($table_records)) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Table Name</th>
                                <th>QR Code ID</th>
                                <th>Assigned User (Waiter/Customer)</th>
                                <th>Card Used</th>
                                <th>Order Time</th>
                                <th>Transaction Mode</th>
                                <th>Menu Ordered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";

                foreach ($table_records as $record) {
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
                                <td>" . $user_link . "</td>
                                <td>" . $card_link . "</td>
                                <td>" . htmlspecialchars($record['time']) . "</td>
                                <td>" . htmlspecialchars($record['transaction_mode']) . "</td>
                                <td>" . $menu_display . "</td>
                                <td>
                                    <a href='TableController.php?action=edit&id=" . $record['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                    <a href='TableController.php?action=delete&id=" . $record['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete table record ID " . $record['id'] . "? This action cannot be undone.\")'>Delete</a>
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
                $html .= "<p>No table records found.</p>";
            }
        }
        return $html;
    }

    private function getCreateContent($users, $cards, $users_error, $cards_error, $success_message = '', $error_message = '') {
        $html = "<h2>Record New Table Order/Assignment</h2><p>Create a new record for a table order or assignment.</p>";

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
                $html .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $html .= "
                <form method='post' action='../controllers/TableController.php?action=create'>
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
                        <button type='submit' class='btn btn-primary'>Record Table Order/Assignment</button>
                    </div>
                </form>
                <div class='mt-3'>
                    <a href='TableController.php?action=index' class='btn btn-secondary'>Back to Table Records List</a>
                </div>
            ";
        }

        return $html;
    }

     private function getEditContent($table_details, $success_message = '', $error_message = '') {
        $html = "<h2>Edit Table Record</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='TableController.php?action=index' class='btn btn-secondary'>Back to Table Records List</a>";
        } elseif ($table_details) {
            $user_name_display = $table_details['waiter_first_name'] ? htmlspecialchars($table_details['waiter_first_name'] . ' ' . $table_details['waiter_last_name']) : htmlspecialchars($table_details['waiter_username']);
            $card_link = "<a href='../views/cards/view.php?id=" . $table_details['card_id'] . "'>Card #" . $table_details['card_id'] . "</a>";
            $customer_name_display = $table_details['customer_first_name'] ? htmlspecialchars($table_details['customer_first_name'] . ' ' . $table_details['customer_last_name']) : htmlspecialchars($table_details['customer_username']);
            $customer_link = $table_details['card_id'] ? "<a href='../views/users/view.php?id=" . $table_details['card_id'] . "'>" . $customer_name_display . " (via Card)</a>" : 'N/A (Customer via card)';

            $html .= "<p>Editing details for Table Record ID: <strong>" . htmlspecialchars($table_details['id']) . "</strong>, Table: <strong>" . htmlspecialchars($table_details['t_name']) . "</strong>, Assigned User: <strong>" . $user_name_display . "</strong>, Customer: <strong>" . $customer_link . "</strong></p>";

            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $mode_selected_cash = $table_details['transaction_mode'] === 'Cash' ? 'selected' : '';
            $mode_selected_card = $table_details['transaction_mode'] === 'Card' ? 'selected' : '';
            $mode_selected_upi = $table_details['transaction_mode'] === 'UPI' ? 'selected' : '';

            $html .= "
            <form method='post' action='../controllers/TableController.php?action=edit&id=" . $table_details['id'] . "'>
                <div class='mb-3'>
                    <label for='table_record_id_display' class='form-label'>Table Record ID (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='table_record_id_display' value='" . htmlspecialchars($table_details['id']) . "' readonly>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='t_name' class='form-label'>Table Name:</label>
                        <select class='form-select' id='t_name' name='t_name' required>
                            <option value='A' " . ($table_details['t_name'] === 'A' ? 'selected' : '') . ">Table A</option>
                            <option value='B' " . ($table_details['t_name'] === 'B' ? 'selected' : '') . ">Table B</option>
                            <option value='C' " . ($table_details['t_name'] === 'C' ? 'selected' : '') . ">Table C</option>
                            <option value='D' " . ($table_details['t_name'] === 'D' ? 'selected' : '') . ">Table D</option>
                            <option value='E' " . ($table_details['t_name'] === 'E' ? 'selected' : '') . ">Table E</option>
                            <option value='F' " . ($table_details['t_name'] === 'F' ? 'selected' : '') . ">Table F</option>
                            <option value='G' " . ($table_details['t_name'] === 'G' ? 'selected' : '') . ">Table G</option>
                            <option value='H' " . ($table_details['t_name'] === 'H' ? 'selected' : '') . ">Table H</option>
                            <option value='I' " . ($table_details['t_name'] === 'I' ? 'selected' : '') . ">Table I</option>
                            <option value='J' " . ($table_details['t_name'] === 'J' ? 'selected' : '') . ">Table J</option>
                        </select>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='t_qr' class='form-label'>QR Code ID:</label>
                        <input type='number' class='form-control' id='t_qr' name='t_qr' value='" . htmlspecialchars($table_details['t_qr']) . "' min='0' required>
                    </div>
                </div>
                 <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='t_s_w' class='form-label'>Seat/Switch Number:</label>
                        <input type='number' class='form-control' id='t_s_w' name='t_s_w' value='" . htmlspecialchars($table_details['t_s_w']) . "' min='0'>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='time' class='form-label'>Order Time:</label>
                        <input type='datetime-local' class='form-control' id='time' name='time' value='" . (new DateTime($table_details['time']))->format('Y-m-d\TH:i:s') . "' required>
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
                        <label for='user_id' class='form-label'>Assigned User (Waiter/Customer):</label>
                        <input type='text' class='form-control-plaintext' id='user_id_display' value='" . $user_name_display . "' readonly>
                        <input type='hidden' name='user_id' value='" . htmlspecialchars($table_details['user_id']) . "'>
                        <small class='form-text text-muted'>Changing the assigned user might require specific permissions.</small>
                    </div>
                </div>
                <div class='row mb-3'>
                    <div class='col-md-6'>
                        <label for='card_id' class='form-label'>Card Used:</label>
                        <input type='text' class='form-control-plaintext' id='card_id_display' value='" . $card_link . "' readonly>
                        <input type='hidden' name='card_id' value='" . htmlspecialchars($table_details['card_id']) . "'>
                        <small class='form-text text-muted'>Changing the card might require specific permissions.</small>
                    </div>
                </div>

                <div class='mb-3'>
                    <h5>Menu Ordered:</h5>
                    <textarea class='form-control' id='menu_ordered' name='menu_ordered' rows='4' required>" . htmlspecialchars($table_details['menu_ordered']) . "</textarea>
                    <small class='form-text text-muted'>Update the items ordered.</small>
                </div>

                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Update Table Record</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='TableController.php?action=index' class='btn btn-secondary'>Back to Table Records List</a>
                <!-- View details link -->
                <a href='TableController.php?action=view&id=" . $table_details['id'] . "' class='btn btn-info'>View Record Details</a>
            </div>
            ";
        } else {
            // This case handles when the table record ID was provided but the record wasn't found or access denied (error_message is set above)
            $html .= "<p>Unable to load table record information.</p>";
            $html .= "<a href='TableController.php?action=index' class='btn btn-secondary'>Back to Table Records List</a>";
        }

        return $html;
    }

    private function getDeleteContent($table_details, $success_message = '', $error_message = '') {
        $html = "<h2>Delete Table Record</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='TableController.php?action=index' class='btn btn-secondary'>Back to Table Records List</a>";
        } elseif ($table_details) {
            if ($success_message) {
                $html .= "<div class='alert alert-success' role='alert'>" . htmlspecialchars($success_message) . "</div>";
                $html .= "<a href='TableController.php?action=index' class='btn btn-secondary'>Back to Table Records List</a>";
            } else {
                $user_name_display = $table_details['waiter_first_name'] ? htmlspecialchars($table_details['waiter_first_name'] . ' ' . $table_details['waiter_last_name']) : htmlspecialchars($table_details['waiter_username']);
                $customer_name_display = $table_details['customer_first_name'] ? htmlspecialchars($table_details['customer_first_name'] . ' ' . $table_details['customer_last_name']) : htmlspecialchars($table_details['customer_username']);
                $customer_link = $table_details['card_id'] ? "<a href='../views/users/view.php?id=" . $table_details['card_id'] . "'>" . $customer_name_display . " (via Card)</a>" : 'N/A (Customer via card)';

                $html .= "
                <div class='alert alert-warning' role='alert'>
                    <h5>Confirm Deletion</h5>
                    <p>Are you sure you want to delete Table Record ID '<strong>" . $table_details['id'] . "</strong>'?</p>
                    <p><strong>Table:</strong> " . htmlspecialchars($table_details['t_name']) . "</p>
                    <p><strong>Assigned User:</strong> " . $user_name_display . "</p>
                    <p><strong>Customer:</strong> " . $customer_link . "</p>
                    <p><strong>Order Time:</strong> " . htmlspecialchars($table_details['time']) . "</p>
                    <p><strong>Menu Ordered:</strong> " . nl2br(htmlspecialchars($table_details['menu_ordered'])) . "</p>
                    <p><strong>This action cannot be undone and may affect related records.</strong></p>
                </div>
                <form method='post' action='../controllers/TableController.php?action=delete&id=" . $table_details['id'] . "'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($table_details['id']) . "'>
                    <input type='hidden' name='confirm_delete' value='1'>
                    <button type='submit' class='btn btn-danger'>Yes, Delete Table Record</button>
                    <a href='TableController.php?action=index' class='btn btn-secondary'>Cancel</a>
                </form>
                ";
            }
        } else {
            // This case handles when the table record ID was provided but the record wasn't found or access denied (error_message is set above)
            $html .= "<p>Unable to load table record information for deletion.</p>";
            $html .= "<a href='TableController.php?action=index' class='btn btn-secondary'>Back to Table Records List</a>";
        }

        return $html;
    }

}

// --- Routing ---
$action = $_GET['action'] ?? '';

$tableController = new TableController();

// Ensure user is logged in before processing actions
requireLogin(); // This check happens *after* controller instantiation so helper functions can use models

// Call the appropriate method based on the action
switch ($action) {
    case 'index':
        $tableController->index();
        break;
    case 'create':
        $tableController->create();
        break;
    case 'edit':
        $tableController->edit();
        break;
    case 'delete':
        $tableController->delete();
        break;
    default:
        // Default to index if action is invalid or not provided
        $tableController->index();
        break;
}

?>