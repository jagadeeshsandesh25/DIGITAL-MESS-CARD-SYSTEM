<?php
// views/dashboard/user/change_password.php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'User';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
$database = new Database();
$db = $database->getConnection();
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
$content = "
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-12'>
                <div class='page-title-box'>
                    <div class='page-title-right'>
                        <ol class='breadcrumb m-0'>
                            <li class='breadcrumb-item'><a href='index.php'>Dashboard</a></li>
                            <li class='breadcrumb-item'><a href='profile.php'>Profile</a></li>
                            <li class='breadcrumb-item active'>Change Password</li>
                        </ol>
                    </div>
                    <h4 class='page-title'>Change Password</h4>
                </div>
            </div>
        </div>

        <div class='row justify-content-center'>
            <div class='col-lg-6'>
                <div class='card'>
                    <div class='card-header'>
                        <h5 class='card-title mb-0'>Update Your Password</h5>
                    </div>
                    <div class='card-body'>
";
if ($message) {
    $content .= "<div class='alert alert-$message_type alert-dismissible fade show' role='alert'>
                    $message
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                 </div>";
}
$content .= "
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
                            <div class='d-grid gap-2'>
                                <button type='submit' class='btn btn-primary'>Change Password</button>
                                <a href='profile.php' class='btn btn-secondary'>Back to Profile</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
";
include '../../layouts/app.php';
?>