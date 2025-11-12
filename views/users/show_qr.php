<?php
// views/dashboard/user/show_qr.php - User QR Display Page
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
    $plan_stmt = $db->prepare("
        SELECT sp.*, p.plan_name 
        FROM student_plans sp 
        JOIN plans p ON sp.plan_id = p.id 
        WHERE sp.user_id = ? AND sp.status = 'active'
    ");
    $plan_stmt->execute([$user_id]);
    $plan_info = $plan_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $plan_info = null;
}

// Generate temporary QR code
$temp_qr = 'TEMP_' . uniqid() . '_' . $user_id . '_' . time();
$_SESSION['temp_qr'] = $temp_qr;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Show QR - Mess Management</title>

    <!-- Bootstrap & Icons -->
    <link href="/jai/assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding-top: 56px;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="/jai/views/dashboard/user/index.php">Mess Management</a>
            <div class="navbar-nav ms-auto d-flex flex-row gap-3">
                <a class="nav-link text-white" href="/jai/views/dashboard/user/index.php">Dashboard</a>
                <a class="nav-link text-white" href="/jai/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Page Content -->
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h4 class="mb-4">Show QR Code to Waiter</h4>

                <div class="row">
                    <div class="col-lg-8 mx-auto">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-qr-code me-2"></i>Your Temporary QR Code
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="mb-4">
                                    <div class="bg-light rounded p-4">
                                        <i class="bi bi-qr-code" style="font-size: 6rem; color: #6f42c1;"></i>
                                        <h3 class="mt-3 text-primary"><?php echo htmlspecialchars($temp_qr); ?></h3>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Show this QR code to the waiter. They will scan it to place your order.
                                </div>

                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" onclick="copyQR()">
                                        <i class="bi bi-clipboard me-1"></i> Copy QR Code
                                    </button>
                                    <a href="/jai/views/dashboard/user/index.php" class="btn btn-secondary">
                                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($plan_info): ?>
                <div class="row mt-4">
                    <div class="col-lg-8 mx-auto">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-card-checklist me-2"></i>Your Plan: 
                                    <?php echo htmlspecialchars($plan_info['plan_name']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <h4 class="text-primary">
                                            <?php echo $plan_info['breakfast_remaining']; ?>/<?php echo $plan_info['breakfast_credits']; ?>
                                        </h4>
                                        <p class="text-muted mb-0">Breakfast</p>
                                    </div>
                                    <div class="col-md-4">
                                        <h4 class="text-success">
                                            <?php echo $plan_info['lunch_remaining']; ?>/<?php echo $plan_info['lunch_credits']; ?>
                                        </h4>
                                        <p class="text-muted mb-0">Lunch</p>
                                    </div>
                                    <div class="col-md-4">
                                        <h4 class="text-warning">
                                            <?php echo $plan_info['dinner_remaining']; ?>/<?php echo $plan_info['dinner_credits']; ?>
                                        </h4>
                                        <p class="text-muted mb-0">Dinner</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="/jai/assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyQR() {
            const qrCode = '<?php echo $temp_qr; ?>';
            navigator.clipboard.writeText(qrCode).then(() => {
                alert('QR Code copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy QR code. Please try again.');
            });
        }
    </script>
</body>
</html>
