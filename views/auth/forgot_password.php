<?php
// views/auth/forgot_password.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Redirect logged-in users
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        header("Location: ../dashboard/admin/index.php");
    } elseif ($role === 'waiter') {
        header("Location: ../dashboard/waiter/index.php");
    } else {
        header("Location: ../dashboard/user/index.php");
    }
    exit;
}

require_once '../../config/database.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login_input'] ?? '');

    if (!empty($login_input)) {
        try {
            $database = new Database();
            $db = $database->getConnection();

            if (!$db) {
                die("Database connection failed!");
            }

            // ✅ Proper placeholders (fixes HY093)
            $query = "SELECT id, email, ph_no FROM user WHERE email = :email OR ph_no = :phone LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $login_input);
            $stmt->bindParam(':phone', $login_input);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // ✅ Generate secure reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $updateQuery = "UPDATE user SET reset_token = :token, reset_expires = :expires WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':token', $token);
                $updateStmt->bindParam(':expires', $expires);
                $updateStmt->bindParam(':id', $user['id']);
                $updateStmt->execute();

                // ✅ Construct reset link
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

                // ⚡ Optional: send email or SMS
                // mail($user['email'], "Password Reset", "Click the link to reset your password: $resetLink");

                $success_message = "A password reset link has been generated successfully.<br><a href='$resetLink' target='_blank' class='reset-link'>Click here to reset your password</a>";
            } else {
                $error_message = "No account found with that email or phone number.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
            error_log("Forgot Password Error: " . $e->getMessage());
        }
    } else {
        $error_message = "Please enter your registered email or phone number.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - Mess Management</title>
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

        .forgot-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 2.2rem;
            width: 100%;
            max-width: 460px;
            margin-top: 2rem;
            transition: transform 0.3s ease;
        }

        .forgot-card:hover {
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

        .reset-link {
            color: #4361ee;
            font-weight: 600;
            text-decoration: none;
        }

        .reset-link:hover {
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
            .forgot-card {
                padding: 1.8rem;
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>

    <div class="container d-flex justify-content-center">
        <div class="forgot-card">
            <div class="card-header">
                <i class="bi bi-unlock-fill"></i>
                <h4>Reset Your Password</h4>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $success_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <div class="mb-4">
                        <label for="login_input" class="form-label">Email or Phone Number</label>
                        <input type="text" class="form-control" id="login_input" name="login_input"
                               placeholder="Enter your registered email or phone number"
                               value="<?= htmlspecialchars($_POST['login_input'] ?? '') ?>" required>
                        <small id="input_hint" class="form-text text-muted">
                            Enter the email or phone number linked to your account.
                        </small>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary">Send Reset Link</button>
                    </div>

                    <div class="divider">
                        <span>or</span>
                    </div>

                    <div class="text-center">
                        <p class="mb-2">
                            Remember your password? <a href="login.php" class="auth-link">Back to Login</a>
                        </p>
                        <p class="mb-0">
                            New user? <a href="signup.php" class="auth-link">Sign Up</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const inputField = document.getElementById('login_input');
        const hint = document.getElementById('input_hint');

        inputField.addEventListener('input', () => {
            const value = inputField.value.trim();
            if (/^\d{10}$/.test(value)) {
                hint.textContent = "Phone number detected.";
                hint.className = "form-text text-success";
            } else if (/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(value)) {
                hint.textContent = "Email address detected.";
                hint.className = "form-text text-success";
            } else {
                hint.textContent = "Enter the email or phone number linked to your account.";
                hint.className = "form-text text-muted";
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>