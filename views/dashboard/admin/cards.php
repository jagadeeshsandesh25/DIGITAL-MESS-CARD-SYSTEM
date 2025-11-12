<?php
// views/dashboard/admin/cards.php

// Include the authentication check and session details
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php"); // Redirect to login if not authenticated as admin
    exit;
}

$user_name = $_SESSION['username'] ?? 'Admin';

// Include database configuration
require_once '../../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

$cards = [];
$error_message = '';

try {
    // Prepare SQL query to fetch all cards with associated user info
    // Joining with 'user' table to show user names instead of just IDs
    $query = "
        SELECT c.id, c.c_status, c.user_id, u.first_name, u.last_name, u.username, c.balance_credits, c.total_credits, c.created_at, c.expired_at
        FROM card c
        LEFT JOIN user u ON c.user_id = u.id
        ORDER BY c.created_at DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error or handle it appropriately in production
    $error_message = "Could not load cards. Please try again later.";
    error_log("Admin Cards page query error: " . $e->getMessage()); // Log the actual error
}

// Prepare the specific content for this page
$content = "
    <h2>Manage Cards</h2>
    <p>View and manage all user cards and their balances.</p>
";

// Display error message if query failed
if ($error_message) {
    $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
} else {
    // Add a button to create a new card (if applicable - maybe admin can create standalone cards?)
    // For now, assume cards are created when a user is created or via recharge.
    // $content .= "
    // <div class='mb-3'>
    //     <a href='create.php' class='btn btn-success'>Add New Card</a>
    // </div>
    // ";

    // Check if cards exist
    if (!empty($cards)) {
        $content .= "
        <div class='table-responsive'>
            <table class='table table-striped table-hover'>
                <thead class='table-dark'>
                    <tr>
                        <th>ID</th>
                        <th>Card Status</th>
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
            $owner_link = $card['user_id'] ? "<a href='users/view.php?id=" . $card['user_id'] . "'>" . $owner_name . "</a>" : 'N/A';
            $content .= "
                    <tr>
                        <td>" . htmlspecialchars($card['id']) . "</td>
                        <td>" . $status_badge . "</td>
                        <td>" . $owner_link . "</td>
                        <td>" . htmlspecialchars($card['balance_credits']) . "</td>
                        <td>" . htmlspecialchars($card['total_credits']) . "</td>
                        <td>" . htmlspecialchars($card['created_at']) . "</td>
                        <td>" . ($card['expired_at'] ? htmlspecialchars($card['expired_at']) : 'N/A') . "</td>
                        <td>
                            <!-- View details (maybe a modal or a dedicated view page) -->
                            <!-- <a href='view.php?id=" . $card['id'] . "' class='btn btn-sm btn-info'>View</a> -->
                            <!-- Edit card details (status, credits?) -->
                            <a href='../../views/cards/edit.php?id=" . $card['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                            <!-- Recharge card -->
                            <a href='../../views/recharge/create.php?card_id=" . $card['id'] . "' class='btn btn-sm btn-success'>Recharge</a>
                            <!-- Consider adding a delete button with confirmation -->
                            <!-- <a href='delete.php?id=" . $card['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this card?\")'>Delete</a> -->
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
        $content .= "<p>No cards found.</p>";
    }
}

// Include the main layout template
include '../../layouts/app.php'; // Adjust path as needed to point to the layout file

?>