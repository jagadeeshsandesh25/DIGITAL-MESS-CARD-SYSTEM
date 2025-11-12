<?php
// views/dashboard/admin/feedback.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch all feedback with user & order details
$feedback_list = [];
try {
    $stmt = $db->prepare("
        SELECT 
            f.id, f.message, f.rating, f.created_at,
            u.first_name, u.last_name, u.username,
            o.id AS order_id, o.total_amount
        FROM feedback f
        LEFT JOIN user u ON f.user_id = u.id
        LEFT JOIN orders o ON f.order_id = o.id
        ORDER BY f.created_at DESC
    ");
    $stmt->execute();
    $feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin Feedback Load Error: " . $e->getMessage());
    $feedback_list = [];
}

// ✅ Build content the same way index.php does
$content = "
<div class='page-title-box'>
    <div class='page-title-right'>
        <ol class='breadcrumb m-0'>
            <li class='breadcrumb-item'><a href='index.php'>Dashboard</a></li>
            <li class='breadcrumb-item active'>Feedback</li>
        </ol>
    </div>
    <h4 class='page-title'>User Feedback</h4>
</div>

<div class='row'>
    <div class='col-12'>
        <div class='card'>
            <div class='card-header d-flex justify-content-between align-items-center'>
                <h5 class='mb-0'>
                    <i class='bi bi-chat-dots me-2'></i>
                    All Feedback
                </h5>
                <span class='badge bg-primary rounded-pill'>" . count($feedback_list) . "</span>
            </div>
            <div class='card-body'>
";

if (empty($feedback_list)) {
    $content .= "
                <div class='text-center py-5 text-muted'>
                    <i class='bi bi-chat-square-text' style='font-size: 3rem; color: #adb5bd;'></i>
                    <h5 class='mt-3'>No feedback yet</h5>
                    <p>Users haven’t submitted any feedback.</p>
                </div>
    ";
} else {
    foreach ($feedback_list as $fb) {
        $user_display = htmlspecialchars($fb['first_name'] . ' ' . $fb['last_name']);
        $username = !empty($fb['username']) ? "<code class='text-muted'>" . htmlspecialchars($fb['username']) . "</code>" : '';
        $order_info = $fb['order_id']
            ? "Order #{$fb['order_id']} • ₹" . number_format($fb['total_amount'], 2)
            : "No order linked";
        $stars = str_repeat('★', (int)$fb['rating']) . str_repeat('☆', 5 - (int)$fb['rating']);
        $date = date('M j, Y', strtotime($fb['created_at']));
        $time = date('g:i A', strtotime($fb['created_at']));
        $message = nl2br(htmlspecialchars($fb['message']));

        $content .= "
                <div class='card mb-3 border-start border-primary' style='border-width:3px;'>
                    <div class='card-body'>
                        <div class='d-flex flex-column flex-md-row justify-content-between'>
                            <div>
                                <h6 class='mb-1'>{$user_display} {$username}</h6>
                                <small class='text-muted'>{$order_info}</small>
                                <p class='mt-2 mb-0 text-break'>{$message}</p>
                            </div>
                            <div class='text-md-end mt-2 mt-md-0'>
                                <div class='text-warning'>{$stars}</div>
                                <small class='text-muted d-block mt-1'>{$date}<br>{$time}</small>
                            </div>
                        </div>
                    </div>
                </div>
        ";
    }
}

$content .= "
            </div>
        </div>
    </div>
</div>
";

// ✅ Include the same layout as index.php — clean & consistent!
include '../../layouts/app.php';
?>