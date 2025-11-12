<?php
// views/dashboard/user/profile.php - No Card References
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
$message = '';
$message_type = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'All password fields are required.';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New password and confirm password do not match.';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $message = 'New password must be at least 6 characters long.';
        $message_type = 'danger';
    } else {
        try {
            // Get current user password
            $query = "SELECT password FROM user WHERE id = :id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current_user || !password_verify($current_password, $current_user['password'])) {
                $message = 'Current password is incorrect.';
                $message_type = 'danger';
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE user SET password = :password WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                
                if ($update_stmt->execute()) {
                    $message = 'Password changed successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to change password. Please try again.';
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            $message = 'Database error occurred. Please try again.';
            $message_type = 'danger';
            error_log("Password change error: " . $e->getMessage());
        }
    }
}

// Get user details
$user_details = null;
try {
    $query = "SELECT id, first_name, last_name, email, ph_no, username, status, role, gender, created_at FROM user WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user_details = null;
}

// Get user's active plan
$plan_info = null;
try {
    $plan_stmt = $db->prepare("SELECT sp.*, p.plan_name FROM student_plans sp JOIN plans p ON sp.plan_id = p.id WHERE sp.user_id = ? AND sp.status = 'active'");
    $plan_stmt->execute([$user_id]);
    $plan_info = $plan_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $plan_info = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Mess Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
                <h4 class="mb-4">My Profile</h4>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-person-circle me-2"></i>
                                    Profile Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($user_details): ?>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <tr>
                                                <th scope="row" class="w-25">User ID:</th>
                                                <td><?php echo htmlspecialchars($user_details['id']); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Username:</th>
                                                <td><?php echo htmlspecialchars($user_details['username']); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Full Name:</th>
                                                <td><?php echo htmlspecialchars($user_details['first_name'] . ' ' . $user_details['last_name']); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Email:</th>
                                                <td><?php echo htmlspecialchars($user_details['email']); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Phone Number:</th>
                                                <td><?php echo htmlspecialchars($user_details['ph_no']); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Role:</th>
                                                <td><?php echo htmlspecialchars(ucfirst($user_details['role'])); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Status:</th>
                                                <td>
                                                    <?php if ($user_details['status'] == 1): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Gender:</th>
                                                <td><?php echo htmlspecialchars($user_details['gender'] ?? 'Not specified'); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Member Since:</th>
                                                <td><?php echo htmlspecialchars($user_details['created_at']); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Unable to load profile information.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <?php if ($plan_info): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-credit-card me-2"></i>
                                        My Plan
                                    </h5>
                                </div>
                                <div class="card-body text-center">
                                    <h5><?php echo htmlspecialchars($plan_info['plan_name']); ?></h5>
                                    <p class="text-muted">Valid until <?php echo date('M d, Y', strtotime($plan_info['end_date'])); ?></p>
                                    <div class="progress mb-3">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, (($plan_info['breakfast_remaining'] + $plan_info['lunch_remaining'] + $plan_info['dinner_remaining']) / ($plan_info['breakfast_credits'] + $plan_info['lunch_credits'] + $plan_info['dinner_credits'])) * 100); ?>%" aria-valuenow="<?php echo ($plan_info['breakfast_remaining'] + $plan_info['lunch_remaining'] + $plan_info['dinner_remaining']); ?>" aria-valuemin="0" aria-valuemax="<?php echo ($plan_info['breakfast_credits'] + $plan_info['lunch_credits'] + $plan_info['dinner_credits']); ?>"></div>
                                    </div>
                                    <p class="text-muted">Total Credits Remaining</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bi bi-credit-card" style="font-size: 3rem; color: #ccc;"></i>
                                    <h5 class="mt-3">No Active Plan</h5>
                                    <p class="text-muted">Contact admin to get your meal plan.</p>
                                    <a href="my_plan.php" class="btn btn-primary">View My Plan</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-shield-lock me-2"></i>
                                    Change Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-primary w-100">
                                        <i class="bi bi-key me-1"></i>
                                        Change Password
                                    </button>
                                </form>
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