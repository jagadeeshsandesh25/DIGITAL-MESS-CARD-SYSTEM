<?php
// views/dashboard/user/order_history.php - Enhanced Order History with Auto-Refresh
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get all orders - Fetching from the modified orders table
$order_stmt = $db->prepare("
    SELECT o.*, t.t_qr as table_qr_name
    FROM orders o
    LEFT JOIN tables t ON o.table_id = t.id -- Join to get table QR if needed, though we also have table_qr column
    WHERE o.user_id = ?
    ORDER BY o.order_time DESC
");
$order_stmt->execute([$user_id]);
$orders = $order_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total credits used instead of total amount (or sum total_amount if you use it)
$total_credits_used = 0;
foreach ($orders as $order) {
    $total_credits_used += $order['credits_used']; // Sum up credits_used
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
            background: linear-gradient(135deg, var(--light-bg), #c3cfe2);
            font-family: 'Poppins', sans-serif;
            padding-top: 72px;
            margin: 0;
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            border-radius: 16px;
            margin-bottom: 1.5rem;
            background: white;
        }

        .form-label {
            font-weight: 500;
            color: #475569;
        }

        .form-select, .form-control {
            border: 1px solid #d1d5db;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            transition: border-color 0.25s;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(40, 65, 148, 0.15);
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), #1a2f6d);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            border-radius: 10px;
            transition: opacity 0.3s;
        }

        .btn-primary:hover {
            opacity: 0.92;
        }

        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }

        .table thead th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
        }

        .alert {
            border-radius: 12px;
        }

        .stat-card {
            text-align: center;
            padding: 1.25rem;
            border-radius: 14px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            color: white;
        }

        .stat-card .content {
            position: relative;
            z-index: 2;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            transition: transform 0.3s ease;
        }

        .stat-card:hover i {
            transform: scale(1.1);
        }

        /* Mobile-specific styles */
        @media (max-width: 576px) {
            body {
                padding-top: 64px;
            }

            .navbar-brand {
                font-size: 1.1rem;
            }

            .navbar-nav .nav-link {
                padding: 0.5rem 0.5rem !important;
                font-size: 0.85rem;
            }

            .container-fluid {
                padding: 0 0.75rem;
            }

            .card {
                margin-bottom: 1rem;
            }

            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .stat-card h5 {
                font-size: 1.25rem;
            }

            .col-md-3 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .table th,
            .table td {
                padding: 0.5rem;
                font-size: 0.85rem;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                align-items: flex-start;
            }

            .d-flex.justify-content-between span {
                margin-top: 0.5rem;
            }

            .btn-lg {
                padding: 0.65rem 1rem;
                font-size: 1rem;
            }

            .card-body {
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .row > [class*="col-"] {
                padding-right: 0.5rem;
                padding-left: 0.5rem;
            }

            .table th,
            .table td {
                white-space: nowrap;
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
                <i class="bi bi-hearts"></i>LittleHearts
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="index.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
                    <a class="nav-link" href="../../../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h4 class="mb-4">Order History</h4>
                
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #6f42c1, #5a32a3);">
                            <div class="content">
                                <i class="bi bi-receipt d-block"></i>
                                <h5 class="mt-2"><?php echo count($orders); ?></h5>
                                <p class="mb-0">Total Orders</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #28a745, #20c997);">
                            <div class="content">
                                <i class="bi bi-currency-rupee d-block"></i>
                                <!-- Updated to show total credits used -->
                                <h5 class="mt-2"><?php echo number_format($total_credits_used, 0); ?></h5>
                                <p class="mb-0">Total Credits Used</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #17a2b8, #6f42c1);">
                            <div class="content">
                                <i class="bi bi-clock-history d-block"></i>
                                <h5 class="mt-2"><?php echo $pending_orders; ?></h5>
                                <p class="mb-0">Pending Orders</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #ffc107, #fd7e14);">
                            <div class="content">
                                <i class="bi bi-check-circle d-block"></i>
                                <h5 class="mt-2"><?php echo $completed_orders; ?></h5>
                                <p class="mb-0">Completed Orders</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                <div>
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-receipt me-2 text-primary"></i>
                                        All Orders
                                    </h5>
                                </div>
                                <span class="badge bg-primary mt-1 mt-md-0"><?php echo count($orders); ?> Orders</span>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($orders)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Order ID</th>
                                                    <th>Table</th> <!-- Added Table QR -->
                                                    <th>Credits Used</th> <!-- Updated header -->
                                                    <th>Meal Type</th> <!-- Added Meal Type -->
                                                    <th>Plan Type</th> <!-- Added Plan Type -->
                                                    <th>Items</th> <!-- Added Items -->
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($orders as $order): ?>
                                                    <tr>
                                                        <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                                        <td><?php echo htmlspecialchars($order['table_qr'] ?? $order['table_qr_name'] ?? 'N/A'); ?></td> <!-- Show table QR -->
                                                        <td><?php echo htmlspecialchars($order['credits_used']); ?></td> <!-- Show credits used -->
                                                        <td><?php echo htmlspecialchars(ucfirst($order['meal_type'])); ?></td> <!-- Show meal type -->
                                                        <td><?php echo htmlspecialchars(ucfirst($order['plan_type'])); ?></td> <!-- Show plan type -->
                                                        <td><?php echo htmlspecialchars($order['items'] ?? 'N/A'); ?></td> <!-- Show items or N/A -->
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

    <!-- Sticky footer for mobile -->
    <div class="sticky-footer d-md-none">
        <div class="d-flex justify-content-around">
            <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2"></i></a>
            <a href="../../../logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <!-- Auto-refresh Script for User Dashboard -->
    <script>
    // Optional: Auto-refresh the user's order history page every 15 seconds
    // This allows users to see status updates without manually refreshing
    // setInterval(function() {
    //     location.reload();
    // }, 15000); // 15 seconds
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>