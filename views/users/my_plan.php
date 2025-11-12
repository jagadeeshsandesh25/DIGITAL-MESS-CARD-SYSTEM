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
                <h4 class="mb-4">My Meal Plan</h4>
                
                <?php if ($active_plan): ?>
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card" style="background: linear-gradient(45deg, #6f42c1, #5a32a3); color: white;">
                                <div class="content">
                                    <i class="bi bi-egg-fry d-block" style="font-size: 2.5rem;"></i>
                                    <h5 class="mt-2"><?php echo $active_plan['breakfast_remaining']; ?>/<?php echo $active_plan['breakfast_credits']; ?></h5>
                                    <p class="mb-0">Breakfast</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card" style="background: linear-gradient(45deg, #28a745, #20c997); color: white;">
                                <div class="content">
                                    <i class="bi bi-cup-straw d-block" style="font-size: 2.5rem;"></i>
                                    <h5 class="mt-2"><?php echo $active_plan['lunch_remaining']; ?>/<?php echo $active_plan['lunch_credits']; ?></h5>
                                    <p class="mb-0">Lunch</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card" style="background: linear-gradient(45deg, #17a2b8, #6f42c1); color: white;">
                                <div class="content">
                                    <i class="bi bi-moon d-block" style="font-size: 2.5rem;"></i>
                                    <h5 class="mt-2"><?php echo $active_plan['dinner_remaining']; ?>/<?php echo $active_plan['dinner_credits']; ?></h5>
                                    <p class="mb-0">Dinner</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card" style="background: linear-gradient(45deg, #ffc107, #fd7e14); color: white;">
                                <div class="content">
                                    <i class="bi bi-calendar d-block" style="font-size: 2.5rem;"></i>
                                    <h5 class="mt-2"><?php echo date('M d', strtotime($active_plan['end_date'])); ?></h5>
                                    <p class="mb-0">Plan Expiry</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8 mx-auto">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-credit-card me-2"></i>
                                        <?php echo htmlspecialchars($active_plan['plan_name']); ?>
                                    </h5>
                                    <span class="badge <?php echo $active_plan['plan_type'] === 'Non-veg' ? 'bg-danger' : 'bg-success'; ?> fs-5">
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
                                        <p class="text-muted">Plan Valid From: <?php echo date('M d, Y', strtotime($active_plan['start_date'])); ?></p>
                                        <p class="text-muted">Plan Expiry: <?php echo date('M d, Y', strtotime($active_plan['end_date'])); ?></p>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>