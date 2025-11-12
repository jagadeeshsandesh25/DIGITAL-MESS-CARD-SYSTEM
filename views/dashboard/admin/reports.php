<?php
// views/dashboard/admin/reports.php

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Helper: Get date range from URL or default to today
function getDateRangeFromRequest($defaultPeriod = 'today') {
    $period = $_GET['period'] ?? $defaultPeriod;
    $custom_start = $_GET['start'] ?? '';
    $custom_end = $_GET['end'] ?? '';

    $today = date('Y-m-d');
    $firstDayOfMonth = date('Y-m-01');
    $lastDayOfMonth = date('Y-m-t');
    $firstDayOfYear = date('Y-01-01');
    $lastDayOfYear = date('Y-12-31');

    if ($period === 'custom' && $custom_start && $custom_end) {
        $start = date('Y-m-d', strtotime($custom_start));
        $end = date('Y-m-d', strtotime($custom_end));
        if ($start && $end && $start <= $end) {
            return [
                'start' => $start,
                'end' => $end,
                'label' => "Custom: " . date('d M Y', strtotime($start)) . " – " . date('d M Y', strtotime($end)),
                'period' => 'custom'
            ];
        }
    }

    switch ($period) {
        case 'year':
            return [
                'start' => $firstDayOfYear,
                'end' => $lastDayOfYear,
                'label' => "Year to Date (" . date('Y') . ")",
                'period' => 'year'
            ];
        case 'month':
            return [
                'start' => $firstDayOfMonth,
                'end' => $lastDayOfMonth,
                'label' => "This Month (" . date('F Y') . ")",
                'period' => 'month'
            ];
        case 'today':
        default:
            return [
                'start' => $today,
                'end' => $today,
                'label' => "Today (" . date('d M Y') . ")",
                'period' => 'today'
            ];
    }
}

// Generate reusable date filter UI
function renderDateFilterUI($reportType, $period, $start, $end) {
    echo "<div class='mb-4'>";
    echo "<div class='btn-group mb-2' role='group'>";
    echo "<a href='?report={$reportType}&period=today' class='btn btn-sm " . ($period === 'today' ? 'btn-primary' : 'btn-outline-primary') . "'>Today</a>";
    echo "<a href='?report={$reportType}&period=month' class='btn btn-sm " . ($period === 'month' ? 'btn-success' : 'btn-outline-success') . "'>Month</a>";
    echo "<a href='?report={$reportType}&period=year' class='btn btn-sm " . ($period === 'year' ? 'btn-info' : 'btn-outline-info') . "'>Year</a>";
    echo "</div>";

    echo "<form method='GET' class='row g-2 align-items-end mt-2'>
            <input type='hidden' name='report' value='{$reportType}'>
            <div class='col-auto'>
                <label class='form-label small'>From</label>
                <input type='date' name='start' class='form-control form-control-sm' value='" . htmlspecialchars($start) . "' required>
            </div>
            <div class='col-auto'>
                <label class='form-label small'>To</label>
                <input type='date' name='end' class='form-control form-control-sm' value='" . htmlspecialchars($end) . "' required>
            </div>
            <div class='col-auto'>
                <button type='submit' class='btn btn-sm btn-outline-dark'>Apply</button>
            </div>
          </form>";
    echo "</div>";
}

$user_name = $_SESSION['username'] ?? 'Admin';
$reportType = $_GET['report'] ?? null;

ob_start();
?>

