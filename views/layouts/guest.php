<?php
// views/layouts/guest.php

// No session check here, as this layout is for non-logged-in users
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mess Management System - Login</title>
    <!-- Include Bootstrap CSS for a responsive layout (optional but recommended) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <!-- Link to your custom CSS file (you might have specific styles for auth pages) -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css"> <!-- Specific CSS for auth pages -->
</head>
<body class="bg-light"> <!-- Add a background class for guest pages -->

    <!-- Main Content Area -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <!-- Page content will be included here -->
                <?php if (isset($content)): ?>
                    <?php echo $content; ?>
                <?php else: ?>
                    <h2>Guest Layout</h2>
                    <p>This is the default content for the guest layout. Individual pages like login/signup will provide their own content.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">&copy; <?php echo date("Y"); ?> Mess Management System. All rights reserved.</span>
        </div>
    </footer>

    <!-- Include Bootstrap JS and Popper for interactive components -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js" integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN" crossorigin="anonymous"></script>
    <!-- Link to your custom JS file -->
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/auth.js"></script> <!-- Specific JS for auth pages -->
</body>
</html>