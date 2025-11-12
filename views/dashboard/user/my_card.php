<?php
// views/dashboard/user/my_card.php - Enhanced My Card
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get card info (automatically create if not exists and plan exists)
$card_info = null;
try {
    $stmt = $db->prepare("SELECT * FROM card WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $card_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no card but has plan, create card
    if (!$card_info) {
        // Check if user has an active plan
        $plan_stmt = $db->prepare("SELECT * FROM student_plans WHERE user_id = ? AND status = 'active' LIMIT 1");
        $plan_stmt->execute([$user_id]);
        $active_plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($active_plan) {
            // Create card with 0 balance initially
            $insert_stmt = $db->prepare("INSERT INTO card (user_id, c_status, balance_credits, total_credits) VALUES (?, 'Active', 0, 0)");
            $insert_stmt->execute([$user_id]);
            
            // Get newly created card
            $stmt = $db->prepare("SELECT * FROM card WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $card_info = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $card_info = null;
}

// Get recent transactions
$recent_transactions = [];
try {
    $stmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY t_time DESC LIMIT 3");
    $stmt->execute([$user_id]);
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_transactions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Card - Mess Management</title>
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

        .card-body.text-center .bi-credit-card {
            font-size: 4rem;
            color: var(--primary-color);
        }

        .progress {
            height: 0.75rem;
            border-radius: 10px;
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

            .table th,
            .table td {
                padding: 0.5rem;
                font-size: 0.85rem;
            }

            .card-body.text-center .bi-credit-card {
                font-size: 3.5rem;
            }

            .btn-lg {
                padding: 0.65rem 1rem;
                font-size: 1rem;
            }

            .col-lg-6, .col-lg-8 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .justify-content-center {
                justify-content: flex-start !important;
            }

            .d-grid.gap-2 {
                gap: 0.5rem !important;
            }
        }

        @media (max-width: 768px) {
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
                    <a class="nav-link" href="index.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
                    <a class="nav-link" href="../../../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h4 class="mb-4">My Card</h4>
                
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <?php if ($card_info): ?>
                                    <div class="mb-4">
                                        <i class="bi bi-credit-card"></i>
                                    </div>
                                    <h4 class="mb-3">Card #<?php echo htmlspecialchars($card_info['id']); ?></h4>
                                    <div class="mb-4">
                                        <h2 class="text-success">₹<?php echo number_format($card_info['balance_credits'], 2); ?></h2>
                                        <p class="text-muted">Current Balance</p>
                                    </div>
                                    <div class="progress mb-4">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, ($card_info['balance_credits'] / 1000) * 100); ?>%" aria-valuenow="<?php echo $card_info['balance_credits']; ?>" aria-valuemin="0" aria-valuemax="1000"></div>
                                    </div>
                                    <p class="text-muted">Balance: ₹<?php echo number_format($card_info['balance_credits'], 2); ?> / ₹1000</p>
                                    <div class="d-grid gap-2 mt-4">
                                        <a href="index.php" class="btn btn-primary btn-lg">
                                            <i class="bi bi-speedometer2 me-1"></i>
                                            Dashboard
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="mb-4">
                                        <i class="bi bi-credit-card" style="color: #ccc;"></i>
                                    </div>
                                    <h4 class="mb-3">No Card Assigned</h4>
                                    <p class="text-muted mb-4">You need an active plan to get a card. Contact admin.</p>
                                    <div class="d-grid gap-2">
                                        <a href="my_plan.php" class="btn btn-primary">Check My Plan</a>
                                        <a href="index.php" class="btn btn-secondary">Dashboard</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($card_info && !empty($recent_transactions)): ?>
                    <div class="row mt-4">
                        <div class="col-lg-8 mx-auto">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <i class="bi bi-receipt me-2 text-primary"></i>
                                    <h5 class="card-title mb-0">Recent Transactions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_transactions as $transaction): ?>
                                                    <tr>
                                                        <td>₹<?php echo number_format($transaction['amount'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($transaction['status']); ?></td>
                                                        <td><?php echo date('M d', strtotime($transaction['t_time'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>