<!-- Print-friendly styling -->
<style>
@media print {
    body * { visibility: hidden; }
    .printable-section,
    .printable-section * { visibility: visible; }
    .printable-section {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
        box-sizing: border-box;
        font-size: 12px;
    }
    .no-print { display: none !important; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #000; padding: 6px; }
    h2, h4 { color: #000 !important; }
}
</style>

<h2 class="mb-3">Reports</h2>
<p class="text-muted">Generate and view time-filtered reports for transactions, orders, and system performance.</p>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <h5 class="card-title">User Reports</h5>
                <p>View all registered users (no time filter).</p>
                <a href="?report=users" class="btn btn-light">View Users</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Transaction Reports</h5>
                <p>View payments with date filters.</p>
                <a href="?report=transactions" class="btn btn-light">View Transactions</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body">
                <h5 class="card-title">Menu Reports</h5>
                <p>View all menu items (no time filter).</p>
                <a href="?report=menu" class="btn btn-dark">View Menu</a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card bg-dark text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Overall Summary</h5>
                <p>Time-filtered system metrics.</p>
                <a href="?report=summary" class="btn btn-light">View Summary</a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Transaction Overview</h5>
                <p>Order-level insights with date filters.</p>
                <a href="?report=txn-overview" class="btn btn-light">View Orders</a>
            </div>
        </div>
    </div>
</div>

<?php
if ($reportType) {
    echo "<div class='card shadow-sm mt-4 printable-section'><div class='card-body'>";

    // Add print/PDF button
    echo "<div class='mb-3 no-print'>
            <button onclick='window.print()' class='btn btn-outline-secondary btn-sm'>
                <i class='bi bi-file-earmark-pdf'></i> Save as PDF
            </button>
          </div>";

    switch ($reportType) {
        case 'users':
            echo "<h4>User Report</h4>";
            $stmt = $db->prepare("SELECT id, first_name, last_name, email, role, status, created_at FROM user ORDER BY created_at DESC");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($users) {
                echo "<div class='table-responsive'><table class='table table-bordered table-striped'>
                        <thead class='table-light'><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created At</th></tr></thead><tbody>";
                foreach ($users as $u) {
                    $status = $u['status'] ? "<span class='badge bg-success'>Active</span>" : "<span class='badge bg-warning'>Inactive</span>";
                    echo "<tr><td>{$u['id']}</td><td>{$u['first_name']} {$u['last_name']}</td><td>{$u['email']}</td><td>{$u['role']}</td><td>$status</td><td>{$u['created_at']}</td></tr>";
                }
                echo "</tbody></table></div>";
            } else echo "<p>No user data available.</p>";
            break;

        case 'menu':
            echo "<h4>Menu Report</h4>";
            $stmt = $db->prepare("SELECT name, price, menu_type, category, created_at FROM menu ORDER BY created_at DESC");
            $stmt->execute();
            $menu = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($menu) {
                echo "<div class='table-responsive'><table class='table table-bordered table-striped'>
                        <thead class='table-light'><tr><th>Name</th><th>Type</th><th>Category</th><th>Price</th><th>Created</th></tr></thead><tbody>";
                foreach ($menu as $m) {
                    echo "<tr><td>{$m['name']}</td><td>{$m['menu_type']}</td><td>{$m['category']}</td><td>₹{$m['price']}</td><td>{$m['created_at']}</td></tr>";
                }
                echo "</tbody></table></div>";
            } else echo "<p>No menu data found.</p>";
            break;

        case 'cards':
            echo "<h4>Card Report</h4>";
            $stmt = $db->prepare("SELECT c.id, u.username, c.c_status, c.balance_credits, c.total_credits, c.created_at 
                                  FROM card c 
                                  JOIN user u ON c.user_id = u.id 
                                  ORDER BY c.created_at DESC");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                echo "<div class='table-responsive'><table class='table table-bordered table-striped'>
                        <thead class='table-light'><tr><th>Card ID</th><th>User</th><th>Status</th><th>Balance</th><th>Total Credits</th><th>Created</th></tr></thead><tbody>";
                foreach ($rows as $r) {
                    echo "<tr><td>{$r['id']}</td><td>{$r['username']}</td><td>{$r['c_status']}</td><td>{$r['balance_credits']}</td><td>{$r['total_credits']}</td><td>{$r['created_at']}</td></tr>";
                }
                echo "</tbody></table></div>";
            } else echo "<p>No card data found.</p>";
            break;

        case 'recharges':
            echo "<h4>Recharge Report</h4>";
            $stmt = $db->prepare("SELECT r.id, u.username, r.amount, r.credits_added, r.r_type, r.r_time 
                                  FROM recharge r 
                                  JOIN user u ON r.user_id = u.id 
                                  ORDER BY r.r_time DESC");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                echo "<div class='table-responsive'><table class='table table-bordered table-striped'>
                        <thead class='table-light'><tr><th>ID</th><th>User</th><th>Amount</th><th>Credits</th><th>Type</th><th>Time</th></tr></thead><tbody>";
                foreach ($rows as $r) {
                    echo "<tr><td>{$r['id']}</td><td>{$r['username']}</td><td>₹{$r['amount']}</td><td>{$r['credits_added']}</td><td>{$r['r_type']}</td><td>{$r['r_time']}</td></tr>";
                }
                echo "</tbody></table></div>";
            } else echo "<p>No recharge data found.</p>";
            break;

        case 'transactions':
            $range = getDateRangeFromRequest('today');
            extract($range);
            echo "<h4>Transaction Report — $label</h4>";
            renderDateFilterUI('transactions', $period, $start, $end);
            $stmt = $db->prepare("SELECT t.id, u.username, t.amount, t.t_type, t.status, t.t_time 
                                  FROM transactions t 
                                  JOIN user u ON t.user_id = u.id 
                                  WHERE DATE(t.t_time) BETWEEN ? AND ?
                                  ORDER BY t.t_time DESC");
            $stmt->execute([$start, $end]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                echo "<div class='table-responsive'><table class='table table-bordered table-striped'>
                        <thead class='table-light'><tr><th>ID</th><th>User</th><th>Amount</th><th>Type</th><th>Status</th><th>Date</th></tr></thead><tbody>";
                foreach ($rows as $r) {
                    echo "<tr><td>{$r['id']}</td><td>{$r['username']}</td><td>₹{$r['amount']}</td><td>{$r['t_type']}</td><td>{$r['status']}</td><td>{$r['t_time']}</td></tr>";
                }
                echo "</tbody></table></div>";
            } else {
                echo "<p class='text-muted'>No transactions found for this period.</p>";
            }
            break;

        case 'txn-overview':
            $range = getDateRangeFromRequest('today');
            extract($range);
            echo "<h4>Transaction Overview — $label</h4>";
            renderDateFilterUI('txn-overview', $period, $start, $end);

            $sql = "SELECT o.*, u.username AS user_name 
                    FROM orders o
                    JOIN user u ON o.user_id = u.id
                    WHERE DATE(o.order_time) BETWEEN ? AND ?
                    ORDER BY o.order_time DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            $filtered_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_orders = count($filtered_orders);
            $total_credits_used = 0;
            $pending = $completed = $cancelled = 0;

            foreach ($filtered_orders as $order) {
                $total_credits_used += (int)($order['credits_used'] ?? 0);
                $status = strtolower($order['status'] ?? '');
                if (in_array($status, ['pending', 'preparing'])) {
                    $pending++;
                } elseif (in_array($status, ['ready', 'served', 'paid'])) {
                    $completed++;
                } elseif ($status === 'cancelled') {
                    $cancelled++;
                }
            }

            echo "<div class='row g-3 mb-4'>";
            echo "<div class='col-md-3'><div class='card text-white bg-primary'><div class='card-body text-center'><h6 class='mb-0'>Total Orders</h6><h4 class='mt-1 mb-0'>$total_orders</h4></div></div></div>";
            echo "<div class='col-md-3'><div class='card text-white bg-success'><div class='card-body text-center'><h6 class='mb-0'>Credits Used</h6><h4 class='mt-1 mb-0'>" . number_format($total_credits_used, 0) . "</h4></div></div></div>";
            echo "<div class='col-md-3'><div class='card text-white bg-warning'><div class='card-body text-center'><h6 class='mb-0'>Pending</h6><h4 class='mt-1 mb-0'>$pending</h4></div></div></div>";
            echo "<div class='col-md-3'><div class='card text-white bg-danger'><div class='card-body text-center'><h6 class='mb-0'>Cancelled</h6><h4 class='mt-1 mb-0'>$cancelled</h4></div></div></div>";
            echo "</div>";

            if (!empty($filtered_orders)) {
                echo "<div class='table-responsive'>
                        <table class='table table-bordered table-striped'>
                            <thead class='table-light'>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Table</th>
                                    <th>Meal</th>
                                    <th>Plan</th>
                                    <th>Credits</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>";
                foreach ($filtered_orders as $order) {
                    $status = strtolower($order['status'] ?? 'unknown');
                    $badge_class = match ($status) {
                        'pending' => 'bg-warning',
                        'preparing' => 'bg-info',
                        'ready' => 'bg-primary',
                        'served', 'paid' => 'bg-success',
                        'cancelled' => 'bg-danger',
                        default => 'bg-secondary'
                    };
                    $orderTime = !empty($order['order_time']) ? date('d M, h:i A', strtotime($order['order_time'])) : 'N/A';
                    echo "<tr>
                            <td>#{$order['id']}</td>
                            <td>" . htmlspecialchars($order['user_name']) . "</td>
                            <td>" . htmlspecialchars($order['table_qr'] ?? 'N/A') . "</td>
                            <td>" . htmlspecialchars($order['meal_type'] ?? 'N/A') . "</td>
                            <td>" . htmlspecialchars($order['plan_type'] ?? 'N/A') . "</td>
                            <td>{$order['credits_used']}</td>
                            <td><span class='badge $badge_class'>" . ucfirst($status) . "</span></td>
                            <td>$orderTime</td>
                          </tr>";
                }
                echo "</tbody></table></div>";
            } else {
                echo "<div class='text-center py-3 text-muted'>No orders found for this period.</div>";
            }
            break;

        case 'summary':
            $range = getDateRangeFromRequest('today');
            extract($range);
            echo "<h4>Overall Summary — $label</h4>";
            renderDateFilterUI('summary', $period, $start, $end);

            $total_users = $db->query("SELECT COUNT(*) FROM user WHERE DATE(created_at) BETWEEN '$start' AND '$end'")->fetchColumn();
            $total_txns = $db->query("SELECT COUNT(*) FROM transactions WHERE DATE(t_time) BETWEEN '$start' AND '$end'")->fetchColumn();
            $total_orders = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(order_time) BETWEEN '$start' AND '$end'")->fetchColumn();
            $total_revenue = $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed' AND DATE(t_time) BETWEEN '$start' AND '$end'")->fetchColumn();

            echo "<ul class='list-group'>
                    <li class='list-group-item'><strong>New Users:</strong> $total_users</li>
                    <li class='list-group-item'><strong>Transactions:</strong> $total_txns</li>
                    <li class='list-group-item'><strong>Orders:</strong> $total_orders</li>
                    <li class='list-group-item'><strong>Revenue:</strong> ₹" . number_format($total_revenue, 2) . "</li>
                  </ul>";
            break;

        default:
            echo "<p class='text-muted'>Select a report type above.</p>";
            break;
    }
    echo "</div></div>";
}
?>

<?php
$content = ob_get_clean();
include '../../layouts/app.php';
?>