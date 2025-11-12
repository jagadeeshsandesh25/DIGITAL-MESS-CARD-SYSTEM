<?php
// views/dashboard/user/recharge.php - Simple Working Version
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
$card_info = null;
try {
    $query = "SELECT * FROM cards WHERE user_id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $card_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $card_info = null;
}
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    if ($amount <= 0) {
        $message = 'Please enter a valid amount.';
        $message_type = 'danger';
    } else {
        try {
            if ($card_info) {
                // Recharge existing card
                $new_balance = $card_info['balance'] + $amount;
                $update_query = "UPDATE cards SET balance = :balance WHERE id = :card_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':balance', $new_balance);
                $update_stmt->bindParam(':card_id', $card_info['id'], PDO::PARAM_INT);
                if ($update_stmt->execute()) {
                    $message = 'Card recharged successfully!';
                    $message_type = 'success';
                    $card_info['balance'] = $new_balance;
                    // Add transaction record
                    $trans_query = "INSERT INTO transactions (user_id, card_id, amount, transaction_type, status) VALUES (:user_id, :card_id, :amount, 'recharge', 'completed')";
                    $trans_stmt = $db->prepare($trans_query);
                    $trans_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $trans_stmt->bindParam(':card_id', $card_info['id'], PDO::PARAM_INT);
                    $trans_stmt->bindParam(':amount', $amount);
                    $trans_stmt->execute();
                } else {
                    $message = 'Failed to recharge card. Please try again.';
                    $message_type = 'danger';
                }
            } else {
                // Create new card
                $new_card_number = 'CARD' . rand(100000, 999999);
                $insert_query = "INSERT INTO cards (user_id, card_number, balance, status) VALUES (:user_id, :card_number, :balance, 1)";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':card_number', $new_card_number);
                $insert_stmt->bindParam(':balance', $amount);
                if ($insert_stmt->execute()) {
                    $message = 'Card created and recharged successfully!';
                    $message_type = 'success';
                    $card_info = [
                        'id' => $db->lastInsertId(),
                        'card_number' => $new_card_number,
                        'balance' => $amount
                    ];
                    // Add transaction record
                    $trans_query = "INSERT INTO transactions (user_id, card_id, amount, transaction_type, status) VALUES (:user_id, :card_id, :amount, 'recharge', 'completed')";
                    $trans_stmt = $db->prepare($trans_query);
                    $trans_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $trans_stmt->bindParam(':card_id', $db->lastInsertId(), PDO::PARAM_INT);
                    $trans_stmt->bindParam(':amount', $amount);
                    $trans_stmt->execute();
                } else {
                    $message = 'Failed to create card. Please try again.';
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            $message = 'Database error occurred. Please try again.';
            $message_type = 'danger';
            error_log("Recharge error: " . $e->getMessage());
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
                            <li class='breadcrumb-item active'>Recharge</li>
                        </ol>
                    </div>
                    <h4 class='page-title'>Recharge Card</h4>
                </div>
            </div>
        </div>

        <div class='row'>
            <div class='col-lg-8 mx-auto'>
                <div class='card'>
                    <div class='card-header'>
                        <h5 class='card-title mb-0'>Recharge Your Card</h5>
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
                        <div class='mb-4'>
                            <div class='card bg-light text-center'>
                                <div class='card-body'>
                                    <h5>Current Balance</h5>
                                    <h3 class='text-success'>" . ($card_info ? '₹' . number_format($card_info['balance'], 2) : '₹0.00') . "</h3>
                                    <p class='text-muted'>" . ($card_info ? htmlspecialchars($card_info['card_number']) : 'No Card') . "</p>
                                </div>
                            </div>
                        </div>

                        <form method='POST' action=''>
                            <div class='mb-3'>
                                <label for='amount' class='form-label'>Recharge Amount (₹)</label>
                                <input type='number' class='form-control' id='amount' name='amount' min='10' step='10' value='100' required>
                                <div class='form-text'>Minimum ₹10, multiple of ₹10</div>
                            </div>
                            
                            <div class='d-grid'>
                                <button type='submit' class='btn btn-success btn-lg'>
                                    <i class='bi bi-currency-rupee me-1'></i>
                                    Recharge Card
                                </button>
                            </div>
                        </form>

                        <div class='mt-4'>
                            <h6>Quick Recharge Options:</h6>
                            <div class='d-grid gap-2 d-md-flex justify-content-md-start'>
                                <button type='button' class='btn btn-outline-primary' onclick='document.getElementById(\"amount\").value=100'>₹100</button>
                                <button type='button' class='btn btn-outline-primary' onclick='document.getElementById(\"amount\").value=200'>₹200</button>
                                <button type='button' class='btn btn-outline-primary' onclick='document.getElementById(\"amount\").value=500'>₹500</button>
                                <button type='button' class='btn btn-outline-primary' onclick='document.getElementById(\"amount\").value=1000'>₹1000</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
";
include '../../layouts/app.php';
?>