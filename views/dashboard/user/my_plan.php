<?php
// views/dashboard/user/my_plan.php - Enhanced Plan Display
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get user's active plan
$active_plan = null;
try {
    $plan_stmt = $db->prepare("SELECT sp.*, p.plan_name, p.plan_type FROM student_plans sp JOIN plans p ON sp.plan_id = p.id WHERE sp.user_id = ? AND sp.status = 'active'");
    $plan_stmt->execute([$user_id]);
    $active_plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $active_plan = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Plan - Mess Management</title>
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

            .col-lg-8 {
                flex: 0 0 100%;
                max-width: 100%;
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
                <h4 class="mb-4">My Meal Plan</h4>
                
                <?php if ($active_plan): ?>
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card" style="background: linear-gradient(45deg, #6f42c1, #5a32a3);">
                                <div class="content">
                               
                                    <h5 class="mt-2"><?php echo $active_plan['breakfast_remaining']; ?>/<?php echo $active_plan['breakfast_credits']; ?></h5>
                                    <p class="mb-0">Breakfast</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card" style="background: linear-gradient(45deg, #28a745, #20c997);">
                                <div class="content">
                            
                                    <h5 class="mt-2"><?php echo $active_plan['lunch_remaining']; ?>/<?php echo $active_plan['lunch_credits']; ?></h5>
                                    <p class="mb-0">Lunch</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card" style="background: linear-gradient(45deg, #17a2b8, #6f42c1);">
                                <div class="content">
                            
                                    <h5 class="mt-2"><?php echo $active_plan['dinner_remaining']; ?>/<?php echo $active_plan['dinner_credits']; ?></h5>
                                    <p class="mb-0">Dinner</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card" style="background: linear-gradient(45deg, #ffc107, #fd7e14);">
                                <div class="content">
                                 <h5 class="mt-2"><?php echo date('M d', strtotime($active_plan['end_date'])); ?></h5>
                                    <p class="mb-0">Plan Expiry</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8 mx-auto">
                            <div class="card">
                                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                    <div>
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-credit-card me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($active_plan['plan_name']); ?>
                                        </h5>
                                    </div>
                                    <span class="badge <?php echo $active_plan['plan_type'] === 'Non-veg' ? 'bg-danger' : 'bg-success'; ?> fs-5 mt-1 mt-md-0">
                                        <?php echo $active_plan['plan_type']; ?> Plan
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-4">
                                        <h3><?php echo htmlspecialchars($active_plan['plan_name']); ?></h3>
                                        <p class="text-muted">Valid until <?php echo date('M d, Y', strtotime($active_plan['end_date'])); ?></p>
                                    </div>
                                    
                                    <div class="row text-center mb-4">
                                        <div class="col-md-4">
                                            <h4 class="text-primary"><?php echo $active_plan['breakfast_remaining']; ?></h4>
                                            <p class="text-muted">Breakfast Remaining</p>
                                        </div>
                                        <div class="col-md-4">
                                            <h4 class="text-success"><?php echo $active_plan['lunch_remaining']; ?></h4>
                                            <p class="text-muted">Lunch Remaining</p>
                                        </div>
                                        <div class="col-md-4">
                                            <h4 class="text-warning"><?php echo $active_plan['dinner_remaining']; ?></h4>
                                            <p class="text-muted">Dinner Remaining</p>
                                        </div>
                                    </div>
                                    
                                    <div class="progress mb-4">
                                        <div class="progress-bar bg-primary" style="width: <?php echo min(100, ($active_plan['breakfast_remaining'] / $active_plan['breakfast_credits']) * 100); ?>%" role="progressbar" aria-valuenow="<?php echo $active_plan['breakfast_remaining']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $active_plan['breakfast_credits']; ?>">
                                            Breakfast: <?php echo $active_plan['breakfast_remaining']; ?>/<?php echo $active_plan['breakfast_credits']; ?>
                                        </div>
                                        <div class="progress-bar bg-success" style="width: <?php echo min(100, ($active_plan['lunch_remaining'] / $active_plan['lunch_credits']) * 100); ?>%" role="progressbar" aria-valuenow="<?php echo $active_plan['lunch_remaining']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $active_plan['lunch_credits']; ?>">
                                            Lunch: <?php echo $active_plan['lunch_remaining']; ?>/<?php echo $active_plan['lunch_credits']; ?>
                                        </div>
                                        <div class="progress-bar bg-warning" style="width: <?php echo min(100, ($active_plan['dinner_remaining'] / $active_plan['dinner_credits']) * 100); ?>%" role="progressbar" aria-valuenow="<?php echo $active_plan['dinner_remaining']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $active_plan['dinner_credits']; ?>">
                                            Dinner: <?php echo $active_plan['dinner_remaining']; ?>/<?php echo $active_plan['dinner_credits']; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center">
                                        <p class="text-muted mb-1">Plan Valid From: <?php echo date('M d, Y', strtotime($active_plan['start_date'])); ?></p>
                                        <p class="text-muted mb-0">Plan Expiry: <?php echo date('M d, Y', strtotime($active_plan['end_date'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <div class="col-lg-8 mx-auto">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bi bi-credit-card" style="font-size: 4rem; color: #ccc;"></i>
                                    <h4 class="mt-3">No Active Plan</h4>
                                    <p class="text-muted mb-4">You don't have an active meal plan yet. Contact admin to get one.</p>
                                    <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
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