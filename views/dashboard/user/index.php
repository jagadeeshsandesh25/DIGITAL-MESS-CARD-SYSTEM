<?php
// views/dashboard/user/index.php - No Card References
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
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
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 16px;
            margin-bottom: 1.5rem;
            background: white;
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 16px;
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
            font-size: 2.5rem;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }

        .stat-card:hover i {
            transform: scale(1.1);
        }

        .quick-action-card {
            text-align: center;
            padding: 1.25rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            background: white;
            border: 2px solid #e9ecef;
            height: 100%;
        }

        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-color: var(--primary-color);
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

            .container-fluid {
                padding: 0 0.75rem;
            }

            .card {
                margin-bottom: 1rem;
            }

            .stat-card {
                padding: 1.25rem;
                margin-bottom: 1rem;
            }

            .quick-action-card {
                padding: 1rem;
            }

            .stat-card h5 {
                font-size: 1.5rem;
            }

            .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .col-md-3 {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .col-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                align-items: flex-start;
            }

            .d-flex.justify-content-between span {
                margin-top: 0.5rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
                font-size: 0.85rem;
            }

            .progress {
                height: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .col-md-3, .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .row > [class*="col-"] {
                padding-right: 0.5rem;
                padding-left: 0.5rem;
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
                    <a class="nav-link" href="feedback.php"><i class="bi bi-chat-right-heart"></i> feedback</a>
                    <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
                    <a class="nav-link" href="../../../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h4 class="mb-4">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h4>
                
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #6f42c1, #5a32a3);">
                            <div class="content">
                               
                                <h5 class="mt-2"><?php echo $plan_info ? $plan_info['breakfast_remaining'] : '0'; ?></h5>
                                <p class="mb-0">Breakfast Credits</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #28a745, #20c997);">
                            <div class="content">
                               
                                <h5 class="mt-2"><?php echo $plan_info ? $plan_info['lunch_remaining'] : '0'; ?></h5>
                                <p class="mb-0">Lunch Credits</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(45deg, #17a2b8, #6f42c1);">
                            <div class="content">
                              
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
                                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                    <div>
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-credit-card me-2"></i>
                                            Your Plan: <?php echo htmlspecialchars($plan_info['plan_name']); ?>
                                        </h5>
                                        <span class="badge bg-success mt-1 mt-md-0">Active</span>
                                    </div>
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
                                    <div class="progress mt-3">
                                        <div class="progress-bar bg-primary" style="width: <?php echo min(100, ($plan_info['breakfast_remaining'] / $plan_info['breakfast_credits']) * 100); ?>%" role="progressbar" aria-valuenow="<?php echo $plan_info['breakfast_remaining']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $plan_info['breakfast_credits']; ?>"></div>
                                        <div class="progress-bar bg-success" style="width: <?php echo min(100, ($plan_info['lunch_remaining'] / $plan_info['lunch_credits']) * 100); ?>%" role="progressbar" aria-valuenow="<?php echo $plan_info['lunch_remaining']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $plan_info['lunch_credits']; ?>"></div>
                                        <div class="progress-bar bg-warning" style="width: <?php echo min(100, ($plan_info['dinner_remaining'] / $plan_info['dinner_credits']) * 100); ?>%" role="progressbar" aria-valuenow="<?php echo $plan_info['dinner_remaining']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $plan_info['dinner_credits']; ?>"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bi bi-credit-card" style="font-size: 3rem; color: #ccc;"></i>
                                    <h5 class="mt-3">No Active Plan</h5>
                                    <p class="text-muted">Contact admin to get your meal plan assigned.</p>
                                    <a href="my_plan.php" class="btn btn-primary">View My Plan</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-lightning-charge me-2"></i>
                                    Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="profile.php" class="text-decoration-none d-block h-100">
                                            <div class="quick-action-card">
                                                <i class="bi bi-person-circle text-primary" style="font-size: 2rem;"></i>
                                                <h6 class="mt-2 mb-0">My Profile</h6>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="my_plan.php" class="text-decoration-none d-block h-100">
                                            <div class="quick-action-card">
                                                <i class="bi bi-credit-card text-success" style="font-size: 2rem;"></i>
                                                <h6 class="mt-2 mb-0">My Plan</h6>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="table_scan.php" class="text-decoration-none d-block h-100">
                                            <div class="quick-action-card">
                                                <i class="bi bi-qr-code text-info" style="font-size: 2rem;"></i>
                                                <h6 class="mt-2 mb-0">Scan Table</h6>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="order_history.php" class="text-decoration-none d-block h-100">
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

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                <div>
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-clock-history me-2"></i>
                                        Today's Orders
                                    </h5>
                                </div>
                                <span class="badge bg-primary mt-1 mt-md-0"><?php echo count($todays_orders); ?></span>
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

    <!-- Sticky footer for mobile -->
    <div class="sticky-footer d-md-none">
        <div class="d-flex justify-content-around">
            <a href="profile.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-person"></i></a>
            <a href="my_plan.php" class="btn btn-outline-success btn-sm"><i class="bi bi-credit-card"></i></a>
            <a href="table_scan.php" class="btn btn-outline-info btn-sm"><i class="bi bi-qr-code"></i></a>
            <a href="../../../logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>