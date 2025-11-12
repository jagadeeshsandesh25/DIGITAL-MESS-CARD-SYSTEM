<?php
// controllers/AdminController.php

require_once '../config/database.php'; // Adjust path
require_once '../models/User.php'; // Adjust path - Needed for user stats
require_once '../models/Card.php'; // Adjust path - Needed for card stats
require_once '../models/Recharge.php'; // Adjust path - Needed for recharge stats
require_once '../models/Table.php'; // Adjust path - Our 'tabels' model
require_once '../models/Menu.php'; // Adjust path - Needed for menu stats
require_once '../models/Transaction.php'; // Adjust path - Needed for transaction stats
require_once '../models/Feedback.php'; // Adjust path - Needed for feedback stats
require_once '../includes/auth.php'; // Adjust path - For permission checks

class AdminController {

    private $database;
    private $db;
    private $userModel;
    private $cardModel;
    private $rechargeModel;
    private $tableModel; // 'tabels'
    private $menuModel;
    private $transactionModel;
    private $feedbackModel;

    public function __construct() {
        $this->database = new Database();
        $this->db = $this->database->getConnection();
        $this->userModel = new User($this->db);
        $this->cardModel = new Card($this->db);
        $this->rechargeModel = new Recharge($this->db);
        $this->tableModel = new Table($this->db); // 'tabels'
        $this->menuModel = new Menu($this->db);
        $this->transactionModel = new Transaction($this->db);
        $this->feedbackModel = new Feedback($this->db);
    }

