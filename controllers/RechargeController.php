<?php
// controllers/RechargeController.php

require_once '../config/database.php'; // Adjust path
require_once '../models/Recharge.php'; // Adjust path
require_once '../models/User.php'; // Adjust path - Needed for user context
require_once '../models/Card.php'; // Adjust path - Needed for card context
require_once '../models/Transaction.php'; // Adjust path - Needed for transaction context
require_once '../includes/auth.php'; // Adjust path - For permission checks

class RechargeController {

    private $database;
    private $db;
    private $rechargeModel;
    private $userModel;
    private $cardModel;
    private $transactionModel;

    public function __construct() {
        $this->database = new Database();
        $this->db = $this->database->getConnection();
        $this->rechargeModel = new Recharge($this->db);
        $this->userModel = new User($this->db);
        $this->cardModel = new Card($this->db);
        $this->transactionModel = new Transaction($this->db); // Assuming Transaction model exists
    }

    public function index() {
        // Check if user is logged in and is an admin (or potentially user for their own recharges)
        requireLogin();
        // For now, restrict to admin only. Later, a user could see their own recharges.
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php"); // Redirect non-admins
            exit;
        }

        $recharges = [];
        $error_message = '';

        try {
            // Fetch all recharges from the model
            $recharges = $this->rechargeModel->findAll(); // Default order by r_time DESC
        } catch (PDOException $e) {
            $error_message = "Could not load recharges. Please try again later.";
            error_log("RechargeController index query error: " . $e->getMessage());
        }

