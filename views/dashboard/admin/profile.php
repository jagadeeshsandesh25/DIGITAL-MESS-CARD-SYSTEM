<?php
// views/dashboard/admin/profile.php - Admin Profile
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'Admin';

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

            if (!$current_user) {
                $message = 'User not found.';
                $message_type = 'danger';
            } else {
                $stored_password = $current_user['password'];
                $is_valid = false;

                // 1. Try modern hash verification (secure)
                if (password_verify($current_password, $stored_password)) {
                    $is_valid = true;
                }
                // 2. Fallback: check plain-text (legacy support ONLY)
                elseif ($stored_password === $current_password) {
                    $is_valid = true;
                    // 🔒 Auto-upgrade plain-text password to hash
                    $upgraded_hash = password_hash($current_password, PASSWORD_DEFAULT);
                    $upgrade_stmt = $db->prepare("UPDATE user SET password = :password WHERE id = :id");
                    $upgrade_stmt->bindParam(':password', $upgraded_hash);
                    $upgrade_stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                    $upgrade_stmt->execute();
                }

                if (!$is_valid) {
                    $message = 'Current password is incorrect.';
                    $message_type = 'danger';
                } else {
                    // Hash & update new password
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
            }
        } catch (PDOException $e) {
            $message = 'Database error occurred. Please try again.';
            $message_type = 'danger';
            error_log("Password change error: " . $e->getMessage());
        }
    }
}

// Fetch user details for display
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

// Build the HTML content
$content = "
<div class='container-fluid py-4'>
    <div class='d-flex justify-content-between align-items-center mb-4'>
        <h4><i class='bi bi-person-circle me-2 text-primary'></i>My Profile</h4>
        <h5 class='mb-0'>Welcome, " . htmlspecialchars($user_name) . "!</h5>
    </div>";

if ($message) {
    $content .= "<div class='alert alert-{$message_type} alert-dismissible fade show' role='alert'>" . htmlspecialchars($message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
}

$content .= "
    <div class='row'>
        <div class='col-lg-8'>
            <div class='card'>
                <div class='card-header bg-white'>
                    <h5 class='card-title mb-0'><i class='bi bi-person-circle me-2 text-primary'></i>Profile Information</h5>
                </div>
                <div class='card-body'>
                    <div class='table-responsive'>
                        <table class='table table-borderless mb-0'>
                            <tbody>
                                <tr>
                                    <th scope='row' class='w-25'>User ID:</th>
                                    <td>" . htmlspecialchars($user_details['id'] ?? 'N/A') . "</td>
                                </tr>
                                <tr>
                                    <th scope='row'>Username:</th>
                                    <td>" . htmlspecialchars($user_details['username'] ?? 'N/A') . "</td>
                                </tr>
                                <tr>
                                    <th scope='row'>Full Name:</th>
                                    <td>" . htmlspecialchars(($user_details['first_name'] ?? '') . ' ' . ($user_details['last_name'] ?? '')) . "</td>
                                </tr>
                                <tr>
                                    <th scope='row'>Email:</th>
                                    <td>" . htmlspecialchars($user_details['email'] ?? 'N/A') . "</td>
                                </tr>
                                <tr>
                                    <th scope='row'>Phone Number:</th>
                                    <td>" . htmlspecialchars($user_details['ph_no'] ?? 'N/A') . "</td>
                                </tr>
                                <tr>
                                    <th scope='row'>Role:</th>
                                    <td>" . htmlspecialchars(ucfirst($user_details['role'] ?? 'N/A')) . "</td>
                                </tr>
                                <tr>
                                    <th scope='row'>Status:</th>
                                    <td>
                                        " . ($user_details && $user_details['status'] == 1 ? "<span class='badge bg-success'>Active</span>" : "<span class='badge bg-warning'>Inactive</span>") . "
                                    </td>
                                </tr>
                                <tr>
                                    <th scope='row'>Gender:</th>
                                    <td>" . htmlspecialchars($user_details['gender'] ?? 'Not specified') . "</td>
                                </tr>
                                <tr>
                                    <th scope='row'>Member Since:</th>
                                    <td>" . htmlspecialchars($user_details['created_at'] ?? 'N/A') . "</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class='col-lg-4'>
            <div class='card'>
                <div class='card-header bg-white'>
                    <h5 class='card-title mb-0'><i class='bi bi-shield-lock me-2 text-warning'></i>Change Password</h5>
                </div>
                <div class='card-body'>
                    <form method='POST' action=''>
                        <div class='mb-3'>
                            <label for='current_password' class='form-label'>Current Password</label>
                            <input type='password' class='form-control' id='current_password' name='current_password' required>
                        </div>
                        <div class='mb-3'>
                            <label for='new_password' class='form-label'>New Password</label>
                            <input type='password' class='form-control' id='new_password' name='new_password' required>
                            <div class='form-text'>Minimum 6 characters</div>
                        </div>
                        <div class='mb-3'>
                            <label for='confirm_password' class='form-label'>Confirm New Password</label>
                            <input type='password' class='form-control' id='confirm_password' name='confirm_password' required>
                        </div>
                        <button type='submit' name='change_password' class='btn btn-primary w-100'>
                            <i class='bi bi-key me-1'></i>
                            Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
";

include '../../layouts/app.php';
?>