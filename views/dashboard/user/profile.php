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

        .form-label {
            font-weight: 500;
            color: #475569;
        }

        .form-control {
            border: 1px solid #d1d5db;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            transition: border-color 0.25s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(40, 65, 148, 0.15);
        }

        .table th {
            font-weight: 600;
            color: #475569;
        }

        .alert {
            border-radius: 12px;
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

            .table-responsive {
                font-size: 0.85rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                align-items: flex-start;
            }

            .d-flex.justify-content-between a {
                margin-top: 0.75rem;
            }

            .form-control {
                padding: 0.55rem 0.875rem;
            }

            .btn {
                padding: 0.55rem 1rem;
            }

            .col-lg-8, .col-lg-4 {
                flex: 0 0 100%;
                max-width: 100%;
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
                    <a class="nav-link" href="index.php"><i class="bi bi-house me-1"></i> Dashboard</a>
                    <a class="nav-link" href="../../../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
                </div>
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
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex align-items-center">
                                <i class="bi bi-person-circle me-2 text-primary"></i>
                                <h5 class="card-title mb-0">Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($user_details): ?>
                                    <div class="table-responsive">
                                        <table class="table table-borderless mb-0">
                                            <tbody>
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
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted mb-0">Unable to load profile information.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <?php if ($plan_info): ?>
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <i class="bi bi-credit-card me-2 text-success"></i>
                                    <h5 class="card-title mb-0">My Plan</h5>
                                </div>
                                <div class="card-body text-center">
                                    <h5><?php echo htmlspecialchars($plan_info['plan_name']); ?></h5>
                                    <p class="text-muted">Valid until <?php echo date('M d, Y', strtotime($plan_info['end_date'])); ?></p>
                                    <div class="progress mb-3">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, (($plan_info['breakfast_remaining'] + $plan_info['lunch_remaining'] + $plan_info['dinner_remaining']) / ($plan_info['breakfast_credits'] + $plan_info['lunch_credits'] + $plan_info['dinner_credits'])) * 100); ?>%" aria-valuenow="<?php echo ($plan_info['breakfast_remaining'] + $plan_info['lunch_remaining'] + $plan_info['dinner_remaining']); ?>" aria-valuemin="0" aria-valuemax="<?php echo ($plan_info['breakfast_credits'] + $plan_info['lunch_credits'] + $plan_info['dinner_credits']); ?>"></div>
                                    </div>
                                    <p class="text-muted mb-0">Total Credits Remaining</p>
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
                            <div class="card-header d-flex align-items-center">
                                <i class="bi bi-shield-lock me-2 text-warning"></i>
                                <h5 class="card-title mb-0">Change Password</h5>
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