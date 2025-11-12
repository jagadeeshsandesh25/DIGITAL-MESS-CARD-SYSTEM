<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// ✅ Add new item (with redirect & duplicate prevention)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)($_POST['price'] ?? 0);
    $menu_type = $_POST['menu_type'] ?? '';
    $category = $_POST['category'] ?? '';

    // Validate
    if (empty($name) || $price <= 0 || !in_array($menu_type, ['Veg', 'Non-veg']) || !in_array($category, ['Breakfast', 'Lunch', 'Dinner'])) {
        $_SESSION['menu_message'] = 'Please enter valid item details.';
        $_SESSION['menu_type'] = 'danger';
        header("Location: menu.php");
        exit;
    }

    // Optional: Prevent exact duplicate (same name, type, category, price)
    $check = $db->prepare("SELECT id FROM menu WHERE name = ? AND menu_type = ? AND category = ? AND price = ?");
    $check->execute([$name, $menu_type, $category, $price]);
    if ($check->fetch()) {
        $_SESSION['menu_message'] = 'This item already exists.';
        $_SESSION['menu_type'] = 'warning';
        header("Location: menu.php");
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO menu (name, description, price, menu_type, category) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $price, $menu_type, $category]);
        $_SESSION['menu_message'] = 'Menu item added successfully!';
        $_SESSION['menu_type'] = 'success';
    } catch (PDOException $e) {
        error_log("Menu insert error: " . $e->getMessage());
        $_SESSION['menu_message'] = 'Error adding menu item.';
        $_SESSION['menu_type'] = 'danger';
    }

    header("Location: menu.php"); // ✅ PRG: Prevents re-submission
    exit;
}

// ✅ Handle AJAX update for Today/Special
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_today'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';

    if ($item_id && in_array($field, ['is_today', 'is_special']) && in_array($value, ['Yes', 'No'])) {
        $stmt = $db->prepare("UPDATE menu SET `$field` = ? WHERE id = ?");
        $stmt->execute([$value, $item_id]);
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

// ✅ Fetch messages from session (after redirect)
if (isset($_SESSION['menu_message'])) {
    $message = $_SESSION['menu_message'];
    $message_type = $_SESSION['menu_type'] ?? 'info';
    unset($_SESSION['menu_message'], $_SESSION['menu_type']);
}

// ✅ Fetch all menu items
$stmt = $db->prepare("SELECT * FROM menu ORDER BY menu_type, category, name");
$stmt->execute();
$menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Group correctly (DB uses 'Non-veg' with hyphen!)
$veg_items = array_filter($menu_items, fn($i) => $i['menu_type'] === 'Veg');
$nonveg_items = array_filter($menu_items, fn($i) => $i['menu_type'] === 'Non-veg'); // ✅ Exact match
$todays_items = array_filter($menu_items, fn($i) => $i['is_today'] === 'Yes');
$special_items = array_filter($menu_items, fn($i) => $i['is_special'] === 'Yes');

ob_start();
?>

<div class="container-fluid">
    <div class="page-title-box">
        <div class="page-title-right">
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Manage Menu</li>
            </ol>
        </div>
        <h4 class="page-title">Manage Menu & Today's Specials</h4>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add Menu Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-plus-circle"></i> Add New Menu Item
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="Breakfast">Breakfast</option>
                            <option value="Lunch">Lunch</option>
                            <option value="Dinner">Dinner</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Menu Type</label>
                        <select name="menu_type" class="form-select" required>
                            <option value="Veg">Veg</option>
                            <option value="Non-veg">Non-Veg</option> <!-- ✅ Matches DB -->
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Price (₹)</label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <button type="submit" name="add_item" class="btn btn-primary mt-3 w-100">
                    <i class="bi bi-save"></i> Add Item
                </button>
            </form>
        </div>
    </div>

    <!-- Today’s Menu -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <i class="bi bi-calendar-day"></i> Today's Menu
        </div>
        <div class="card-body">
            <?php if (empty($todays_items)): ?>
                <p class="text-muted text-center mb-0">No items marked as today's menu.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($todays_items as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($item['name']) ?> 
                            <span class="badge bg-secondary"><?= htmlspecialchars($item['menu_type']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Today’s Specials -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <i class="bi bi-star-fill"></i> Today's Specials
        </div>
        <div class="card-body">
            <?php if (empty($special_items)): ?>
                <p class="text-muted text-center mb-0">No specials selected for today.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($special_items as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($item['name']) ?>
                            <span class="badge bg-danger"><?= htmlspecialchars($item['menu_type']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Veg Items -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <i class="bi bi-leaf"></i> Veg Items
        </div>
        <div class="card-body">
            <?php if (empty($veg_items)): ?>
                <p class="text-muted text-center mb-0">No Veg items found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Today</th><th>Special</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($veg_items as $item): ?>
                                <tr>
                                    <td><?= $item['id'] ?></td>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['category']) ?></td>
                                    <td>₹<?= number_format($item['price'], 2) ?></td>
                                    <td><input type="checkbox" <?= $item['is_today'] === 'Yes' ? 'checked' : '' ?> onchange="updateToday(<?= $item['id'] ?>, 'is_today', this.checked)"></td>
                                    <td><input type="checkbox" <?= $item['is_special'] === 'Yes' ? 'checked' : '' ?> onchange="updateToday(<?= $item['id'] ?>, 'is_special', this.checked)"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Non-Veg Items -->
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            <i class="bi bi-egg-fried"></i> Non-Veg Items
        </div>
        <div class="card-body">
            <?php if (empty($nonveg_items)): ?>
                <p class="text-muted text-center mb-0">No Non-Veg items found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Today</th><th>Special</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nonveg_items as $item): ?>
                                <tr>
                                    <td><?= $item['id'] ?></td>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['category']) ?></td>
                                    <td>₹<?= number_format($item['price'], 2) ?></td>
                                    <td><input type="checkbox" <?= $item['is_today'] === 'Yes' ? 'checked' : '' ?> onchange="updateToday(<?= $item['id'] ?>, 'is_today', this.checked)"></td>
                                    <td><input type="checkbox" <?= $item['is_special'] === 'Yes' ? 'checked' : '' ?> onchange="updateToday(<?= $item['id'] ?>, 'is_special', this.checked)"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateToday(id, field, checked) {
    const value = checked ? 'Yes' : 'No';
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `update_today=1&item_id=${id}&field=${encodeURIComponent(field)}&value=${encodeURIComponent(value)}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) alert('Update failed');
    })
    .catch(() => alert('Network error'));
}
</script>

<?php
$content = ob_get_clean();
include '../../layouts/app.php';
?>