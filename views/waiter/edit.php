<?php
// views/waiter/edit.php

// Include the authentication check and session details
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../../login.php"); // Redirect to login if not authenticated as waiter
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'] ?? 'Waiter';

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Get order (tabels record) ID from query string
$order_id_to_edit = $_GET['id'] ?? null;

if (!$order_id_to_edit) {
    header("Location: index.php"); // Redirect to waiter's order list if no ID provided
    exit;
}

$order_details = null;
$success_message = '';
$error_message = '';

// Fetch the specific order record's details along with related user (customer via card), card, and waiter info
try {
    $query = "
        SELECT t.id, t.t_name, t.t_qr, t.t_s_w, t.time, t.transaction_mode, t.user_id as waiter_user_id, t.card_id, t.menu_ordered,
               cu.username as customer_username, cu.first_name as customer_first_name, cu.last_name as customer_last_name, -- Customer linked via card
               w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name, -- Waiter linked via tabels.user_id
               c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status
        FROM tabels t
        LEFT JOIN card c ON t.card_id = c.id
        LEFT JOIN user cu ON c.user_id = cu.id -- Get customer via card's user_id
        LEFT JOIN user w ON t.user_id = w.id -- Get waiter via tabels.user_id
        WHERE t.id = :id AND t.user_id = :waiter_user_id -- Ensure the order belongs to the logged-in waiter
        LIMIT 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $order_id_to_edit);
    $stmt->bindParam(':waiter_user_id', $user_id); // Crucial: Only allow editing orders assigned to the waiter
    $stmt->execute();

    $order_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order_details) {
        $error_message = "Order not found or access denied.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load order details. Please try again later.";
    error_log("Edit Waiter Order page - fetch query error: " . $e->getMessage());
}

