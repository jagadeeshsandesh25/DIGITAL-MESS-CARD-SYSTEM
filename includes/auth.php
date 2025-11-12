<?php
// includes/auth.php

// Start the session if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the user is logged in.
 *
 * @return bool True if logged in, false otherwise.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Requires the user to be logged in. Redirects to login if not.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Redirect to the login page
        // Use BASE_URL constant for reliability
        header("Location: " . BASE_URL . "views/auth/login.php");
        exit; // Stop script execution after redirect
    }
}

/**
 * Gets the logged-in user's role.
 *
 * @return string|null The user's role ('admin', 'waiter', 'user') or null if not logged in.
 */
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Checks if the logged-in user is an admin.
 *
 * @return bool True if admin, false otherwise.
 */
function isAdmin() {
    return getUserRole() === 'admin';
}

/**
 * Checks if the logged-in user is a waiter.
 *
 * @return bool True if waiter, false otherwise.
 */
function isWaiter() {
    return getUserRole() === 'waiter';
}

/**
 * Checks if the logged-in user is a regular user.
 *
 * @return bool True if user, false otherwise.
 */
function isRegularUser() {
    return getUserRole() === 'user';
}

/**
 * Gets the logged-in user's ID.
 *
 * @return int|null The user's ID or null if not logged in.
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Gets the logged-in user's username.
 *
 * @return string|null The user's username or null if not logged in.
 */
function getUserName() {
    return $_SESSION['username'] ?? null;
}

/**
 * Logs the user out by destroying the session.
 */
function logout() {
    // Unset all session variables
    session_unset();
    // Destroy the session
    session_destroy();
    // Optionally, regenerate the session ID for security
    // session_regenerate_id(true);
}

/**
 * Checks if the session has timed out.
 * Requires SESSION_TIMEOUT to be defined in config/config.php
 *
 * @return bool True if timed out, false otherwise.
 */
function isSessionTimedOut() {
    if (!isLoggedIn()) {
        return false; // Not logged in, so not timed out
    }

    // Check if the last activity timestamp is set
    if (isset($_SESSION['last_activity'])) {
        // Calculate the time difference
        $time_since_last_activity = time() - $_SESSION['last_activity'];

        // Compare with the defined timeout
        if ($time_since_last_activity > SESSION_TIMEOUT) {
            return true; // Session has timed out
        }
    } else {
        // If last_activity is not set, update it
        $_SESSION['last_activity'] = time();
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    return false; // Session is still active
}

/**
 * Handles session timeout by logging out and redirecting.
 */
function handleSessionTimeout() {
    if (isSessionTimedOut()) {
        logout();
        // Redirect to login with a timeout message
        header("Location: " . BASE_URL . "views/auth/login.php?timeout=1");
        exit;
    }
}

// --- Password Hashing Helper Functions (Optional, but useful) ---

/**
 * Hashes a password using PHP's default algorithm (bcrypt).
 *
 * @param string $password The plain text password.
 * @return string|false The hashed password, or false on failure.
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifies a plain text password against a hashed password.
 *
 * @param string $password The plain text password.
 * @param string $hash The hashed password.
 * @return bool True if the password matches the hash, false otherwise.
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// --- Role-Based Access Control (RBAC) Helper Functions (Optional) ---

/**
 * Requires the user to have a specific role. Redirects if not.
 *
 * @param string $required_role The required role ('admin', 'waiter', 'user').
 */
function requireRole($required_role) {
    requireLogin(); // Ensure user is logged in first
    if (getUserRole() !== $required_role) {
        // Redirect to a forbidden page or the user's dashboard
        // For now, redirect to the main dashboard index
        header("Location: " . BASE_URL . "views/dashboard/index.php");
        exit;
    }
}

/**
 * Requires the user to have one of several roles. Redirects if not.
 *
 * @param array $allowed_roles An array of allowed roles (e.g., ['admin', 'waiter']).
 */
function requireAnyRole($allowed_roles) {
    requireLogin(); // Ensure user is logged in first
    if (!in_array(getUserRole(), $allowed_roles)) {
        // Redirect to a forbidden page or the user's dashboard
        // For now, redirect to the main dashboard index
        header("Location: " . BASE_URL . "views/dashboard/index.php");
        exit;
    }
}

?>