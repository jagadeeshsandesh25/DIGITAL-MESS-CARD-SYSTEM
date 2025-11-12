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
    background: linear-gradient(135deg, var(--light-bg), #ffffffff);
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

                <?php
                $vegItems = ["Paneer Butter Masala", "Veg Biryani", "Palak Paneer", "Dal Tadka", "Aloo Gobi"];
                $nonVegItems = ["Chicken Biryani", "Butter Chicken", "Mutton Curry", "Fish Fry", "Egg Curry"];

                if (stripos($plan_type, 'non') !== false) {
                    // Non-Veg Plan
                    echo "<h5><i class='bi bi-egg-fried me-2 text-danger'></i> Non-Veg Dishes</h5>";
                    foreach ($nonVegItems as $item) {
                        echo "<div class='form-check mb-2'>
                                <input class='form-check-input' type='checkbox' name='menu_items[]' value='$item' id='$item'>
                                <label class='form-check-label' for='$item'>$item</label>
                              </div>";
                    }
                } else {
                    // Veg Plan
                    echo "<h5><i class='bi bi-leaf me-2 text-success'></i> Veg Dishes</h5>";
                    foreach ($vegItems as $item) {
                        echo "<div class='form-check mb-2'>
                                <input class='form-check-input' type='checkbox' name='menu_items[]' value='$item' id='$item'>
                                <label class='form-check-label' for='$item'>$item</label>
                              </div>";
                    }
                }
                ?>
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
</body>
</html>