// Process form submission if order details were found and user has permission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order_details) {
    // Note: Updating t_qr, t_s_w, time, user_id (waiter), card_id might not be standard for an 'update order' action.
    // Typically, for a waiter, the focus is on updating the menu_ordered and potentially transaction_mode.
    // We'll allow updating t_name (table), transaction_mode, and menu_ordered.
    $table_name = $_POST['table_name'] ?? null;
    $transaction_mode = $_POST['transaction_mode'] ?? 'Card';
    $menu_ordered_input = trim($_POST['menu_ordered'] ?? '');
    // Optional: Allow updating other fields if necessary, but validate carefully.
    // $t_s_w = (int)($_POST['t_s_w'] ?? 0);

    // Basic validation
    $errors = [];
    if (empty($table_name)) {
        $errors[] = "Please select a table name.";
    }
    if (empty($menu_ordered_input)) {
        $errors[] = "Menu ordered cannot be empty.";
    }
    if (!in_array($transaction_mode, ['Cash', 'Card', 'UPI'])) {
        $errors[] = "Please select a valid transaction mode.";
    }
    // Optional validation for t_s_w if it's made editable
    // if ($t_s_w < 0) {
    //     $errors[] = "Seat/Switch number cannot be negative.";
    // }

    if (empty($errors)) {
        try {
            // Prepare SQL query to update the order record
            // Focus on fields commonly updated by a waiter: table name, transaction mode, menu ordered
            $update_query = "
                UPDATE tabels SET t_name = :t_name, transaction_mode = :transaction_mode, menu_ordered = :menu_ordered WHERE id = :id AND user_id = :waiter_user_id -- Ensure update is only for the waiter's order
            ";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':t_name', $table_name);
            $update_stmt->bindParam(':transaction_mode', $transaction_mode);
            $update_stmt->bindParam(':menu_ordered', $menu_ordered_input);
            $update_stmt->bindParam(':id', $order_id_to_edit);
            $update_stmt->bindParam(':waiter_user_id', $user_id); // Verify waiter owns the order during update

            if ($update_stmt->execute()) {
                $success_message = "Order updated successfully!";
                // Optionally, refetch the order details to show updated info
                 $stmt->execute(); // Re-execute the fetch query
                 $order_details = $stmt->fetch(PDO::FETCH_ASSOC); // Update the local variable
            } else {
                $error_message = "Failed to update order. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again later.";
            error_log("Edit Waiter Order error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch list of menu items for the order update (optional, might be used for suggestions or validation)
$menu_items = [];
if ($order_details) {
    try {
        $menu_query = "SELECT id, category, description, menu_type FROM menu ORDER BY category, menu_type, description";
        $menu_stmt = $db->prepare($menu_query);
        $menu_stmt->execute();
        $menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Error fetching menu items might not be critical for editing, log it
        error_log("Edit Waiter Order page - Menu items query error: " . $e->getMessage());
        // $error_message = "Could not load menu items for reference. Details can still be edited.";
    }
}


// Prepare the specific content for this page
$content = "
    <h2>Update Order</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Your Orders</a>";
} elseif ($order_details) {
    $customer_name_display = $order_details['customer_first_name'] ? htmlspecialchars($order_details['customer_first_name'] . ' ' . $order_details['customer_last_name']) : htmlspecialchars($order_details['customer_username']);
    $customer_link = $order_details['card_id'] ? "<a href='../../views/users/view.php?id=" . $order_details['card_id'] . "'>" . $customer_name_display . "</a>" : 'N/A (Customer info via card)';
    $card_link = $order_details['card_id'] ? "<a href='../../views/cards/view.php?id=" . $order_details['card_id'] . "'>Card #" . $order_details['card_id'] . " (Status: " . $order_details['card_status'] . ")</a>" : 'N/A';
    $waiter_name_display = $order_details['waiter_first_name'] ? htmlspecialchars($order_details['waiter_first_name'] . ' ' . $order_details['waiter_last_name']) : htmlspecialchars($order_details['waiter_username']);

    $content .= "<p>Editing details for Order ID: <strong>" . htmlspecialchars($order_details['id']) . "</strong>, Table: <strong>" . htmlspecialchars($order_details['t_name']) . "</strong>, Customer: <strong>" . $customer_link . "</strong></p>";

    if ($success_message) {
        $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }

    $type_selected_cash = $order_details['transaction_mode'] === 'Cash' ? 'selected' : '';
    $type_selected_card = $order_details['transaction_mode'] === 'Card' ? 'selected' : '';
    $type_selected_upi = $order_details['transaction_mode'] === 'UPI' ? 'selected' : '';

    $content .= "
    <form method='post' action=''>
        <div class='mb-3'>
            <label for='order_id_display' class='form-label'>Order ID (Read-only):</label>
            <input type='text' class='form-control-plaintext' id='order_id_display' value='" . htmlspecialchars($order_details['id']) . "' readonly>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='t_name_display' class='form-label'>Assigned Waiter (Read-only):</label>
                <input type='text' class='form-control-plaintext' id='t_name_display' value='" . htmlspecialchars($waiter_name_display) . "' readonly>
            </div>
             <div class='col-md-6 mb-3'>
                <label for='card_id_display' class='form-label'>Card Used (Read-only):</label>
                <input type='text' class='form-control-plaintext' id='card_id_display' value='" . $card_link . "' readonly>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='table_name' class='form-label'>Table Name:</label>
                <select class='form-select' id='table_name' name='table_name' required>
                    <option value='A' " . ($order_details['t_name'] === 'A' ? 'selected' : '') . ">Table A</option>
                    <option value='B' " . ($order_details['t_name'] === 'B' ? 'selected' : '') . ">Table B</option>
                    <option value='C' " . ($order_details['t_name'] === 'C' ? 'selected' : '') . ">Table C</option>
                    <option value='D' " . ($order_details['t_name'] === 'D' ? 'selected' : '') . ">Table D</option>
                    <option value='E' " . ($order_details['t_name'] === 'E' ? 'selected' : '') . ">Table E</option>
                    <option value='F' " . ($order_details['t_name'] === 'F' ? 'selected' : '') . ">Table F</option>
                    <option value='G' " . ($order_details['t_name'] === 'G' ? 'selected' : '') . ">Table G</option>
                    <option value='H' " . ($order_details['t_name'] === 'H' ? 'selected' : '') . ">Table H</option>
                    <option value='I' " . ($order_details['t_name'] === 'I' ? 'selected' : '') . ">Table I</option>
                    <option value='J' " . ($order_details['t_name'] === 'J' ? 'selected' : '') . ">Table J</option>
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='transaction_mode' class='form-label'>Transaction Mode:</label>
                <select class='form-select' id='transaction_mode' name='transaction_mode' required>
                    <option value='Cash' " . $type_selected_cash . ">Cash</option>
                    <option value='Card' " . $type_selected_card . ">Card</option>
                    <option value='UPI' " . $type_selected_upi . ">UPI</option>
                </select>
            </div>
        </div>
        <div class='mb-3'>
            <label for='menu_ordered' class='form-label'>Menu Ordered:</label>
            <textarea class='form-control' id='menu_ordered' name='menu_ordered' rows='4' required>" . htmlspecialchars($order_details['menu_ordered']) . "</textarea>
            <small class='form-text text-muted'>Enter the items ordered, e.g., 'Dosa (x2), Sambar, Filter Coffee (x2)'. You can update this as needed.</small>
            <!-- Optional: Show available menu items for reference (requires JS to populate the textarea) -->
            <!-- Available menu items reference removed to avoid mixing PHP string termination and raw HTML; implement dynamic rendering here if needed. -->
        </div>
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Update Order</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Your Orders</a>
        <a href='view.php?id=" . $order_details['id'] . "' class='btn btn-info'>View Order Details</a>
    </div>
    ";
} else {
    // This case handles when the order ID was provided but the record wasn't found or access denied
    $content .= "<p>Unable to load order information or access denied.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Your Orders</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>