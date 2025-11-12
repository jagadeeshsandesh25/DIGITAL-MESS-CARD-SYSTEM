<?php
// views/auth/signup.php - Fixed for User Role Only
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ph_no = trim($_POST['ph_no'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $gender = $_POST['gender'] ?? '';
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($ph_no) || empty($username) || empty($password) || empty($confirm_password) || empty($gender)) {
        $message = 'All fields are required.';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'danger';
    } elseif (!preg_match('/^[0-9]{10}$/', $ph_no)) {
        $message = 'Please enter a valid 10-digit phone number.';
        $message_type = 'danger';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $message_type = 'danger';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'danger';
    } else {
        try {
            $check_query = "SELECT * FROM user WHERE username = :username OR email = :email LIMIT 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            $existing_user = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_user) {
                $message = 'Username or email already exists.';
                $message_type = 'danger';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_query = "INSERT INTO user (first_name, last_name, email, ph_no, username, password, status, role, gender) VALUES (:first_name, :last_name, :email, :ph_no, :username, :password, 1, 'user', :gender)";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':first_name', $first_name);
                $insert_stmt->bindParam(':last_name', $last_name);
                $insert_stmt->bindParam(':email', $email);
                $insert_stmt->bindParam(':ph_no', $ph_no);
                $insert_stmt->bindParam(':username', $username);
                $insert_stmt->bindParam(':password', $hashed_password);
                $insert_stmt->bindParam(':gender', $gender);
                
                if ($insert_stmt->execute()) {
                    $user_id = $db->lastInsertId();
                    $details_query = "INSERT INTO user_details (user_id, present_pswd, contact_details) VALUES (:user_id, :present_pswd, :contact_details)";
                    $details_stmt = $db->prepare($details_query);
                    $details_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $details_stmt->bindParam(':present_pswd', $hashed_password);
                    $details_stmt->bindParam(':contact_details', $ph_no);
                    
                    if ($details_stmt->execute()) {
                        $message = 'Account created successfully! You can now login.';
                        $message_type = 'success';
                    } else {
                        $message = 'Account created but failed to create user details.';
                        $message_type = 'warning';
                    }
                } else {
                    $message = 'Failed to create account. Please try again.';
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            $message = 'Database error occurred.';
            $message_type = 'danger';
            error_log("Signup error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Mess Management System</title>
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
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .signup-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 2.5rem;
            width: 100%;
            max-width: 520px;
            transition: transform 0.3s ease;
        }

        .signup-card:hover {
            transform: translateY(-4px);
        }

        .logo {
            text-align: center;
            margin-bottom: 1.8rem;
        }

        .logo i {
            font-size: 3rem;
            color: #4361ee;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo h3 {
            font-weight: 600;
            color: #2b2d42;
            margin-top: 0.5rem;
            letter-spacing: -0.5px;
        }

        .form-label {
            font-weight: 500;
            color: #4a4a68;
            margin-bottom: 0.4rem;
            font-size: 0.92rem;
        }

        .form-control, .form-select {
            border: 1px solid #d1d5db;
            padding: 0.65rem 1rem;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: border-color 0.25s, box-shadow 0.25s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            outline: none;
        }

        .form-text {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
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
            transform: none;
        }

        .alert {
            border-radius: 10px;
            font-weight: 500;
        }

        .login-link {
            color: #4361ee;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .signup-card {
                padding: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="signup-card">
        <div class="logo">
            <i class="bi bi-people-fill"></i>
            <h3>Mess Management</h3>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="tel" class="form-control" name="ph_no" value="<?php echo htmlspecialchars($_POST['ph_no'] ?? ''); ?>" required>
                <div class="form-text">Enter 10-digit Indian mobile number</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                    <div class="form-text">At least 6 characters</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Gender</label>
                <select class="form-select" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo (($_POST['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo (($_POST['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo (($_POST['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" name="signup" class="btn btn-primary">Create Account</button>
            </div>

            <div class="text-center">
                Already have an account? <a href="login.php" class="login-link">Login here</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>