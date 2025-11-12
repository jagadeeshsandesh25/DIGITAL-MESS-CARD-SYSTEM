<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// ✅ Handle Activate / Deactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $user_id = (int)$_POST['user_id'];
    $new_status = (int)$_POST['new_status'];

    if ($user_id === $_SESSION['user_id']) {
        $message = "You cannot change status of your own account.";
        $message_type = "warning";
    } else {
        try {
            $stmt = $db->prepare("UPDATE user SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $user_id]);

            $message = $new_status ? "User activated successfully." : "User deactivated successfully.";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Error updating status.";
            $message_type = "danger";
        }
    }
}

// ✅ Handle ADD user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ph_no = trim($_POST['ph_no'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password) ||
        !in_array($role, ['admin', 'waiter', 'user']) || !in_array($gender, ['Male', 'Female', 'Other'])) {
        $message = "Please fill all required fields correctly.";
        $message_type = "danger";
    } else {
        $check = $db->prepare("SELECT id FROM user WHERE email = ? OR username = ?");
        $check->execute([$email, $username]);
        if ($check->fetch()) {
            $message = "Email or username already exists.";
            $message_type = "warning";
        } else {
            $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                INSERT INTO user (first_name, last_name, email, ph_no, username, password, status, role, gender)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $first_name, $last_name, $email, $ph_no, $username, $hashed_pass, $status, $role, $gender
            ]);

            if ($result) {
                $message = "New user added successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to add user.";
                $message_type = "danger";
            }
        }
    }
}

// ✅ Fetch all users
try {
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, username, role, gender, status, created_at FROM user ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    $message = "Could not load users.";
    $message_type = "danger";
}

ob_start();
?>

<div class="page-title-box mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-semibold mb-0">Manage Users</h2>
            <p class="text-muted mb-0">View, add, activate or deactivate users.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-circle"></i> Add New User
        </button>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($users)): ?>
<ul class="nav nav-tabs" id="userTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">Active Users</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="inactive-tab" data-bs-toggle="tab" data-bs-target="#inactive" type="button" role="tab">Inactive Users</button>
    </li>
</ul>

<div class="tab-content pt-3" id="userTabsContent">
    <!-- Active Users -->
    <div class="tab-pane fade show active" id="active" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email / Username</th>
                        <th>Role</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): if ($u['status'] != 1) continue; ?>
                    <tr>
                        <td><?= htmlspecialchars($u['id']) ?></td>
                        <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td><small><?= htmlspecialchars($u['email']) ?><br><strong><?= htmlspecialchars($u['username']) ?></strong></small></td>
                        <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'waiter' ? 'warning' : 'info') ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td><?= htmlspecialchars($u['gender']) ?></td>
                        <td><span class="badge bg-success">Active</span></td>
                        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="new_status" value="0">
                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-person-dash"></i> Deactivate
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Inactive Users -->
    <div class="tab-pane fade" id="inactive" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email / Username</th>
                        <th>Role</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): if ($u['status'] != 0) continue; ?>
                    <tr>
                        <td><?= htmlspecialchars($u['id']) ?></td>
                        <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td><small><?= htmlspecialchars($u['email']) ?><br><strong><?= htmlspecialchars($u['username']) ?></strong></small></td>
                        <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'waiter' ? 'warning' : 'info') ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td><?= htmlspecialchars($u['gender']) ?></td>
                        <td><span class="badge bg-secondary">Inactive</span></td>
                        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="new_status" value="1">
                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-person-check"></i> Activate
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<p class="text-muted">No users found.</p>
<?php endif; ?>


<!-- ✅ Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="ph_no" class="form-control" maxlength="10">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select" required>
                            <option value="user">User</option>
                            <option value="waiter">Waiter</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender *</label>
                        <select name="gender" class="form-select" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="status" id="statusCheck" class="form-check-input" checked>
                        <label class="form-check-label" for="statusCheck">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>
 <!-- #region -->