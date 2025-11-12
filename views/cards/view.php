<?php
// views/cards/view.php

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

// Get card ID from query string
$card_id_to_view = $_GET['id'] ?? null;

if (!$card_id_to_view) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$card_details = null;
$user_details = null; // Store associated user info
$error_message = '';

// Fetch the specific card's details and associated user info
try {
    // Join card with user to get owner name
    $query = "
        SELECT c.id, c.c_status, c.user_id, c.recharge_details, c.created_at, c.expired_at, c.balance_credits, c.total_credits, u.first_name, u.last_name, u.username
        FROM card c
        LEFT JOIN user u ON c.user_id = u.id
        WHERE c.id = :id LIMIT 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $card_id_to_view);
    $stmt->execute();

    $card_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$card_details) {
        $error_message = "Card not found.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load card details. Please try again later.";
    error_log("View Card page - fetch query error: " . $e->getMessage());
}

// Prepare the specific content for this page
$content = "
    <h2>View Card Details</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Cards List</a>";
} elseif ($card_details) {
    $status_badge = $card_details['c_status'] === 'Active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
    $owner_name = $card_details['first_name'] ? htmlspecialchars($card_details['first_name'] . ' ' . $card_details['last_name']) : htmlspecialchars($card_details['username']);
    $owner_link = $card_details['user_id'] ? "<a href='../../views/users/view.php?id=" . $card_details['user_id'] . "'>" . $owner_name . "</a>" : 'N/A';
    $expiry_status = $card_details['expired_at'] ? ($card_details['expired_at'] < date('Y-m-d H:i:s') ? '<span class="badge bg-danger">Expired</span>' : '<span class="badge bg-warning">Expires: ' . htmlspecialchars($card_details['expired_at']) . '</span>') : '<span class="badge bg-secondary">No Expiry</span>';

    $content .= "
    <div class='card'>
        <div class='card-header'>
            <h5>Card Information: ID " . htmlspecialchars($card_details['id']) . "</h5>
        </div>
        <div class='card-body'>
            <table class='table table-borderless'>
                <tr>
                    <th scope='row'>Card ID:</th>
                    <td>" . htmlspecialchars($card_details['id']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Status:</th>
                    <td>" . $status_badge . "</td>
                </tr>
                <tr>
                    <th scope='row'>Owner:</th>
                    <td>" . $owner_link . "</td>
                </tr>
                <tr>
                    <th scope='row'>Current Balance:</th>
                    <td>₹" . number_format($card_details['balance_credits'], 2) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Total Credits:</th>
                    <td>₹" . number_format($card_details['total_credits'], 2) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Expiry Date:</th>
                    <td>" . $expiry_status . "</td>
                </tr>
                <tr>
                    <th scope='row'>Issued On:</th>
                    <td>" . htmlspecialchars($card_details['created_at']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Recharge Details ID (FK):</th>
                    <td>" . ($card_details['recharge_details'] ? "<a href='../../views/recharge/view.php?id=" . $card_details['recharge_details'] . "'>" . $card_details['recharge_details'] . "</a>" : 'N/A') . "</td>
                </tr>
            </table>
        </div>
    </div>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Cards List</a>
        <a href='edit.php?id=" . $card_details['id'] . "' class='btn btn-warning'>Edit Card</a>
        <a href='../../views/recharge/create.php?card_id=" . $card_details['id'] . "' class='btn btn-success'>Recharge Card</a>
    </div>
    ";
} else {
    // This case handles when the card ID was provided but the card wasn't found (error_message is set above)
    $content .= "<p>Unable to load card information.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Cards List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>