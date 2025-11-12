<?php
//waiter/support_order.php
session_start();
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../../auth/login.php");
    exit;
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$waiter_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

/* ===========================
   FETCH DATA
=========================== */
$tables = $db->query("SELECT id, t_name FROM tables ORDER BY t_name")->fetchAll(PDO::FETCH_ASSOC);
$menu_items = $db->query("SELECT id, name, price, menu_type FROM menu ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);

// ✅ Include 'user' and 'admin' (optionally 'waiter') in customer list
$customers = $db->query("
    SELECT id, username, role 
    FROM user 
    WHERE role IN ('user', 'admin') 
    ORDER BY role DESC, username
")->fetchAll(PDO::FETCH_ASSOC);

/* ===========================
   HANDLE TRANSACTION CREATION
=========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $support_mode = isset($_POST['support_mode']);
    $table_id = $_POST['table_id'] ?? '';
    $payment_mode = $_POST['payment_mode'] ?? 'Cash';
    $ordered_items = $_POST['ordered_items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];

    if (empty($table_id) || empty($ordered_items)) {
        $error_message = "Please select a table and at least one menu item.";
    } else {
        try {
            // Calculate total amount
            $total_amount = 0;
            foreach ($ordered_items as $index => $menu_id) {
                $qty = max(1, intval($quantities[$index] ?? 1));
                $stmt = $db->prepare("SELECT price FROM menu WHERE id = ?");
                $stmt->execute([$menu_id]);
                $price = $stmt->fetchColumn();
                if ($price) {
                    $total_amount += $price * $qty;
                }
            }

            if ($total_amount <= 0) {
                throw new Exception("Invalid total amount.");
            }

            // Determine user_id for transaction
            if ($support_mode) {
                // In support mode, use waiter as the user (e.g., internal billing)
                $user_id_for_txn = $waiter_id;
            } else {
                $customer_id = $_POST['customer_id'] ?? null;
                if (!$customer_id) {
                    throw new Exception("Please select a customer.");
                }
                $user_id_for_txn = $customer_id;
            }

            // Map payment_mode to t_type
            $t_type = ($payment_mode === 'Online') ? 'UPI' : 'Cash';

            $db->beginTransaction();

            // ✅ INSERT INTO transactions (not orders)
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, t_type, amount, status)
                VALUES (?, ?, ?, 'completed')
            ");
            $stmt->execute([
                $user_id_for_txn,
                $t_type,
                $total_amount
            ]);

            $db->commit();
            $success_message = $support_mode
                ? "Support transaction recorded successfully!"
                : "Transaction recorded successfully!";

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Transaction error: " . $e->getMessage());
            $error_message = "Error recording transaction. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support / Create Transaction</title>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf9 100%);
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

        .menu-item-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }

        body.dark-mode .menu-item-card {
            background: #2d3748;
            border-color: #4a5568;
        }

        @media (max-width: 576px) {
            body { padding-top: 64px; }
            .navbar-brand { font-size: 1.1rem; }
            .navbar-nav .nav-link { padding: 0.5rem 0.5rem !important; font-size: 0.85rem; }
            .container { padding: 0 0.75rem; }
            .card { margin-bottom: 1rem; padding: 1rem; }
            .card-header { padding: 0.875rem !important; }
            .row.g-2 > [class*="col-"] { flex: 0 0 100%; max-width: 100%; }
            .form-control, .form-select { padding: 0.55rem 0.875rem; }
            .menu-item-card { padding: 0.5rem; }
            .d-flex.justify-content-between { flex-direction: column; align-items: flex-start; }
            .d-flex.justify-content-between a { margin-top: 0.75rem; }
            .btn-brand { width: 100%; text-align: center; }
        }

        @media (max-width: 768px) {
            .row.g-2 { margin: 0; }
            .row.g-2 > [class*="col-"] { padding-right: 0.5rem; padding-left: 0.5rem; }
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
                    <a class="nav-link" href="profile.php"><i class="bi bi-person me-1"></i> Profile</a>
                    <a class="nav-link" href="../../../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
            <h4 class="fw-bold"><i class="bi bi-credit-card me-2 text-warning"></i> Record Transaction</h4>
            <a href="index.php" class="btn btn-sm btn-brand d-flex align-items-center mt-2 mt-md-0">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" class="card p-4">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="support_toggle" name="support_mode">
                <label class="form-check-label fw-medium" for="support_toggle">
                    Support Mode (Walk-in: transaction under your account)
                </label>
            </div>

            <div id="customerSelect">
                <label class="form-label">Select Customer</label>
                <select name="customer_id" class="form-select mb-3">
                    <option value="">Select Customer...</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['username']) ?> (<?= ucfirst($c['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Select Table</label>
                    <select name="table_id" class="form-select" required>
                        <option value="">Select Table</option>
                        <?php foreach ($tables as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['t_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Mode</label>
                    <select name="payment_mode" class="form-select">
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                    </select>
                </div>
            </div>

            <h5 class="fw-semibold mb-3">Menu Items (for amount calculation)</h5>
            <div class="row g-2">
                <?php foreach ($menu_items as $m): ?>
                    <div class="col-md-4">
                        <div class="menu-item-card">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="ordered_items[]" value="<?= $m['id'] ?>" id="item<?= $m['id'] ?>" onchange="toggleQty(<?= $m['id'] ?>)">
                                <label class="form-check-label" for="item<?= $m['id'] ?>">
                                    <?= htmlspecialchars($m['name']) ?> — ₹<?= number_format($m['price'], 2) ?>
                                    <span class="badge <?= $m['menu_type'] == 'Veg' ? 'bg-success' : 'bg-danger' ?>"><?= $m['menu_type'] ?></span>
                                </label>
                                <input type="number" class="form-control form-control-sm mt-1 d-none" name="quantities[]" id="qty<?= $m['id'] ?>" value="1" min="1" style="width:80px;">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 text-end">
                <button class="btn btn-brand px-4">
                    <i class="bi bi-save me-1"></i> Record Transaction
                </button>
            </div>
        </form>
    </div>

    <!-- Sticky footer for mobile -->
    <div class="sticky-footer d-md-none">
        <div class="d-flex justify-content-around">
            <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2"></i></a>
            <a href="profile.php" class="btn btn-outline-success btn-sm"><i class="bi bi-person"></i></a>
            <a href="../../../logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <script>
        document.getElementById('support_toggle').addEventListener('change', e => {
            document.getElementById('customerSelect').style.display = e.target.checked ? 'none' : 'block';
        });
        function toggleQty(id) {
            document.getElementById('qty' + id).classList.toggle('d-none');
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>