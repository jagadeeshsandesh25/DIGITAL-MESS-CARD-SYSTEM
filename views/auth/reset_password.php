<?php
// views/auth/reset_password.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
session_start();

$token = $_GET['token'] ?? '';
$success_message = '';
$error_message = '';

if (empty($token)) {
    die("Invalid or missing reset token.");
}

$database = new Database();
$db = $database->getConnection();

// Step 1: Verify token and check expiry
try {
    $query = "SELECT id, reset_expires FROM user WHERE reset_token = :token LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error_message = "Invalid or expired reset link.";
    } elseif (strtotime($user['reset_expires']) < time()) {
        $error_message = "Reset link has expired. Please request a new one.";
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($password) || empty($confirm_password)) {
            $error_message = "Both password fields are required.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            // Step 2: Update password and clear token
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update = "UPDATE user 
                       SET password = :password, reset_token = NULL, reset_expires = NULL 
                       WHERE id = :id";
            $update_stmt = $db->prepare($update);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':id', $user['id']);

            if ($update_stmt->execute()) {
                $success_message = "Your password has been reset successfully! <a href='login.php' class='login-link'>Login here</a>.";
            } else {
                $error_message = "Failed to reset password. Please try again.";
            }
        }
    }
} catch (PDOException $e) {
    $error_message = "Database error: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Mess Management System</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf9 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 60px;
        }

        .navbar-brand {
            font-weight: 600;
            letter-spacing: -0.5px;
        }

        .navbar-brand i {
            color: #4361ee;
        }

        .reset-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 2.2rem;
            width: 100%;
            max-width: 460px;
            margin-top: 2rem;
            transition: transform 0.3s ease;
        }

        .reset-card:hover {
            transform: translateY(-3px);
        }

        .card-header {
            background: none;
            border: none;
            padding-bottom: 0.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .card-header h4 {
            font-weight: 600;
            color: #2b2d42;
            font-size: 1.5rem;
        }

        .card-header i {
            font-size: 2rem;
            color: #4361ee;
            margin-bottom: 0.5rem;
        }

        .form-label {
            font-weight: 500;
            color: #4a4a68;
            margin-bottom: 0.4rem;
            font-size: 0.92rem;
        }

        .form-control {
            border: 1px solid #d1d5db;
            padding: 0.65rem 1rem;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: border-color 0.25s, box-shadow 0.25s;
        }

        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(to right, #4361ee, #3a0ca3);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 10px;
            transition: opacity 0.3s;
        }

        .btn-primary:hover {
            opacity: 0.92;
        }

        .alert {
            border-radius: 10px;
            font-weight: 500;
        }

        .login-link {
            color: #4361ee;
            font-weight: 600;
            text-decoration: none;
        }

        .login-link:hover {
            text-decoration: underline;
        }

        .auth-link {
            color: #4361ee;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.92rem;
        }

        .auth-link:hover {
            text-decoration: underline;
        }

        .divider {
            text-align: center;
            position: relative;
            margin: 1.2rem 0;
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .divider::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e2e8f0;
            z-index: 1;
        }

        .divider span {
            background: white;
            position: relative;
            z-index: 2;
            padding: 0 0.6rem;
        }

        @media (max-width: 576px) {
            .reset-card {
                padding: 1.8rem;
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-white shadow-sm fixed-top border-bottom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center text-dark" href="#">
                <i class="bi bi-people-fill me-2"></i>
                Mess Management
            </a>
        </div>
    </nav>

    <div class="container d-flex justify-content-center">
        <div class="reset-card">
            <div class="card-header">
                <i class="bi bi-key-fill"></i>
                <h4>Set New Password</h4>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php else: ?>
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">At least 6 characters</div>
                        </div>
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary">Reset Password</button>
                        </div>
                    </form>

                    <div class="divider">
                        <span>or</span>
                    </div>

                    <div class="text-center">
                        <p class="mb-0">
                            <a href="login.php" class="auth-link">Back to Login</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>