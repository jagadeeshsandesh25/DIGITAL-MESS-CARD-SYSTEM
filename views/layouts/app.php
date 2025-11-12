<?php
// views/layouts/app.php — Merged Layout (Orange Theme + Collapsible Sidebar + Notifications + Waiter Support + Theme Toggle)
// Combines features from both provided app.php files

if (session_status() === PHP_SESSION_NONE) session_start();

// Authentication check
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id      = $_SESSION['user_id'];
$user_role    = strtolower($_SESSION['role']);
$username     = $_SESSION['username'] ?? 'User';
$current_page = basename($_SERVER['PHP_SELF']);
$full_path    = $_SERVER['SCRIPT_NAME'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= ucfirst(htmlspecialchars($user_role)) ?> Dashboard | Mess Management</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
:root {
  --brand: #ff8c42;
  --brand-dark: #e6762f;
  --brand-light: #ffeedb;
  --bg: #fafbfc;
  --text: #1e293b;
  --muted: #64748b;
  --border: #e2e8f0;
  --sidebar-width: 260px;
  --sidebar-mini: 72px;
  --transition: all 0.28s cubic-bezier(0.25, 0.8, 0.25, 1);
}
html, body { height: 100%; margin: 0; }
body {
  font-family: 'Poppins', sans-serif;
  background: var(--bg);
  color: var(--text);
  padding-top: 64px;
  transition: background 0.4s, color 0.4s;
}
body.dark-mode {
  --bg: #121212;
  --text: #f1f5f9;
  --muted: #94a8c4;
  --border: #2d3748;
  background: var(--bg);
  color: var(--text);
}

/* ===== Navbar ===== */
.navbar-fixed {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 64px;
  background: linear-gradient(90deg, var(--brand-dark) 0%, var(--brand) 100%);
  color: #fff;
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.25rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.navbar-fixed .navbar-brand {
  color: #fff;
  text-decoration: none;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 1.1rem;
}
.navbar-fixed .btn-link {
  color: white;
  text-decoration: none;
  padding: 0.4rem;
  border-radius: 8px;
  transition: background 0.2s;
}
.navbar-fixed .btn-link:hover {
  background: rgba(255, 255, 255, 0.15);
}
.navbar-fixed .dropdown-menu {
  border-radius: 12px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.18);
  border: none;
  padding: 0.25rem 0;
}

/* ===== Sidebar ===== */
.sidebar {
  position: fixed;
  top: 64px;
  left: 0;
  height: calc(100vh - 64px);
  width: var(--sidebar-width);
  background: #fff;
  border-right: 1px solid var(--border);
  transition: var(--transition);
  overflow-y: auto;
  z-index: 1050;
  scrollbar-width: thin;
}
body.dark-mode .sidebar {
  background: #1e1e1e;
  border-color: #2d3748;
}
.sidebar.collapsed {
  width: var(--sidebar-mini);
}

/* Brand area */
.sidebar .brand-area {
  padding: 1.5rem 1.25rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.sidebar .brand-text {
  font-weight: 600;
  color: var(--text);
  font-size: 1.05rem;
}
.sidebar.collapsed .brand-text {
  display: none;
}

/* Nav items */
.sidebar .nav {
  padding: 1.25rem 0;
}
.sidebar .nav-link {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  color: var(--muted);
  padding: 0.65rem 1.25rem;
  margin: 0.2rem 0.75rem;
  border-radius: 10px;
  text-decoration: none;
  transition: var(--transition);
  white-space: nowrap;
  font-weight: 500;
  font-size: 0.95rem;
}
.sidebar .nav-link i {
  width: 24px;
  text-align: center;
  font-size: 1.15rem;
  opacity: 0.9;
}
.sidebar .nav-text {
  display: inline-block;
  opacity: 0.95;
}

/* Hover / Active */
.sidebar .nav-link:hover {
  background: rgba(255, 140, 66, 0.12);
  color: var(--brand-dark);
}
.sidebar .nav-link.active {
  background: rgba(255, 140, 66, 0.2);
  color: var(--brand-dark);
  font-weight: 600;
  border-left: 3px solid var(--brand);
}

/* Collapsed state */
.sidebar.collapsed .nav-link {
  justify-content: center;
  padding-left: 0.4rem;
  padding-right: 0.4rem;
}
.sidebar.collapsed .nav-link i {
  margin: 0;
}
.sidebar.collapsed .nav-text {
  display: none;
}

/* Tooltip on collapsed (optional enhancement) */
.sidebar.collapsed .nav-link::after {
  content: attr(title);
  position: absolute;
  left: 72px;
  top: 50%;
  transform: translateY(-50%);
  background: #333;
  color: white;
  padding: 0.35rem 0.6rem;
  border-radius: 6px;
  font-size: 0.85rem;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s;
  z-index: 1200;
}
.sidebar.collapsed .nav-link:hover::after {
  opacity: 1;
}

/* ===== Main Content ===== */
.main-content {
  margin-left: var(--sidebar-width);
  padding: 2rem;
  transition: var(--transition);
  min-height: calc(100vh - 64px);
}
.sidebar.collapsed ~ .main-content {
  margin-left: var(--sidebar-mini);
}

/* Cards & Boxes */
.card {
  border: none;
  border-radius: 16px;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.06);
  transition: var(--transition);
  background: white;
}
body.dark-mode .card {
  background: #1e1e1e;
}
.card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.page-title-box {
  background: white;
  border-left: 5px solid var(--brand);
  border-radius: 14px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
body.dark-mode .page-title-box {
  background: #1e1e1e;
}

/* Buttons */
.btn-brand {
  background: var(--brand);
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 0.6rem 1.25rem;
  font-weight: 600;
  transition: background 0.25s;
}
.btn-brand:hover {
  background: var(--brand-dark);
  transform: translateY(-1px);
}

/* Footer */
footer {
  text-align: center;
  font-size: 0.875rem;
  color: var(--muted);
  margin-top: 2.5rem;
  padding: 1.25rem 0;
  border-top: 1px solid var(--border);
}
body.dark-mode footer {
  border-color: #2d3748;
}

/* Responsive */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    width: 280px;
  }
  .sidebar.active {
    transform: translateX(0);
  }
  .main-content {
    margin-left: 0;
    padding: 1.5rem;
  }
  .navbar-fixed {
    padding: 0 1rem;
  }
}

