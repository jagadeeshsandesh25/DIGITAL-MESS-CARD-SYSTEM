<?php
// views/dashboard/user/feedback.php
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

// Fetch user’s recent orders (for optional linking)
$recent_orders = [];
try {
    $stmt = $db->prepare("SELECT id, total_amount, order_time FROM orders WHERE user_id = ? ORDER BY order_time DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // silently handle; optional feature
}

$message = '';
$success = false;

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
    $message_text = trim($_POST['message'] ?? '');

    // Validation
    if ($rating < 1 || $rating > 5) {
        $message = "Please select a valid rating (1–5 stars).";
    } elseif (empty($message_text)) {
        $message = "Feedback message cannot be empty.";
    } elseif ($order_id && !in_array($order_id, array_column($recent_orders, 'id'))) {
        $message = "Invalid order selection.";
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO feedback (user_id, order_id, message, rating) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $order_id, $message_text, $rating]);
            $success = true;
            $message = "Thank you for your feedback! 🙏";
            // Clear form values after success
            $rating = null;
            $message_text = '';
        } catch (PDOException $e) {
            $message = "Oops! Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Give Feedback - Mess Management</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #284194;
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Poppins', sans-serif;
            padding-top: 72px;
        }
        .navbar {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: 600;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            background: white;
        }
        .rating {
            direction: rtl;
            unicode-bidi: bidi-override;
            font-size: 2rem;
            color: #ddd;
        }
        .rating input {
            display: none;
        }
        .rating label {
            cursor: pointer;
            color: #ddd;
        }
        .rating label:hover,
        .rating label:hover ~ label,
        .rating input:checked ~ label {
            color: #ffc107;
        }
    </style>
</head>
<body>

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
                <a class="nav-link" href="index.php"><i class="bi bi-house me-1"></i> Dashboard</a>
                <a class="nav-link" href="../../../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-chat-dots me-2"></i> Share Your Feedback</h5>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        

                        <!-- Rating -->
                        <div class="mb-3">
                            <label class="form-label">Your Rating <span class="text-danger">*</span></label>
                            <div class="rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" <?php echo (isset($rating) && $rating == $i) ? 'checked' : ''; ?>>
                                    <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> stars">&#9733;</label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Message -->
                        <div class="mb-3">
                            <label class="form-label">Your Message <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="4" placeholder="Tell us what you liked or how we can improve..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Submit Feedback</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="index.php" class="text-muted"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>