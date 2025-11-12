<?php
// views/dashboard/admin/orders.php
// Purpose: Admin can record manual transactions (e.g., cash payments, UPI, etc.)

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_name = $_SESSION['username'] ?? 'Admin';
define('BASE_PATH', dirname(__DIR__, 3));

require_once BASE_PATH . '/config/database.php';
$database = new Database();
$db = $database->getConnection();

/* --------------------------------------------------------------------------
   Handle Transaction Creation (POST)
-------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['transaction_mode'], $_POST['total_amount'])) {
    $user_id = (int)$_POST['user_id'];
    $transaction_mode = trim($_POST['transaction_mode']);
    $total_amount = (float)$_POST['total_amount'];
    $utr_number = trim($_POST['utr_number'] ?? '');

    try {
        // Validate user exists (admin or user)
        $userCheck = $db->prepare("SELECT id FROM user WHERE id = ?");
        $userCheck->execute([$user_id]);
        if (!$userCheck->fetch()) {
            throw new Exception("Selected user does not exist.");
        }

        // Build t_type based on mode and UTR
        $t_type = $transaction_mode;
        if ($transaction_mode === 'UPI' && !empty($utr_number)) {
            $t_type = "UPI - UTR";
        } elseif ($transaction_mode === 'Card' && !empty($utr_number)) {
            $t_type = "Card - UTR";
        } elseif ($transaction_mode === 'Cash' && !empty($utr_number)) {
            $t_type = "Cash - UTR";
        }

        // Insert into transactions (status = 'completed' since admin-initiated)
        $stmt = $db->prepare("
            INSERT INTO transactions (user_id, t_type, utr_number, amount, status)
            VALUES (?, ?, ?, ?, 'completed')
        ");
        $stmt->execute([
            $user_id,
            $t_type,
            !empty($utr_number) ? $utr_number : null,
            $total_amount
        ]);

        $success_msg = "✅ Transaction recorded successfully for user ID: $user_id";
    } catch (Exception $e) {
        $error_msg = "⚠️ Error: " . htmlspecialchars($e->getMessage());
    }
}

/* --------------------------------------------------------------------------
   Fetch Users: Include 'user' and 'admin' roles
-------------------------------------------------------------------------- */
$users = $db->query("
    SELECT id, first_name, last_name, role 
    FROM user 
    WHERE role IN ('user', 'admin') 
    ORDER BY role DESC, first_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent transactions (last 5)
$recent_txns = $db->query("
    SELECT t.id, t.amount, t.t_type, t.status, t.t_time, 
           u.first_name, u.last_name 
    FROM transactions t
    JOIN user u ON t.user_id = u.id
    ORDER BY t.id DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="page-title-box">
    <h2><i class="bi bi-credit-card"></i> Record Transaction</h2>
    <p class="text-muted mb-0">Welcome, <?= htmlspecialchars($user_name) ?></p>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h4>Record a New Payment</h4>
        <p class="text-muted">Manually log cash, UPI, or card payments for any user (including admins).</p>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" class="row g-3 mt-3">
            <!-- Select User (now includes admin) -->
            <div class="col-md-4">
                <label class="form-label">Select User</label>
                <select name="user_id" class="form-select" required>
                    <option value="">-- Choose User --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>">
                            <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                            (<?= ucfirst($u['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Transaction Mode -->
            <div class="col-md-4">
                <label class="form-label">Payment Method</label>
                <select name="transaction_mode" class="form-select" required id="txnMode">
                    <option value="Cash">Cash</option>
                    <option value="UPI">UPI</option>
                    <option value="Card">Card</option>
                </select>
            </div>

            <!-- UTR / Reference Number (optional for all, shown conditionally) -->
            <div class="col-md-4" id="utrField" style="display: none;">
                <label class="form-label">Reference / UTR (Optional)</label>
                <input type="text" name="utr_number" class="form-control" placeholder="Enter UTR or ref. no.">
                <div class="form-text">Shown in transaction type if provided.</div>
            </div>

            <!-- Amount -->
            <div class="col-md-4">
                <label class="form-label">Amount (₹)</label>
                <input type="number" name="total_amount" class="form-control" required min="1" step="0.01" value="100">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Record Transaction</button>
                <a href="orders.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Recent Transactions -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-clock-history me-2"></i> Recent Transactions</h5>
    </div>
    <div class="card-body">
        <?php if (count($recent_txns) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_txns as $r): ?>
                            <tr>
                                <td><?= $r['id'] ?></td>
                                <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                <td>₹<?= number_format($r['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($r['t_type']) ?></td>
                                <td><span class="badge bg-success"><?= ucfirst($r['status']) ?></span></td>
                                <td><?= date('d M Y, H:i', strtotime($r['t_time'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">No transactions found.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Toggle UTR field visibility
document.getElementById('txnMode').addEventListener('change', function() {
    const utrField = document.getElementById('utrField');
    // Show for all modes (optional), or only UPI — your call!
    utrField.style.display = 'block';
});

// Also show on page load if needed (optional)
</script>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layouts/app.php';
?>