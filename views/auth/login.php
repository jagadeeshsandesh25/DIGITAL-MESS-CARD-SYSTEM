<?php
// views/auth/login.php - Check Both Plain Text & Hashed Passwords
session_start();
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
$database = new Database();
$db = $database->getConnection();
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $message = 'Please enter both username and password.';
        $message_type = 'danger';
    } else {
        try {
            $query = "SELECT * FROM user WHERE username = :username OR email = :email LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $password_valid = false;
                if ($user['password'] === $password) {
                    $password_valid = true;
                } elseif (password_verify($password, $user['password'])) {
                    $password_valid = true;
                }
                
                if ($password_valid && $user['status'] == 1) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    if ($user['role'] === 'admin') {
                        header("Location: ../dashboard/admin/index.php");
                    } elseif ($user['role'] === 'waiter') {
                        header("Location: ../dashboard/waiter/index.php");
                    } else {
                        header("Location: ../dashboard/user/index.php");
                    }
                    exit;
                } else {
                    $message = 'Invalid password or account is inactive.';
                    $message_type = 'danger';
                }
            } else {
                $message = 'Invalid username or email.';
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Database error occurred.';
            $message_type = 'danger';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mess Management System</title>
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

        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 2.2rem;
            width: 100%;
            max-width: 460px;
            margin-top: 2rem;
            transition: transform 0.3s ease;
        }

        .login-card:hover {
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
            .login-card {
                padding: 1.8rem;
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center">
        <div class="login-card">
            <div class="card-header">
                <h4>Welcome Back</h4>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Username or Email</label>
                        <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>

                    <div class="divider">
                        <span>or</span>
                    </div>

                    <div class="text-center">
                        <p class="mb-2">
                            Don’t have an account? <a href="signup.php" class="auth-link">Sign up</a>
                        </p>
                        <p class="mb-2">
                         <a href="forgot_password.php" class="auth-link">forgot password</a>
                        </p>
                      
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>