<?php
// index.php (Main Entry Point)

// Start the session to access session variables
session_start();

// Include the authentication functions
// Adjust the path based on your actual file structure
require_once 'includes/auth.php'; // Make sure this path is correct

// --- Determine Redirect Destination ---

// Check if the user is logged in using the isLoggedIn() function from auth.php
if (isLoggedIn()) {
    // User is logged in, get their role
    $user_role = $_SESSION['role'] ?? 'user'; // Default to 'user' if role is not set

    // Redirect based on the user's role
    switch ($user_role) {
        case 'admin':
            // Redirect admin users to the admin dashboard index
            header("Location: views/dashboard/admin/index.php");
            break;
        case 'waiter':
            // Redirect waiter users to the waiter dashboard index
            header("Location: views/dashboard/waiter/index.php");
            break;
        case 'user':
        default:
            // Redirect regular users (or any other role) to the user dashboard index
            header("Location: views/dashboard/user/index.php");
            break;
    }
    // Important: Always call exit() after a header() redirect to stop further script execution
    exit;
} else {
    // User is not logged in
    // Redirect them to the login page
    header("Location: views/auth/login.php");
    // Important: Always call exit() after a header() redirect to stop further script execution
    exit;
}

// --- Optional: Fallback Content (Highly Unlikely to be Reached) ---

// In a normal flow, one of the redirects above will always execute.
// However, as a safeguard, you can include minimal HTML content here.
// This content would only be displayed if there was an unexpected issue with the redirects above
// or if a user somehow bypassed the session checks (which is unlikely with proper server configuration).

/*
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mess Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container text-center mt-5">
        <h1>Welcome to Mess Management System</h1>
        <p>You are being redirected...</p>
        <?php if (isLoggedIn()): ?>
            <a href="<?php echo getDashboardUrl(); ?>" class="btn btn-primary">Go to Your Dashboard</a>
        <?php else: ?>
            <a href="views/auth/login.php" class="btn btn-primary">Go to Login</a>
        <?php endif; ?>
    </div>
    <script>
        // Simple JavaScript redirect as an additional fallback
        setTimeout(function() {
            <?php if (isLoggedIn()): ?>
                window.location.href = "<?php echo getDashboardUrl(); ?>";
            <?php else: ?>
                window.location.href = "views/auth/login.php";
            <?php endif; ?>
        }, 2000); // Redirect after 2 seconds
    </script>
</body>
</html>
*/
?>