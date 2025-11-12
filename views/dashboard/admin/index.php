<?php
// views/dashboard/admin/index.php — Cleaned up & fixed "Manage Tables" link

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'Admin';

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Dashboard statistics
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'total_cards' => 0,
    'total_transactions' => 0,
    'total_recharges' => 0,
    'total_orders' => 0
];

try {
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM user WHERE role = 'user'");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Active users
    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM user WHERE role = 'user' AND status = 1");
    $stmt->execute();
    $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total cards
    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM card");
    $stmt->execute();
    $stats['total_cards'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total transactions
    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM transactions");
    $stmt->execute();
    $stats['total_transactions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total recharges
    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM recharge");
    $stmt->execute();
    $stats['total_recharges'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // ✅ FIXED: your table name typo (was "tabels")
    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM tables");
    $stmt->execute();
    $stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

} catch (PDOException $e) {
    error_log("Dashboard Stats Error: " . $e->getMessage());
}

// Start page content
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
        <div class='col-md-2 mb-3'>
            <div class='card stat-card' style='background: linear-gradient(45deg,#6f42c1,#5a32a3);color:white;'>
                <div class='content text-center'>
                    <i class='bi bi-people d-block' style='font-size:2rem;'></i>
                    <h5 class='mt-2'>{$stats['total_users']}</h5>
                    <p class='mb-0'>Total Users</p>
                </div>
            </div>
        </div>
        <div class='col-md-2 mb-3'>
            <div class='card stat-card' style='background:linear-gradient(45deg,#28a745,#20c997);color:white;'>
                <div class='content text-center'>
                    <i class='bi bi-person-check d-block' style='font-size:2rem;'></i>
                    <h5 class='mt-2'>{$stats['active_users']}</h5>
                    <p class='mb-0'>Active Users</p>
                </div>
            </div>
        </div>
        <div class='col-md-2 mb-3'>
            <div class='card stat-card' style='background:linear-gradient(45deg,#17a2b8,#6f42c1);color:white;'>
                <div class='content text-center'>
                    <i class='bi bi-credit-card d-block' style='font-size:2rem;'></i>
                    <h5 class='mt-2'>{$stats['total_cards']}</h5>
                    <p class='mb-0'>Total Cards</p>
                </div>
            </div>
        </div>
        <div class='col-md-2 mb-3'>
            <div class='card stat-card' style='background:linear-gradient(45deg,#ffc107,#fd7e14);color:white;'>
                <div class='content text-center'>
                    <i class='bi bi-receipt d-block' style='font-size:2rem;'></i>
                    <h5 class='mt-2'>{$stats['total_transactions']}</h5>
                    <p class='mb-0'>Transactions</p>
                </div>
            </div>
        </div>
        <div class='col-md-2 mb-3'>
            <div class='card stat-card' style='background:linear-gradient(45deg,#dc3545,#e74c3c);color:white;'>
                <div class='content text-center'>
                    <i class='bi bi-currency-rupee d-block' style='font-size:2rem;'></i>
                    <h5 class='mt-2'>{$stats['total_recharges']}</h5>
                    <p class='mb-0'>Recharges</p>
                </div>
            </div>
        </div>
        <div class='col-md-2 mb-3'>
            <div class='card stat-card' style='background:linear-gradient(45deg,#6c757d,#5a6268);color:white;'>
                <div class='content text-center'>
                    <i class='bi bi-list-check d-block' style='font-size:2rem;'></i>
                    <h5 class='mt-2'>{$stats['total_orders']}</h5>
                    <p class='mb-0'>Tables</p>
                </div>
            </div>
        </div>
    </div>

    <div class='row mb-4'>
        <div class='col-lg-12'>
            <div class='card'>
                <div class='card-header'>
                    <h5 class='card-title mb-0'><i class='bi bi-lightning-charge me-2'></i>Quick Actions</h5>
                </div>
                <div class='card-body'>
                    <div class='row'>
                        <div class='col-md-3 col-6 mb-3'>
                            <a href='users.php' class='text-decoration-none d-block'>
                                <div class='quick-action-card'>
                                    <i class='bi bi-people text-primary' style='font-size:2rem;'></i>
                                    <h6 class='mt-2 mb-0'>Manage Users</h6>
                                </div>
                            </a>
                        </div>
                        <div class='col-md-3 col-6 mb-3'>
                            <a href='assign_plan.php' class='text-decoration-none d-block'>
                                <div class='quick-action-card'>
                                    <i class='bi bi-credit-card text-success' style='font-size:2rem;'></i>
                                    <h6 class='mt-2 mb-0'>Assign Plan</h6>
                                </div>
                            </a>
                        </div>
                        <div class='col-md-3 col-6 mb-3'>
                            <a href='menu.php' class='text-decoration-none d-block'>
                                <div class='quick-action-card'>
                                    <i class='bi bi-list text-warning' style='font-size:2rem;'></i>
                                    <h6 class='mt-2 mb-0'>Manage Menu</h6>
                                </div>
                            </a>
                        </div>
                        <div class='col-md-3 col-6 mb-3'>
                            <a href='transactions.php' class='text-decoration-none d-block'>
                                <div class='quick-action-card'>
                                    <i class='bi bi-receipt text-danger' style='font-size:2rem;'></i>
                                    <h6 class='mt-2 mb-0'>Transactions</h6>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class='row'>
                        <div class='col-md-3 col-6 mb-3'>
                            <a href='orders.php' class='text-decoration-none d-block'>
                                <div class='quick-action-card'>
                                    <i class='bi bi-list-check text-success' style='font-size:2rem;'></i>
                                    <h6 class='mt-2 mb-0'>Orders</h6>
                                </div>
                            </a>
                        </div>

                        <!-- ✅ Fixed Manage Tables link -->
                        <div class='col-md-3 col-6 mb-3'>
                            <a href='tables.php' class='text-decoration-none d-block'>
                                <div class='quick-action-card'>
                                    <i class='bi bi-table text-primary' style='font-size:2rem;'></i>
                                    <h6 class='mt-2 mb-0'>Manage Tables</h6>
                                </div>
                            </a>
                        </div>

                        <div class='col-md-3 col-6 mb-3'>
                            <a href='reports.php' class='text-decoration-none d-block'>
                                <div class='quick-action-card'>
                                    <i class='bi bi-bar-chart text-info' style='font-size:2rem;'></i>
                                    <h6 class='mt-2 mb-0'>Reports</h6>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
";

include '../../layouts/app.php';
?>
