<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Handle success/error messages from redirect
$message = "";
if (isset($_SESSION['profile_message'])) {
    $msg_text = $_SESSION['profile_message'];
    $is_success = stripos($msg_text, 'error') === false;
    $message = "<div class='alert alert-" . ($is_success ? 'success' : 'danger') . " alert-dismissible fade show' role='alert'>" . htmlspecialchars($msg_text) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    unset($_SESSION['profile_message']);
}

// Handle Profile Update (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['ph_no']);
    $gender     = $_POST['gender'] ?? null;

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $_SESSION['profile_message'] = "Please fill all required fields.";
        header("Location: profile.php");
        exit;
    }

    $profile_image = null;
    if (!empty($_FILES['profile_image']['name'])) {
        $upload_dir = realpath(__DIR__ . '/../../../uploads/');
        if (!$upload_dir || !is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $filename = time() . "_" . preg_replace("/[^a-zA-Z0-9._-]/", "_", $_FILES["profile_image"]["name"]);
        $target_file = $upload_dir . DIRECTORY_SEPARATOR . $filename;

        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            $profile_image = $filename;
        }
    }

    $sql = "UPDATE user SET first_name = :first_name, last_name = :last_name, email = :email, ph_no = :ph_no, gender = :gender";
    if ($profile_image) {
        $sql .= ", profile_image = :profile_image";
    }
    $sql .= " WHERE id = :id";

    try {
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':ph_no', $phone);
        $stmt->bindParam(':gender', $gender);
        $stmt->bindParam(':id', $user_id);
        if ($profile_image) {
            $stmt->bindParam(':profile_image', $profile_image);
        }

        if ($stmt->execute()) {
            $_SESSION['profile_message'] = "Profile updated successfully!";
        } else {
            $_SESSION['profile_message'] = "Error updating profile.";
        }
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        $_SESSION['profile_message'] = "Database error occurred.";
    }

    header("Location: profile.php");
    exit;
}

// Handle Password Update (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_password'])) {
    $old_pass = $_POST['old_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($old_pass) || empty($new_pass) || empty($confirm_pass)) {
        $_SESSION['profile_message'] = "All password fields are required.";
        header("Location: profile.php");
        exit;
    }

    if ($new_pass !== $confirm_pass) {
        $_SESSION['profile_message'] = "New passwords do not match.";
        header("Location: profile.php");
        exit;
    }

    $stmt = $db->prepare("SELECT password FROM user WHERE id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_record) {
        $_SESSION['profile_message'] = "User not found.";
        header("Location: profile.php");
        exit;
    }

    $hashed_password = $user_record['password'];

    if (password_verify($old_pass, $hashed_password)) {
        $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $update = $db->prepare("UPDATE user SET password = :password WHERE id = :id");
        $update->bindParam(':password', $new_hash);
        $update->bindParam(':id', $user_id);

        if ($update->execute()) {
            $_SESSION['profile_message'] = "Password updated successfully!";
        } else {
            $_SESSION['profile_message'] = "Failed to update password.";
        }
    } else {
        $_SESSION['profile_message'] = "Incorrect old password.";
    }

    header("Location: profile.php");
    exit;
}

