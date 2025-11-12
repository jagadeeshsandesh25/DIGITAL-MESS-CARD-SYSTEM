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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding-top: 56px; font-family: 'Poppins', sans-serif; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0.75rem; margin-bottom: 1.5rem; }
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
                <h4 class="mb-4">My Card</h4>
                
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <?php if ($card_info): ?>
                                    <div class="mb-4">
                                        <i class="bi bi-credit-card" style="font-size: 4rem; color: #6f42c1;"></i>
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
                                        <i class="bi bi-credit-card" style="font-size: 4rem; color: #ccc;"></i>
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
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-receipt me-2"></i>
                                        Recent Transactions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>