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

$showForm = (!$meal_type || !$credits);

// Fetch menu items from database if meal_type and plan_type are available
$menu_items = [];
$menu_items_veg = []; // Separate array for Veg items (if Non-Veg plan)
$menu_items_plan_specific = []; // Separate array for Plan-specific items (Veg or Non-Veg)

if (!$showForm && $meal_type && $plan_type) {
    try {
        $meal_type_ucfirst = ucfirst($meal_type); // 'breakfast' -> 'Breakfast'

        if (stripos($plan_type, 'non') !== false) { // If plan is Non-Veg
            // Fetch Non-Veg items for the specific meal type
            $stmt = $db->prepare("
                SELECT id, name, price, menu_type, description, category
                FROM menu 
                WHERE category = :meal_type AND menu_type = 'Non-veg'
            ");
            $stmt->bindParam(':meal_type', $meal_type_ucfirst, PDO::PARAM_STR);
            $stmt->execute();
            $menu_items_plan_specific = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch Veg items for the specific meal type (allowed for Non-Veg plan users)
            $stmt = $db->prepare("
                SELECT id, name, price, menu_type, description, category
                FROM menu 
                WHERE category = :meal_type AND menu_type = 'Veg'
            ");
            $stmt->bindParam(':meal_type', $meal_type_ucfirst, PDO::PARAM_STR);
            $stmt->execute();
            $menu_items_veg = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else { // If plan is Veg (or anything else considered Veg)
            // Fetch Veg items for the specific meal type
            $stmt = $db->prepare("
                SELECT id, name, price, menu_type, description, category
                FROM menu 
                WHERE category = :meal_type AND menu_type = 'Veg'
            ");
            $stmt->bindParam(':meal_type', $meal_type_ucfirst, PDO::PARAM_STR);
            $stmt->execute();
            $menu_items_plan_specific = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // No need to fetch Non-Veg for Veg plan (or do you want to? If yes, add similar block as Non-Veg above)
            // For now, keeping $menu_items_veg empty for Veg plan.
        }

    } catch (PDOException $e) {
        // Handle potential errors gracefully, maybe show a message or fallback items
        error_log("Menu fetch error: " . $e->getMessage());
        $menu_items_plan_specific = []; // Set to empty array on error
        $menu_items_veg = [];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restaurant Menu</title>
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
    padding-top: 72px;
    margin: 0;
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
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    border-radius: 16px;
    margin-bottom: 1.5rem;
    background: white;
}

.form-label {
    font-weight: 500;
    color: #475569;
}

.form-select, .form-control {
    border: 1px solid #d1d5db;
    padding: 0.6rem 1rem;
    border-radius: 10px;
    transition: border-color 0.25s;
}

.form-select:focus, .form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(40, 65, 148, 0.15);
}

.btn-primary {
    background: linear-gradient(to right, var(--primary-color), #1a2f6d);
    border: none;
    padding: 0.75rem;
    font-weight: 600;
    border-radius: 10px;
    transition: opacity 0.3s;
}

.btn-primary:hover {
    opacity: 0.92;
}

.form-check-input {
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.menu-container {
    max-width: 700px;
    margin: 0 auto 2rem;
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

h5 {
    color: var(--primary-color);
    margin-top: 1.5rem;
    font-weight: 600;
}

.text-muted {
    color: #64748b !important;
}

.menu-item {
    background: #f8fafc;
    padding: 0.75rem;
    border-radius: 10px;
    margin-bottom: 0.75rem;
    border-left: 3px solid var(--secondary-color);
}

/* Mobile-specific styles */
@media (max-width: 576px) {
    body {
        padding-top: 64px;
    }

    .navbar-brand {
        font-size: 1.1rem;
    }

    .navbar-nav .nav-link {
        padding: 0.5rem 0.5rem !important;
        font-size: 0.85rem;
    }

    .container-fluid {
        padding: 0 0.75rem;
    }

    .card, .menu-container {
        padding: 1.25rem;
        margin: 0.5rem auto;
    }

    .menu-header h3 {
        font-size: 1.25rem;
    }

    .menu-header p {
        font-size: 0.85rem;
    }

    .btn-primary {
        padding: 0.65rem;
        font-size: 1rem;
    }

    .form-check {
        margin-bottom: 0.75rem;
    }

    .form-label {
        font-size: 0.9rem;
    }

    .menu-item {
        padding: 0.65rem;
    }

    .col-lg-8, .col-lg-4 {
        flex: 0 0 100%;
        max-width: 100%;
    }
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
               <i class="bi bi-hearts"></i>LittleHearts
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

    <div class="container-fluid mt-4">
        <?php if($showForm): ?>
        <div class="card text-center mx-auto" style="max-width: 500px;">
            <div class="card-header py-3">
                <h4 class="card-title mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i> Select Meal Details</h4>
            </div>
            <div class="card-body">
                <form method="GET" action="menuu.php">
                    <input type="hidden" name="table_qr" value="<?php echo htmlspecialchars($table_qr); ?>">
                    <input type="hidden" name="card_owner_id" value="<?php echo htmlspecialchars($card_owner_id); ?>">

                    <div class="mb-3 text-start">
                        <label class="form-label">Meal Type</label>
                        <select class="form-select" name="meal_type" required>
                            <option value="breakfast" <?php echo ($meal_type === 'breakfast') ? 'selected' : ''; ?>>Breakfast</option>
                            <option value="lunch" <?php echo ($meal_type === 'lunch') ? 'selected' : ''; ?>>Lunch</option>
                            <option value="dinner" <?php echo ($meal_type === 'dinner') ? 'selected' : ''; ?>>Dinner</option>
                        </select>
                    </div>

                    <div class="mb-3 text-start">
                        <label class="form-label">Number of Credits</label>
                        <select class="form-select" name="credits" required>
                            <option value="1" <?php echo ($credits == 1) ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo ($credits == 2) ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo ($credits == 3) ? 'selected' : ''; ?>>3</option>
                            <option value="4" <?php echo ($credits == 4) ? 'selected' : ''; ?>>4</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        Next <i class="bi bi-arrow-right-circle ms-1"></i>
                    </button>
                </form>
            </div>
        </div>

        <?php else: ?>
        <div class="menu-container">
            <div class="menu-header text-center mb-4">
                <h3><i class="bi bi-card-checklist me-2 text-primary"></i> <?php echo ucfirst($meal_type); ?> Menu (<?php echo ucfirst($plan_type); ?> Plan)</h3>
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

                <?php if (stripos($plan_type, 'non') !== false): ?>
                    <!-- Show Non-Veg items first for Non-Veg plan -->
                    <?php if (!empty($menu_items_plan_specific)): ?>
                        <h5><i class="bi bi-egg-fried me-2 text-danger"></i> Non-Veg Dishes</h5>
                        <?php foreach ($menu_items_plan_specific as $item): ?>
                            <div class="menu-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="menu_items[]" value="<?php echo htmlspecialchars($item['name']); ?>" id="item_<?php echo $item['id']; ?>">
                                    <label class="form-check-label" for="item_<?php echo $item['id']; ?>">
                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong> - ₹<?php echo number_format($item['price'], 2); ?>
                                        <?php if (!empty($item['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Show Veg items for Non-Veg plan -->
                    <?php if (!empty($menu_items_veg)): ?>
                        <h5><i class="bi bi-leaf me-2 text-success"></i> Veg Dishes (Available)</h5>
                        <?php foreach ($menu_items_veg as $item): ?>
                            <div class="menu-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="menu_items[]" value="<?php echo htmlspecialchars($item['name']); ?>" id="item_<?php echo $item['id']; ?>">
                                    <label class="form-check-label" for="item_<?php echo $item['id']; ?>">
                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong> - ₹<?php echo number_format($item['price'], 2); ?>
                                        <?php if (!empty($item['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Show Veg items for Veg plan (or other plans considered Veg) -->
                    <?php if (!empty($menu_items_plan_specific)): ?>
                        <h5><i class="bi bi-leaf me-2 text-success"></i> <?php echo ucfirst($plan_type); ?> Dishes</h5>
                        <?php foreach ($menu_items_plan_specific as $item): ?>
                            <div class="menu-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="menu_items[]" value="<?php echo htmlspecialchars($item['name']); ?>" id="item_<?php echo $item['id']; ?>">
                                    <label class="form-check-label" for="item_<?php echo $item['id']; ?>">
                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong> - ₹<?php echo number_format($item['price'], 2); ?>
                                        <?php if (!empty($item['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (empty($menu_items_plan_specific) && empty($menu_items_veg)): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <p class="mb-0">No <?php echo ucfirst($plan_type); ?> <?php echo ucfirst($meal_type); ?> items available at the moment.</p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary w-100 mt-4">
                    Order Now <i class="bi bi-arrow-right-circle ms-1"></i>
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sticky footer for mobile -->
    <div class="sticky-footer d-md-none">
        <div class="d-flex justify-content-around">
            <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2"></i></a>
            <a href="profile.php" class="btn btn-outline-success btn-sm"><i class="bi bi-person"></i></a>
            <a href="../../../logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.getElementById('orderForm').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('input[name="menu_items[]"]:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert("Please select at least one item.");
    }
});
</script>

</body>
</html>