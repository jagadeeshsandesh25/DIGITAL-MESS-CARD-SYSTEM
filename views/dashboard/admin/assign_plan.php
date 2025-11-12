<?php
// views/dashboard/admin/assign_plan.php - Plan Assignment + Auto Transaction (with Searchable Select)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Fetch all users (only 'user' role)
$users_stmt = $db->prepare("SELECT id, first_name, last_name, username FROM user WHERE role = 'user' ORDER BY id ASC");
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all plans
$plans_stmt = $db->prepare("SELECT id, plan_name, price, breakfast_credits, lunch_credits, dinner_credits FROM plans ORDER BY id ASC");
$plans_stmt->execute();
$plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_plan'])) {
    $user_id = (int)$_POST['user_id'];
    $plan_id = (int)$_POST['plan_id'];
    $payment_method = trim($_POST['payment_method'] ?? 'Cash');
    $utr_number = trim($_POST['utr_number'] ?? '');

    try {
        // Validate user exists and is a normal user
        $user_check = $db->prepare("SELECT id FROM user WHERE id = ? AND role = 'user'");
        $user_check->execute([$user_id]);
        if (!$user_check->fetch()) {
            $message = "❌ Invalid user selected.";
            $message_type = "danger";
        } else {
            // Check if the user already has an active plan
            $check = $db->prepare("SELECT id FROM student_plans WHERE user_id = ? AND status = 'active'");
            $check->execute([$user_id]);
            if ($check->fetch()) {
                $message = "⚠️ This user already has an active plan.";
                $message_type = "warning";
            } else {
                // Get plan details
                $plan_stmt = $db->prepare("SELECT id, price, breakfast_credits, lunch_credits, dinner_credits FROM plans WHERE id = ?");
                $plan_stmt->execute([$plan_id]);
                $plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$plan) {
                    $message = "❌ Invalid plan selected.";
                    $message_type = "danger";
                } else {
                    $db->beginTransaction();

                    // ✅ FIXED DATE FORMAT HERE
                    $start_date = date('Y-m-d');
                    $end_date = date('Y-m-d', strtotime('+3 months')); // ←←← This was broken before!

                    // Insert into student_plans
                    $insert_plan = $db->prepare("
                        INSERT INTO student_plans 
                        (user_id, plan_id, breakfast_remaining, lunch_remaining, dinner_remaining, 
                         breakfast_credits, lunch_credits, dinner_credits, start_date, end_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $insert_plan->execute([
                        $user_id,
                        $plan_id,
                        $plan['breakfast_credits'],
                        $plan['lunch_credits'],
                        $plan['dinner_credits'],
                        $plan['breakfast_credits'],
                        $plan['lunch_credits'],
                        $plan['dinner_credits'],
                        $start_date,
                        $end_date
                    ]);

                    // Prepare transaction type
                    $t_type = $payment_method;
                    if ($payment_method === 'UPI' && !empty($utr_number)) {
                        $t_type .= " UTR:" . $utr_number;
                    }

                    // Add transaction record
                    $add_transaction = $db->prepare("
                        INSERT INTO transactions (user_id, amount, t_type, status, t_time)
                        VALUES (?, ?, ?, 'completed', NOW())
                    ");
                    $add_transaction->execute([
                        $user_id,
                        $plan['price'],
                        $t_type
                    ]);

                    $db->commit();
                    $message = "✅ Plan assigned and transaction recorded successfully.";
                    $message_type = "success";
                }
            }
        }
    } catch (Exception $e) {
        $db->rollBack();
        // Log error in real app; for now, show user-friendly message
        $message = "❌ Database error occurred. Please try again.";
        $message_type = "danger";
        error_log("Assign Plan Error: " . $e->getMessage());
    }
}

// Build HTML content
$content = "
<div class='container-fluid py-4'>
    <div class='d-flex justify-content-between align-items-center mb-4'>
        <h4><i class='bi bi-credit-card me-2 text-primary'></i>Assign Meal Plan</h4>
        <a href='transactions.php' class='btn btn-outline-primary btn-sm'><i class='bi bi-receipt me-1'></i> View Transactions</a>
    </div>";

if ($message) {
    $content .= "<div class='alert alert-{$message_type} alert-dismissible fade show' role='alert'>{$message}
        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
    </div>";
}

$content .= "
    <div class='row'>
        <div class='col-lg-8 mx-auto'>
            <div class='card'>
                <div class='card-body'>
                    <form method='POST'>
                        <div class='mb-3'>
                            <label class='form-label'>Select User</label>
                            <select name='user_id' class='form-select searchable-select' required>
                                <option value=''>Choose a user...</option>";
foreach ($users as $u) {
    $display = "ID {$u['id']} - " . htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) . " ({$u['username']})";
    $content .= "<option value='{$u['id']}'>{$display}</option>";
}
$content .= "
                            </select>
                        </div>

                        <div class='mb-3'>
                            <label class='form-label'>Select Plan</label>
                            <select name='plan_id' class='form-select' required>
                                <option value=''>Choose a plan...</option>";
foreach ($plans as $p) {
    $content .= "<option value='{$p['id']}'>" . htmlspecialchars($p['plan_name']) . " - ₹" . number_format($p['price'], 2) . "</option>";
}
$content .= "
                            </select>
                        </div>

                        <div class='mb-3'>
                            <label class='form-label'>Payment Method</label>
                            <select name='payment_method' class='form-select' id='paymentMethod'>
                                <option value='Cash'>Cash</option>
                                <option value='UPI'>UPI</option>
                                <option value='Card'>Card</option>
                            </select>
                        </div>

                        <div class='mb-3' id='utrField' style='display:none;'>
                            <label class='form-label'>UTR Number (if UPI)</label>
                            <input type='text' name='utr_number' class='form-control' placeholder='Enter UTR if applicable'>
                        </div>

                        <button type='submit' name='assign_plan' class='btn btn-primary w-100'>
                            <i class='bi bi-check-circle me-2'></i>Assign Plan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Choices.js for searchable dropdown -->
<script src='https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js'></script>
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css'>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make user select searchable
    new Choices('.searchable-select', {
        searchEnabled: true,
        searchPlaceholderValue: 'Search by name, ID, or username...',
        noResultsText: 'No user found',
        itemSelectText: 'Click to select',
        position: 'bottom'
    });

    // Toggle UTR field
    const paymentMethod = document.getElementById('paymentMethod');
    const utrField = document.getElementById('utrField');
    if (paymentMethod && utrField) {
        paymentMethod.addEventListener('change', () => {
            utrField.style.display = paymentMethod.value === 'UPI' ? 'block' : 'none';
        });
    }
});
</script>
";

include '../../layouts/app.php';
?>