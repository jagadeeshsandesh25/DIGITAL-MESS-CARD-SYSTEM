<?php
// views/recharge/view.php

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

// Get recharge ID from query string
$recharge_id_to_view = $_GET['id'] ?? null;

if (!$recharge_id_to_view) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$recharge_details = null;
$error_message = '';

// Fetch the specific recharge record's details along with related user, card, and transaction info
try {
    $query = "
        SELECT r.id, r.r_type, r.user_id, r.card_id, r.transaction_id, r.r_time,
               u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
               c.balance_credits as card_balance, c.total_credits as card_total,
               t.t_time as transaction_time, t.t_type as transaction_type
        FROM recharge r
        LEFT JOIN user u ON r.user_id = u.id
        LEFT JOIN card c ON r.card_id = c.id
        LEFT JOIN transactions t ON r.transaction_id = t.id
        WHERE r.id = :id LIMIT 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $recharge_id_to_view);
    $stmt->execute();

    $recharge_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recharge_details) {
        $error_message = "Recharge record not found.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load recharge details. Please try again later.";
    error_log("View Recharge page - fetch query error: " . $e->getMessage());
}

// Prepare the specific content for this page
$content = "
    <h2>View Recharge Details</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Recharges List</a>";
} elseif ($recharge_details) {
    $user_name_display = $recharge_details['user_first_name'] ? htmlspecialchars($recharge_details['user_first_name'] . ' ' . $recharge_details['user_last_name']) : htmlspecialchars($recharge_details['user_username']);
    $user_link = $recharge_details['user_id'] ? "<a href='../../views/users/view.php?id=" . $recharge_details['user_id'] . "'>" . $user_name_display . "</a>" : 'N/A';
    $card_link = $recharge_details['card_id'] ? "<a href='../../views/cards/view.php?id=" . $recharge_details['card_id'] . "'>Card #" . $recharge_details['card_id'] . "</a>" : 'N/A';
    $transaction_link = $recharge_details['transaction_id'] ? "<a href='../../views/transactions/view.php?id=" . $recharge_details['transaction_id'] . "'>#" . $recharge_details['transaction_id'] . "</a>" : 'N/A (Not Linked Yet)';

    $content .= "
    <div class='card'>
        <div class='card-header'>
            <h5>Recharge Record Information: ID " . htmlspecialchars($recharge_details['id']) . "</h5>
        </div>
        <div class='card-body'>
            <table class='table table-borderless'>
                <tr>
                    <th scope='row'>Recharge ID:</th>
                    <td>" . htmlspecialchars($recharge_details['id']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Type:</th>
                    <td>" . htmlspecialchars($recharge_details['r_type']) . "</td>
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
                    <th scope='row'>Recharge Time:</th>
                    <td>" . htmlspecialchars($recharge_details['r_time']) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Linked Transaction ID:</th>
                    <td>" . $transaction_link . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Linked Transaction Time:</th>
                    <td>" . ($recharge_details['transaction_time'] ? htmlspecialchars($recharge_details['transaction_time']) : 'N/A') . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Linked Transaction Type:</th>
                    <td>" . ($recharge_details['transaction_type'] ? htmlspecialchars($recharge_details['transaction_type']) : 'N/A') . "</td>
                </tr>
            </table>
        </div>
    </div>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Recharges List</a>
        <a href='edit.php?id=" . $recharge_details['id'] . "' class='btn btn-warning'>Edit Recharge Record</a>
    </div>
    ";
} else {
    // This case handles when the recharge ID was provided but the record wasn't found (error_message is set above)
    $content .= "<p>Unable to load recharge information.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Recharges List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>