// Fetch current user data
$stmt = $db->prepare("SELECT * FROM user WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

$profile_image_url = !empty($user['profile_image'])
    ? "../../../uploads/" . htmlspecialchars($user['profile_image'])
    : "https://cdn-icons-png.flaticon.com/512/847/847969.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
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
            background-color: var(--light-bg);
            color: #1e293b;
            font-family: 'Poppins', sans-serif;
            padding-top: 72px;
            margin: 0;
        }

        body.dark-mode {
            background-color: var(--dark-bg);
            color: #e2e8f0;
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
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            margin-bottom: 1.5rem;
            background: white;
        }

        body.dark-mode .card {
            background: #1e1e1e;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--secondary-color);
            transition: transform 0.25s ease;
        }

        .profile-avatar:hover {
            transform: scale(1.03);
        }

        .btn-brand {
            background: linear-gradient(to right, var(--secondary-color), #e6762f);
            color: white;
            border: none;
            padding: 0.55rem 1.25rem;
            font-weight: 600;
            border-radius: 10px;
            transition: opacity 0.25s;
        }

        .btn-brand:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }

        .form-label {
            font-weight: 500;
            color: #475569;
            margin-bottom: 0.4rem;
        }

        body.dark-mode .form-label {
            color: #cbd5e1;
        }

        .form-control, .form-select {
            border: 1px solid #d1d5db;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            transition: border-color 0.25s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(255, 140, 66, 0.15);
        }

        .card-header {
            background: white;
            font-weight: 600;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 16px 16px 0 0 !important;
        }

        body.dark-mode .card-header {
            background: #1e1e1e;
            border-bottom-color: #2d3748;
            color: #f1f5f9;
        }

        .alert {
            border-radius: 12px;
        }

        .profile-section {
            margin-bottom: 1.5rem;
        }

        .profile-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .password-form .row {
            flex-direction: column;
        }

        .password-form .col-md-4 {
            width: 100%;
            margin-bottom: 0.75rem;
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

            .container {
                padding: 0 0.75rem;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
            }

            .card {
                margin-bottom: 1rem;
            }

            .card-header {
                padding: 0.875rem !important;
            }

            .card-header h5 {
                font-size: 1rem;
            }

            .form-control, .form-select {
                padding: 0.55rem 0.875rem;
            }

            .btn-brand {
                width: 100%;
                text-align: center;
            }

            .password-form .btn-warning {
                width: 100%;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                align-items: flex-start;
            }

            .d-flex.justify-content-between a {
                margin-top: 0.75rem;
            }

            .row.g-4 {
                margin: 0;
            }

            .col-lg-4, .col-lg-8 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .profile-section {
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 768px) {
            .row.g-4 > [class*="col-"] {
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
            <i class="bi bi-person-badge me-2 text-primary"></i> Waiter Dashboard
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
                <a class="nav-link" href="support_order.php"><i class="bi bi-headset me-1"></i> Support</a>
                <a class="nav-link" href="../../../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?= $message ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-person-circle me-2 text-primary"></i> My Profile</h3>
        <a href="index.php" class="btn btn-sm btn-outline-primary d-flex align-items-center mt-2 mt-md-0">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="row g-4">
        <!-- Profile Preview -->
        <div class="col-lg-4">
            <div class="card profile-card p-4">
                <img src="<?= $profile_image_url ?>" id="profilePreview" class="profile-avatar mx-auto mb-3" alt="Profile Picture">
                <h5 class="fw-semibold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                <p class="text-muted small"><?= htmlspecialchars($user['email']); ?></p>
                <span class="badge bg-success px-3 py-2"><?= ucfirst(htmlspecialchars($user['role'])); ?></span>
                <?php if (!empty($user['created_at'])): ?>
                    <div class="mt-3 text-muted small">
                        Joined: <?= date('M Y', strtotime($user['created_at'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Update Profile Form -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center">
                    <i class="bi bi-pencil-square me-2 text-warning"></i> Update Profile
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']); ?>" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']); ?>" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="ph_no" value="<?= htmlspecialchars($user['ph_no']); ?>" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?= $user['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $user['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= $user['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Profile Picture</label>
                                <input type="file" name="profile_image" id="profileImageInput" class="form-control" accept="image/*">
                                <div class="form-text small">JPG, PNG up to 2MB</div>
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button type="submit" name="update_profile" class="btn btn-brand">
                                <i class="bi bi-save2 me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password Form -->
            <div class="card password-form">
                <div class="card-header d-flex align-items-center">
                    <i class="bi bi-key me-2 text-warning"></i> Change Password
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Old Password</label>
                                <input type="password" name="old_password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required minlength="6">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="6">
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button type="submit" name="update_password" class="btn btn-warning text-white px-4">
                                <i class="bi bi-shield-lock me-1"></i> Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sticky footer for mobile -->
<div class="sticky-footer d-md-none">
    <div class="d-flex justify-content-around">
        <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2"></i></a>
        <a href="support_order.php" class="btn btn-outline-success btn-sm"><i class="bi bi-headset"></i></a>
        <a href="../../../logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>

<!-- Live Image Preview -->
<script>
    document.getElementById("profileImageInput").addEventListener("change", function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (event) => {
                document.getElementById("profilePreview").src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>