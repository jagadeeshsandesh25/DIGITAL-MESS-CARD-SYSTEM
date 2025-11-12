<?php
// views/waiter/view.php

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
$order_id_to_view = $_GET['id'] ?? null;

if (!$order_id_to_view) {
    header("Location: index.php"); // Redirect to waiter's order list if no ID provided
    exit;
}

$order_details = null;
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
    $stmt->bindParam(':id', $order_id_to_view);
    $stmt->bindParam(':waiter_user_id', $user_id); // Crucial: Only allow viewing orders assigned to the waiter
    $stmt->execute();

    $order_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order_details) {
        $error_message = "Order not found or access denied.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load order details. Please try again later.";
    error_log("View Waiter Order page - fetch query error: " . $e->getMessage());
}

// Prepare the specific content for this page
$content = "
    <h2>View Order Details</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Your Orders</a>";
} elseif ($order_details) {
    $customer_name_display = $order_details['customer_first_name'] ? htmlspecialchars($order_details['customer_first_name'] . ' ' . $order_details['customer_last_name']) : htmlspecialchars($order_details['customer_username']);
    $customer_link = $order_details['card_id'] ? "<a href='../../views/users/view.php?id=" . $order_details['card_id'] . "'>" . $customer_name_display . "</a>" : 'N/A (Customer info via card)';
    $card_link = $order_details['card_id'] ? "<a href='../../views/cards/view.php?id=" . $order_details['card_id'] . "'>Card #" . $order_details['card_id'] . " (Status: " . $order_details['card_status'] . ")</a>" : 'N/A';
    $card_balance_display = $order_details['card_balance'] !== null ? '₹' . number_format($order_details['card_balance'], 2) : 'N/A';
    $card_total_display = $order_details['card_total'] !== null ? '₹' . number_format($order_details['card_total'], 2) : 'N/A';
    $waiter_name_display = $order_details['waiter_first_name'] ? htmlspecialchars($order_details['waiter_first_name'] . ' ' . $order_details['waiter_last_name']) : htmlspecialchars($order_details['waiter_username']);

    $content .= "
    <div class='card'>
        <div class='card-header'>
            <h5>Order Information: ID " . htmlspecialchars($order_details['id']) . "</h5>
        </div>
        <div class='card-body'>
            <table class='table table-borderless'>
                <tr>
                    <th scope='row'>Order ID:</th>
                    <td>" . htmlspecialchars($order_details['id']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Table Name:</th>
                    <td>" . htmlspecialchars($order_details['t_name']) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>QR Code ID:</th>
                    <td>" . htmlspecialchars($order_details['t_qr']) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Seat/Switch Number:</th>
                    <td>" . htmlspecialchars($order_details['t_s_w']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Assigned Waiter:</th>
                    <td>" . htmlspecialchars($waiter_name_display) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Customer (via Card):</th>
                    <td>" . $customer_link . "</td>
                </tr>
                <tr>
                    <th scope='row'>Card Used:</th>
                    <td>" . $card_link . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Card Balance (at time of order):</th>
                    <td>" . $card_balance_display . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Card Total Credits (at time of order):</th>
                    <td>" . $card_total_display . "</td>
                </tr>
                <tr>
                    <th scope='row'>Order Time:</th>
                    <td>" . htmlspecialchars($order_details['time']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Transaction Mode:</th>
                    <td>" . htmlspecialchars($order_details['transaction_mode']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Menu Ordered:</th>
                    <td>" . nl2br(htmlspecialchars($order_details['menu_ordered'])) . "</td> <!-- nl2br to preserve line breaks if any -->
                </tr>
            </table>
        </div>
    </div>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Your Orders</a>
        <a href='edit.php?id=" . $order_details['id'] . "' class='btn btn-warning'>Update Order</a>
    </div>
    ";
} else {
    // This case handles when the order ID was provided but the record wasn't found or access denied (due to waiter mismatch)
    $content .= "<p>Unable to load order information or access denied.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Your Orders</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>