<?php
// views/dashboard/user/index.php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'User';

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get user's active plan
$plan_info = null;
try {
    $plan_stmt = $db->prepare("SELECT sp.*, p.plan_name FROM student_plans sp JOIN plans p ON sp.plan_id = p.id WHERE sp.user_id = ? AND sp.status = 'active'");
    $plan_stmt->execute([$user_id]);
    $plan_info = $plan_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $plan_info = null;
}

// Get today's orders
$todays_orders = [];
try {
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? AND DATE(order_time) = CURDATE() ORDER BY order_time DESC");
    $stmt->execute([$user_id]);
    $todays_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $todays_orders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mess Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding-top: 56px;
            font-family: 'Poppins', sans-serif;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), transparent);
            z-index: 1;
        }
        .stat-card .content {
            position: relative;
            z-index: 2;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .quick-action-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
            background: white;
            border: 2px solid #e9ecef;
        }
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-color: #6f42c1;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Mess Management</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="profile.php">Profile</a>
                <a class="nav-link text-white" href="../../../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h4 class="mb-4">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h4>

                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #6f42c1, #5a32a3); color: white;">
                            <div class="content">
                                <i class="bi bi-egg-fry d-block" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-2"><?php echo $plan_info ? $plan_info['breakfast_remaining'] : '0'; ?></h5>
                                <p class="mb-0">Breakfast Credits</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #28a745, #20c997); color: white;">
                            <div class="content">
                                <i class="bi bi-cup-straw d-block" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-2"><?php echo $plan_info ? $plan_info['lunch_remaining'] : '0'; ?></h5>
                                <p class="mb-0">Lunch Credits</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #17a2b8, #6f42c1); color: white;">
                            <div class="content">
                                <i class="bi bi-moon d-block" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-2"><?php echo $plan_info ? $plan_info['dinner_remaining'] : '0'; ?></h5>
                                <p class="mb-0">Dinner Credits</p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($plan_info): ?>
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-credit-card me-2"></i>
                                        Your Plan: <?php echo htmlspecialchars($plan_info['plan_name']); ?>
                                    </h5>
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <h4 class="text-primary"><?php echo $plan_info['breakfast_remaining']; ?>/<?php echo $plan_info['breakfast_credits']; ?></h4>
                                            <p class="text-muted">Breakfast</p>
                                        </div>
                                        <div class="col-md-4">
                                            <h4 class="text-success"><?php echo $plan_info['lunch_remaining']; ?>/<?php echo $plan_info['lunch_credits']; ?></h4>
                                            <p class="text-muted">Lunch</p>
                                        </div>
                                        <div class="col-md-4">
                                            <h4 class="text-warning"><?php echo $plan_info['dinner_remaining']; ?>/<?php echo $plan_info['dinner_credits']; ?></h4>
                                            <p class="text-muted">Dinner</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="bi bi-credit-card" style="font-size: 3rem; color: #ccc;"></i>
                                    <h5 class="mt-3">No Active Plan</h5>
                                    <p class="text-muted">Contact admin to get your meal plan assigned.</p>
                                    <a href="my_plan.php" class="btn btn-primary">View My Plan</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ✅ QUICK ACTIONS SECTION -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-lightning-charge me-2"></i>
                                    Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="profile.php" class="text-decoration-none d-block">
                                            <div class="quick-action-card">
                                                <i class="bi bi-person-circle text-primary" style="font-size: 2rem;"></i>
                                                <h6 class="mt-2 mb-0">My Profile</h6>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="my_plan.php" class="text-decoration-none d-block">
                                            <div class="quick-action-card">
                                                <i class="bi bi-credit-card text-success" style="font-size: 2rem;"></i>
                                                <h6 class="mt-2 mb-0">My Plan</h6>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="menu.php" class="text-decoration-none d-block">
                                            <div class="quick-action-card">
                                                <i class="bi bi-qr-code-scan text-info" style="font-size: 2rem;"></i>
                                                <h6 class="mt-2 mb-0">Scan QR</h6>  
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="order_history.php" class="text-decoration-none d-block">
                                            <div class="quick-action-card">
                                                <i class="bi bi-clock-history text-warning" style="font-size: 2rem;"></i>
                                                <h6 class="mt-2 mb-0">Order History</h6>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ✅ TODAY'S ORDERS -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-clock-history me-2"></i>
                                    Today's Orders
                                </h5>
                                <span class="badge bg-primary"><?php echo count($todays_orders); ?></span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($todays_orders)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-clock-history" style="font-size: 3rem; color: #ccc;"></i>
                                        <h5 class="mt-3">No orders today</h5>
                                        <p class="text-muted">Start your day with a delicious meal!</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($todays_orders as $order): ?>
                                                    <tr>
                                                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($order['status']); ?></td>
                                                        <td><?php echo date('h:i A', strtotime($order['order_time'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
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
