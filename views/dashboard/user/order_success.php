<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}

// Get data from menuu.php form
$table_qr = $_POST['table_qr'] ?? '';
$card_owner_id = (int)($_POST['card_owner_id'] ?? $_SESSION['user_id']); // Ensure it's an integer
$credits = (int)($_POST['credits'] ?? 0); // Sanitize as integer
$seating = $_POST['seating'] ?? '';
$meal_type = strtolower($_POST['meal_type'] ?? ''); // Convert to lowercase for consistency
$plan_type = $_POST['plan_type'] ?? '';
$menu_items = $_POST['menu_items'] ?? [];

// Validate required inputs
if (!$table_qr || $credits < 1 || $credits > 4 || !in_array($meal_type, ['breakfast', 'lunch', 'dinner'])) {
    die("Invalid input received for order processing.");
}

// Load Database class
require_once '../../../config/database.php'; // Adjust path if necessary

// Create DB connection using your Database class
$database = new Database();
$pdo = $database->getConnection();

if ($pdo === null) {
    die("Database connection failed.");
}

try {
    // Start a transaction for data integrity (order and credit update together)
    $pdo->beginTransaction();

    // Get current user ID from session (the one placing the order)
    $user_id = $_SESSION['user_id'];

    // Determine the column to update based on meal_type
    $column_map = [
        'breakfast' => 'breakfast_remaining',
        'lunch' => 'lunch_remaining',
        'dinner' => 'dinner_remaining'
    ];
    $column_to_update = $column_map[$meal_type] ?? null;

    if (!$column_to_update) {
         throw new Exception("Invalid meal type: $meal_type");
    }

    // Fetch the current remaining credits for the specific meal type
    // Only for active plans
    $stmt = $pdo->prepare("
        SELECT id, `$column_to_update` as remaining_credits
        FROM student_plans
        WHERE user_id = :user_id AND status = 'active'
        ORDER BY end_date DESC LIMIT 1
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new Exception("No active plan found for user $user_id.");
    }

    $current_remaining = (int)$plan['remaining_credits'];
    $plan_id = (int)$plan['id'];

    // Check if sufficient credits are available
    if ($current_remaining < $credits) {
        throw new Exception("Insufficient $meal_type credits. Available: $current_remaining, Requested: $credits.");
    }

    // Calculate new remaining credits
    $new_remaining = $current_remaining - $credits;

    // Update the student_plans table with the new remaining credits
    $update_stmt = $pdo->prepare("
        UPDATE student_plans
        SET `$column_to_update` = :new_remaining
        WHERE id = :plan_id
    ");
    $update_stmt->bindParam(':new_remaining', $new_remaining, PDO::PARAM_INT);
    $update_stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);

    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update credits in database.");
    }

    // --- NEW: Get table_id from table_qr ---
    $get_table_id_stmt = $pdo->prepare("SELECT id FROM tables WHERE t_qr = :table_qr LIMIT 1");
    $get_table_id_stmt->bindParam(':table_qr', $table_qr, PDO::PARAM_STR);
    $get_table_id_stmt->execute();
    $table = $get_table_id_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$table) {
        throw new Exception("Table QR code not found: $table_qr");
    }
    $table_id = (int)$table['id'];
    // --- End of table_id fetch ---

    // --- NEW: Save the order details to the `orders` table (with modified structure) ---
    $order_items_str = implode(', ', $menu_items); // Convert array to comma-separated string
    $status = 'Pending'; // Default status
    $transaction_mode = 'Credits'; // Or any default value for your system
    $total_amount = 0.00; // Assuming amount is based on credits, set to 0 or calculate if needed

    $insert_order_stmt = $pdo->prepare("
        INSERT INTO orders (
            table_id, table_qr, user_id, card_id, card_owner_id,
            transaction_mode, total_amount, meal_type, plan_type, credits_used, seating, items, status
        )
        VALUES (
            :table_id, :table_qr, :user_id, NULL, :card_owner_id, -- Assuming card_id might not be used directly here
            :transaction_mode, :total_amount, :meal_type, :plan_type, :credits_used, :seating, :items, :status
        )
    ");
    $insert_order_stmt->bindParam(':table_id', $table_id, PDO::PARAM_INT);
    $insert_order_stmt->bindParam(':table_qr', $table_qr, PDO::PARAM_STR);
    $insert_order_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    // $insert_order_stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT); // If you pass card_id from menuu.php, use it here
    $insert_order_stmt->bindParam(':card_owner_id', $card_owner_id, PDO::PARAM_INT);
    $insert_order_stmt->bindParam(':transaction_mode', $transaction_mode, PDO::PARAM_STR);
    $insert_order_stmt->bindParam(':total_amount', $total_amount, PDO::PARAM_STR); // Use PARAM_STR for decimal if needed, else PARAM_INT/STR for 0.00
    $insert_order_stmt->bindParam(':meal_type', $meal_type, PDO::PARAM_STR);
    $insert_order_stmt->bindParam(':plan_type', $plan_type, PDO::PARAM_STR);
    $insert_order_stmt->bindParam(':credits_used', $credits, PDO::PARAM_INT);
    $insert_order_stmt->bindParam(':seating', $seating, PDO::PARAM_STR);
    $insert_order_stmt->bindParam(':items', $order_items_str, PDO::PARAM_STR); // Can be empty string if no items
    $insert_order_stmt->bindParam(':status', $status, PDO::PARAM_STR);

    if (!$insert_order_stmt->execute()) {
        throw new Exception("Failed to save order details to database.");
    }
    // --- End of order saving ---

    // Commit the transaction
    $pdo->commit();

    // --- Success Message with your UI/UX ---
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Success - Mess Management</title>
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
                background: linear-gradient(135deg, var(--light-bg), #c3cfe2);
                font-family: 'Poppins', sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                margin: 0;
            }

            .success-card {
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                padding: 2.5rem;
                width: 100%;
                max-width: 500px;
                text-align: center;
                transition: transform 0.3s ease;
            }

            .success-card:hover {
                transform: translateY(-4px);
            }

            .success-icon {
                font-size: 4rem;
                color: var(--success-color);
                margin-bottom: 1.5rem;
            }

            .success-title {
                color: var(--success-color);
                font-weight: 600;
                margin-bottom: 1rem;
            }

            .btn-primary {
                background: linear-gradient(to right, var(--primary-color), #1a2f6d);
                border: none;
                padding: 0.75rem 1.5rem;
                font-weight: 600;
                border-radius: 10px;
                transition: opacity 0.3s;
                font-size: 1rem;
            }

            .btn-primary:hover {
                opacity: 0.92;
            }

            /* Mobile-specific styles */
            @media (max-width: 576px) {
                body {
                    padding: 0.5rem;
                }

                .success-card {
                    padding: 1.8rem;
                }

                .success-icon {
                    font-size: 3.5rem;
                }

                .success-title {
                    font-size: 1.5rem;
                }

                .btn-primary {
                    padding: 0.65rem 1rem;
                    font-size: 1rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="success-card">
            <div class="text-center">
                <i class="bi bi-check-circle-fill success-icon"></i>
                <h1 class="success-title">Order Successful!</h1>
                <p class="lead mb-3">
                    Successfully used <strong><?php echo $credits; ?> <?php echo htmlspecialchars($meal_type); ?></strong> credits for table <strong><?php echo htmlspecialchars($table_qr); ?></strong>.
                </p>
                <p class="mb-4">
                    New <strong><?php echo htmlspecialchars($meal_type); ?></strong> credits remaining: <strong><?php echo $new_remaining; ?></strong>
                </p>

                <?php if (!empty($menu_items)): ?>
                    <p class="mb-4">
                        <strong>Ordered Items:</strong><br>
                        <?php echo htmlspecialchars($order_items_str); ?>
                    </p>
                <?php endif; ?>

                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-speedometer2 me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    // Rollback the transaction on error
    $pdo->rollback();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Error - Mess Management</title>
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
                --error-color: #e53e3e;
            }

            body {
                background: linear-gradient(135deg, var(--light-bg), #c3cfe2);
                font-family: 'Poppins', sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                margin: 0;
            }

            .error-card {
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                padding: 2.5rem;
                width: 100%;
                max-width: 500px;
                text-align: center;
                transition: transform 0.3s ease;
            }

            .error-card:hover {
                transform: translateY(-4px);
            }

            .error-icon {
                font-size: 4rem;
                color: var(--error-color);
                margin-bottom: 1.5rem;
            }

            .error-title {
                color: var(--error-color);
                font-weight: 600;
                margin-bottom: 1rem;
            }

            .btn-secondary {
                background: var(--primary-color);
                border: none;
                padding: 0.75rem 1.5rem;
                font-weight: 600;
                border-radius: 10px;
                transition: opacity 0.3s;
                font-size: 1rem;
            }

            .btn-secondary:hover {
                opacity: 0.92;
            }

            /* Mobile-specific styles */
            @media (max-width: 576px) {
                body {
                    padding: 0.5rem;
                }

                .error-card {
                    padding: 1.8rem;
                }

                .error-icon {
                    font-size: 3.5rem;
                }

                .error-title {
                    font-size: 1.5rem;
                }

                .btn-secondary {
                    padding: 0.65rem 1rem;
                    font-size: 1rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="text-center">
                <i class="bi bi-exclamation-triangle-fill error-icon"></i>
                <h1 class="error-title">Error!</h1>
                <p class="lead mb-4">
                    <?php echo htmlspecialchars($e->getMessage()); ?>
                </p>
                <a href="javascript:history.back()" class="btn btn-secondary btn-lg">
                    <i class="bi bi-arrow-left me-1"></i> Go Back
                </a>    
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    error_log("Order/Credit update error: " . $e->getMessage()); // Log error for debugging
}
?>