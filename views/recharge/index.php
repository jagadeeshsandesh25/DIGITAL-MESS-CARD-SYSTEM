<?php
// views/dashboard/user/index.php - Enhanced UX Dashboard
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'User';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
$database = new Database();
$db = $database->getConnection();
$card_info = null;
try {
    $query = "SELECT * FROM cards WHERE user_id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $card_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $card_info = null;
}
$recent_transactions = [];
try {
    $query = "SELECT t.*, m.item_name FROM transactions t 
              LEFT JOIN menu m ON t.menu_id = m.id 
              WHERE t.user_id = :user_id 
              ORDER BY t.created_at DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_transactions = [];
}
$todays_orders = [];
try {
    $query = "SELECT t.*, m.item_name FROM transactions t 
              LEFT JOIN menu m ON t.menu_id = m.id 
              WHERE t.user_id = :user_id AND DATE(t.created_at) = CURDATE()
              ORDER BY t.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $todays_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $todays_orders = [];
}
$content = "
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-12'>
                <div class='page-title-box'>
                    <div class='page-title-right'>
                        <ol class='breadcrumb m-0'>
                            <li class='breadcrumb-item active'>Dashboard</li>
                        </ol>
                    </div>
                    <h4 class='page-title'>Welcome, " . htmlspecialchars($user_name) . "!</h4>
                </div>
            </div>
        </div>

        <div class='row mb-4'>
            <div class='col-md-3 mb-3'>
                <div class='card stat-card' style='background: linear-gradient(45deg, #6f42c1, #5a32a3); color: white;'>
                    <div class='card-body'>
                        <i class='bi bi-credit-card d-block' style='font-size: 2.5rem;'></i>
                        <h5 class='mt-2'>" . ($card_info ? 'Card Active' : 'No Card') . "</h5>
                        <p class='mb-0'>Your mess card</p>
                    </div>
                </div>
            </div>
            <div class='col-md-3 mb-3'>
                <div class='card stat-card' style='background: linear-gradient(45deg, #28a745, #20c997); color: white;'>
                    <div class='card-body'>
                        <i class='bi bi-currency-rupee d-block' style='font-size: 2.5rem;'></i>
                        <h5 class='mt-2'>₹" . ($card_info ? number_format($card_info['balance'] ?? 0, 2) : '0.00') . "</h5>
                        <p class='mb-0'>Current Balance</p>
                    </div>
                </div>
            </div>
            <div class='col-md-3 mb-3'>
                <div class='card stat-card' style='background: linear-gradient(45deg, #17a2b8, #6f42c1); color: white;'>
                    <div class='card-body'>
                        <i class='bi bi-receipt d-block' style='font-size: 2.5rem;'></i>
                        <h5 class='mt-2'>" . count($recent_transactions) . "</h5>
                        <p class='mb-0'>Recent Orders</p>
                    </div>
                </div>
            </div>
            <div class='col-md-3 mb-3'>
                <div class='card stat-card' style='background: linear-gradient(45deg, #ffc107, #fd7e14); color: white;'>
                    <div class='card-body'>
                        <i class='bi bi-clock-history d-block' style='font-size: 2.5rem;'></i>
                        <h5 class='mt-2'>" . count($todays_orders) . "</h5>
                        <p class='mb-0'>Today's Orders</p>
                    </div>
                </div>
            </div>
        </div>

        <div class='row mb-4'>
            <div class='col-lg-12'>
                <div class='card'>
                    <div class='card-header'>
                        <h5 class='card-title mb-0'>
                            <i class='bi bi-lightning-charge me-2'></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class='card-body'>
                        <div class='row'>
                            <div class='col-md-3 col-6 mb-3'>
                                <a href='profile.php' class='text-decoration-none d-block'>
                                    <div class='quick-action-card'>
                                        <i class='bi bi-person-circle text-primary' style='font-size: 2rem;'></i>
                                        <h6 class='mt-2 mb-0'>My Profile</h6>
                                    </div>
                                </a>
                            </div>
                            <div class='col-md-3 col-6 mb-3'>
                                <a href='my_card.php' class='text-decoration-none d-block'>
                                    <div class='quick-action-card'>
                                        <i class='bi bi-credit-card text-success' style='font-size: 2rem;'></i>
                                        <h6 class='mt-2 mb-0'>My Card</h6>
                                    </div>
                                </a>
                            </div>
                            <div class='col-md-3 col-6 mb-3'>
                                <a href='order_history.php' class='text-decoration-none d-block'>
                                    <div class='quick-action-card'>
                                        <i class='bi bi-clock-history text-info' style='font-size: 2rem;'></i>
                                        <h6 class='mt-2 mb-0'>Order History</h6>
                                    </div>
                                </a>
                            </div>
                            <div class='col-md-3 col-6 mb-3'>
                                <a href='recharge.php' class='text-decoration-none d-block'>
                                    <div class='quick-action-card'>
                                        <i class='bi bi-currency-rupee text-warning' style='font-size: 2rem;'></i>
                                        <h6 class='mt-2 mb-0'>Recharge</h6>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class='row'>
            <div class='col-lg-6 mb-4'>
                <div class='card'>
                    <div class='card-header d-flex justify-content-between align-items-center'>
                        <h5 class='card-title mb-0'>
                            <i class='bi bi-clock-history me-2'></i>
                            Today's Orders
                        </h5>
                        <span class='badge bg-primary'>" . count($todays_orders) . "</span>
                    </div>
                    <div class='card-body'>
                        " . (empty($todays_orders) ? "
                            <div class='text-center py-4'>
                                <i class='bi bi-clock-history' style='font-size: 3rem; color: #ccc;'></i>
                                <h5 class='mt-3'>No orders today</h5>
                                <p class='text-muted'>Start your day with a delicious meal!</p>
                            </div>
                        " : "
                            <div class='table-responsive'>
                                <table class='table table-hover'>
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Amount</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        ") . "
";
if (!empty($todays_orders)) {
    foreach ($todays_orders as $order) {
        $content .= "
                                        <tr>
                                            <td>" . htmlspecialchars($order['item_name'] ?? 'N/A') . "</td>
                                            <td>₹" . number_format($order['amount'] ?? 0, 2) . "</td>
                                            <td>" . date('h:i A', strtotime($order['created_at'])) . "</td>
                                        </tr>
        ";
    }
    $content .= "
                                    </tbody>
                                </table>
                            </div>
        ";
}
$content .= "
                    </div>
                </div>
            </div>

            <div class='col-lg-6 mb-4'>
                <div class='card'>
                    <div class='card-header d-flex justify-content-between align-items-center'>
                        <h5 class='card-title mb-0'>
                            <i class='bi bi-receipt me-2'></i>
                            Recent Orders
                        </h5>
                        <span class='badge bg-info'>" . count($recent_transactions) . "</span>
                    </div>
                    <div class='card-body'>
                        " . (empty($recent_transactions) ? "
                            <div class='text-center py-4'>
                                <i class='bi bi-receipt' style='font-size: 3rem; color: #ccc;'></i>
                                <h5 class='mt-3'>No recent orders</h5>
                                <p class='text-muted'>Your recent orders will appear here</p>
                            </div>
                        " : "
                            <div class='table-responsive'>
                                <table class='table table-hover'>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Item</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        ") . "
";
if (!empty($recent_transactions)) {
    foreach ($recent_transactions as $transaction) {
        $content .= "
                                        <tr>
                                            <td>" . date('M d', strtotime($transaction['created_at'])) . "</td>
                                            <td>" . htmlspecialchars($transaction['item_name'] ?? 'N/A') . "</td>
                                            <td>₹" . number_format($transaction['amount'] ?? 0, 2) . "</td>
                                        </tr>
        ";
    }
    $content .= "
                                    </tbody>
                                </table>
                            </div>
        ";
}
$content .= "
                    </div>
                </div>
            </div>
        </div>

        " . ($card_info && $card_info['balance'] < 100 ? "
        <div class='row'>
            <div class='col-lg-12'>
                <div class='alert alert-warning fade show' role='alert'>
                    <i class='bi bi-exclamation-triangle me-2'></i>
                    Your card balance is low (₹" . number_format($card_info['balance'], 2) . "). Consider recharging soon.
                    <a href='recharge.php' class='btn btn-warning btn-sm ms-2'>Recharge Now</a>
                </div>
            </div>
        </div>
        " : "") . "
    </div>
";
include '../../layouts/app.php';
?>