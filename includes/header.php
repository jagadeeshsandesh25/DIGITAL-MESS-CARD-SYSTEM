<?php
// includes/header.php

// Ensure session is started (often done in auth.php or index.php, but good to check)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Configuration and Constants ---
// Ensure BASE_URL is defined. This should ideally be in config/config.php
// For safety, we'll define it here if it's not already defined.
if (!defined('BASE_URL')) {
    // --- Auto-detect BASE_URL ---
    // Get the protocol (http or https)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    // Get the host (e.g., localhost, yourdomain.com)
    $host = $_SERVER['HTTP_HOST'];
    // Get the script name (e.g., /jai/index.php)
    $script_name = $_SERVER['SCRIPT_NAME'];
    // Get the directory path of the current script relative to the document root (e.g., /jai/)
    $script_dir = dirname($script_name);
    // Combine to form the base URL (e.g., http://localhost/jai/)
    // This assumes your project is in a subdirectory named after the project folder
    // Adjust the path depth if your structure is different (e.g., if views are in a deeper subfolder)
    define('BASE_URL', $protocol . '://' . $host . $script_dir . '/');
}

// --- Get User Details from Session ---
// Retrieve user information from the session superglobal ($_SESSION)
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;
$user_name = $_SESSION['username'] ?? 'Guest';

// --- Determine Login Status ---
// Check if the user is logged in by verifying session variables
$is_logged_in = isset($user_id) && isset($user_role);

// --- Helper Function: isActivePage ---
/**
 * Checks if the current page matches a given page name.
 *
 * @param string $pageName The name of the page to check against (e.g., 'login', 'dashboard').
 * @return bool True if the current page matches, false otherwise.
 */
function isActivePage($pageName) {
    // Get the current script name (e.g., /jai/views/auth/login.php)
    $currentScript = $_SERVER['SCRIPT_NAME'];
    // Extract the filename without extension (e.g., login)
    $currentPage = pathinfo($currentScript, PATHINFO_FILENAME);
    // Compare with the provided page name
    return $currentPage === $pageName;
}

// --- Helper Function: getActiveClass ---
/**
 * Returns the 'active' CSS class if the current page matches a given page name.
 *
 * @param string $pageName The name of the page to check against.
 * @return string 'active' if the page is active, '' otherwise.
 */
function getActiveClass($pageName) {
    return isActivePage($pageName) ? 'active' : '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mess Management System</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/auth.css">
    <!-- You can add more specific CSS for different sections here if needed -->
    <?php if (defined('ENABLE_DEBUG_MODE') && ENABLE_DEBUG_MODE): ?>
    <!-- Optional: Debugging CSS (e.g., outlines for layout) -->
    <!-- <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/debug.css"> -->
    <?php endif; ?>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>index.php">Mess Management</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <!-- Navigation items vary based on user role and login status -->
                    <?php if ($is_logged_in): ?>
                        <?php if ($user_role === 'admin'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo getActiveClass('admin'); ?>" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Admin Dashboard
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/dashboard/admin/users.php">Manage Users</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/dashboard/admin/cards.php">Manage Cards</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/dashboard/admin/transactions.php">Manage Transactions</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/dashboard/admin/reports.php">Reports</a></li>
                                    <!-- Add more admin-specific links here -->
                                </ul>
                            </li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('users'); ?>" href="<?php echo BASE_URL; ?>views/users/index.php">Users</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('cards'); ?>" href="<?php echo BASE_URL; ?>views/cards/index.php">Cards</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('menu'); ?>" href="<?php echo BASE_URL; ?>views/menu/index.php">Menu</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('recharge'); ?>" href="<?php echo BASE_URL; ?>views/recharge/index.php">Recharge</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('tables'); ?>" href="<?php echo BASE_URL; ?>views/tables/index.php">Tables</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('transactions'); ?>" href="<?php echo BASE_URL; ?>views/transactions/index.php">Transactions</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('feedback'); ?>" href="<?php echo BASE_URL; ?>views/feedback/index.php">Feedback</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('user_details'); ?>" href="<?php echo BASE_URL; ?>views/user_details/index.php">User Details</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('waiter'); ?>" href="<?php echo BASE_URL; ?>views/waiter/index.php">Waiter Orders</a></li>
                        <?php elseif ($user_role === 'waiter'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo getActiveClass('waiter'); ?>" href="#" id="waiterDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Waiter Dashboard
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="waiterDropdown">
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/dashboard/waiter/assigned_tables.php">Assigned Tables</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/dashboard/waiter/take_order.php">Take Order</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/tables/index.php">My Tables</a></li> <!-- Or a specific waiter view -->
                                </ul>
                            </li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('assigned_tables'); ?>" href="<?php echo BASE_URL; ?>views/dashboard/waiter/assigned_tables.php">Assigned Tables</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('take_order'); ?>" href="<?php echo BASE_URL; ?>views/dashboard/waiter/take_order.php">Take Order</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('tables'); ?>" href="<?php echo BASE_URL; ?>views/tables/index.php">My Tables</a></li> <!-- Or a specific waiter view -->
                        <?php elseif ($user_role === 'user'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo getActiveClass('user'); ?>" href="#" id="userDropdownMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    User Dashboard
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="userDropdownMenu">
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/dashboard/user/profile.php">My Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/dashboard/user/my_card.php">My Card</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/dashboard/user/order_history.php">Order History</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/menu/index.php">View Menu</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/recharge/index.php">Recharge Card</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/feedback/index.php">Give Feedback</a></li>
                                </ul>
                            </li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('profile'); ?>" href="<?php echo BASE_URL; ?>views/dashboard/user/profile.php">My Profile</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('my_card'); ?>" href="<?php echo BASE_URL; ?>views/dashboard/user/my_card.php">My Card</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('order_history'); ?>" href="<?php echo BASE_URL; ?>views/dashboard/user/order_history.php">Order History</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('menu'); ?>" href="<?php echo BASE_URL; ?>views/menu/index.php">View Menu</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('recharge'); ?>" href="<?php echo BASE_URL; ?>views/recharge/index.php">Recharge Card</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo getActiveClass('feedback'); ?>" href="<?php echo BASE_URL; ?>views/feedback/index.php">Give Feedback</a></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Guest navigation items -->
                        <li class="nav-item"><a class="nav-link <?php echo getActiveClass('login'); ?>" href="<?php echo BASE_URL; ?>views/auth/login.php">Login</a></li>
                        <?php if (defined('ENABLE_USER_REGISTRATION') && ENABLE_USER_REGISTRATION): ?>
                        <li class="nav-item"><a class="nav-link <?php echo getActiveClass('signup'); ?>" href="<?php echo BASE_URL; ?>views/auth/signup.php">Sign Up</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link <?php echo getActiveClass('menu'); ?>" href="<?php echo BASE_URL; ?>views/menu/index.php">View Menu</a></li> <!-- Public menu view? -->
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Welcome, <?php echo htmlspecialchars($user_name); ?> (<?php echo htmlspecialchars($user_role); ?>)
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <?php if ($user_role === 'admin' || $user_role === 'waiter' || $user_role === 'user'): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/dashboard/<?php echo $user_role; ?>/index.php">My Dashboard</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>views/users/view.php?id=<?php echo $user_id; ?>">My Profile</a></li>
                                <!-- <li><hr class="dropdown-divider"></li> -->
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <span class="navbar-text">Guest</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="container-fluid mt-3">
        <div class="row">
            <main class="col-md-12 px-md-4"> <!-- Adjust column width if you add a sidebar later -->