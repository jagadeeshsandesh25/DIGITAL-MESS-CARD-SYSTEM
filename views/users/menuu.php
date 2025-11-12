<?php
// menuu.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Fetch user's active plan type (Veg / Non-Veg)
$plan_type = 'Veg';
try {
    $stmt = $db->prepare("SELECT p.plan_type 
        FROM student_plans sp 
        JOIN plans p ON sp.plan_id = p.id 
        WHERE sp.user_id = ? AND sp.status = 'active' 
        LIMIT 1");
    $stmt->execute([$user_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($plan && !empty($plan['plan_type'])) {
        $plan_type = $plan['plan_type'];
    }
} catch (PDOException $e) {
    $plan_type = 'Veg';
}

// Get parameters
$table_qr = $_GET['table_qr'] ?? null;
$card_owner_id = $_GET['card_owner_id'] ?? $_SESSION['user_id'];
$meal_type = $_GET['meal_type'] ?? null;
$credits = $_GET['credits'] ?? null;
$seating = $_GET['seating'] ?? null;

// Auto compute seating
if ($credits !== null) {
    $credits = intval($credits);
    if ($credits < 1) $credits = 1;
    if ($credits > 4) $credits = 4;
    $seating = $credits . "/4";
}

$showForm = !$meal_type || !$credits;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restaurant Menu</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg, #0a0f13, #1a2b35); color: #fff; font-family: 'Poppins', sans-serif; padding: 40px; }
.card { background: rgba(255,255,255,0.05); border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 0 25px rgba(0,255,204,0.1); padding: 30px; max-width: 500px; margin: 0 auto; }
.btn-next, .btn-order { background: #00ffcc; color: #000; border: none; font-weight: 600; border-radius: 8px; width: 100%; padding: 12px; margin-top: 20px; transition: 0.3s; }
.btn-next:hover, .btn-order:hover { background: #00c7a2; }
.form-label { color: #00ffcc; }
.form-select { background: rgba(255,255,255,0.08); color: #fff; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); }
.form-select:focus { box-shadow: 0 0 6px #00ffcc; border-color: #00ffcc; }
.menu-container { max-width: 700px; margin: 0 auto; background: rgba(255,255,255,0.05); border-radius: 20px; padding: 30px; box-shadow: 0 0 25px rgba(0,255,204,0.1); }
h5 { color: #00ffcc; margin-top: 20px; }
.text-muted { color: #aaa !important; }
</style>
</head>
<body>

<?php if($showForm): ?>
<div class="card text-center">
    <h4 class="mb-4"><i class="bi bi-pencil-square"></i> Select Meal Details</h4>
    <form method="GET" action="menuu.php">
        <input type="hidden" name="table_qr" value="<?php echo htmlspecialchars($table_qr); ?>">
        <input type="hidden" name="card_owner_id" value="<?php echo htmlspecialchars($card_owner_id); ?>">

        <div class="mb-3 text-start">
            <label class="form-label">Meal Type</label>
            <select class="form-select" name="meal_type" required>
                <option value="breakfast">Breakfast</option>
                <option value="lunch">Lunch</option>
                <option value="dinner">Dinner</option>
            </select>
        </div>

        <div class="mb-3 text-start">
            <label class="form-label">Number of Credits</label>
            <select class="form-select" name="credits" required>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
            </select>
        </div>

        <button type="submit" class="btn-next">Next <i class="bi bi-arrow-right-circle"></i></button>
    </form>
</div>

<?php else: ?>
<div class="menu-container">
    <div class="menu-header text-center mb-4">
        <h3><i class="bi bi-card-checklist"></i> <?php echo ucfirst($meal_type); ?> Menu (<?php echo ucfirst($plan_type); ?> Plan)</h3>
        <p class="text-muted">
            Table: <strong><?php echo htmlspecialchars($table_qr); ?></strong> |
            Owner ID: <strong><?php echo htmlspecialchars($card_owner_id); ?></strong> |
            Credits: <strong><?php echo htmlspecialchars($credits); ?></strong> |
            Seating: <strong><?php echo htmlspecialchars($seating); ?></strong>
        </p>
    </div>

    <form id="orderForm" method="POST" action="order_success.php">
        <input type="hidden" name="table_qr" value="<?php echo htmlspecialchars($table_qr); ?>">
        <input type="hidden" name="card_owner_id" value="<?php echo htmlspecialchars($card_owner_id); ?>">
        <input type="hidden" name="credits" value="<?php echo htmlspecialchars($credits); ?>">
        <input type="hidden" name="seating" value="<?php echo htmlspecialchars($seating); ?>">
        <input type="hidden" name="meal_type" value="<?php echo htmlspecialchars($meal_type); ?>">
        <input type="hidden" name="plan_type" value="<?php echo htmlspecialchars($plan_type); ?>">

        <?php
        $vegItems = ["Paneer Butter Masala", "Veg Biryani", "Palak Paneer", "Dal Tadka", "Aloo Gobi"];
        $nonVegItems = ["Chicken Biryani", "Butter Chicken", "Mutton Curry", "Fish Fry", "Egg Curry"];

        if (stripos($plan_type, 'non') !== false) {
            // Non-Veg Plan
            echo "<h5><i class='bi bi-egg-fried'></i> Non-Veg Dishes</h5>";
            foreach ($nonVegItems as $item) {
                echo "<div class='form-check'>
                        <input class='form-check-input' type='checkbox' name='menu_items[]' value='$item' id='$item'>
                        <label class='form-check-label' for='$item'>$item</label>
                      </div>";
            }
        } else {
            // Veg Plan
            echo "<h5><i class='bi bi-leaf'></i> Veg Dishes</h5>";
            foreach ($vegItems as $item) {
                echo "<div class='form-check'>
                        <input class='form-check-input' type='checkbox' name='menu_items[]' value='$item' id='$item'>
                        <label class='form-check-label' for='$item'>$item</label>
                      </div>";
            }
        }
        ?>
        <button type="submit" class="btn-order">Order Now <i class="bi bi-arrow-right-circle"></i></button>
    </form>
</div>
<?php endif; ?>

</body>
</html>
