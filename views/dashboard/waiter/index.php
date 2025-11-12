<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$orders_stmt = $db->prepare("
    SELECT o.*, u.username, t.t_qr as table_qr_name
    FROM orders o
    JOIN user u ON o.user_id = u.id
    LEFT JOIN tables t ON o.table_id = t.id
    WHERE o.status IN ('pending', 'preparing')
    ORDER BY o.order_time ASC
");
$orders_stmt->execute();
$pending_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

$completed_orders_stmt = $db->prepare("
    SELECT o.*, u.username, t.t_qr as table_qr_name
    FROM orders o
    JOIN user u ON o.user_id = u.id
    LEFT JOIN tables t ON o.table_id = t.id
    WHERE o.status IN ('ready', 'served', 'paid')
    ORDER BY o.order_time DESC
    LIMIT 10
");
$completed_orders_stmt->execute();
$completed_orders = $completed_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiter Dashboard</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #284194;
            --secondary-color: #ff8c42;
            --success-color: #6ee7b7;
            --warning-color: #fcd34d;
            --info-color: #a78bfa;
            --dark-bg: #121212;
            --light-bg: #f5f7fa;
        }

        body {
            background-color: var(--light-bg);
            color: #1e293b;
            font-family: 'Poppins', sans-serif;
            padding-top: 72px;
            margin: 0;
        }

        body.dark-mode {
            background-color: var(--dark-bg);
            color: #e2e8f0;
        }

        .navbar {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 600;
            letter-spacing: -0.5px;
            color: var(--primary-color) !important;
        }

        .navbar-nav .nav-link {
            color: #64748b !important;
            font-weight: 500;
            padding: 0.5rem 0.75rem !important;
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary-color) !important;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            margin-bottom: 1.5rem;
            transition: transform 0.25s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .order-card {
            background: white;
            border-radius: 14px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--secondary-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.25s ease;
        }

        body.dark-mode .card,
        body.dark-mode .order-card {
            background: #1e1e1e;
        }

        .order-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-weight: 600;
        }

        .status-badge.pending { background-color: var(--warning-color); color: #92400e; }
        .status-badge.preparing { background-color: #93c5fd; color: #1e40af; }
        .status-badge.ready { background-color: #86efac; color: #166534; }
        .status-badge.served { background-color: var(--info-color); color: #4c1d95; }
        .status-badge.paid { background-color: var(--success-color); color: #065f46; }

        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .table thead th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            padding: 0.75rem 1rem;
        }

        .table tbody td {
            padding: 0.75rem 1rem;
        }

        body.dark-mode .table thead th {
            background: #1e293b;
            color: #cbd5e1;
        }

        .btn-brand {
            background: linear-gradient(to right, var(--secondary-color), #e6762f);
            border: none;
            padding: 0.45rem 1rem;
            font-weight: 600;
            border-radius: 8px;
            font-size: 0.875rem;
        }

        .btn-brand:hover {
            opacity: 0.92;
        }

        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .info-card {
            background: linear-gradient(135deg, #ffeedb, #fff5e6);
            border: 1px solid #ffdbc7;
        }

        body.dark-mode .info-card {
            background: linear-gradient(135deg, #2d241b, #221d17);
            border-color: #4a3a2a;
            color: #fde68a;
        }

        .refresh-btn {
            font-size: 0.85rem;
        }

        .order-details {
            background: #f8fafc;
            padding: 0.5rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }

        body.dark-mode .order-details {
            background: #2d3748;
            color: #e2e8f0;
        }

        /* Mobile-specific styles */
        @media (max-width: 576px) {
            body { padding-top: 64px; }
            
            .navbar-brand {
                font-size: 1.1rem;
            }

            .navbar-nav .nav-link {
                padding: 0.5rem 0.5rem !important;
                font-size: 0.85rem;
            }

            .main-content {
                padding: 0 0.75rem;
            }

            .card-header {
                padding: 1rem !important;
            }

            .order-card {
                padding: 0.875rem;
            }

            .order-card .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .order-card .ms-3 {
                margin-left: 0 !important;
                margin-top: 0.75rem;
                width: 100%;
            }

            .btn-brand {
                width: 100%;
                text-align: center;
            }

            .table-responsive {
                font-size: 0.85rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
            }

            .info-card .card-body {
                padding: 1rem;
            }

            .info-card ul {
                padding-left: 1.25rem;
            }

            .badge {
                font-size: 0.75rem;
            }

            .card-header h5 {
                font-size: 1rem;
            }
        }

        @media (max-width: 768px) {
            .col-lg-8, .col-lg-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .row {
                margin: 0;
            }

            .card {
                margin-bottom: 1rem;
            }
        }

        .sticky-footer {
            position: sticky;
            top: 100vh;
            background: white;
            padding: 0.5rem;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.08);
            z-index: 100;
        }

        body.dark-mode .sticky-footer {
            background: #1e1e1e;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <i class="bi bi-person-badge me-2 text-primary"></i> Waiter Dashboard
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="profile.php"><i class="bi bi-person me-1"></i> Profile</a>
                <a class="nav-link" href="support_order.php"><i class="bi bi-headset me-1"></i> Support</a>
                <a class="nav-link" href="../../../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
                <button class="btn btn-outline-primary btn-sm ms-2 refresh-btn" onclick="manualRefresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid main-content mt-4">
    <div class="row g-3">
        <div class="col-lg-8">
            <!-- Pending Orders -->
            <div class="card">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center py-3">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-list-task me-2 text-warning"></i> Pending Orders</h5>
                        <p class="mb-0 text-muted small d-md-none">Tap refresh to update</p>
                    </div>
                    <span class="badge bg-warning text-dark mt-1 mt-md-0"><?= count($pending_orders); ?> Pending</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($pending_orders)): ?>
                        <?php foreach ($pending_orders as $order): ?>
                            <div class="order-card">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start">
                                    <div style="flex: 1;">
                                        <div class="d-flex flex-wrap align-items-center mb-2">
                                            <h6 class="mb-0 me-2">#<?= htmlspecialchars($order['id']); ?></h6>
                                            <span class="badge status-badge <?= htmlspecialchars($order['status']); ?>">
                                                <?= htmlspecialchars(ucfirst($order['status'])); ?>
                                            </span>
                                        </div>
                                        <p class="mb-1 text-muted small">
                                            <i class="bi bi-people me-1"></i> <?= htmlspecialchars($order['username']); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="bi bi-grid-3x3-gap me-1"></i> 
                                            <?= htmlspecialchars($order['table_qr'] ?? $order['table_qr_name'] ?? 'Takeaway'); ?>
                                        </p>
                                        <p class="mb-2 text-muted small">
                                            <i class="bi bi-clock me-1"></i> 
                                            <?= date('M d, g:i A', strtotime($order['order_time'])); ?>
                                        </p>
                                        <div class="order-details">
                                            <strong>Items:</strong> <?= htmlspecialchars($order['items'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                    <div class="ms-3 ms-md-0 mt-2 mt-md-0 text-end">
                                        <?php if (in_array($order['status'], ['pending', 'preparing'])): ?>
                                            <button class="btn btn-brand btn-sm w-100" onclick="updateOrderStatus(<?= $order['id']; ?>, 'ready')">
                                                <i class="bi bi-check-circle me-1"></i> Mark Ready
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-check2-circle"></i>
                            <h5>No Pending Orders</h5>
                            <p class="mb-0">All orders are up-to-date. Great job!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Completed Orders -->
            <div class="card">
                <div class="card-header py-3">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2 text-primary"></i> Recent Completed Orders</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($completed_orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Table</th>
                                        <th>User</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completed_orders as $order): ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($order['id']); ?></td>
                                            <td><?= htmlspecialchars($order['table_qr'] ?? $order['table_qr_name'] ?? 'Takeaway'); ?></td>
                                            <td><?= htmlspecialchars($order['username']); ?></td>
                                            <td>
                                                <span class="badge status-badge <?= htmlspecialchars($order['status']); ?>">
                                                    <?= htmlspecialchars(ucfirst($order['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="small"><?= date('M d, g:i A', strtotime($order['order_time'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3 text-muted">
                            No recent completed orders.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card info-card">
                <div class="card-header py-3">
                    <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i> Tips for Waiters</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0 small">
                        <li>✔️ Click <strong>Mark Ready</strong> when food is prepared</li>
                        <li>✔️ Orders auto-sort by time (oldest first)</li>
                        <li>✔️ Use <strong>Support Order</strong> for special requests</li>
                        <li>✔️ Refresh manually if needed</li>
                    </ul>
                    <div class="mt-3 text-center">
                        <button class="btn btn-sm btn-outline-warning w-100" onclick="manualRefresh()">
                            <i class="bi bi-arrow-clockwise"></i> Manual Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sticky footer for mobile -->
<div class="sticky-footer d-md-none">
    <div class="d-flex justify-content-around">
        <a href="profile.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-person"></i></a>
        <a href="support_order.php" class="btn btn-outline-success btn-sm"><i class="bi bi-headset"></i></a>
        <button class="btn btn-outline-warning btn-sm" onclick="manualRefresh()"><i class="bi bi-arrow-clockwise"></i></button>
        <a href="../../../logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>

<script>
function manualRefresh() {
    location.reload();
}

function updateOrderStatus(orderId, newStatus) {
    if (!confirm(`Mark order #${orderId} as "${newStatus}"?`)) return;

    const btn = event.target;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...';

    fetch('update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, status: newStatus })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed: ' + (data.message || 'Unknown error'));
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    })
    .catch(e => {
        console.error(e);
        alert('Network error. Try again.');
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>