        // Prepare the content for the index view using a helper
        $content = $this->getIndexContent($recharges, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function create() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        // Fetch list of users and active cards for the recharge form
        $users = [];
        $cards = []; // Will be populated based on selected user or fetched all
        $transactions = []; // Might be populated based on context or fetched recent ones
        $users_error = '';
        $cards_error = '';
        $transactions_error = '';

        try {
            $user_query = "SELECT id, first_name, last_name, username FROM user ORDER BY username";
            $user_stmt = $this->db->prepare($user_query);
            $user_stmt->execute();
            $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $users_error = "Could not load users for recharge.";
            error_log("RechargeController create - Users query error: " . $e->getMessage());
        }

        // Optionally, fetch recent transactions if needed for linking (complex logic often involved)
        // try {
        //     $transaction_query = "SELECT id, t_type, t_time, user_id, card_id FROM transactions ORDER BY t_time DESC LIMIT 20";
        //     $transaction_stmt = $this->db->prepare($transaction_query);
        //     $transaction_stmt->execute();
        //     $transactions = $transaction_stmt->fetchAll(PDO::FETCH_ASSOC);
        // } catch (PDOException $e) {
        //     $transactions_error = "Could not load recent transactions for reference.";
        //     error_log("RechargeController create - Transactions query error: " . $e->getMessage());
        // }

        $success_message = '';
        $error_message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($users_error)) {
            $user_id = $_POST['user_id'] ?? null;
            $card_id = $_POST['card_id'] ?? null; // The card to be recharged
            $r_type = $_POST['r_type'] ?? 'Cash'; // Default recharge type
            $transaction_id = $_POST['transaction_id'] ?? null; // Optional, might be created during process
            $r_time_input = trim($_POST['r_time'] ?? '');
            $r_time = !empty($r_time_input) ? $r_time_input : date('Y-m-d H:i:s'); // Use provided time or current time

            // Basic validation
            $errors = [];
            if (empty($user_id) || $user_id == 0) {
                $errors[] = "Please select a user.";
            }
            if (empty($card_id) || $card_id == 0) {
                $errors[] = "Please select a card to recharge.";
            }
            if (!in_array($r_type, ['Cash', 'Card', 'UPI'])) {
                $errors[] = "Please select a valid recharge type.";
            }
            // Validate r_time format if provided
            if (!empty($r_time_input) && DateTime::createFromFormat('Y-m-d H:i:s', $r_time_input) === FALSE) {
                 $errors[] = "Invalid recharge time format. Please use YYYY-MM-DD HH:MM:SS.";
            }
            // Note: transaction_id validation depends on business logic - might be created internally or linked to an existing one.
            // For now, we'll allow it to be NULL or a provided ID.

            if (empty($errors)) {
                // --- Critical Business Logic for Recharge ---
                // 1. Fetch the current card details (balance, total)
                $card_details = null;
                try {
                    $card_details = $this->cardModel->findById($card_id);
                    if (!$card_details) {
                         $errors[] = "Selected card details could not be retrieved.";
                    }
                } catch (PDOException $e) {
                    $errors[] = "Database error fetching card details.";
                    error_log("RechargeController create - Card fetch error: " . $e->getMessage());
                }

                if (empty($errors) && $card_details) {
                    // 2. Determine the amount to recharge (This is missing from the schema!)
                    // For demonstration, let's assume a fixed amount or derive from a form field (which we don't have).
                    // A real system would have a 'recharge_plans' table or an amount input field.
                    // Let's assume an amount is passed via a hidden or calculated field for now.
                    // *** SCHEMA LIMITATION ***
                    // The `recharge` table schema does not have an `amount` column.
                    // To update the card balance, we need to know the recharge amount.
                    // This is a critical piece of information missing from the provided schema.
                    // We will proceed assuming an amount is somehow determined (e.g., fixed plan, or user input not shown in schema).
                    // Let's add a placeholder amount input to the form and process it.
                    // --- End Schema Limitation Note ---

                    $recharge_amount = floatval($_POST['recharge_amount'] ?? 0);
                    if ($recharge_amount <= 0) {
                         $errors[] = "Please enter a valid recharge amount greater than zero.";
                    }

                    if (empty($errors)) {
                         // 3. Update the card's balance_credits and total_credits
                         // IMPORTANT: This should ideally be wrapped in a database transaction to ensure atomicity.
                         try {
                             $this->db->beginTransaction();

                             $new_balance = $card_details['balance_credits'] + $recharge_amount;
                             $new_total = $card_details['total_credits'] + $recharge_amount;

                             $card_update_query = "UPDATE card SET balance_credits = :new_balance, total_credits = :new_total WHERE id = :card_id";
                             $card_update_stmt = $this->db->prepare($card_update_query);
                             $card_update_stmt->bindParam(':new_balance', $new_balance, PDO::PARAM_INT);
                             $card_update_stmt->bindParam(':new_total', $new_total, PDO::PARAM_INT);
                             $card_update_stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);

                             if (!$card_update_stmt->execute()) {
                                 throw new Exception("Failed to update card balance.");
                             }

                             // 4. Create the transaction record (if not already existing)
                             // Assume transaction is created *as part of* this recharge process.
                             // Link it back to the recharge.
                             $transaction_insert_query = "INSERT INTO transactions (user_id, t_time, t_type, card_id) VALUES (:user_id, :t_time, :t_type, :card_id)";
                             $transaction_insert_stmt = $this->db->prepare($transaction_insert_query);
                             $transaction_insert_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                             $transaction_insert_stmt->bindParam(':t_time', $r_time); // Use recharge time?
                             $transaction_insert_stmt->bindParam(':t_type', $r_type); // Match recharge type?
                             $transaction_insert_stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);

                             if (!$transaction_insert_stmt->execute()) {
                                 throw new Exception("Failed to create transaction record.");
                             }
                             $new_transaction_id = $this->db->lastInsertId();

                             // 5. Prepare data array for the model to create the recharge record
                             $new_recharge_data = [
                                 'r_type' => $r_type,
                                 'user_id' => $user_id,
                                 'card_id' => $card_id,
                                 'transaction_id' => $new_transaction_id, // Link to the newly created transaction
                                 'r_time' => $r_time // Use provided or current time
                             ];

                             // 6. Attempt to create the recharge using the model
                             $new_recharge_id = $this->rechargeModel->create($new_recharge_data);

                             if ($new_recharge_id) {
                                 // 7. Update the transaction record to link it to the newly created recharge
                                 $transaction_update_recharge_query = "UPDATE transactions SET recharge_id = :recharge_id WHERE id = :transaction_id";
                                 $transaction_update_recharge_stmt = $this->db->prepare($transaction_update_recharge_query);
                                 $transaction_update_recharge_stmt->bindParam(':recharge_id', $new_recharge_id, PDO::PARAM_INT);
                                 $transaction_update_recharge_stmt->bindParam(':transaction_id', $new_transaction_id, PDO::PARAM_INT);

                                 if (!$transaction_update_recharge_stmt->execute()) {
                                     throw new Exception("Failed to link transaction to recharge.");
                                 }

                                 // Commit the transaction if all steps succeeded
                                 $this->db->commit();

                                 $success_message = "Recharge processed successfully! Recharge ID: $new_recharge_id, Transaction ID: $new_transaction_id. Card balance updated.";
                                 // Optionally, redirect to the recharges list page after successful creation
                                 // header("Location: index.php?success=recharged");
                                 // exit;
                             } else {
                                 throw new Exception("Failed to create recharge record.");
                             }
                         } catch (Exception $e) {
                             // Rollback the transaction on any error
                             $this->db->rollback();
                             $error_message = "Recharge failed: " . $e->getMessage();
                             error_log("RechargeController create - Process error: " . $e->getMessage());
                         }
                    }
                }
            }

            if (!empty($errors)) {
                $error_message = implode("<br>", $errors);
            }
        }

        // Load the create recharge view
        $content = $this->getCreateContent($users, $cards, $transactions, $users_error, $cards_error, $transactions_error, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function edit() {
         // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $recharge_id_to_edit = $_GET['id'] ?? null;
        if (!$recharge_id_to_edit) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $recharge_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific recharge's details
        try {
             $recharge_details = $this->rechargeModel->findById($recharge_id_to_edit);

             if (!$recharge_details) {
                 $error_message = "Recharge record not found.";
             }
        } catch (PDOException $e) {
            $error_message = "Could not load recharge details. Please try again later.";
            error_log("RechargeController edit fetch query error: " . $e->getMessage());
        }

        // Process form submission if recharge details were found
        // Editing financial records is highly discouraged. This is just a placeholder.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $recharge_details) {
             $r_type = $_POST['r_type'] ?? 'Cash';
             $r_time_input = trim($_POST['r_time'] ?? '');
             $r_time = !empty($r_time_input) ? $r_time_input : $recharge_details['r_time']; // Use provided time or keep existing

             // Basic validation
             $errors = [];
             if (!in_array($r_type, ['Cash', 'Card', 'UPI'])) {
                 $errors[] = "Please select a valid recharge type.";
             }
             // Validate r_time format if provided
             if (!empty($r_time_input) && DateTime::createFromFormat('Y-m-d H:i:s', $r_time_input) === FALSE) {
                  $errors[] = "Invalid recharge time format. Please use YYYY-MM-DD HH:MM:SS.";
             }

             if (empty($errors)) {
                 // Prepare data array for the model, including the ID
                 $update_recharge_data = [
                     'id' => $recharge_id_to_edit,
                     'r_type' => $r_type,
                     'r_time' => $r_time // Allow updating time, though unusual
                 ];

                 // Attempt to update the recharge using the model
                 if ($this->rechargeModel->update($update_recharge_data)) {
                     $success_message = "Recharge record updated successfully!";
                     // Optionally, refetch the recharge details to show updated info
                      $recharge_details = $this->rechargeModel->findById($recharge_id_to_edit); // Update the local variable
                 } else {
                     $error_message = "Failed to update recharge record. Please try again.";
                 }
             } else {
                 $error_message = implode("<br>", $errors);
             }
        }


        // Load the edit recharge view
        $content = $this->getEditContent($recharge_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function delete() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $recharge_id_to_delete = $_GET['id'] ?? null;
        if (!$recharge_id_to_delete) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $recharge_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific recharge's details for confirmation
        try {
             $recharge_details = $this->rechargeModel->findById($recharge_id_to_delete);

             if (!$recharge_details) {
                 $error_message = "Recharge record not found.";
             }
        } catch (PDOException $e) {
            $error_message = "Could not load recharge details for deletion. Please try again later.";
            error_log("RechargeController delete fetch query error: " . $e->getMessage());
        }

        // Process deletion if confirmed via POST
        // Deleting financial records is highly discouraged and often impossible due to constraints.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $recharge_details && isset($_POST['confirm_delete'])) {
            // Attempt to delete the recharge using the model
            // WARNING: This is likely to fail due to foreign key constraints unless cascade delete is set or related records are handled.
            if ($this->rechargeModel->delete($recharge_id_to_delete)) {
                $success_message = "Recharge record deleted successfully!";
                // Optionally, redirect to the recharges list page after successful deletion
                // header("Location: index.php?success=deleted");
                // exit;
            } else {
                // This could mean the row didn't exist or a constraint prevented deletion
                $error_message = "Failed to delete recharge record. It might not exist or be linked to other records that prevent deletion (e.g., related transactions).";
            }
        }


        // Load the delete recharge confirmation view
        $content = $this->getDeleteContent($recharge_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    // --- Helper Methods to Generate View Content ---

    private function getIndexContent($recharges, $error_message = '') {
        $html = "<h2>Manage Recharges</h2><p>View and manage recharge records.</p>";

        if ($error_message) {
            $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
        } else {
            // Add a button to create a new recharge (admin only)
            $html .= "
            <div class='mb-3'>
                <a href='RechargeController.php?action=create' class='btn btn-success'>Process New Recharge</a>
            </div>
            ";

            // Check if recharges exist
            if (!empty($recharges)) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>User</th>
                                <th>Card ID</th>
                                <th>Transaction ID (FK)</th>
                                <th>Recharge Time</th>
                                <th>Linked Transaction Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";

                foreach ($recharges as $recharge) {
                    $user_name_display = $recharge['user_first_name'] ? htmlspecialchars($recharge['user_first_name'] . ' ' . $recharge['user_last_name']) : htmlspecialchars($recharge['user_username']);
                    $user_link = $recharge['user_id'] ? "<a href='UserController.php?action=view&id=" . $recharge['user_id'] . "'>" . $user_name_display . "</a>" : 'N/A';
                    $card_link = $recharge['card_id'] ? "<a href='CardController.php?action=view&id=" . $recharge['card_id'] . "'>Card #" . $recharge['card_id'] . "</a>" : 'N/A';
                    $transaction_link = $recharge['transaction_id'] ? "<a href='TransactionController.php?action=view&id=" . $recharge['transaction_id'] . "'>#" . $recharge['transaction_id'] . "</a>" : 'N/A';

                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($recharge['id']) . "</td>
                                <td>" . htmlspecialchars($recharge['r_type']) . "</td>
                                <td>" . $user_link . "</td>
                                <td>" . $card_link . "</td>
                                <td>" . $transaction_link . "</td>
                                <td>" . htmlspecialchars($recharge['r_time']) . "</td>
                                <td>" . ($recharge['transaction_time'] ? htmlspecialchars($recharge['transaction_time']) : 'N/A') . "</td>
                                <td>
                                    <a href='RechargeController.php?action=edit&id=" . $recharge['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                    <a href='RechargeController.php?action=delete&id=" . $recharge['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete recharge ID " . $recharge['id'] . "? This action cannot be undone and may affect related financial records.\")'>Delete</a>
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
                $html .= "<p>No recharges found.</p>";
            }
        }
        return $html;
    }

    private function getCreateContent($users, $cards, $transactions, $users_error, $cards_error, $transactions_error, $success_message = '', $error_message = '') {
        $html = "<h2>Process New Recharge</h2><p>Record a new recharge for a user's card.</p>";

        if ($users_error || $cards_error || $transactions_error) {
            $html .= "<div class='alert alert-danger' role='alert'>";
            if ($users_error) $html .= htmlspecialchars($users_error) . "<br>";
            if ($cards_error) $html .= htmlspecialchars($cards_error) . "<br>";
            if ($transactions_error) $html .= htmlspecialchars($transactions_error);
            $html .= "</div>";
        } else {
            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }
            if ($error_message) {
                $html .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $html .= "
                <form method='post' action='../controllers/RechargeController.php?action=create'>
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <label for='user_id' class='form-label'>Select User:</label>
                            <select class='form-select' id='user_id' name='user_id' required onchange='fetchCardsForUser(this.value)'>
                                <option value='0'>Choose User...</option>
            ";

            foreach ($users as $user) {
                 $selected_attr = ($_POST['user_id'] ?? null) == $user['id'] ? 'selected' : '';
                 $html .= "<option value='" . $user['id'] . "' " . $selected_attr . ">" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')') . "</option>";
            }

            $html .= "
                            </select>
                        </div>
                        <div class='col-md-6 mb-3'>
                            <label for='card_id' class='form-label'>Select Card to Recharge:</label>
                            <select class='form-select' id='card_id' name='card_id' required>
                                <option value='0'>Choose Card...</option>
            ";

            // Populate cards based on the selected user (from POST or fetched via JS) or show all if no user selected yet
            $selected_user_id = $_POST['user_id'] ?? null;
            if ($selected_user_id && !empty($cards)) {
                 foreach ($cards as $card) {
                     $selected_attr = ($_POST['card_id'] ?? null) == $card['id'] ? 'selected' : '';
                     $html .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (User ID: " . $card['user_id'] . ", Balance: ₹" . number_format($card['balance_credits'], 2) . ", Total: ₹" . number_format($card['total_credits'], 2) . ")</option>";
                 }
            }

            $html .= "
                            </select>
                        </div>
                    </div>
                    <div class='row'>
                         <div class='col-md-6 mb-3'>
                            <label for='r_type' class='form-label'>Recharge Type:</label>
                            <select class='form-select' id='r_type' name='r_type' required>
                                <option value='Cash' " . (($_POST['r_type'] ?? 'Cash') === 'Cash' ? 'selected' : '') . ">Cash</option>
                                <option value='Card' " . (($_POST['r_type'] ?? 'Cash') === 'Card' ? 'selected' : '') . ">Card</option>
                                <option value='UPI' " . (($_POST['r_type'] ?? 'Cash') === 'UPI' ? 'selected' : '') . ">UPI</option>
                            </select>
                        </div>
                         <div class='col-md-6 mb-3'>
                            <label for='recharge_amount' class='form-label'>Recharge Amount (₹):</label>
                            <input type='number' class='form-control' id='recharge_amount' name='recharge_amount' value='" . htmlspecialchars($_POST['recharge_amount'] ?? '') . "' min='0.01' step='0.01' required>
                            <small class='form-text text-muted'>Enter the amount to add to the card.</small>
                        </div>
                    </div>
                    <div class='row'>
                        <!-- Optional: Link to an existing transaction ID if applicable -->
                        <!-- <div class='col-md-6 mb-3'>
                            <label for='transaction_id' class='form-label'>Linked Transaction ID (Optional):</label>
                            <select class='form-select' id='transaction_id' name='transaction_id'>
                                <option value=''>Select Transaction...</option>
                                ";
                                // foreach ($transactions as $transaction) {
                                //      $selected_attr = ($_POST['transaction_id'] ?? null) == $transaction['id'] ? 'selected' : '';
                                //      $html .= "<option value='" . $transaction['id'] . "' " . $selected_attr . ">#" . $transaction['id'] . " (" . $transaction['t_type'] . " on " . $transaction['t_time'] . ")</option>";
                                // }
                                $html .= "
                            </select>
                        </div> -->
                         <div class='col-md-6 mb-3'>
                            <label for='r_time' class='form-label'>Recharge Time:</label>
                            <input type='datetime-local' class='form-control' id='r_time' name='r_time' value='" . (new DateTime())->format('Y-m-d\TH:i:s') . "' required>
                            <small class='form-text text-muted'>Defaults to current time.</small>
                        </div>
                    </div>
                    <div class='d-grid'>
                        <button type='submit' class='btn btn-primary'>Process Recharge</button>
                    </div>
                </form>
                <div class='mt-3'>
                    <a href='RechargeController.php?action=index' class='btn btn-secondary'>Back to Recharges List</a>
                </div>

                <script>
                    // Function to fetch cards via AJAX when user is selected (improves UX)
                    // Requires an API endpoint (e.g., ../api/recharge/fetch_cards.php) to return JSON
                    function fetchCardsForUser(userId) {
                        const cardSelect = document.getElementById('card_id');
                        cardSelect.innerHTML = '<option value=\"0\">Loading cards...</option>'; // Show loading state

                        if (userId && userId != 0) {
                            // Example AJAX call (requires fetch_cards.php endpoint)
                            fetch('../api/recharge/fetch_cards.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ user_id: userId }),
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok');
                                }
                                return response.json();
                            })
                            .then(data => {
                                cardSelect.innerHTML = '<option value=\"0\">Choose Card...</option>'; // Clear existing options
                                data.forEach(card => {
                                    const option = document.createElement('option');
                                    option.value = card.id;
                                    option.textContent = `Card ID: \${card.id} (Balance: ₹\${card.balance_credits}, Total: ₹\${card.total_credits})`;
                                    cardSelect.appendChild(option);
                                });
                            })
                            .catch(error => {
                                console.error('Error fetching cards:', error);
                                cardSelect.innerHTML = '<option value=\"0\">Error loading cards</option>';
                            });
                        } else {
                            cardSelect.innerHTML = '<option value=\"0\">Choose Card...</option>'; // Reset if no user selected
                        }
                    }
                </script>
            ";
        }

        return $html;
    }

     private function getEditContent($recharge_details, $success_message = '', $error_message = '') {
        $html = "<h2>Edit Recharge Record</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='RechargeController.php?action=index' class='btn btn-secondary'>Back to Recharges List</a>";
        } elseif ($recharge_details) {
            $html .= "<p>Editing details for Recharge ID: <strong>" . htmlspecialchars($recharge_details['id']) . "</strong></p>";

            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $type_selected_cash = $recharge_details['r_type'] === 'Cash' ? 'selected' : '';
            $type_selected_card = $recharge_details['r_type'] === 'Card' ? 'selected' : '';
            $type_selected_upi = $recharge_details['r_type'] === 'UPI' ? 'selected' : '';

            $html .= "
            <form method='post' action='../controllers/RechargeController.php?action=edit&id=" . $recharge_details['id'] . "'>
                <div class='mb-3'>
                    <label for='recharge_id_display' class='form-label'>Recharge ID (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='recharge_id_display' value='" . htmlspecialchars($recharge_details['id']) . "' readonly>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='r_type' class='form-label'>Recharge Type:</label>
                        <select class='form-select' id='r_type' name='r_type' required>
                            <option value='Cash' " . $type_selected_cash . ">Cash</option>
                            <option value='Card' " . $type_selected_card . ">Card</option>
                            <option value='UPI' " . $type_selected_upi . ">UPI</option>
                        </select>
                    </div>
                     <div class='col-md-6 mb-3'>
                        <label for='r_time' class='form-label'>Recharge Time:</label>
                        <input type='datetime-local' class='form-control' id='r_time' name='r_time' value='" . (new DateTime($recharge_details['r_time']))->format('Y-m-d\TH:i:s') . "' required>
                    </div>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Update Recharge Record</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='RechargeController.php?action=index' class='btn btn-secondary'>Back to Recharges List</a>
                <!-- View details link -->
                <a href='RechargeController.php?action=view&id=" . $recharge_details['id'] . "' class='btn btn-info'>View Recharge Details</a>
            </div>
            ";
        } else {
            // This case handles when the recharge ID was provided but the record wasn't found (error_message is set above)
            $html .= "<p>Unable to load recharge information.</p>";
            $html .= "<a href='RechargeController.php?action=index' class='btn btn-secondary'>Back to Recharges List</a>";
        }

        return $html;
    }

    private function getDeleteContent($recharge_details, $success_message = '', $error_message = '') {
        $html = "<h2>Delete Recharge Record</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='RechargeController.php?action=index' class='btn btn-secondary'>Back to Recharges List</a>";
        } elseif ($recharge_details) {
            if ($success_message) {
                $html .= "<div class='alert alert-success' role='alert'>" . htmlspecialchars($success_message) . "</div>";
                $html .= "<a href='RechargeController.php?action=index' class='btn btn-secondary'>Back to Recharges List</a>";
            } else {
                $html .= "
                <div class='alert alert-warning' role='alert'>
                    <h5>Confirm Deletion</h5>
                    <p>Are you sure you want to delete Recharge Record ID '<strong>" . $recharge_details['id'] . "</strong>'?</p>
                    <p><strong>WARNING: This action is highly discouraged for financial records. It may fail due to database constraints or cause data inconsistency. It cannot be undone.</strong></p>
                </div>
                <form method='post' action='../controllers/RechargeController.php?action=delete&id=" . $recharge_details['id'] . "'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($recharge_details['id']) . "'>
                    <input type='hidden' name='confirm_delete' value='1'>
                    <button type='submit' class='btn btn-danger'>Yes, Delete Recharge Record</button>
                    <a href='RechargeController.php?action=index' class='btn btn-secondary'>Cancel</a>
                </form>
                ";
            }
        } else {
            // This case handles when the recharge ID was provided but the record wasn't found (error_message is set above)
            $html .= "<p>Unable to load recharge information for deletion.</p>";
            $html .= "<a href='RechargeController.php?action=index' class='btn btn-secondary'>Back to Recharges List</a>";
        }

        return $html;
    }

}

// --- Routing ---
$action = $_GET['action'] ?? '';

$rechargeController = new RechargeController();

// Ensure only admin can access recharge management (for now)
// This check happens *after* controller instantiation so helper functions can use models
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../views/auth/login.php"); // Redirect to login if not authenticated as admin
    exit;
}

// Call the appropriate method based on the action
switch ($action) {
    case 'index':
        $rechargeController->index();
        break;
    case 'create':
        $rechargeController->create();
        break;
    case 'edit':
        $rechargeController->edit();
        break;
    case 'delete':
        $rechargeController->delete();
        break;
    default:
        // Default to index if action is invalid or not provided
        $rechargeController->index();
        break;
}

?>