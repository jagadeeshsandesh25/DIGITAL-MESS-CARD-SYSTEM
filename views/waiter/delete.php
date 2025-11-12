<?php
// views/waiter/delete.php

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

// Get order (tabels record) ID from query string or POST data
$order_id_to_delete = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$order_id_to_delete) {
    header("Location: index.php"); // Redirect to waiter's order list if no ID provided
    exit;
}

$order_details = null;
$success_message = '';
$error_message = '';

// Fetch the specific order record's details to confirm it belongs to the waiter and show confirmation
try {
    $query = "
        SELECT t.id, t.t_name, t.time, t.menu_ordered,
               cu.username as customer_username, cu.first_name as customer_first_name, cu.last_name as customer_last_name -- Customer linked via card
        FROM tabels t
        LEFT JOIN card c ON t.card_id = c.id
        LEFT JOIN user cu ON c.user_id = cu.id -- Get customer via card's user_id
        WHERE t.id = :id AND t.user_id = :waiter_user_id -- Ensure the order belongs to the logged-in waiter
        LIMIT 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $order_id_to_delete);
    $stmt->bindParam(':waiter_user_id', $user_id); // Crucial: Only allow deleting orders assigned to the waiter
    $stmt->execute();

    $order_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order_details) {
        $error_message = "Order not found or access denied.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load order details for deletion check. Please try again later.";
    error_log("Delete Waiter Order page - fetch query error: " . $e->getMessage());
}

// Process deletion if confirmed via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order_details && isset($_POST['confirm_delete'])) {
    try {
        // Prepare SQL query to delete the order record
        // Ensure the deletion is only for the order ID and user ID combination belonging to the waiter
        $delete_query = "DELETE FROM tabels WHERE id = :id AND user_id = :waiter_user_id LIMIT 1"; // LIMIT 1 for safety
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $order_id_to_delete);
        $delete_stmt->bindParam(':waiter_user_id', $user_id); // Verify waiter owns the order during deletion

        if ($delete_stmt->execute() && $delete_stmt->rowCount() > 0) {
            $success_message = "Order deleted successfully!";
            // Optionally, redirect to the waiter's order list page after successful deletion
            // header("Location: index.php?success=deleted");
            // exit;
        } else {
            // This could mean the row didn't exist or the user_id check failed, or a constraint prevented deletion
            $error_message = "Failed to delete order. It might not exist or you might not have permission. It's also possible the order is linked to other records that prevent deletion.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error during deletion. Please try again later.";
        error_log("Delete Waiter Order error: " . $e->getMessage());
    }
}


// Prepare the specific content for this page
$content = "
    <h2>Delete Order</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Your Orders</a>";
} elseif ($order_details) {
    $customer_name_display = $order_details['customer_first_name'] ? htmlspecialchars($order_details['customer_first_name'] . ' ' . $order_details['customer_last_name']) : htmlspecialchars($order_details['customer_username']);
    // Truncate long menu descriptions for display
    $menu_display = strlen($order_details['menu_ordered']) > 100 ? substr(htmlspecialchars($order_details['menu_ordered']), 0, 97) . '...' : htmlspecialchars($order_details['menu_ordered']);

    if ($success_message) {
        $content .= "<div class='alert alert-success' role='alert'>" . htmlspecialchars($success_message) . "</div>";
        $content .= "<a href='index.php' class='btn btn-secondary'>Back to Your Orders</a>";
    } else {
        $content .= "
        <div class='alert alert-warning' role='alert'>
            <h5>Confirm Deletion</h5>
            <p>Are you sure you want to delete the following order?</p>
            <p><strong>Order ID:</strong> " . htmlspecialchars($order_details['id']) . "</p>
            <p><strong>Table:</strong> " . htmlspecialchars($order_details['t_name']) . "</p>
            <p><strong>Customer:</strong> " . $customer_name_display . "</p>
            <p><strong>Order Time:</strong> " . htmlspecialchars($order_details['time']) . "</p>
            <p><strong>Menu Ordered:</strong> " . $menu_display . "</p>
            <p><strong>This action cannot be undone.</strong></p>
        </div>
        <form method='post' action=''>
            <input type='hidden' name='id' value='" . htmlspecialchars($order_details['id']) . "'>
            <input type='hidden' name='confirm_delete' value='1'>
            <button type='submit' class='btn btn-danger'>Yes, Delete Order</button>
            <a href='index.php' class='btn btn-secondary'>Cancel</a>
        </form>
        ";
    }
} else {
    // This case handles when the order ID was provided but the record wasn't found or access denied (shouldn't happen if error_message is set, but good practice)
    $content .= "<p>Unable to load order information for deletion or access denied.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Your Orders</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>