/* Animations */
.fade-in {
  animation: fadeIn 0.45s ease-out;
}
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Scrollbar (optional polish) */
.sidebar::-webkit-scrollbar {
  width: 6px;
}
.sidebar::-webkit-scrollbar-track {
  background: transparent;
}
.sidebar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}
body.dark-mode .sidebar::-webkit-scrollbar-thumb {
  background: #4a5568;
}
</style>
</head>
<body>

<!-- Navbar -->
<header class="navbar-fixed">
  <div class="d-flex align-items-center">
    <button id="sidebarToggle" class="btn btn-link text-white me-2" aria-label="Toggle sidebar">
      <i class="bi bi-list fs-4"></i>
    </button>
    <a href="#" class="navbar-brand">
     <i class="bi bi-hearts"></i>LittleHearts
    </a>
  </div>

  <div class="d-flex align-items-center gap-3">
    <?php if ($user_role === 'waiter'): ?>
      <div class="dropdown me-2" id="notifDropdown">
        <button class="btn btn-link text-white position-relative" id="notifButton" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="bi bi-bell-fill fs-5"></i>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifCount" style="display:none; font-size:0.7rem; padding:0.25em 0.45em;">0</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm p-0" id="notifMenu" style="width:340px; max-height:380px; overflow-y:auto;">
          <li class="dropdown-header py-2 px-3 fw-semibold bg-light text-dark">Notifications</li>
          <li id="notifItems"><div class="p-3 text-center text-muted small">No new notifications</div></li>
        </ul>
      </div>
    <?php endif; ?>

    <button id="themeToggle" class="btn btn-link text-white" aria-label="Toggle theme">
      <i class="bi bi-moon fs-5"></i>
    </button>

    <div class="dropdown">
      <a href="#" class="text-white dropdown-toggle fw-medium d-flex align-items-center" data-bs-toggle="dropdown">
        <i class="bi bi-person-circle me-1"></i>
        <span class="d-none d-sm-inline"><?= htmlspecialchars($username) ?></span>
      </a>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><a class="dropdown-item" href="../<?= $user_role ?>/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="../../../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</header>

