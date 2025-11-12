<?php
// order_success.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}

// Collect order details safely
$table_qr = $_POST['table_qr'] ?? 'Unknown';
$card_owner_id = $_POST['card_owner_id'] ?? 'N/A';
$credits = $_POST['credits'] ?? 'N/A';
$seating = $_POST['seating'] ?? 'N/A';
$meal_type = $_POST['meal_type'] ?? 'N/A';
$menu_items = $_POST['menu_items'] ?? [];

// ✅ Generate a strong random unique order ID
$random_code = 'ORDER-' . strtoupper(bin2hex(random_bytes(5)));  // Example: ORDER-AB12CD34EF

// ✅ Generate QR URL using reliable QR API
$qr_data = "Order Code: " . $random_code;
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($qr_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Order Success</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #0a0f13, #1a2b35);
      color: #fff;
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 40px;
    }

    .success-card {
      background: rgba(255, 255, 255, 0.05);
      border-radius: 20px;
      border: 1px solid rgba(255,255,255,0.1);
      box-shadow: 0 0 25px rgba(0,255,204,0.1);
      padding: 40px;
      width: 500px;
      text-align: center;
      backdrop-filter: blur(10px);
    }

    .success-card h3 {
      color: #00ffcc;
      font-weight: 600;
      margin-bottom: 15px;
    }

    .details {
      font-size: 15px;
      margin-bottom: 15px;
      color: rgba(255,255,255,0.8);
      text-align: left;
    }

    .qr-code {
      margin-top: 20px;
    }

    .btn-home {
      background: #00ffcc;
      color: #000;
      border: none;
      font-weight: 600;
      border-radius: 8px;
      margin-top: 20px;
      transition: 0.3s;
    }

    .btn-home:hover {
      background: #00c7a2;
    }

    ul {
      text-align: left;
      padding-left: 20px;
    }

    .text-muted {
      color: rgba(255,255,255,0.5) !important;
    }
  </style>
</head>
<body>

  <div class="success-card">
    <h3><i class="bi bi-check-circle-fill"></i> Order Placed Successfully!</h3>
    <p>Your order has been received. Here are the details:</p>

    <div class="details">
      <p><strong>Table:</strong> <?php echo htmlspecialchars($table_qr); ?></p>
      <p><strong>Card Owner ID:</strong> <?php echo htmlspecialchars($card_owner_id); ?></p>
      <p><strong>Credits:</strong> <?php echo htmlspecialchars($credits); ?></p>
      <p><strong>Seating:</strong> <?php echo htmlspecialchars($seating); ?></p>
      <p><strong>Meal Type:</strong> <?php echo htmlspecialchars($meal_type); ?></p>
      <p><strong>Order QR Code:</strong> <?php echo htmlspecialchars($random_code); ?></p>

      <h6 class="mt-3">Ordered Items:</h6>
      <ul>
        <?php
        if (!empty($menu_items)) {
            foreach ($menu_items as $item) {
                echo "<li>" . htmlspecialchars($item) . "</li>";
            }
        } else {
            echo "<li>No items selected</li>";
        }
        ?>
      </ul>
    </div>

    <div class="qr-code">
      <img src="<?php echo $qr_url; ?>" 
           alt="Order QR Code" 
           class="img-fluid rounded shadow" 
           width="200" height="200"
           style="background:#fff; border-radius:10px; padding:10px;"
           onerror="this.onerror=null; this.src='https://via.placeholder.com/250?text=QR+Unavailable';">
      <p class="text-muted mt-2">Scan this QR to track your order</p>
    </div>

    <!-- ✅ Updated Button -->
    <a href="index.php" class="btn btn-home w-100">
      <i class="bi bi-speedometer2"></i> Back to Dashboard
    </a>
  </div>

</body>
</html>