    public function index() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php"); // Redirect non-admins
            exit;
        }

        $user_name = $_SESSION['username'] ?? 'Admin';

        // Aggregate dashboard data
        $dashboard_data = [
            'total_users' => 0,
            'active_cards' => 0,
            'total_recharges' => 0,
            'todays_recharges' => 0,
            'total_transactions' => 0,
            'todays_transactions' => 0,
            'total_table_orders' => 0,
            'todays_table_orders' => 0,
            'total_menu_items' => 0,
            'total_feedback' => 0,
            'recent_users' => [],
            'recent_cards' => [],
            'recent_recharges' => [],
            'recent_transactions' => [],
            'recent_table_orders' => [],
            'recent_feedback' => []
        ];

        $error_message = '';

        try {
            // --- Fetch Summary Stats ---
            // 1. Total Users
            $stmt = $this->db->query("SELECT COUNT(*) FROM user");
            $dashboard_data['total_users'] = $stmt->fetchColumn();

            // 2. Active Cards
            $stmt = $this->db->query("SELECT COUNT(*) FROM card WHERE c_status = 'Active'");
            $dashboard_data['active_cards'] = $stmt->fetchColumn();

            // 3. Total Recharges
            $stmt = $this->db->query("SELECT COUNT(*) FROM recharge");
            $dashboard_data['total_recharges'] = $stmt->fetchColumn();

            // 4. Today's Recharges
            $stmt = $this->db->query("SELECT COUNT(*) FROM recharge WHERE DATE(r_time) = CURDATE()");
            $dashboard_data['todays_recharges'] = $stmt->fetchColumn();

            // 5. Total Transactions
            $stmt = $this->db->query("SELECT COUNT(*) FROM transactions");
            $dashboard_data['total_transactions'] = $stmt->fetchColumn();

            // 6. Today's Transactions
            $stmt = $this->db->query("SELECT COUNT(*) FROM transactions WHERE DATE(t_time) = CURDATE()");
            $dashboard_data['todays_transactions'] = $stmt->fetchColumn();

            // 7. Total Table Orders (from 'tabels')
            $stmt = $this->db->query("SELECT COUNT(*) FROM tabels");
            $dashboard_data['total_table_orders'] = $stmt->fetchColumn();

            // 8. Today's Table Orders (from 'tabels')
            $stmt = $this->db->query("SELECT COUNT(*) FROM tabels WHERE DATE(time) = CURDATE()");
            $dashboard_data['todays_table_orders'] = $stmt->fetchColumn();

            // 9. Total Menu Items
            $stmt = $this->db->query("SELECT COUNT(*) FROM menu");
            $dashboard_data['total_menu_items'] = $stmt->fetchColumn();

            // 10. Total Feedback
            $stmt = $this->db->query("SELECT COUNT(*) FROM feedback");
            $dashboard_data['total_feedback'] = $stmt->fetchColumn();


            // --- Fetch Recent Records ---
            // 1. Recent Users (last 5)
            $stmt = $this->db->query("SELECT id, first_name, last_name, username, email, ph_no, role, status, created_at FROM user ORDER BY created_at DESC LIMIT 5");
            $dashboard_data['recent_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Recent Cards (last 5)
            $stmt = $this->db->query("SELECT id, user_id, c_status, balance_credits, total_credits, created_at, expired_at FROM card ORDER BY created_at DESC LIMIT 5");
            $dashboard_data['recent_cards'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Recent Recharges (last 5)
            $stmt = $this->db->query("SELECT id, r_type, user_id, card_id, r_time FROM recharge ORDER BY r_time DESC LIMIT 5");
            $dashboard_data['recent_recharges'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Recent Transactions (last 5)
            $stmt = $this->db->query("SELECT id, t_type, user_id, card_id, t_time FROM transactions ORDER BY t_time DESC LIMIT 5");
            $dashboard_data['recent_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 5. Recent Table Orders (last 5 from 'tabels')
            $stmt = $this->db->query("SELECT id, t_name, t_qr, user_id, card_id, time, transaction_mode FROM tabels ORDER BY time DESC LIMIT 5");
            $dashboard_data['recent_table_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 6. Recent Feedback (last 5)
            $stmt = $this->db->query("SELECT id, user_id, message, rating, created_at FROM feedback ORDER BY created_at DESC LIMIT 5");
            $dashboard_data['recent_feedback'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $error_message = "Could not load dashboard data. Please try again later.";
            error_log("AdminController index query error: " . $e->getMessage());
        }

        // Prepare the content for the index view using a helper
        $content = $this->getIndexContent($dashboard_data, $error_message, $user_name);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    // --- Helper Method to Generate Dashboard Content ---

    private function getIndexContent($dashboard_data, $error_message = '', $user_name) {
        $html = "<h2>Admin Dashboard</h2><p>Welcome back, <strong>" . htmlspecialchars($user_name) . "</strong>. Here's an overview of your system.</p>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
        } else {
            // --- Summary Stats Cards ---
            $html .= "
            <div class='row'>
                <div class='col-md-3 mb-3'>
                    <div class='card text-white bg-primary'>
                        <div class='card-body'>
                            <h5 class='card-title'>Total Users</h5>
                            <p class='card-text display-5'>" . $dashboard_data['total_users'] . "</p>
                        </div>
                    </div>
                </div>
                <div class='col-md-3 mb-3'>
                    <div class='card text-white bg-success'>
                        <div class='card-body'>
                            <h5 class='card-title'>Active Cards</h5>
                            <p class='card-text display-5'>" . $dashboard_data['active_cards'] . "</p>
                        </div>
                    </div>
                </div>
                <div class='col-md-3 mb-3'>
                    <div class='card text-white bg-info'>
                        <div class='card-body'>
                            <h5 class='card-title'>Total Recharges</h5>
                            <p class='card-text display-5'>" . $dashboard_data['total_recharges'] . "</p>
                        </div>
                    </div>
                </div>
                <div class='col-md-3 mb-3'>
                    <div class='card text-white bg-warning'>
                        <div class='card-body'>
                            <h5 class='card-title'>Today's Recharges</h5>
                            <p class='card-text display-5'>" . $dashboard_data['todays_recharges'] . "</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class='row'>
                <div class='col-md-3 mb-3'>
                    <div class='card text-white bg-secondary'>
                        <div class='card-body'>
                            <h5 class='card-title'>Total Transactions</h5>
                            <p class='card-text display-5'>" . $dashboard_data['total_transactions'] . "</p>
                        </div>
                    </div>
                </div>
                <div class='col-md-3 mb-3'>
                    <div class='card text-white bg-dark'>
                        <div class='card-body'>
                            <h5 class='card-title'>Today's Transactions</h5>
                            <p class='card-text display-5'>" . $dashboard_data['todays_transactions'] . "</p>
                        </div>
                    </div>
                </div>
                <div class='col-md-3 mb-3'>
                    <div class='card text-white bg-danger'>
                        <div class='card-body'>
                            <h5 class='card-title'>Total Table Orders</h5>
                            <p class='card-text display-5'>" . $dashboard_data['total_table_orders'] . "</p>
                        </div>
                    </div>
                </div>
                <div class='col-md-3 mb-3'>
                    <div class='card text-white bg-primary'>
                        <div class='card-body'>
                            <h5 class='card-title'>Today's Table Orders</h5>
                            <p class='card-text display-5'>" . $dashboard_data['todays_table_orders'] . "</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class='row'>
                <div class='col-md-3 mb-3'>
                    <div class='card text-white bg-success'>
                        <div class='card-body'>
                            <h5 class='card-title'>Total Menu Items</h5>
                            <p class='card-text display-5'>" . $dashboard_data['total_menu_items'] . "</p>
                        </div>
                    </div>
                </div>
                <div class='col-md-3 mb-3'>
                    <div class='card text-white bg-info'>
                        <div class='card-body'>
                            <h5 class='card-title'>Total Feedback</h5>
                            <p class='card-text display-5'>" . $dashboard_data['total_feedback'] . "</p>
                        </div>
                    </div>
                </div>
                 <div class='col-md-6 mb-3'>
                    <div class='card'>
                        <div class='card-header'>
                            <h5>Quick Links</h5>
                        </div>
                        <div class='card-body'>
                            <a href='../views/users/index.php' class='btn btn-outline-primary me-2 mb-2'>Manage Users</a>
                            <a href='../views/cards/index.php' class='btn btn-outline-success me-2 mb-2'>Manage Cards</a>
                            <a href='../views/recharge/index.php' class='btn btn-outline-info me-2 mb-2'>Manage Recharges</a>
                            <a href='../views/tables/index.php' class='btn btn-outline-warning me-2 mb-2'>Manage Tables</a>
                            <a href='../views/menu/index.php' class='btn btn-outline-danger me-2 mb-2'>Manage Menu</a>
                            <a href='../views/transactions/index.php' class='btn btn-outline-secondary me-2 mb-2'>Manage Transactions</a>
                            <a href='../views/feedback/index.php' class='btn btn-outline-dark me-2 mb-2'>Manage Feedback</a>
                        </div>
                    </div>
                </div>
            </div>
            ";

            // --- Recent Activity Sections ---
            $html .= "<hr class='my-4'>"; // Divider

            // 1. Recent Users
            $html .= "<h4>Recent User Registrations</h4>";
            if (!empty($dashboard_data['recent_users'])) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";
                foreach ($dashboard_data['recent_users'] as $user) {
                    $status_badge = $user['status'] == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($user['id']) . "</td>
                                <td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>
                                <td>" . htmlspecialchars($user['username']) . "</td>
                                <td>" . htmlspecialchars($user['email']) . "</td>
                                <td>" . htmlspecialchars($user['ph_no']) . "</td>
                                <td>" . htmlspecialchars($user['role']) . "</td>
                                <td>" . $status_badge . "</td>
                                <td>" . htmlspecialchars($user['created_at']) . "</td>
                                <td>
                                    <a href='../views/users/view.php?id=" . $user['id'] . "' class='btn btn-sm btn-info'>View</a>
                                    <a href='../views/users/edit.php?id=" . $user['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                </td>
                            </tr>
                    ";
                }
                $html .= "
                        </tbody>
                    </table>
                </div>
                ";
            } else {
                $html .= "<p>No recent user registrations.</p>";
            }

            // 2. Recent Cards
            $html .= "<h4>Recently Issued Cards</h4>";
            if (!empty($dashboard_data['recent_cards'])) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>User ID</th>
                                <th>Status</th>
                                <th>Balance Credits</th>
                                <th>Total Credits</th>
                                <th>Created At</th>
                                <th>Expired At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";
                foreach ($dashboard_data['recent_cards'] as $card) {
                    $status_badge = $card['c_status'] === 'Active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($card['id']) . "</td>
                                <td>" . htmlspecialchars($card['user_id']) . "</td>
                                <td>" . $status_badge . "</td>
                                <td>₹" . number_format($card['balance_credits'], 2) . "</td>
                                <td>₹" . number_format($card['total_credits'], 2) . "</td>
                                <td>" . htmlspecialchars($card['created_at']) . "</td>
                                <td>" . ($card['expired_at'] ? htmlspecialchars($card['expired_at']) : 'N/A') . "</td>
                                <td>
                                    <a href='../views/cards/view.php?id=" . $card['id'] . "' class='btn btn-sm btn-info'>View</a>
                                    <a href='../views/cards/edit.php?id=" . $card['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                </td>
                            </tr>
                    ";
                }
                $html .= "
                        </tbody>
                    </table>
                </div>
                ";
            } else {
                $html .= "<p>No recently issued cards.</p>";
            }

            // 3. Recent Recharges
            $html .= "<h4>Recent Recharges</h4>";
            if (!empty($dashboard_data['recent_recharges'])) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>User ID</th>
                                <th>Card ID</th>
                                <th>Recharge Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";
                foreach ($dashboard_data['recent_recharges'] as $recharge) {
                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($recharge['id']) . "</td>
                                <td>" . htmlspecialchars($recharge['r_type']) . "</td>
                                <td>" . htmlspecialchars($recharge['user_id']) . "</td>
                                <td>" . htmlspecialchars($recharge['card_id']) . "</td>
                                <td>" . htmlspecialchars($recharge['r_time']) . "</td>
                                <td>
                                    <a href='../views/recharge/view.php?id=" . $recharge['id'] . "' class='btn btn-sm btn-info'>View</a>
                                    <a href='../views/recharge/edit.php?id=" . $recharge['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                </td>
                            </tr>
                    ";
                }
                $html .= "
                        </tbody>
                    </table>
                </div>
                ";
            } else {
                $html .= "<p>No recent recharges.</p>";
            }

            // 4. Recent Transactions
            $html .= "<h4>Recent Transactions</h4>";
            if (!empty($dashboard_data['recent_transactions'])) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>User ID</th>
                                <th>Card ID</th>
                                <th>Transaction Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";
                foreach ($dashboard_data['recent_transactions'] as $transaction) {
                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($transaction['id']) . "</td>
                                <td>" . htmlspecialchars($transaction['t_type']) . "</td>
                                <td>" . htmlspecialchars($transaction['user_id']) . "</td>
                                <td>" . htmlspecialchars($transaction['card_id']) . "</td>
                                <td>" . htmlspecialchars($transaction['t_time']) . "</td>
                                <td>
                                    <a href='../views/transactions/view.php?id=" . $transaction['id'] . "' class='btn btn-sm btn-info'>View</a>
                                    <a href='../views/transactions/edit.php?id=" . $transaction['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                </td>
                            </tr>
                    ";
                }
                $html .= "
                        </tbody>
                    </table>
                </div>
                ";
            } else {
                $html .= "<p>No recent transactions.</p>";
            }

             // 5. Recent Table Orders (from 'tabels')
            $html .= "<h4>Recent Table Orders</h4>";
            if (!empty($dashboard_data['recent_table_orders'])) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Table Name</th>
                                <th>QR Code ID</th>
                                <th>User ID (Waiter/Customer)</th>
                                <th>Card ID</th>
                                <th>Order Time</th>
                                <th>Transaction Mode</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";
                foreach ($dashboard_data['recent_table_orders'] as $order) {
                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($order['id']) . "</td>
                                <td>" . htmlspecialchars($order['t_name']) . "</td>
                                <td>" . htmlspecialchars($order['t_qr']) . "</td>
                                <td>" . htmlspecialchars($order['user_id']) . "</td>
                                <td>" . htmlspecialchars($order['card_id']) . "</td>
                                <td>" . htmlspecialchars($order['time']) . "</td>
                                <td>" . htmlspecialchars($order['transaction_mode']) . "</td>
                                <td>
                                    <a href='../views/tables/view.php?id=" . $order['id'] . "' class='btn btn-sm btn-info'>View</a>
                                    <a href='../views/tables/edit.php?id=" . $order['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                </td>
                            </tr>
                    ";
                }
                $html .= "
                        </tbody>
                    </table>
                </div>
                ";
            } else {
                $html .= "<p>No recent table orders.</p>";
            }

            // 6. Recent Feedback
            $html .= "<h4>Recent Feedback</h4>";
            if (!empty($dashboard_data['recent_feedback'])) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>User ID</th>
                                <th>Message</th>
                                <th>Rating</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";
                foreach ($dashboard_data['recent_feedback'] as $feedback) {
                    $rating_display = $feedback['rating'] !== null ? str_repeat('<span class="text-warning">&#9733;</span>', $feedback['rating']) . str_repeat('<span class="text-muted">&#9734;</span>', 5 - $feedback['rating']) : '<span class="text-muted">No Rating</span>';
                    // Truncate long messages for display in the table
                    $message_display = strlen($feedback['message']) > 100 ? substr(htmlspecialchars($feedback['message']), 0, 97) . '...' : htmlspecialchars($feedback['message']);
                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($feedback['id']) . "</td>
                                <td>" . htmlspecialchars($feedback['user_id']) . "</td>
                                <td>" . $message_display . "</td>
                                <td>" . $rating_display . "</td>
                                <td>" . htmlspecialchars($feedback['created_at']) . "</td>
                                <td>
                                    <a href='../views/feedback/view.php?id=" . $feedback['id'] . "' class='btn btn-sm btn-info'>View</a>
                                    <a href='../views/feedback/edit.php?id=" . $feedback['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                </td>
                            </tr>
                    ";
                }
                $html .= "
                        </tbody>
                    </table>
                </div>
                ";
            } else {
                $html .= "<p>No recent feedback.</p>";
            }

        }

        return $html;
    }

}

// --- Routing ---
$action = $_GET['action'] ?? '';

$adminController = new AdminController();

// Ensure only admin can access the admin dashboard
requireLogin();
if (!isAdmin()) {
    header("Location: ../views/dashboard/index.php"); // Redirect non-admins
    exit;
}

// Call the appropriate method based on the action
switch ($action) {
    case 'index':
        $adminController->index();
        break;
    default:
        // Default to index if action is invalid or not provided
        $adminController->index();
        break;
}

?>