<?php
// views/user_details/index.php

// Include the authentication check and session details
// This page should typically be accessible only by admins due to sensitive nature of details
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

$user_details_records = [];
$error_message = '';

try {
    // Prepare SQL query to fetch all user details records with related user, card, transaction, and recharge info
    // Joining with user, card, transactions, and recharge tables for more context
    $query = "
        SELECT udt.id, udt.user_id, udt.card_id, udt.transaction_id, udt.recharge_id,
               u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
               c.id as card_id_main, c.c_status as card_status,
               t.id as transaction_id_main, t.t_type as transaction_type, t.t_time as transaction_time,
               r.id as recharge_id_main, r.r_type as recharge_type, r.r_time as recharge_time,
               udt.present_pswd, udt.previous_pswd, udt.username as udt_username, udt.contact_details, udt.created_at, udt.updated_at
        FROM user_details_tabel udt
        LEFT JOIN user u ON udt.user_id = u.id
        LEFT JOIN card c ON udt.card_id = c.id
        LEFT JOIN transactions t ON udt.transaction_id = t.id
        LEFT JOIN recharge r ON udt.recharge_id = r.id
        ORDER BY udt.created_at DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $user_details_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error or handle it appropriately in production
    $error_message = "Could not load user details records. Please try again later.";
    error_log("User Details index page query error: " . $e->getMessage()); // Log the actual error
}

// Prepare the specific content for this page
$content = "
    <h2>Manage User Details Records</h2>
    <p>View and manage user-specific detail records (e.g., password history, contact changes). <strong>Access restricted to admins.</strong></p>
";

// Display error message if query failed
if ($error_message) {
    $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
} else {
    // Add a button to create a new user details record (might be rare, usually auto-generated)
    // $content .= "
    // <div class='mb-3'>
    //     <a href='create.php' class='btn btn-success'>Add New User Details Record</a>
    // </div>
    // ";

    // Check if user details records exist
    if (!empty($user_details_records)) {
        $content .= "
        <div class='table-responsive'>
            <table class='table table-striped table-hover'>
                <thead class='table-dark'>
                    <tr>
                        <th>ID</th>
                        <th>Associated User</th>
                        <th>Associated Card</th>
                        <th>Associated Transaction</th>
                        <th>Associated Recharge</th>
                        <th>Username (in record)</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($user_details_records as $record) {
            $user_name_display = $record['user_first_name'] ? htmlspecialchars($record['user_first_name'] . ' ' . $record['user_last_name']) : htmlspecialchars($record['user_username']);
            $user_link = $record['user_id'] ? "<a href='../../views/users/view.php?id=" . $record['user_id'] . "'>" . $user_name_display . "</a>" : 'N/A';
            $card_link = $record['card_id_main'] ? "<a href='../../views/cards/view.php?id=" . $record['card_id_main'] . "'>Card #" . $record['card_id_main'] . "</a>" : 'N/A';
            $transaction_link = $record['transaction_id_main'] ? "<a href='../../views/transactions/view.php?id=" . $record['transaction_id_main'] . "'>#" . $record['transaction_id_main'] . "</a>" : 'N/A';
            $recharge_link = $record['recharge_id_main'] ? "<a href='../../views/recharge/view.php?id=" . $record['recharge_id_main'] . "'>#" . $record['recharge_id_main'] . "</a>" : 'N/A';

            $content .= "
                    <tr>
                        <td>" . htmlspecialchars($record['id']) . "</td>
                        <td>" . $user_link . "</td>
                        <td>" . $card_link . "</td>
                        <td>" . $transaction_link . "</td>
                        <td>" . $recharge_link . "</td>
                        <td>" . htmlspecialchars($record['udt_username']) . "</td>
                        <td>" . htmlspecialchars($record['created_at']) . "</td>
                        <td>" . htmlspecialchars($record['updated_at']) . "</td>
                        <td>
                            <a href='view.php?id=" . $record['id'] . "' class='btn btn-sm btn-info'>View</a>
                            <a href='edit.php?id=" . $record['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                            <a href='delete.php?id=" . $record['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete user details record ID " . $record['id'] . "? This action cannot be undone.\")'>Delete</a>
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
        $content .= "<p>No user details records found.</p>";
    }
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>