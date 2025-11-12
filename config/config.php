<?php
// config/config.php

// --- General Application Configuration ---

// Define the base URL of your application
// This helps create absolute links and is useful for redirects
// Adjust this to match your local development setup or production URL
// Examples:
// Local XAMPP/WAMP (assuming project folder is 'mess_management_system'): http://localhost/mess_management_system/
// Local PHP Built-in Server: http://localhost:8000/
// Production: https://yourdomain.com/
define('BASE_URL', 'http://localhost/mess_management_system/'); // <<<--- CHANGE THIS TO YOUR ACTUAL BASE URL ---

// --- Path Definitions ---
// Define paths relative to the project root for easier includes
// These constants can be used to include files or link to assets consistently
define('ROOT_PATH', dirname(__DIR__) . '/'); // Path to the project root directory
define('CONFIG_PATH', __DIR__ . '/'); // Path to the config directory
define('INCLUDES_PATH', ROOT_PATH . 'includes/'); // Path to the includes directory
define('MODELS_PATH', ROOT_PATH . 'models/'); // Path to the models directory
define('CONTROLLERS_PATH', ROOT_PATH . 'controllers/'); // Path to the controllers directory
define('VIEWS_PATH', ROOT_PATH . 'views/'); // Path to the views directory
define('ASSETS_PATH', ROOT_PATH . 'assets/'); // Path to the assets directory
define('UPLOADS_PATH', ROOT_PATH . 'uploads/'); // Path to the uploads directory
define('LOGS_PATH', ROOT_PATH . 'logs/'); // Path to the logs directory
define('API_PATH', ROOT_PATH . 'api/'); // Path to the API directory

// --- Security Settings ---

// Secret key for hashing passwords or creating tokens (CHANGE THIS TO A STRONG, RANDOM STRING)
// Note: For password hashing, PHP's password_hash() is recommended over manual hashing with a secret key.
// This key might be used for other purposes like signing JWTs or CSRF tokens if implemented manually.
define('SECRET_KEY', 'your_very_strong_secret_key_here_change_this_immediately_do_not_use_this_default');

// Session timeout in seconds (e.g., 30 minutes = 1800 seconds, 1 hour = 3600 seconds)
define('SESSION_TIMEOUT', 1800); // 30 minutes

// --- Feature Flags ---

// Enable or disable specific features during development or maintenance
define('ENABLE_DEBUG_MODE', true); // Set to false in production to hide errors and debug info
define('ENABLE_USER_REGISTRATION', true); // Allow new user signups
define('ENABLE_PASSWORD_RESET', true); // Allow password reset via email (requires mail setup)
define('ENABLE_PUBLIC_MENU_VIEW', true); // Allow guests to view the menu

// --- Database Table Name Constants ---
// You can define database table names as constants to avoid typos
// and make refactoring easier if table names change
// Note the corrections for the typos in the schema
define('TABLE_USERS', 'user');
define('TABLE_CARDS', 'card');
define('TABLE_MENUS', 'menu');
define('TABLE_RECHARGES', 'recharge');
define('TABLE_TABLES', 'tabels'); // Note the typo in the schema
define('TABLE_TRANSACTIONS', 'transactions');
define('TABLE_FEEDBACK', 'feedback');
define('TABLE_USER_DETAILS', 'user_details_tabel'); // Note the long name in the schema

// --- Pagination Settings (Optional) ---
// Define default number of items per page for list views
define('ITEMS_PER_PAGE', 10);

// --- Date and Time Format Constants (Optional) ---
// Define standard date/time formats used throughout the application
define('DATE_FORMAT_STANDARD', 'Y-m-d');
define('TIME_FORMAT_STANDARD', 'H:i:s');
define('DATETIME_FORMAT_STANDARD', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd M Y'); // e.g., 25 Oct 2023
define('DISPLAY_DATETIME_FORMAT', 'd M Y, H:i'); // e.g., 25 Oct 2023, 14:30

// --- Currency Settings (Optional) ---
// Define currency symbol and formatting
define('CURRENCY_SYMBOL', '₹'); // Indian Rupee
define('CURRENCY_DECIMAL_PLACES', 2); // Two decimal places for paisa

// --- Application Name (Optional) ---
define('APP_NAME', 'Mess Management System');

// --- Default Role (Optional) ---
// Define the default role assigned to new users (if registration is enabled)
define('DEFAULT_USER_ROLE', 'user'); // Options: 'admin', 'waiter', 'user'

// Add other configuration settings as needed for your application
// Examples: API keys, third-party service credentials, email settings, etc.

?>