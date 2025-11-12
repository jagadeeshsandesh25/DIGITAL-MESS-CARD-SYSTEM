<?php
// views/dashboard/user/order_history.php - Enhanced Order History
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get all orders
$order_stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_time DESC");
$order_stmt->execute([$user_id]);
$orders = $order_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_spent = 0;
foreach ($orders as $order) {
    $total_spent += $order['total_amount'];
}

$pending_orders = count(array_filter($orders, function($o) { return $o['status'] === 'pending'; }));
$completed_orders = count(array_filter($orders, function($o) { return $o['status'] === 'completed' || $o['status'] === 'served' || $o['status'] === 'paid'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - Mess Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding-top: 56px; font-family: 'Poppins', sans-serif; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0.75rem; margin-bottom: 1.5rem; }
        .stat-card { text-align: center; padding: 1.5rem; border-radius: 0.75rem; transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(45deg, rgba(255,255,255,0.1), transparent); z-index: 1; }
        .stat-card .content { position: relative; z-index: 2; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .stat-card i { font-size: 2.5rem; margin-bottom: 1rem; transition: transform 0.3s ease; }
        .stat-card:hover i { transform: scale(1.1); }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Mess Management</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="index.php">Dashboard</a>
                <a class="nav-link text-white" href="../../../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h4 class="mb-4">Order History</h4>
                
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #6f42c1, #5a32a3); color: white;">
                            <div class="content">
                                <i class="bi bi-receipt d-block" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-2"><?php echo count($orders); ?></h5>
                                <p class="mb-0">Total Orders</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #28a745, #20c997); color: white;">
                            <div class="content">
                                <i class="bi bi-currency-rupee d-block" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-2">₹<?php echo number_format($total_spent, 2); ?></h5>
                                <p class="mb-0">Total Spent</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #17a2b8, #6f42c1); color: white;">
                            <div class="content">
                                <i class="bi bi-clock-history d-block" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-2"><?php echo $pending_orders; ?></h5>
                                <p class="mb-0">Pending Orders</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #ffc107, #fd7e14); color: white;">
                            <div class="content">
                                <i class="bi bi-check-circle d-block" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-2"><?php echo $completed_orders; ?></h5>
                                <p class="mb-0">Completed Orders</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-receipt me-2"></i>
                                    All Orders
                                </h5>
                                <span class="badge bg-primary"><?php echo count($orders); ?> Orders</span>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($orders)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Order ID</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($orders as $order): ?>
                                                    <tr>
                                                        <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                        <td>
                                                            <?php 
                                                            $status_class = '';
                                                            switch ($order['status']) {
                                                                case 'pending': $status_class = 'bg-warning'; break;
                                                                case 'preparing': $status_class = 'bg-info'; break;
                                                                case 'ready': $status_class = 'bg-primary'; break;
                                                                case 'served': $status_class = 'bg-success'; break;
                                                                case 'cancelled': $status_class = 'bg-danger'; break;
                                                                case 'paid': $status_class = 'bg-success'; break;
                                                                default: $status_class = 'bg-secondary';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span>
                                                        </td>
                                                        <td><?php echo date('M d, Y h:i A', strtotime($order['order_time'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-receipt" style="font-size: 3rem; color: #ccc;"></i>
                                        <h5 class="mt-3">No Orders Found</h5>
                                        <p class="text-muted">You haven't placed any orders yet.</p>
                                        <a href="table_scan.php" class="btn btn-primary">Place Your First Order</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>