<!-- Sidebar -->
<aside id="appSidebar" class="sidebar" role="navigation" aria-label="Main sidebar">
  <div class="brand-area">
    <i class="bi bi-shop" style="color:var(--brand); font-size:1.35rem;"></i>
    <span class="brand-text">Admin</span>
  </div>

  <ul class="nav flex-column mt-2">
    <li>
      <a class="nav-link <?= $current_page==='index.php'?'active':'' ?>"
         href="../<?= $user_role ?>/index.php"
         title="Dashboard">
      <i class="bi bi-house me-1"></i>
        <span class="nav-text">Dashboard</span>
      </a>
    </li>

    <?php if ($user_role === 'waiter'): ?>
      <li>
        <a class="nav-link <?= strpos($full_path,'support_order')!==false?'active':'' ?>"
           href="../waiter/support_order.php"
           title="Support / Create Order">
          <i class="bi bi-headset"></i>
          <span class="nav-text">Support / Create Order</span>
        </a>
      </li>
      <li>
        <a class="nav-link <?= strpos($full_path,'orders')!==false?'active':'' ?>"
           href="../waiter/orders.php"
           title="Orders">
          <i class="bi bi-list-check"></i>
          <span class="nav-text">Orders</span>
        </a>
      </li>
      <li>
        <a class="nav-link <?= strpos($full_path,'tables')!==false?'active':'' ?>"
           href="../waiter/tables.php"
           title="Tables">
          <i class="bi bi-grid"></i>
          <span class="nav-text">Tables</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if ($user_role === 'admin'): ?>
      <li><a class="nav-link <?= strpos($full_path,'users')!==false?'active':'' ?>" href="../admin/users.php" title="Users"><i class="bi bi-people"></i><span class="nav-text">Users</span></a></li>
      <li><a class="nav-link <?= strpos($full_path,'menu')!==false?'active':'' ?>" href="../admin/menu.php" title="Menu"><i class="bi bi-list"></i><span class="nav-text">Menu</span></a></li>
      <li><a class="nav-link <?= strpos($full_path,'tables')!==false?'active':'' ?>" href="../admin/tables.php" title="Manage Tables"><i class="bi bi-grid"></i><span class="nav-text">Manage Tables</span></a></li>
      <li><a class="nav-link <?= strpos($full_path,'transactions')!==false?'active':'' ?>" href="../admin/transactions.php" title="Transactions"><i class="bi bi-receipt"></i><span class="nav-text">Transactions</span></a></li>
      <li><a class="nav-link <?= strpos($full_path,'assign_plan')!==false?'active':'' ?>" href="../admin/assign_plan.php" title="Assign Plans"><i class="bi bi-credit-card"></i><span class="nav-text">Assign Plans</span></a></li>
      <li><a class="nav-link <?= strpos($full_path,'feedback')!==false?'active':'' ?>" href="../admin/feedback.php" title="feedback"><i class="bi bi-chat-quote"></i><span class="nav-text">feedback</span></a></li>
    <?php endif; ?>

    <?php if ($user_role === 'user'): ?>
      <li><a class="nav-link <?= strpos($full_path,'table_scan')!==false?'active':'' ?>" href="../user/table_scan.php" title="Scan Table"><i class="bi bi-qr-code-scan"></i><span class="nav-text">Scan Table</span></a></li>
      <li><a class="nav-link <?= strpos($full_path,'order_history')!==false?'active':'' ?>" href="../user/order_history.php" title="Order History"><i class="bi bi-receipt"></i><span class="nav-text">Order History</span></a></li>
      <li><a class="nav-link <?= strpos($full_path,'profile')!==false?'active':'' ?>" href="../user/profile.php" title="Profile"><i class="bi bi-person"></i><span class="nav-text">Profile</span></a></li>
    <?php endif; ?>
  </ul>
</aside>

<!-- Main content -->
<main class="main-content fade-in" id="mainContent" role="main">
  <?= $content ?? ''; ?>
  <footer>© <?= date('Y') ?> Mess Management System</footer>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sidebar = document.getElementById('appSidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const themeToggle = document.getElementById('themeToggle');

// Sidebar toggle
sidebarToggle.addEventListener('click', () => {
  if (window.innerWidth <= 768) {
    sidebar.classList.toggle('active');
  } else {
    sidebar.classList.toggle('collapsed');
  }
});

// Close sidebar on mobile link click
sidebar.querySelectorAll('a.nav-link').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) sidebar.classList.remove('active');
  });
});

// Theme toggle with localStorage
function setTheme(isDark) {
  document.body.classList.toggle('dark-mode', isDark);
  themeToggle.innerHTML = isDark ? '<i class="bi bi-sun fs-5"></i>' : '<i class="bi bi-moon fs-5"></i>';
  localStorage.setItem('theme', isDark ? 'dark' : 'light');
}
setTheme(localStorage.getItem('theme') === 'dark');
themeToggle.addEventListener('click', () => setTheme(!document.body.classList.contains('dark-mode')));

// Waiter notifications
<?php if ($user_role === 'waiter'): ?>
function loadNotifications() {
  fetch('../waiter/fetch_notifications.php')
    .then(r => r.ok ? r.json() : [])
    .then(data => {
      const menu = document.getElementById('notifItems');
      const count = document.getElementById('notifCount');
      const unreadCount = data.filter(n => !n.read_at).length;
      
      if (!data.length || unreadCount === 0) {
        menu.innerHTML = '<div class="p-3 text-center text-muted small">No new notifications</div>';
        count.style.display = 'none';
      } else {
        count.textContent = unreadCount;
        count.style.display = 'inline-block';
        menu.innerHTML = data.filter(n => !n.read_at).map(n => `
          <li class="border-bottom px-3 py-2 d-flex justify-content-between align-items-start">
            <div class="small text-dark fw-medium">${n.message}</div>
            <button class="btn btn-sm btn-outline-danger ms-2 p-0 px-1" title="Mark as read" onclick="markAsRead(${n.id})">
              <i class="bi bi-x-lg"></i>
            </button>
          </li>
        `).join('');
      }
    })
    .catch(() => {
      document.getElementById('notifItems').innerHTML = '<div class="p-3 text-center text-muted small">Failed to load</div>';
    });
}

function markAsRead(id) {
  fetch('../waiter/mark_notification.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'id=' + id
  }).then(() => loadNotifications()).catch(console.error);
}

loadNotifications();
setInterval(loadNotifications, 12000);
<?php endif; ?>
</script>
</body>
</html>