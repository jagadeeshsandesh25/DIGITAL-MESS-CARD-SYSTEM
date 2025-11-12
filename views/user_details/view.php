<?php
// views/user_details/view.php

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

// Get user details record ID from query string
$record_id_to_view = $_GET['id'] ?? null;

if (!$record_id_to_view) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$record_details = null;
$error_message = '';

// Fetch the specific user details record's information along with related user, card, transaction, and recharge info
try {
    $query = "
        SELECT udt.id, udt.user_id, udt.card_id, udt.transaction_id, udt.recharge_id,
               u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name, u.email as user_email, u.ph_no as user_phone,
               c.id as card_id_main, c.c_status as card_status, c.balance_credits, c.total_credits,
               t.id as transaction_id_main, t.t_type as transaction_type, t.t_time as transaction_time,
               r.id as recharge_id_main, r.r_type as recharge_type, r.r_time as recharge_time,
               udt.present_pswd, udt.previous_pswd, udt.username as udt_username, udt.contact_details, udt.created_at, udt.updated_at
        FROM user_details_tabel udt
        LEFT JOIN user u ON udt.user_id = u.id
        LEFT JOIN card c ON udt.card_id = c.id
        LEFT JOIN transactions t ON udt.transaction_id = t.id
        LEFT JOIN recharge r ON udt.recharge_id = r.id
        WHERE udt.id = :id LIMIT 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $record_id_to_view);
    $stmt->execute();

    $record_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record_details) {
        $error_message = "User details record not found.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load user details record. Please try again later.";
    error_log("View User Details page - fetch query error: " . $e->getMessage());
}

// Prepare the specific content for this page
$content = "
    <h2>View User Details Record</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to User Details List</a>";
} elseif ($record_details) {
    $user_name_display = $record_details['user_first_name'] ? htmlspecialchars($record_details['user_first_name'] . ' ' . $record_details['user_last_name']) : htmlspecialchars($record_details['user_username']);
    $user_link = $record_details['user_id'] ? "<a href='../../views/users/view.php?id=" . $record_details['user_id'] . "'>" . $user_name_display . "</a>" : 'N/A';
    $card_link = $record_details['card_id_main'] ? "<a href='../../views/cards/view.php?id=" . $record_details['card_id_main'] . "'>Card #" . $record_details['card_id_main'] . "</a>" : 'N/A';
    $transaction_link = $record_details['transaction_id_main'] ? "<a href='../../views/transactions/view.php?id=" . $record_details['transaction_id_main'] . "'>#" . $record_details['transaction_id_main'] . "</a>" : 'N/A';
    $recharge_link = $record_details['recharge_id_main'] ? "<a href='../../views/recharge/view.php?id=" . $record_details['recharge_id_main'] . "'>#" . $record_details['recharge_id_main'] . "</a>" : 'N/A';

    $content .= "
    <div class='card'>
        <div class='card-header'>
            <h5>User Details Record Information: ID " . htmlspecialchars($record_details['id']) . "</h5>
        </div>
        <div class='card-body'>
            <table class='table table-borderless'>
                <tr>
                    <th scope='row'>Record ID:</th>
                    <td>" . htmlspecialchars($record_details['id']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Associated User:</th>
                    <td>" . $user_link . "</td>
                </tr>
                 <tr>
                    <th scope='row'>User Email:</th>
                    <td>" . htmlspecialchars($record_details['user_email']) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>User Phone:</th>
                    <td>" . htmlspecialchars($record_details['user_phone']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Associated Card:</th>
                    <td>" . $card_link . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Card Status:</th>
                    <td>" . htmlspecialchars($record_details['card_status']) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Card Balance (at record time):</th>
                    <td>₹" . number_format($record_details['balance_credits'], 2) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Card Total Credits (at record time):</th>
                    <td>₹" . number_format($record_details['total_credits'], 2) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Associated Transaction:</th>
                    <td>" . $transaction_link . "</td>
                </tr>
                <tr>
                    <th scope='row'>Transaction Type:</th>
                    <td>" . htmlspecialchars($record_details['transaction_type']) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Transaction Time:</th>
                    <td>" . htmlspecialchars($record_details['transaction_time']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Associated Recharge:</th>
                    <td>" . $recharge_link . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Recharge Type:</th>
                    <td>" . htmlspecialchars($record_details['recharge_type']) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Recharge Time:</th>
                    <td>" . htmlspecialchars($record_details['recharge_time']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Username (in this record):</th>
                    <td>" . htmlspecialchars($record_details['udt_username']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Contact Details (in this record):</th>
                    <td>" . htmlspecialchars($record_details['contact_details']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Present Password Hash (in this record):</th>
                    <td>" . htmlspecialchars($record_details['present_pswd']) . "</td> <!-- Consider masking or not showing this -->
                </tr>
                <tr>
                    <th scope='row'>Previous Password Hash (in this record):</th>
                    <td>" . htmlspecialchars($record_details['previous_pswd']) . "</td> <!-- Consider masking or not showing this -->
                </tr>
                <tr>
                    <th scope='row'>Record Created At:</th>
                    <td>" . htmlspecialchars($record_details['created_at']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Record Updated At:</th>
                    <td>" . htmlspecialchars($record_details['updated_at']) . "</td>
                </tr>
            </table>
        </div>
    </div>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to User Details List</a>
        <a href='edit.php?id=" . $record_details['id'] . "' class='btn btn-warning'>Edit Record</a>
    </div>
    ";
} else {
    // This case handles when the record ID was provided but the record wasn't found (error_message is set above)
    $content .= "<p>Unable to load user details record information.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to User Details List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>