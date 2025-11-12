<?php
// views/cards/index.php

// Include the authentication check and session details
// This page should typically be accessible only by admins, maybe users for their own cards
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'user')) {
    // Allow admin and user roles, but not waiter for this specific list (waiter might see assigned cards differently)
    header("Location: ../../login.php"); // Redirect to login if not authenticated as admin/user
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'] ?? 'User';

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

$cards = [];
$error_message = '';

try {
    // Prepare SQL query to fetch cards
    // Admins see all cards, users see only their own
    $query = "
        SELECT c.id, c.c_status, c.user_id, u.first_name, u.last_name, u.username, c.balance_credits, c.total_credits, c.created_at, c.expired_at
        FROM card c
        LEFT JOIN user u ON c.user_id = u.id
    ";

    if ($user_role === 'user') {
        $query .= " WHERE c.user_id = :user_id "; // Filter for user's own cards
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
    } else { // Admin
        $query .= " ORDER BY c.created_at DESC "; // Order by creation for admin
        $stmt = $db->prepare($query);
    }

    $stmt->execute();
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error or handle it appropriately in production
    $error_message = "Could not load cards. Please try again later.";
    error_log("Cards index page query error: " . $e->getMessage()); // Log the actual error
}

// Prepare the specific content for this page
$content = "
    <h2>Manage Cards</h2>
    <p>View, edit, and manage " . ($user_role === 'admin' ? 'all' : 'your') . " cards.</p>
";

// Display error message if query failed
if ($error_message) {
    $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
} else {
    // Add a button to create a new card (maybe only for admin, or for user to request/link a card?)
    // For users, maybe this is more about viewing/recharging their existing card(s).
    if ($user_role === 'admin') {
        $content .= "
        <div class='mb-3'>
            <!-- Admin might create a card directly or link it to a user -->
            <!-- <a href='create.php' class='btn btn-success'>Add New Card</a> -->
            <!-- Or maybe a button to generate a new card for a user? -->
            <a href='../../views/recharge/create.php' class='btn btn-success'>Recharge a Card</a> <!-- Recharge might be a common action -->
        </div>
        ";
    }
    // For users, the primary action might be to recharge their card, which could be linked from here or from the dashboard/my_card page.

    // Check if cards exist
    if (!empty($cards)) {
        $content .= "
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
            $owner_link = $card['user_id'] ? "<a href='../../views/users/view.php?id=" . $card['user_id'] . "'>" . $owner_name . "</a>" : 'N/A';

            $content .= "
                    <tr>
                        <td>" . htmlspecialchars($card['id']) . "</td>
                        <td>" . $status_badge . "</td>
                        <td>" . $owner_link . "</td>
                        <td>₹" . number_format($card['balance_credits'], 2) . "</td>
                        <td>₹" . number_format($card['total_credits'], 2) . "</td>
                        <td>" . htmlspecialchars($card['created_at']) . "</td>
                        <td>" . ($card['expired_at'] ? htmlspecialchars($card['expired_at']) : 'N/A') . "</td>
                        <td>
                            <a href='view.php?id=" . $card['id'] . "' class='btn btn-sm btn-info'>View</a>
                            <a href='edit.php?id=" . $card['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                            <!-- Recharge card -->
                            <a href='../../views/recharge/create.php?card_id=" . $card['id'] . "' class='btn btn-sm btn-success'>Recharge</a>
                            <!-- Delete button might be restricted to admin only -->
                            " . ($user_role === 'admin' ? "<a href='delete.php?id=" . $card['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete card ID " . $card['id'] . "? This action cannot be undone.\")'>Delete</a>" : "") . "
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
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>