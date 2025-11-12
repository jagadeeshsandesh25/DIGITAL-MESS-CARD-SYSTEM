<?php
// includes/functions.php

/**
 * Sanitizes user input to prevent XSS attacks.
 * Uses htmlspecialchars with ENT_QUOTES and UTF-8 encoding.
 *
 * @param string $input The input string to sanitize.
 * @return string The sanitized string.
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirects the user to a specified URL.
 * Includes a call to exit to stop script execution.
 *
 * @param string $url The URL to redirect to.
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Generates a CSRF token and stores it in the session.
 * Helps protect against Cross-Site Request Forgery attacks.
 *
 * @return string The generated token.
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Generate a random token
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a CSRF token against the one stored in the session.
 *
 * @param string $token The token to validate.
 * @return bool True if valid, false otherwise.
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Clears the CSRF token from the session.
 * Should be called after validating a token to prevent reuse.
 */
function clearCSRFToken() {
    unset($_SESSION['csrf_token']);
}

/**
 * Logs a message to a file.
 * Uses the LOGS_PATH constant from config/config.php.
 *
 * @param string $message The message to log.
 * @param string $level The log level (e.g., 'INFO', 'WARNING', 'ERROR').
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;

    // Ensure the logs directory exists
    if (!is_dir(LOGS_PATH)) {
        mkdir(LOGS_PATH, 0755, true);
    }

    // Append the log entry to the app.log file
    file_put_contents(LOGS_PATH . 'app.log', $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Formats a number as Indian Rupees (₹).
 *
 * @param float $amount The amount to format.
 * @param int $decimals Number of decimal places.
 * @return string The formatted amount string.
 */
function formatCurrency($amount, $decimals = 2) {
    return '₹' . number_format($amount, $decimals, '.', ',');
}

/**
 * Truncates a string to a specified length and adds an ellipsis if needed.
 *
 * @param string $string The string to truncate.
 * @param int $length The maximum length of the string.
 * @param string $suffix The suffix to append if truncated (default '...').
 * @return string The truncated string.
 */
function truncateString($string, $length, $suffix = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }
    return substr($string, 0, $length - strlen($suffix)) . $suffix;
}

// Add more general utility functions as needed for your application

?>