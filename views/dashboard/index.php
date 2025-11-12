<?php
// views/dashboard/index.php

// Include the authentication check and session details
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php"); // Redirect to login if not authenticated
    exit;
}

$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'] ?? 'User';

// Include database configuration to fetch some summary data (optional for dashboard)
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

$summary_data = [
    'total_users' => 0,
    'active_cards' => 0,
    'todays_transactions' => 0,
    'recharge_amount_today' => 0
];

try {
    if ($user_role === 'admin') {
        // Fetch summary data for admin dashboard
        $stmt = $db->query("SELECT COUNT(*) FROM user");
        $summary_data['total_users'] = $stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM card WHERE c_status = 'Active'");
        $summary_data['active_cards'] = $stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM transactions WHERE DATE(t_time) = CURDATE()");
        $summary_data['todays_transactions'] = $stmt->fetchColumn();

        $stmt = $db->query("SELECT COALESCE(SUM(amount), 0) FROM recharge WHERE DATE(r_time) = CURDATE()"); // Assuming an 'amount' field exists in recharge or related table
        // Note: The schema doesn't explicitly show an 'amount' in the 'recharge' table. You might need to join with 'transactions' or another table, or add an 'amount' column to 'recharge'.
        // For now, this query might not work directly based on the provided schema. We'll leave it as a placeholder.
        $summary_data['recharge_amount_today'] = $stmt->fetchColumn(); // This might return 0 if the query fails or 'amount' doesn't exist

    } elseif ($user_role === 'user') {
        // Fetch user-specific data (e.g., card balance, recent transactions)
        $user_id = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT balance_credits FROM card WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1"); // Get latest card balance
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $card_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_balance = $card_info['balance_credits'] ?? 0;

         $stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = :user_id AND DATE(t_time) = CURDATE()");
         $stmt->bindParam(':user_id', $user_id);
         $stmt->execute();
         $user_todays_transactions = $stmt->fetchColumn();

    } elseif ($user_role === 'waiter') {
        // Fetch waiter-specific data (e.g., assigned tables, pending orders)
        $user_id = $_SESSION['user_id'];
        // Example query for assigned tables (assuming 'tabels' table links to waiter via user_id)
        $stmt = $db->prepare("SELECT COUNT(*) FROM tabels WHERE user_id = :user_id AND time >= CURDATE()"); // Tables assigned today
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $waiter_assigned_tables = $stmt->fetchColumn();

        // Example query for pending orders (if status exists in 'tabels' or related order table - schema doesn't show status clearly)
        // This is a placeholder as the schema for 'tabels' doesn't explicitly define an order status.
        // You might need a separate 'orders' table or a status column in 'tabels'.
        // $stmt = $db->prepare("SELECT COUNT(*) FROM tabels WHERE user_id = :user_id AND status = 'pending'");
        // $stmt->bindParam(':user_id', $user_id);
        // $stmt->execute();
        // $waiter_pending_orders = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    // Log the error or handle it appropriately in production
    error_log("Dashboard summary query error: " . $e->getMessage()); // Log the actual error
    // You might want to set default values or show a specific message
    // $error_message = "Could not load dashboard data.";
}


// Prepare the specific content for this page
$content = "
    <h2>Dashboard Overview</h2>
    <p>Welcome back, " . htmlspecialchars($user_name) . " (Role: " . htmlspecialchars($user_role) . ").</p>
";

if ($user_role === 'admin') {
    $content .= "
    <div class='row'>
        <div class='col-md-3 mb-3'>
            <div class='card text-white bg-primary'>
                <div class='card-body'>
                    <h5 class='card-title'>Total Users</h5>
                    <p class='card-text display-5'>" . $summary_data['total_users'] . "</p>
                </div>
            </div>
        </div>
        <div class='col-md-3 mb-3'>
            <div class='card text-white bg-success'>
                <div class='card-body'>
                    <h5 class='card-title'>Active Cards</h5>
                    <p class='card-text display-5'>" . $summary_data['active_cards'] . "</p>
                </div>
            </div>
        </div>
        <div class='col-md-3 mb-3'>
            <div class='card text-white bg-info'>
                <div class='card-body'>
                    <h5 class='card-title'>Today's Transactions</h5>
                    <p class='card-text display-5'>" . $summary_data['todays_transactions'] . "</p>
                </div>
            </div>
        </div>
        <div class='col-md-3 mb-3'>
            <div class='card text-white bg-warning'>
                <div class='card-body'>
                    <h5 class='card-title'>Recharge Amount (Today)</h5>
                    <p class='card-text display-5'>₹" . number_format($summary_data['recharge_amount_today'], 2) . "</p>
                </div>
            </div>
        </div>
    </div>
    ";
} elseif ($user_role === 'user') {
    $content .= "
    <div class='row'>
        <div class='col-md-6 mb-3'>
            <div class='card'>
                <div class='card-body'>
                    <h5 class='card-title'>My Card Balance</h5>
                    <p class='card-text display-5'>₹" . number_format($user_balance, 2) . "</p>
                    <a href='user/my_card.php' class='btn btn-primary'>View Details</a>
                </div>
            </div>
        </div>
        <div class='col-md-6 mb-3'>
            <div class='card'>
                <div class='card-body'>
                    <h5 class='card-title'>Today's Transactions</h5>
                    <p class='card-text display-5'>" . $user_todays_transactions . "</p>
                    <a href='user/order_history.php' class='btn btn-primary'>View History</a>
                </div>
            </div>
        </div>
    </div>
    ";
} elseif ($user_role === 'waiter') {
    $content .= "
    <div class='row'>
        <div class='col-md-6 mb-3'>
            <div class='card'>
                <div class='card-body'>
                    <h5 class='card-title'>Assigned Tables (Today)</h5>
                    <p class='card-text display-5'>" . $waiter_assigned_tables . "</p>
                    <a href='waiter/assigned_tables.php' class='btn btn-primary'>View Tables</a>
                </div>
            </div>
        </div>
        <div class='col-md-6 mb-3'>
            <div class='card'>
                <div class='card-body'>
                    <h5 class='card-title'>Pending Orders</h5>
                    <p class='card-text display-5'>N/A <!-- Placeholder, requires specific order status logic --></p>
                    <a href='waiter/take_order.php' class='btn btn-primary'>Take Order</a>
                </div>
            </div>
        </div>
    </div>
    ";
}

// Add role-specific quick links section
$content .= "
<div class='row mt-4'>
    <div class='col-12'>
        <div class='card'>
            <div class='card-header'>
                <h5>Quick Links</h5>
            </div>
            <div class='card-body'>
";

switch ($user_role) {
    case 'admin':
        $content .= "
            <a href='admin/users.php' class='btn btn-outline-primary me-2 mb-2'>Manage Users</a>
            <a href='admin/cards.php' class='btn btn-outline-success me-2 mb-2'>Manage Cards</a>
            <a href='admin/transactions.php' class='btn btn-outline-info me-2 mb-2'>View Transactions</a>
            <a href='menu/index.php' class='btn btn-outline-warning me-2 mb-2'>Manage Menu</a>
            <a href='tables/index.php' class='btn btn-outline-secondary me-2 mb-2'>Manage Tables</a>
        ";
        break;
    case 'user':
        $content .= "
            <a href='user/my_card.php' class='btn btn-outline-primary me-2 mb-2'>My Card</a>
            <a href='menu/index.php' class='btn btn-outline-success me-2 mb-2'>View Menu</a>
            <a href='recharge/index.php' class='btn btn-outline-warning me-2 mb-2'>Recharge Card</a>
            <a href='user/order_history.php' class='btn btn-outline-info me-2 mb-2'>Order History</a>
        ";
        break;
    case 'waiter':
        $content .= "
            <a href='waiter/assigned_tables.php' class='btn btn-outline-primary me-2 mb-2'>Assigned Tables</a>
            <a href='waiter/take_order.php' class='btn btn-outline-success me-2 mb-2'>Take Order</a>
        ";
        break;
    default:
        $content .= "<p>Invalid role.</p>";
}

$content .= "
            </div>
        </div>
    </div>
</div> <!-- End Quick Links Row -->

";

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>