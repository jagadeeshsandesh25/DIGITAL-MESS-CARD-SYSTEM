<?php
// controllers/CardController.php

require_once '../config/database.php'; // Adjust path
require_once '../models/Card.php'; // Adjust path
require_once '../models/User.php'; // Adjust path - Needed to fetch users for card assignment
require_once '../includes/auth.php'; // Adjust path - For permission checks

class CardController {

    private $database;
    private $db;
    private $cardModel;
    private $userModel; // Need user model to fetch list of users

    public function __construct() {
        $this->database = new Database();
        $this->db = $this->database->getConnection();
        $this->cardModel = new Card($this->db);
        $this->userModel = new User($this->db); // Pass the same DB connection
    }

    public function index() {
        // Check if user is logged in and is an admin or user (users might see their own cards)
        requireLogin();
        // For now, restrict to admin only. Later, a user could see their own cards.
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php"); // Redirect non-admins
            exit;
        }

        $cards = [];
        $error_message = '';

        try {
            // Fetch all cards from the model
            $cards = $this->cardModel->findAll(); // Default order by created_at DESC
        } catch (PDOException $e) {
            $error_message = "Could not load cards. Please try again later.";
            error_log("CardController index query error: " . $e->getMessage());
        }

        // Prepare the content for the index view using a helper
        $content = $this->getIndexContent($cards, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function create() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        // Fetch list of users to link the card to
        $users = [];
        $users_error = '';
        try {
            $user_query = "SELECT id, first_name, last_name, username FROM user ORDER BY username";
            $user_stmt = $this->db->prepare($user_query);
            $user_stmt->execute();
            $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $users_error = "Could not load users for card assignment.";
            error_log("CardController create - Users query error: " . $e->getMessage());
        }

        $success_message = '';
        $error_message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($users_error)) {
            $user_id = $_POST['user_id'] ?? null;
            $status = $_POST['status'] ?? 'Inactive'; // Default to Inactive
            $balance_credits = (int)($_POST['balance_credits'] ?? 0);
            $total_credits = (int)($_POST['total_credits'] ?? 0);
            $expired_at_input = trim($_POST['expired_at'] ?? '');
            $expired_at = !empty($expired_at_input) ? $expired_at_input : null; // Can be NULL
            // recharge_details is a FK, might be set later during a recharge, or default to NULL initially if allowed by schema
            $recharge_details = null; // Or fetch a default recharge ID if applicable

            // Basic validation
            $errors = [];
            if (empty($user_id) || $user_id == 0) {
                $errors[] = "Please select a user.";
            }
            if ($balance_credits < 0) {
                $errors[] = "Balance credits cannot be negative.";
            }
            if ($total_credits < 0) {
                $errors[] = "Total credits cannot be negative.";
            }
            if ($balance_credits > $total_credits) {
                $errors[] = "Balance credits cannot exceed total credits.";
            }
            // Validate expiry date format if provided
            if (!empty($expired_at_input) && DateTime::createFromFormat('Y-m-d', $expired_at_input) === FALSE) {
                $errors[] = "Invalid expiry date format. Please use YYYY-MM-DD.";
            }

            if (empty($errors)) {
                // Prepare data array for the model
                $new_card_data = [
                    'c_status' => $status,
                    'user_id' => $user_id, // The user the card is linked to
                    'recharge_details' => $recharge_details, // Initially NULL or a default ID
                    'balance_credits' => $balance_credits,
                    'total_credits' => $total_credits,
                    'expired_at' => $expired_at // Can be NULL
                ];

                // Attempt to create the card using the model
                $new_card_id = $this->cardModel->create($new_card_data);

                if ($new_card_id) {
                    $success_message = "Card created successfully! Card ID: $new_card_id";
                    // Optionally, redirect to the cards list page after successful creation
                    // header("Location: index.php"); // Adjust path
                    // exit;
                } else {
                    $error_message = "Failed to create card. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }

        // Load the create card view
        $content = $this->getCreateContent($users, $users_error, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function edit() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $card_id_to_edit = $_GET['id'] ?? null;
        if (!$card_id_to_edit) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $card_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific card's details
        try {
             $card_details = $this->cardModel->findById($card_id_to_edit);

             if (!$card_details) {
                 $error_message = "Card not found.";
             }
        } catch (PDOException $e) {
            $error_message = "Could not load card details. Please try again later.";
            error_log("CardController edit fetch query error: " . $e->getMessage());
        }

        // Process form submission if card details were found
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $card_details) {
            $status = $_POST['status'] ?? 'Inactive';
            $balance_credits = (int)($_POST['balance_credits'] ?? 0);
            $total_credits = (int)($_POST['total_credits'] ?? 0);
            $expired_at_input = trim($_POST['expired_at'] ?? '');
            $expired_at = !empty($expired_at_input) ? $expired_at_input : null; // Can be NULL

            // Basic validation
            $errors = [];
            if ($balance_credits < 0) {
                $errors[] = "Balance credits cannot be negative.";
            }
            if ($total_credits < 0) {
                $errors[] = "Total credits cannot be negative.";
            }
            if ($balance_credits > $total_credits) {
                $errors[] = "Balance credits cannot exceed total credits.";
            }
            if (!in_array($status, ['Active', 'Inactive'])) {
                $errors[] = "Invalid status selected.";
            }
            // Validate expiry date format if provided
            if (!empty($expired_at_input) && DateTime::createFromFormat('Y-m-d', $expired_at_input) === FALSE) {
                $errors[] = "Invalid expiry date format. Please use YYYY-MM-DD.";
            }

            if (empty($errors)) {
                // Prepare data array for the model, including the ID
                $update_card_data = [
                    'id' => $card_id_to_edit,
                    'c_status' => $status,
                    'balance_credits' => $balance_credits,
                    'total_credits' => $total_credits,
                    'expired_at' => $expired_at // Can be NULL
                ];

                // Attempt to update the card using the model
                if ($this->cardModel->update($update_card_data)) {
                    $success_message = "Card updated successfully!";
                    // Optionally, refetch the card details to show updated info
                     $card_details = $this->cardModel->findById($card_id_to_edit); // Update the local variable
                } else {
                    $error_message = "Failed to update card. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }


        // Load the edit card view
        $content = $this->getEditContent($card_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function delete() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $card_id_to_delete = $_GET['id'] ?? null;
        if (!$card_id_to_delete) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $card_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific card's details for confirmation
        try {
             $card_details = $this->cardModel->findById($card_id_to_delete);

             if (!$card_details) {
                 $error_message = "Card not found.";
             }
        } catch (PDOException $e) {
            $error_message = "Could not load card details for deletion. Please try again later.";
            error_log("CardController delete fetch query error: " . $e->getMessage());
        }

        // Process deletion if confirmed via POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $card_details && isset($_POST['confirm_delete'])) {
            // Attempt to delete the card using the model
            if ($this->cardModel->delete($card_id_to_delete)) {
                $success_message = "Card deleted successfully!";
                // Optionally, redirect to the cards list page after successful deletion
                // header("Location: index.php?success=deleted");
                // exit;
            } else {
                // This could mean the row didn't exist or a constraint prevented deletion
                $error_message = "Failed to delete card. It might not exist or be linked to other records that prevent deletion (e.g., related transactions, recharges, table orders).";
            }
        }


        // Load the delete card confirmation view
        $content = $this->getDeleteContent($card_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    // --- Helper Methods to Generate View Content ---

    private function getIndexContent($cards, $error_message = '') {
        $html = "<h2>Manage Cards</h2><p>View, edit, and manage user cards.</p>";

        if ($error_message) {
            $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
        } else {
            // Add a button to create a new card
            $html .= "
            <div class='mb-3'>
                <a href='CardController.php?action=create' class='btn btn-success'>Add New Card</a>
            </div>
            ";

            // Check if cards exist
            if (!empty($cards)) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Owner (User)</th>
                                <th>Balance Credits</th>
                                <th>Total Credits</th>
                                <th>Created At</th>
                                <th>Expired At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";

                foreach ($cards as $card) {
                    $status_badge = $card['c_status'] === 'Active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                    $owner_name = $card['first_name'] ? htmlspecialchars($card['first_name'] . ' ' . $card['last_name']) : htmlspecialchars($card['username']);
                    $owner_link = $card['user_id'] ? "<a href='UserController.php?action=view&id=" . $card['user_id'] . "'>" . $owner_name . "</a>" : 'N/A';
                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($card['id']) . "</td>
                                <td>" . $status_badge . "</td>
                                <td>" . $owner_link . "</td>
                                <td>₹" . number_format($card['balance_credits'], 2) . "</td>
                                <td>₹" . number_format($card['total_credits'], 2) . "</td>
                                <td>" . htmlspecialchars($card['created_at']) . "</td>
                                <td>" . ($card['expired_at'] ? htmlspecialchars($card['expired_at']) : 'N/A') . "</td>
                                <td>
                                    <a href='CardController.php?action=edit&id=" . $card['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                    <a href='CardController.php?action=delete&id=" . $card['id'] . "' class='btn btn-sm btn-danger'>Delete</a>
                                    <!-- Recharge card might be a common action -->
                                    <a href='../views/recharge/create.php?card_id=" . $card['id'] . "' class='btn btn-sm btn-success'>Recharge</a>
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
                $html .= "<p>No cards found.</p>";
            }
        }
        return $html;
    }

    private function getCreateContent($users, $users_error, $success_message = '', $error_message = '') {
        $html = "<h2>Create New Card</h2><p>Link a new card to an existing user.</p>";

        if ($users_error) {
            $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($users_error) . "</div>";
        } else {
            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }
            if ($error_message) {
                $html .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $html .= "
                <form method='post' action='../controllers/CardController.php?action=create'>
                    <div class='mb-3'>
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
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <label for='balance_credits' class='form-label'>Initial Balance Credits:</label>
                            <input type='number' class='form-control' id='balance_credits' name='balance_credits' value='" . htmlspecialchars($_POST['balance_credits'] ?? 0) . "' min='0' step='0.01' required>
                        </div>
                        <div class='col-md-6 mb-3'>
                            <label for='total_credits' class='form-label'>Initial Total Credits:</label>
                            <input type='number' class='form-control' id='total_credits' name='total_credits' value='" . htmlspecialchars($_POST['total_credits'] ?? 0) . "' min='0' step='0.01' required>
                        </div>
                    </div>
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <label for='status' class='form-label'>Card Status:</label>
                            <select class='form-select' id='status' name='status'>
                                <option value='Active' " . (($_POST['status'] ?? '') === 'Active' ? 'selected' : '') . ">Active</option>
                                <option value='Inactive' " . (($_POST['status'] ?? 'Inactive') === 'Inactive' ? 'selected' : '') . ">Inactive</option>
                            </select>
                        </div>
                        <div class='col-md-6 mb-3'>
                            <label for='expired_at' class='form-label'>Expiry Date (Optional):</label>
                            <input type='date' class='form-control' id='expired_at' name='expired_at' value='" . htmlspecialchars($_POST['expired_at'] ?? '') . "'>
                        </div>
                    </div>
                    <div class='d-grid'>
                        <button type='submit' class='btn btn-primary'>Create Card</button>
                    </div>
                </form>
                <div class='mt-3'>
                    <a href='CardController.php?action=index' class='btn btn-secondary'>Back to Cards List</a>
                </div>
            ";
        }

        return $html;
    }

     private function getEditContent($card_details, $success_message = '', $error_message = '') {
        $html = "<h2>Edit Card</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='CardController.php?action=index' class='btn btn-secondary'>Back to Cards List</a>";
        } elseif ($card_details) {
            $html .= "<p>Editing details for Card ID: <strong>" . htmlspecialchars($card_details['id']) . "</strong>, Owner: <strong>" . htmlspecialchars($card_details['first_name'] . ' ' . $card_details['last_name'] . ' (' . $card_details['username'] . ')') . "</strong></p>";

            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $status_selected_active = $card_details['c_status'] === 'Active' ? 'selected' : '';
            $status_selected_inactive = $card_details['c_status'] === 'Inactive' ? 'selected' : '';


            $html .= "
            <form method='post' action='../controllers/CardController.php?action=edit&id=" . $card_details['id'] . "'>
                <div class='mb-3'>
                    <label for='card_id_display' class='form-label'>Card ID (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='card_id_display' value='" . htmlspecialchars($card_details['id']) . "' readonly>
                </div>
                <div class='mb-3'>
                    <label for='owner_info' class='form-label'>Card Owner (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='owner_info' value='" . htmlspecialchars($card_details['first_name'] . ' ' . $card_details['last_name'] . ' (' . $card_details['username'] . ')') . "' readonly>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='balance_credits' class='form-label'>Balance Credits:</label>
                        <input type='number' class='form-control' id='balance_credits' name='balance_credits' value='" . htmlspecialchars($card_details['balance_credits']) . "' min='0' step='0.01' required>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='total_credits' class='form-label'>Total Credits:</label>
                        <input type='number' class='form-control' id='total_credits' name='total_credits' value='" . htmlspecialchars($card_details['total_credits']) . "' min='0' step='0.01' required>
                    </div>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='status' class='form-label'>Card Status:</label>
                        <select class='form-select' id='status' name='status'>
                            <option value='Active' " . $status_selected_active . ">Active</option>
                            <option value='Inactive' " . $status_selected_inactive . ">Inactive</option>
                        </select>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='expired_at' class='form-label'>Expiry Date (Optional):</label>
                        <input type='date' class='form-control' id='expired_at' name='expired_at' value='" . htmlspecialchars($card_details['expired_at'] ?? '') . "'>
                    </div>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Update Card</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='CardController.php?action=index' class='btn btn-secondary'>Back to Cards List</a>
                <!-- Recharge card might be a common next step -->
                <a href='../views/recharge/create.php?card_id=" . $card_details['id'] . "' class='btn btn-success'>Recharge Card</a>
            </div>
            ";
        } else {
            // This case handles when the card ID was provided but the card wasn't found (error_message is set above)
            $html .= "<p>Unable to load card information.</p>";
            $html .= "<a href='CardController.php?action=index' class='btn btn-secondary'>Back to Cards List</a>";
        }

        return $html;
    }

    private function getDeleteContent($card_details, $success_message = '', $error_message = '') {
        $html = "<h2>Delete Card</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='CardController.php?action=index' class='btn btn-secondary'>Back to Cards List</a>";
        } elseif ($card_details) {
            if ($success_message) {
                $html .= "<div class='alert alert-success' role='alert'>" . htmlspecialchars($success_message) . "</div>";
                $html .= "<a href='CardController.php?action=index' class='btn btn-secondary'>Back to Cards List</a>";
            } else {
                $html .= "
                <div class='alert alert-warning' role='alert'>
                    <h5>Confirm Deletion</h5>
                    <p>Are you sure you want to delete Card ID '<strong>" . $card_details['id'] . "</strong>' belonging to user '<strong>" . htmlspecialchars($card_details['first_name'] . ' ' . $card_details['last_name']) . "</strong>'?</p>
                    <p><strong>This action cannot be undone and may affect related records.</strong></p>
                </div>
                <form method='post' action='../controllers/CardController.php?action=delete&id=" . $card_details['id'] . "'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($card_details['id']) . "'>
                    <input type='hidden' name='confirm_delete' value='1'>
                    <button type='submit' class='btn btn-danger'>Yes, Delete Card</button>
                    <a href='CardController.php?action=index' class='btn btn-secondary'>Cancel</a>
                </form>
                ";
            }
        } else {
            // This case handles when the card ID was provided but the card wasn't found (error_message is set above)
            $html .= "<p>Unable to load card information for deletion.</p>";
            $html .= "<a href='CardController.php?action=index' class='btn btn-secondary'>Back to Cards List</a>";
        }

        return $html;
    }

}

// --- Routing ---
$action = $_GET['action'] ?? '';

$cardController = new CardController();

// Ensure only admin can access card management (for now)
// This check happens *after* controller instantiation so helper functions can use models
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../views/auth/login.php"); // Redirect to login if not authenticated as admin
    exit;
}

// Call the appropriate method based on the action
switch ($action) {
    case 'index':
        $cardController->index();
        break;
    case 'create':
        $cardController->create();
        break;
    case 'edit':
        $cardController->edit();
        break;
    case 'delete':
        $cardController->delete();
        break;
    default:
        // Default to index if action is invalid or not provided
        $cardController->index();
        break;
}

?>