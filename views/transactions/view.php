<?php
// views/transactions/view.php

// Include the authentication check and session details
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php"); // Redirect to login if not authenticated as admin
    exit;
}

$user_name = $_SESSION['username'] ?? 'Admin';

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

// Get transaction ID from query string
$transaction_id_to_view = $_GET['id'] ?? null;

if (!$transaction_id_to_view) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$transaction_details = null;
$error_message = '';

// Fetch the specific transaction record's details along with related user, card, and recharge info
try {
    $query = "
        SELECT t.id, t.user_id, t.card_id, t.t_time, t.t_type, t.recharge_id,
               u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
               c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
               r.r_time as recharge_time, r.r_type as recharge_type
        FROM transactions t
        LEFT JOIN user u ON t.user_id = u.id
        LEFT JOIN card c ON t.card_id = c.id
        LEFT JOIN recharge r ON t.recharge_id = r.id
        WHERE t.id = :id LIMIT 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $transaction_id_to_view);
    $stmt->execute();

    $transaction_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction_details) {
        $error_message = "Transaction record not found.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load transaction details. Please try again later.";
    error_log("View Transaction page - fetch query error: " . $e->getMessage());
}

// Prepare the specific content for this page
$content = "
    <h2>View Transaction Details</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Transactions List</a>";
} elseif ($transaction_details) {
    $user_name_display = $transaction_details['user_first_name'] ? htmlspecialchars($transaction_details['user_first_name'] . ' ' . $transaction_details['user_last_name']) : htmlspecialchars($transaction_details['user_username']);
    $user_link = $transaction_details['user_id'] ? "<a href='../../views/users/view.php?id=" . $transaction_details['user_id'] . "'>" . $user_name_display . "</a>" : 'N/A';
    $card_link = $transaction_details['card_id'] ? "<a href='../../views/cards/view.php?id=" . $transaction_details['card_id'] . "'>Card #" . $transaction_details['card_id'] . "</a>" : 'N/A';
    $recharge_link = $transaction_details['recharge_id'] ? "<a href='../../views/recharge/view.php?id=" . $transaction_details['recharge_id'] . "'>#" . $transaction_details['recharge_id'] . "</a>" : 'N/A (Not Linked Yet)';

    $content .= "
    <div class='card'>
        <div class='card-header'>
            <h5>Transaction Record Information: ID " . htmlspecialchars($transaction_details['id']) . "</h5>
        </div>
        <div class='card-body'>
            <table class='table table-borderless'>
                <tr>
                    <th scope='row'>Transaction ID:</th>
                    <td>" . htmlspecialchars($transaction_details['id']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>User:</th>
                    <td>" . $user_link . "</td>
                </tr>
                <tr>
                    <th scope='row'>Card:</th>
                    <td>" . $card_link . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Card Status (at transaction time):</th>
                    <td>" . htmlspecialchars($transaction_details['card_status']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Transaction Time:</th>
                    <td>" . htmlspecialchars($transaction_details['t_time']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Transaction Type:</th>
                    <td>" . htmlspecialchars($transaction_details['t_type']) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Linked Recharge ID:</th>
                    <td>" . $recharge_link . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Linked Recharge Time:</th>
                    <td>" . ($transaction_details['recharge_time'] ? htmlspecialchars($transaction_details['recharge_time']) : 'N/A') . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Linked Recharge Type:</th>
                    <td>" . ($transaction_details['recharge_type'] ? htmlspecialchars($transaction_details['recharge_type']) : 'N/A') . "</td>
                </tr>
            </table>
        </div>
    </div>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Transactions List</a>
        <a href='edit.php?id=" . $transaction_details['id'] . "' class='btn btn-warning'>Edit Transaction Record</a>
    </div>
    ";
} else {
    // This case handles when the transaction ID was provided but the record wasn't found (error_message is set above)
    $content .= "<p>Unable to load transaction information.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Transactions List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>