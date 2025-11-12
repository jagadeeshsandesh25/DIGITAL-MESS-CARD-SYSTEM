<?php
// views/waiter/index.php

// Include the authentication check and session details
// This page is specifically for waiters to see their assigned orders/tables
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

$assigned_orders = []; // 'orders' refers to records in the 'tabels' table representing orders/assignments
$error_message = '';

try {
    // Prepare SQL query to fetch orders (tabels records) assigned to the current waiter
    // Joining with user (for customer via card.user_id), card, and user (for waiter via tabels.user_id) tables for more context
    // This query filters specifically for the logged-in waiter's ID in tabels.user_id
    $query = "
        SELECT t.id, t.t_name, t.t_qr, t.t_s_w, t.time, t.transaction_mode, t.user_id as waiter_user_id, t.card_id, t.menu_ordered,
               cu.username as customer_username, cu.first_name as customer_first_name, cu.last_name as customer_last_name, -- Customer linked via card
               w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name, -- Waiter linked via tabels.user_id
               c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status
        FROM tabels t
        LEFT JOIN card c ON t.card_id = c.id
        LEFT JOIN user cu ON c.user_id = cu.id -- Get customer via card's user_id
        LEFT JOIN user w ON t.user_id = w.id -- Get waiter via tabels.user_id
        WHERE t.user_id = :waiter_user_id -- Filter for orders assigned to the current waiter
        ORDER BY t.time DESC -- Order by time, newest first
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':waiter_user_id', $user_id); // Use the logged-in waiter's ID
    $stmt->execute();

    $assigned_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error or handle it appropriately in production
    $error_message = "Could not load your assigned orders. Please try again later.";
    error_log("Waiter Orders index page query error: " . $e->getMessage()); // Log the actual error
}

// Prepare the specific content for this page
$content = "
    <h2>Your Assigned Orders</h2>
    <p>View and manage the orders assigned to you.</p>
";

// Display error message if query failed
if ($error_message) {
    $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
} else {
    // Add a button to take a new order
    $content .= "
    <div class='mb-3'>
        <a href='take_order.php' class='btn btn-success'>Take New Order</a>
    </div>
    ";

    // Check if assigned orders exist
    if (!empty($assigned_orders)) {
        $content .= "
        <div class='table-responsive'>
            <table class='table table-striped table-hover'>
                <thead class='table-dark'>
                    <tr>
                        <th>Order ID</th>
                        <th>Table Name</th>
                        <th>Customer</th>
                        <th>Order Time</th>
                        <th>Transaction Mode</th>
                        <th>Menu Ordered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($assigned_orders as $order) {
            $customer_name_display = $order['customer_first_name'] ? htmlspecialchars($order['customer_first_name'] . ' ' . $order['customer_last_name']) : htmlspecialchars($order['customer_username']);
            $customer_link = $order['card_id'] ? "<a href='../../views/users/view.php?id=" . $order['card_id'] . "'>" . $customer_name_display . "</a>" : 'N/A (Customer info via card)';
            // Truncate long menu descriptions for display in the table
            $menu_display = strlen($order['menu_ordered']) > 100 ? substr(htmlspecialchars($order['menu_ordered']), 0, 97) . '...' : htmlspecialchars($order['menu_ordered']);

            $content .= "
                    <tr>
                        <td>" . htmlspecialchars($order['id']) . "</td>
                        <td>" . htmlspecialchars($order['t_name']) . "</td>
                        <td>" . $customer_link . "</td>
                        <td>" . htmlspecialchars($order['time']) . "</td>
                        <td>" . htmlspecialchars($order['transaction_mode']) . "</td>
                        <td>" . $menu_display . "</td>
                        <td>
                            <a href='view.php?id=" . $order['id'] . "' class='btn btn-sm btn-info'>View Order</a>
                            <a href='edit.php?id=" . $order['id'] . "' class='btn btn-sm btn-warning'>Update Order</a>
                            <!-- A 'Mark Served' button could go here if applicable -->
                            <!-- <a href='#' class='btn btn-sm btn-success'>Mark Served</a> -->
                        </td>
                    </tr>
            ";
        }

        $content .= "
                </tbody>
            </table>
        </div>
        ";
    } else {
        $content .= "<p>No orders are currently assigned to you.</p>";
    }
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>