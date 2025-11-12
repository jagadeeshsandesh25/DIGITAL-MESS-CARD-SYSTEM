<?php
// controllers/UserDetailsController.php

require_once '../config/database.php'; // Adjust path
require_once '../models/UserDetails.php'; // Adjust path
require_once '../models/User.php'; // Adjust path - Needed for user context
require_once '../models/Card.php'; // Adjust path - Needed for card context
require_once '../models/Transaction.php'; // Adjust path - Needed for transaction context
require_once '../models/Recharge.php'; // Adjust path - Needed for recharge context
require_once '../includes/auth.php'; // Adjust path - For permission checks

class UserDetailsController {

    private $database;
    private $db;
    private $userDetailsModel;
    private $userModel;
    private $cardModel;
    private $transactionModel;
    private $rechargeModel;

    public function __construct() {
        $this->database = new Database();
        $this->db = $this->database->getConnection();
        $this->userDetailsModel = new UserDetails($this->db);
        $this->userModel = new User($this->db); // Pass the same DB connection
        $this->cardModel = new Card($this->db); // Pass the same DB connection
        $this->transactionModel = new Transaction($this->db); // Pass the same DB connection
        $this->rechargeModel = new Recharge($this->db); // Pass the same DB connection
    }

    public function index() {
        // Check if user is logged in and is an admin (due to sensitive nature of details)
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php"); // Redirect non-admins
            exit;
        }

        $user_details_records = [];
        $error_message = '';

        try {
            // Fetch all user details records from the model
            $user_details_records = $this->userDetailsModel->findAll(); // Default order by created_at DESC
        } catch (PDOException $e) {
            $error_message = "Could not load user details records. Please try again later.";
            error_log("UserDetailsController index query error: " . $e->getMessage());
        }

