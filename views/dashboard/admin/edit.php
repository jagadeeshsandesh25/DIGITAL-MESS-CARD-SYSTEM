<?php
// views/dashboard/admin/edit.php

if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Allow only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// ✅ Correct database config path (3 levels up)
require_once __DIR__ . '/../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$user_id = $_GET['id'] ?? null;
$error_message = '';
$success_message = '';

if (!$user_id) {
    header("Location: users.php");
    exit;
}

// Fetch user details
try {
    $stmt = $db->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error_message = "User not found.";
    }
} catch (PDOException $e) {
    $error_message = "Error loading user details.";
    error_log($e->getMessage());
}

// Handle form submission (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = $_POST['first_name'];
    $last = $_POST['last_name'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $role = $_POST['role'];
    $status = isset($_POST['status']) ? 1 : 0;

    try {
        $update = $db->prepare("
            UPDATE user 
            SET first_name=?, last_name=?, email=?, username=?, role=?, status=?
            WHERE id=?
        ");
        $update->execute([$first, $last, $email, $username, $role, $status, $user_id]);
        $success_message = "User updated successfully!";
        // Refresh user data
        $stmt = $db->prepare("SELECT * FROM user WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Error updating user. Try again.";
        error_log($e->getMessage());
    }
}

// Page content
ob_start();
?>

<div class="page-title-box mb-4">
    <h2 class="fw-semibold mb-0">Edit User</h2>
    <p class="text-muted">Modify user information and status.</p>
</div>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
<?php elseif ($success_message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>

<?php if ($user): ?>
<form method="post" class="card p-4 shadow-sm" style="max-width:650px;">
    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($user['first_name']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($user['last_name']) ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']) ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($user['username']) ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select" required>
            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="waiter" <?= $user['role'] === 'waiter' ? 'selected' : '' ?>>Waiter</option>
            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
        </select>
    </div>

    <div class="form-check mb-4">
        <input type="checkbox" name="status" class="form-check-input" id="status" <?= $user['status'] ? 'checked' : '' ?>>
        <label for="status" class="form-check-label">Active</label>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-brand">Save Changes</button>
        <a href="users.php" class="btn btn-secondary">Back</a>
    </div>
</form>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
