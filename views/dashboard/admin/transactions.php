<?php
// views/dashboard/admin/transactions.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("Database connection failed.");
}

// ✅ Fetch all orders safely
$orders_stmt = $db->prepare("
    SELECT 
        o.*, 
        u.username AS user_name
    FROM orders o
    JOIN user u ON o.user_id = u.id
    ORDER BY o.order_time DESC
");
$orders_stmt->execute();
$all_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Calculate stats
$total_orders = count($all_orders);
$total_credits_used = 0;
$pending_orders = 0;
$completed_orders = 0;
$cancelled_orders = 0;

foreach ($all_orders as $order) {
    $total_credits_used += (int)($order['credits_used'] ?? 0);

    $status = strtolower($order['status'] ?? '');
    if (in_array($status, ['pending', 'preparing'])) $pending_orders++;
    elseif (in_array($status, ['ready', 'served', 'paid'])) $completed_orders++;
    elseif ($status === 'cancelled') $cancelled_orders++;
}

ob_start();
?>

<div class="page-title-box mb-4">
    <h2><i class="bi bi-cash-stack"></i> Transactions Overview</h2>
    <p class="text-muted mb-0">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(45deg,#6f42c1,#5a32a3);">
            <div class="card-body text-center">
                <i class="bi bi-receipt fs-1"></i>
                <h4 class="mt-2"><?= $total_orders ?></h4>
                <p>Total Orders</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(45deg,#198754,#20c997);">
            <div class="card-body text-center">
                <i class="bi bi-currency-rupee fs-1"></i>
                <h4 class="mt-2"><?= number_format($total_credits_used, 0) ?></h4>
                <p>Total Credits Used</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(45deg,#17a2b8,#6f42c1);">
            <div class="card-body text-center">
                <i class="bi bi-hourglass-split fs-1"></i>
                <h4 class="mt-2"><?= $pending_orders ?></h4>
                <p>Pending Orders</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(45deg,#ffc107,#fd7e14);">
            <div class="card-body text-center">
                <i class="bi bi-check2-circle fs-1"></i>
                <h4 class="mt-2"><?= $completed_orders ?></h4>
                <p>Completed Orders</p>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-table"></i> All Transactions</h5>
        <span class="badge bg-primary"><?= $total_orders ?> Records</span>
    </div>
    <div class="card-body p-3">
        <?php if (!empty($all_orders)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Table</th>
                            <th>Meal Type</th>
                            <th>Plan Type</th>
                            <th>Credits Used</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Order Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_orders as $order): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($order['id']) ?></td>
                                <td><?= htmlspecialchars($order['user_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($order['table_qr'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($order['meal_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($order['plan_type'] ?? 'N/A') ?></td>
                                <td><?= (int)($order['credits_used'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($order['items'] ?? 'N/A') ?></td>
                                <td>
                                    <?php
                                    $status = strtolower($order['status'] ?? 'unknown');
                                    $status_badge = match ($status) {
                                        'pending'   => 'bg-warning',
                                        'preparing' => 'bg-info',
                                        'ready'     => 'bg-primary',
                                        'served', 'paid' => 'bg-success',
                                        'cancelled' => 'bg-danger',
                                        default => 'bg-secondary',
                                    };
                                    ?>
                                    <span class="badge <?= $status_badge ?>">
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td><?= !empty($order['order_time']) ? date('d M Y, h:i A', strtotime($order['order_time'])) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-receipt fs-2"></i>
                <p class="mt-2 mb-0">No transactions found yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layouts/app.php';
?>