        // Prepare the content for the index view using a helper
        $content = $this->getIndexContent($user_details_records, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function create() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        // Fetch list of users, cards, transactions, and recharges for the form
        // Note: Creating a record manually is unusual. This is for demonstration.
        $users = [];
        $cards = [];
        $transactions = [];
        $recharges = [];
        $users_error = '';
        $cards_error = '';
        $transactions_error = '';
        $recharges_error = '';

        try {
            $user_query = "SELECT id, first_name, last_name, username FROM user ORDER BY username";
            $user_stmt = $this->db->prepare($user_query);
            $user_stmt->execute();
            $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $users_error = "Could not load users.";
            error_log("UserDetailsController create - Users query error: " . $e->getMessage());
        }

        try {
            $card_query = "SELECT id, user_id, balance_credits, total_credits, c_status FROM card ORDER BY user_id, id";
            $card_stmt = $this->db->prepare($card_query);
            $card_stmt->execute();
            $cards = $card_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $cards_error = "Could not load cards.";
            error_log("UserDetailsController create - Cards query error: " . $e->getMessage());
        }

        try {
            $transaction_query = "SELECT id, user_id, card_id, t_time, t_type FROM transactions ORDER BY t_time DESC LIMIT 50"; // Limit for performance
            $transaction_stmt = $this->db->prepare($transaction_query);
            $transaction_stmt->execute();
            $transactions = $transaction_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $transactions_error = "Could not load recent transactions.";
            error_log("UserDetailsController create - Transactions query error: " . $e->getMessage());
        }

        try {
            $recharge_query = "SELECT id, user_id, card_id, r_time, r_type FROM recharge ORDER BY r_time DESC LIMIT 50"; // Limit for performance
            $recharge_stmt = $this->db->prepare($recharge_query);
            $recharge_stmt->execute();
            $recharges = $recharge_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $recharges_error = "Could not load recent recharges.";
            error_log("UserDetailsController create - Recharges query error: " . $e->getMessage());
        }

        $success_message = '';
        $error_message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($users_error) && empty($cards_error) && empty($transactions_error) && empty($recharges_error)) {
            // Note: Passwords should be hashed BEFORE reaching the controller/model.
            // This is a simplified example. In reality, you'd get the hash from the user object or a password service.
            $user_id = $_POST['user_id'] ?? null;
            $card_id = $_POST['card_id'] ?? null;
            $transaction_id = $_POST['transaction_id'] ?? null;
            $recharge_id = $_POST['recharge_id'] ?? null;
            $present_pswd = $_POST['present_pswd'] ?? ''; // Pre-hashed password
            $previous_pswd = $_POST['previous_pswd'] ?? ''; // Pre-hashed password
            $username = trim($_POST['username'] ?? '');
            $contact_details = trim($_POST['contact_details'] ?? '');
            $created_at = date('Y-m-d H:i:s'); // Use current time
            $updated_at = date('Y-m-d H:i:s'); // Use current time

            // Basic validation (simplified)
            $errors = [];
            if (empty($user_id) || $user_id == 0) {
                $errors[] = "Please select a user.";
            }
            if (empty($card_id) || $card_id == 0) {
                $errors[] = "Please select a card.";
            }
            if (empty($transaction_id) || $transaction_id == 0) {
                $errors[] = "Please select a transaction.";
            }
            if (empty($recharge_id) || $recharge_id == 0) {
                $errors[] = "Please select a recharge.";
            }
            if (empty($username)) {
                $errors[] = "Username cannot be empty.";
            }
            if (empty($contact_details)) {
                $errors[] = "Contact details cannot be empty.";
            }
            // In a real scenario, you'd validate password hashes (length, format) and ensure they are indeed hashes.

            if (empty($errors)) {
                // Prepare data array for the model
                $new_user_details_data = [
                    'user_id' => $user_id,
                    'card_id' => $card_id,
                    'transaction_id' => $transaction_id,
                    'recharge_id' => $recharge_id,
                    'present_pswd' => $present_pswd, // Pre-hashed
                    'previous_pswd' => $previous_pswd, // Pre-hashed
                    'username' => $username,
                    'contact_details' => $contact_details,
                    'created_at' => $created_at,
                    'updated_at' => $updated_at
                ];

                // Attempt to create the user details record using the model
                $new_record_id = $this->userDetailsModel->create($new_user_details_data);

                if ($new_record_id) {
                    $success_message = "User details record created successfully! Record ID: $new_record_id";
                    // Optionally, redirect to the user details list page after successful creation
                    // header("Location: index.php");
                    // exit;
                } else {
                    $error_message = "Failed to create user details record. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors); // Join multiple errors with line breaks
            }
        }

        // Load the create user details record view
        $content = $this->getCreateContent($users, $cards, $transactions, $recharges, $users_error, $cards_error, $transactions_error, $recharges_error, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function edit() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $record_id_to_edit = $_GET['id'] ?? null;
        if (!$record_id_to_edit) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $record_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific user details record's information
        try {
             $record_details = $this->userDetailsModel->findById($record_id_to_edit);

             if (!$record_details) {
                 $error_message = "User details record not found.";
             }
        } catch (PDOException $e) {
            $error_message = "Could not load user details record. Please try again later.";
            error_log("UserDetailsController edit fetch query error: " . $e->getMessage());
        }

        // Process form submission if record details were found
        // Editing password hashes or linked IDs is highly discouraged. This is just a placeholder for non-sensitive fields.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $record_details) {
            $username = trim($_POST['username'] ?? $record_details['udt_username']); // Default to existing
            $contact_details = trim($_POST['contact_details'] ?? $record_details['contact_details']); // Default to existing
            $updated_at = date('Y-m-d H:i:s'); // Update the timestamp

            // Basic validation (simplified)
            $errors = [];
            if (empty($username)) {
                $errors[] = "Username cannot be empty.";
            }
            if (empty($contact_details)) {
                $errors[] = "Contact details cannot be empty.";
            }

            if (empty($errors)) {
                // Prepare data array for the model, including the ID
                $update_record_data = [
                    'id' => $record_id_to_edit,
                    'username' => $username,
                    'contact_details' => $contact_details,
                    'updated_at' => $updated_at // Update the timestamp
                ];

                // Attempt to update the user details record using the model
                // WARNING: Only updating non-sensitive fields.
                if ($this->userDetailsModel->update($update_record_data)) {
                    $success_message = "User details record updated successfully!";
                    // Optionally, refetch the record details to show updated info
                     $record_details = $this->userDetailsModel->findById($record_id_to_edit); // Update the local variable
                } else {
                    $error_message = "Failed to update user details record. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }


        // Load the edit user details record view
        $content = $this->getEditContent($record_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function delete() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $record_id_to_delete = $_GET['id'] ?? null;
        if (!$record_id_to_delete) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $record_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific user details record's information for confirmation
        try {
             $record_details = $this->userDetailsModel->findById($record_id_to_delete);

             if (!$record_details) {
                 $error_message = "User details record not found.";
             }
        } catch (PDOException $e) {
            $error_message = "Could not load user details record for deletion. Please try again later.";
            error_log("UserDetailsController delete fetch query error: " . $e->getMessage());
        }

        // Process deletion if confirmed via POST
        // Deleting historical records is highly discouraged.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $record_details && isset($_POST['confirm_delete'])) {
            // Attempt to delete the user details record using the model
            // WARNING: This is likely to fail due to foreign key constraints or policy restrictions.
            if ($this->userDetailsModel->delete($record_id_to_delete)) {
                $success_message = "User details record deleted successfully!";
                // Optionally, redirect to the user details list page after successful deletion
                // header("Location: index.php?success=deleted");
                // exit;
            } else {
                // This could mean the row didn't exist or a constraint prevented deletion
                $error_message = "Failed to delete user details record. It might not exist or be linked to other records that prevent deletion (e.g., audit trail requirements).";
            }
        }


        // Load the delete user details record confirmation view
        $content = $this->getDeleteContent($record_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    // --- Helper Methods to Generate View Content ---

    private function getIndexContent($records, $error_message = '') {
        $html = "<h2>Manage User Details Records</h2><p>View and manage user-specific detail records (e.g., password history, contact changes). <strong>Access restricted to admins.</strong></p>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
        } else {
            // Add a button to create a new user details record (might be rare, usually auto-generated)
            // $html .= "
            // <div class='mb-3'>
            //     <a href='UserDetailsController.php?action=create' class='btn btn-success'>Add New User Details Record</a>
            // </div>
            // ";

            // Check if records exist
            if (!empty($records)) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Card ID</th>
                                <th>Card Status</th>
                                <th>Transaction ID</th>
                                <th>Transaction Time</th>
                                <th>Transaction Type</th>
                                <th>Recharge ID</th>
                                <th>Recharge Time</th>
                                <th>Recharge Type</th>
                                <th>Username (in record)</th>
                                <th>Contact Details</th>
                                <th>Created At</th>
                                <th>Updated At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";

                foreach ($records as $record) {
                    $user_name_display = $record['user_first_name'] ? htmlspecialchars($record['user_first_name'] . ' ' . $record['user_last_name']) : htmlspecialchars($record['user_username']);
                    $user_link = $record['user_id'] ? "<a href='UserController.php?action=view&id=" . $record['user_id'] . "'>" . $user_name_display . "</a>" : 'N/A';
                    $card_link = $record['card_id'] ? "<a href='CardController.php?action=view&id=" . $record['card_id'] . "'>Card #" . $record['card_id'] . "</a>" : 'N/A';
                    $transaction_link = $record['transaction_id'] ? "<a href='TransactionController.php?action=view&id=" . $record['transaction_id'] . "'>#" . $record['transaction_id'] . "</a>" : 'N/A';
                    $recharge_link = $record['recharge_id'] ? "<a href='RechargeController.php?action=view&id=" . $record['recharge_id'] . "'>#" . $record['recharge_id'] . "</a>" : 'N/A';

                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($record['id']) . "</td>
                                <td>" . $user_link . "</td>
                                <td>" . $card_link . "</td>
                                <td>" . htmlspecialchars($record['card_status']) . "</td>
                                <td>" . $transaction_link . "</td>
                                <td>" . htmlspecialchars($record['transaction_time']) . "</td>
                                <td>" . htmlspecialchars($record['transaction_type']) . "</td>
                                <td>" . $recharge_link . "</td>
                                <td>" . htmlspecialchars($record['recharge_time']) . "</td>
                                <td>" . htmlspecialchars($record['recharge_type']) . "</td>
                                <td>" . htmlspecialchars($record['udt_username']) . "</td>
                                <td>" . htmlspecialchars($record['contact_details']) . "</td>
                                <td>" . htmlspecialchars($record['created_at']) . "</td>
                                <td>" . htmlspecialchars($record['updated_at']) . "</td>
                                <td>
                                    <a href='UserDetailsController.php?action=view&id=" . $record['id'] . "' class='btn btn-sm btn-info'>View</a>
                                    <a href='UserDetailsController.php?action=edit&id=" . $record['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                    <a href='UserDetailsController.php?action=delete&id=" . $record['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete user details record ID " . $record['id'] . "? This action cannot be undone and may affect audit trails.\")'>Delete</a>
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
                $html .= "<p>No user details records found.</p>";
            }
        }
        return $html;
    }

    private function getCreateContent($users, $cards, $transactions, $recharges, $users_error, $cards_error, $transactions_error, $recharges_error, $success_message = '', $error_message = '') {
        $html = "<h2>Create New User Details Record</h2><p><strong>Warning:</strong> Manually creating records in this table is unusual and should be done with extreme care. It typically stores snapshots linked to user actions.</p>";

        if ($users_error || $cards_error || $transactions_error || $recharges_error) {
            $html .= "<div class='alert alert-danger' role='alert'>";
            if ($users_error) $html .= htmlspecialchars($users_error) . "<br>";
            if ($cards_error) $html .= htmlspecialchars($cards_error) . "<br>";
            if ($transactions_error) $html .= htmlspecialchars($transactions_error) . "<br>";
            if ($recharges_error) $html .= htmlspecialchars($recharges_error);
            $html .= "</div>";
        } else {
            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }
            if ($error_message) {
                $html .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>"; // Don't escape HTML for error messages as they might contain <br>
            }

            $html .= "
                <form method='post' action='../controllers/UserDetailsController.php?action=create'>
                    <div class='row mb-3'>
                        <div class='col-md-6'>
                            <label for='user_id' class='form-label'>Select User:</label>
                            <select class='form-select' id='user_id' name='user_id' required>
                                <option value='0'>Choose User...</option>
            ";

            foreach ($users as $user) {
                 $selected_attr = ($_POST['user_id'] ?? null) == $user['id'] ? 'selected' : '';
                 $html .= "<option value='" . $user['id'] . "' " . $selected_attr . ">" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')') . "</option>";
            }

            $html .= "
                            </select>
                        </div>
                        <div class='col-md-6'>
                            <label for='card_id' class='form-label'>Select Card:</label>
                            <select class='form-select' id='card_id' name='card_id' required>
                                <option value='0'>Choose Card...</option>
            ";

            foreach ($cards as $card) {
                 $selected_attr = ($_POST['card_id'] ?? null) == $card['id'] ? 'selected' : '';
                 $html .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (User ID: " . $card['user_id'] . ", Balance: ₹" . number_format($card['balance_credits'], 2) . ", Total: ₹" . number_format($card['total_credits'], 2) . ", Status: " . $card['c_status'] . ")</option>";
            }

            $html .= "
                            </select>
                        </div>
                    </div>
                    <div class='row mb-3'>
                        <div class='col-md-6'>
                            <label for='transaction_id' class='form-label'>Select Transaction:</label>
                            <select class='form-select' id='transaction_id' name='transaction_id' required>
                                <option value='0'>Choose Transaction...</option>
            ";

            foreach ($transactions as $transaction) {
                 $selected_attr = ($_POST['transaction_id'] ?? null) == $transaction['id'] ? 'selected' : '';
                 $html .= "<option value='" . $transaction['id'] . "' " . $selected_attr . ">#" . $transaction['id'] . " (User: " . $transaction['user_id'] . ", Card: " . $transaction['card_id'] . ", Time: " . $transaction['t_time'] . ", Type: " . $transaction['t_type'] . ")</option>";
            }

            $html .= "
                            </select>
                        </div>
                        <div class='col-md-6'>
                            <label for='recharge_id' class='form-label'>Select Recharge:</label>
                            <select class='form-select' id='recharge_id' name='recharge_id' required>
                                <option value='0'>Choose Recharge...</option>
            ";

            foreach ($recharges as $recharge) {
                 $selected_attr = ($_POST['recharge_id'] ?? null) == $recharge['id'] ? 'selected' : '';
                 $html .= "<option value='" . $recharge['id'] . "' " . $selected_attr . ">#" . $recharge['id'] . " (User: " . $recharge['user_id'] . ", Card: " . $recharge['card_id'] . ", Time: " . $recharge['r_time'] . ", Type: " . $recharge['r_type'] . ")</option>";
            }

            $html .= "
                            </select>
                        </div>
                    </div>
                    <div class='row mb-3'>
                        <div class='col-md-6'>
                            <label for='present_pswd' class='form-label'>Present Password Hash:</label>
                            <input type='text' class='form-control' id='present_pswd' name='present_pswd' value='" . htmlspecialchars($_POST['present_pswd'] ?? '') . "' required>
                            <small class='form-text text-muted'>Must be a pre-hashed password string.</small>
                        </div>
                        <div class='col-md-6'>
                            <label for='previous_pswd' class='form-label'>Previous Password Hash:</label>
                            <input type='text' class='form-control' id='previous_pswd' name='previous_pswd' value='" . htmlspecialchars($_POST['previous_pswd'] ?? '') . "' required>
                            <small class='form-text text-muted'>Must be a pre-hashed password string.</small>
                        </div>
                    </div>
                    <div class='row mb-3'>
                        <div class='col-md-6'>
                            <label for='username' class='form-label'>Username (Stored in this Record):</label>
                            <input type='text' class='form-control' id='username' name='username' value='" . htmlspecialchars($_POST['username'] ?? '') . "' required>
                        </div>
                        <div class='col-md-6'>
                            <label for='contact_details' class='form-label'>Contact Details:</label>
                            <input type='text' class='form-control' id='contact_details' name='contact_details' value='" . htmlspecialchars($_POST['contact_details'] ?? '') . "' required>
                        </div>
                    </div>
                    <div class='d-grid'>
                        <button type='submit' class='btn btn-primary'>Create User Details Record</button>
                    </div>
                </form>
                <div class='mt-3'>
                    <a href='UserDetailsController.php?action=index' class='btn btn-secondary'>Back to User Details List</a>
                </div>
            ";
        }

        return $html;
    }

     private function getEditContent($record_details, $success_message = '', $error_message = '') {
        $html = "<h2>Edit User Details Record</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='UserDetailsController.php?action=index' class='btn btn-secondary'>Back to User Details List</a>";
        } elseif ($record_details) {
            $user_name_display = $record_details['user_first_name'] ? htmlspecialchars($record_details['user_first_name'] . ' ' . $record_details['user_last_name']) : htmlspecialchars($record_details['user_username']);
            $user_link = "<a href='UserController.php?action=view&id=" . $record_details['user_id'] . "'>" . $user_name_display . "</a>";
            $card_link = "<a href='CardController.php?action=view&id=" . $record_details['card_id'] . "'>Card #" . $record_details['card_id'] . "</a>";
            $transaction_link = "<a href='TransactionController.php?action=view&id=" . $record_details['transaction_id'] . "'>#" . $record_details['transaction_id'] . "</a>";
            $recharge_link = "<a href='RechargeController.php?action=view&id=" . $record_details['recharge_id'] . "'>#" . $record_details['recharge_id'] . "</a>";

            $html .= "<p>Editing details for User Details Record ID: <strong>" . htmlspecialchars($record_details['id']) . "</strong>, User: <strong>" . $user_link . "</strong></p>";

            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $html .= "
            <div class='alert alert-warning' role='alert'>
                <strong>Warning:</strong> Be extremely careful when editing. This table stores snapshots.
                Editing password hashes or linked IDs (<code>user_id</code>, <code>card_id</code>, <code>transaction_id</code>, <code>recharge_id</code>) is highly discouraged and can break data integrity.
                Only non-sensitive fields like <em>Username</em> and <em>Contact Details</em> are editable here.
            </div>

            <form method='post' action='../controllers/UserDetailsController.php?action=edit&id=" . $record_details['id'] . "'>
                <div class='mb-3'>
                    <label for='record_id_display' class='form-label'>Record ID (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='record_id_display' value='" . htmlspecialchars($record_details['id']) . "' readonly>
                </div>
                <div class='row mb-3'>
                    <div class='col-md-6'>
                        <label for='user_info_display' class='form-label'>Associated User (Read-only):</label>
                        <input type='text' class='form-control-plaintext' id='user_info_display' value='" . $user_name_display . "' readonly>
                    </div>
                    <div class='col-md-6'>
                        <label for='card_info_display' class='form-label'>Associated Card (Read-only):</label>
                        <input type='text' class='form-control-plaintext' id='card_info_display' value='" . $card_link . "' readonly>
                    </div>
                </div>
                <div class='row mb-3'>
                    <div class='col-md-6'>
                        <label for='transaction_info_display' class='form-label'>Associated Transaction (Read-only):</label>
                        <input type='text' class='form-control-plaintext' id='transaction_info_display' value='" . $transaction_link . "' readonly>
                    </div>
                    <div class='col-md-6'>
                        <label for='recharge_info_display' class='form-label'>Associated Recharge (Read-only):</label>
                        <input type='text' class='form-control-plaintext' id='recharge_info_display' value='" . $recharge_link . "' readonly>
                    </div>
                </div>
                <div class='row mb-3'>
                    <div class='col-md-6'>
                        <label for='present_pswd_display' class='form-label'>Present Password Hash (Read-only):</label>
                        <input type='text' class='form-control-plaintext' id='present_pswd_display' value='" . htmlspecialchars($record_details['present_pswd']) . "' readonly>
                        <small class='form-text text-muted'>Password hashes should not be manually edited.</small>
                    </div>
                    <div class='col-md-6'>
                        <label for='previous_pswd_display' class='form-label'>Previous Password Hash (Read-only):</label>
                        <input type='text' class='form-control-plaintext' id='previous_pswd_display' value='" . htmlspecialchars($record_details['previous_pswd']) . "' readonly>
                        <small class='form-text text-muted'>Password hashes should not be manually edited.</small>
                    </div>
                </div>
                <div class='row mb-3'>
                    <div class='col-md-6'>
                        <label for='username' class='form-label'>Username (Stored in this Record):</label>
                        <input type='text' class='form-control' id='username' name='username' value='" . htmlspecialchars($record_details['udt_username']) . "' required>
                    </div>
                    <div class='col-md-6'>
                        <label for='contact_details' class='form-label'>Contact Details:</label>
                        <input type='text' class='form-control' id='contact_details' name='contact_details' value='" . htmlspecialchars($record_details['contact_details']) . "' required>
                    </div>
                </div>
                <div class='mb-3'>
                    <label for='created_at_display' class='form-label'>Record Created At (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='created_at_display' value='" . htmlspecialchars($record_details['created_at']) . "' readonly>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Update User Details Record</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='UserDetailsController.php?action=index' class='btn btn-secondary'>Back to User Details List</a>
                <a href='UserDetailsController.php?action=view&id=" . $record_details['id'] . "' class='btn btn-info'>View Record Details</a>
            </div>
            ";
        } else {
            // This case handles when the record ID was provided but the record wasn't found (error_message is set above)
            $html .= "<p>Unable to load user details record information.</p>";
            $html .= "<a href='UserDetailsController.php?action=index' class='btn btn-secondary'>Back to User Details List</a>";
        }

        return $html;
    }

    private function getDeleteContent($record_details, $success_message = '', $error_message = '') {
        $html = "<h2>Delete User Details Record</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='UserDetailsController.php?action=index' class='btn btn-secondary'>Back to User Details List</a>";
        } elseif ($record_details) {
            if ($success_message) {
                $html .= "<div class='alert alert-success' role='alert'>" . htmlspecialchars($success_message) . "</div>";
                $html .= "<a href='UserDetailsController.php?action=index' class='btn btn-secondary'>Back to User Details List</a>";
            } else {
                $user_name_display = $record_details['user_first_name'] ? htmlspecialchars($record_details['user_first_name'] . ' ' . $record_details['user_last_name']) : htmlspecialchars($record_details['user_username']);
                $user_link = "<a href='UserController.php?action=view&id=" . $record_details['user_id'] . "'>" . $user_name_display . "</a>";
                $card_link = "<a href='CardController.php?action=view&id=" . $record_details['card_id'] . "'>Card #" . $record_details['card_id'] . "</a>";
                $transaction_link = "<a href='TransactionController.php?action=view&id=" . $record_details['transaction_id'] . "'>#" . $record_details['transaction_id'] . "</a>";
                $recharge_link = "<a href='RechargeController.php?action=view&id=" . $record_details['recharge_id'] . "'>#" . $record_details['recharge_id'] . "</a>";

                $html .= "
                <div class='alert alert-warning' role='alert'>
                    <h5>Confirm Deletion</h5>
                    <p>Are you sure you want to delete User Details Record ID '<strong>" . $record_details['id'] . "</strong>'?</p>
                    <p><strong>User:</strong> " . $user_link . "</p>
                    <p><strong>Card:</strong> " . $card_link . "</p>
                    <p><strong>Transaction:</strong> " . $transaction_link . "</p>
                    <p><strong>Recharge:</strong> " . $recharge_link . "</p>
                    <p><strong>Username (in record):</strong> " . htmlspecialchars($record_details['udt_username']) . "</p>
                    <p><strong>Contact Details:</strong> " . htmlspecialchars($record_details['contact_details']) . "</p>
                    <p><strong>WARNING: This action cannot be undone and may affect audit trails or data lineage. It is highly discouraged.</strong></p>
                </div>
                <form method='post' action='../controllers/UserDetailsController.php?action=delete&id=" . $record_details['id'] . "'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($record_details['id']) . "'>
                    <input type='hidden' name='confirm_delete' value='1'>
                    <button type='submit' class='btn btn-danger'>Yes, Delete User Details Record</button>
                    <a href='UserDetailsController.php?action=index' class='btn btn-secondary'>Cancel</a>
                </form>
                ";
            }
        } else {
            // This case handles when the record ID was provided but the record wasn't found (error_message is set above)
            $html .= "<p>Unable to load user details record information for deletion.</p>";
            $html .= "<a href='UserDetailsController.php?action=index' class='btn btn-secondary'>Back to User Details List</a>";
        }

        return $html;
    }

}

// --- Routing ---
$action = $_GET['action'] ?? '';

$userDetailsController = new UserDetailsController();

// Ensure only admin can access user details management (for now)
// This check happens *after* controller instantiation so helper functions can use models
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../views/auth/login.php"); // Redirect to login if not authenticated as admin
    exit;
}

// Call the appropriate method based on the action
switch ($action) {
    case 'index':
        $userDetailsController->index();
        break;
    case 'create':
        $userDetailsController->create();
        break;
    case 'edit':
        $userDetailsController->edit();
        break;
    case 'delete':
        $userDetailsController->delete();
        break;
    default:
        // Default to index if action is invalid or not provided
        $userDetailsController->index();
        break;
}

?>