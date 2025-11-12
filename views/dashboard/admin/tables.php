<?php
// views/dashboard/admin/tables.php — Final version (Edit functionality REMOVED)
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

/* ===========================================================
   TABLE STRUCTURE:
   id | t_qr | t_name | t_s_w | status | created_at
=========================================================== */

// ✅ CREATE NEW TABLE
if (isset($_POST['add_table'])) {
    $t_qr = trim($_POST['t_qr']);
    $t_name = $_POST['t_name'];
    $t_s_w = (int) $_POST['t_s_w'];
    $status = $_POST['status'] ?? 'available';

    if (!empty($t_qr) && !empty($t_name) && $t_s_w > 0) {
        $stmt = $db->prepare("INSERT INTO tables (t_qr, t_name, t_s_w, status) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$t_qr, $t_name, $t_s_w, $status])) {
            $message = "✅ Table added successfully!";
            $message_type = "success";
        } else {
            $message = "❌ Failed to add table.";
            $message_type = "danger";
        }
    } else {
        $message = "⚠️ Please fill all fields correctly.";
        $message_type = "warning";
    }
}

// ✅ DELETE TABLE
if (isset($_POST['delete_table'])) {
    $id = (int) $_POST['id'];
    $stmt = $db->prepare("DELETE FROM tables WHERE id=?");
    if ($stmt->execute([$id])) {
        $message = "🗑️ Table deleted successfully!";
        $message_type = "success";
    } else {
        $message = "❌ Failed to delete table.";
        $message_type = "danger";
    }
}

// ✅ FETCH ALL TABLES
$stmt = $db->query("SELECT * FROM tables ORDER BY id ASC");
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

$content = '
<div class="container-fluid">
    <div class="page-title-box">
        <h4 class="page-title">Manage Tables</h4>
    </div>';

if ($message) {
    $content .= "
    <div class='alert alert-$message_type alert-dismissible fade show' role='alert'>
        $message
        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
    </div>";
}

$content .= '
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>Tables List</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTableModal">
            <i class="bi bi-plus-circle"></i> Add New Table
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>QR Code</th>
                            <th>Table Name</th>
                            <th>Waiter</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

if (!empty($tables)) {
    foreach ($tables as $table) {
        $status_badge = match ($table['status']) {
            'available' => '<span class="badge bg-success">Available</span>',
            'reserved' => '<span class="badge bg-warning text-dark">Reserved</span>',
            default => '<span class="badge bg-danger">Occupied</span>'
        };

        $content .= "
        <tr>
            <td>{$table['id']}</td>
            <td>" . htmlspecialchars($table['t_qr']) . "</td>
            <td>{$table['t_name']}</td>
            <td>{$table['t_s_w']}</td>
            <td>{$status_badge}</td>
            <td>
                <form method='POST' class='d-inline' onsubmit='return confirm(\"Delete this table?\")'>
                    <input type='hidden' name='id' value='{$table['id']}'>
                    <button type='submit' name='delete_table' class='btn btn-sm btn-danger'>
                        <i class='bi bi-trash'></i>
                    </button>
                </form>
            </td>
        </tr>";
    }
} else {
    $content .= "<tr><td colspan='6' class='text-center text-muted'>No tables found.</td></tr>";
}

$content .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Table Modal -->
<div class="modal fade" id="addTableModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Add New Table</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">QR Code</label>
            <input type="text" name="t_qr" class="form-control" placeholder="Enter QR Code" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Table Name</label>
            <select name="t_name" class="form-select" required>
              <option value="A">A</option>
              <option value="B">B</option>
              <option value="C">C</option>
              <option value="D">D</option>
              <option value="E">E</option>
              <option value="F">F</option>
              <option value="G">G</option>
              <option value="H">H</option>
              <option value="I">I</option>
              <option value="J">J</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Seats / Waiter</label>
            <input type="number" name="t_s_w" class="form-control" placeholder="Enter seats or waiter ID" required min="1">
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="available" selected>Available</option>
              <option value="reserved">Reserved</option>
              <option value="occupied">Occupied</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_table" class="btn btn-primary">Add Table</button>
        </div>
      </form>
    </div>
  </div>
</div>
';

include '../../layouts/app.php';
?>