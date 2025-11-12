<?php
// controllers/AuthController.php

// Include the database configuration
require_once '../config/database.php'; // Adjust path as needed
// Include the User model
require_once '../models/User.php'; // Adjust path as needed
// Include general utility functions (if needed, e.g., for redirects or sanitization)
require_once '../includes/functions.php'; // Adjust path as needed
// Include authentication functions (isLoggedIn, isAdmin, etc.)
require_once '../includes/auth.php'; // Adjust path as needed

class AuthController {

    private $database;
    private $db;
    private $userModel;

    public function __construct() {
        // Initialize database connection
        $this->database = new Database();
        $this->db = $this->database->getConnection();

        // Initialize User model with the database connection
        $this->userModel = new User($this->db);
    }

    /**
     * Handles the login process.
     * This method is called by login.php and api/auth/login.php
     */
    public function login() {
        // Check if the user is already logged in
        if (isLoggedIn()) {
            // Redirect based on role
            $this->redirectToDashboard($_SESSION['role']);
            return; // Stop execution after redirect
        }

        $error_message = '';

        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Get input data (from $_POST if form submission, or JSON if API call)
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            // Sanitize and retrieve input data
            $username_or_email = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';

            // Basic validation
            if (empty($username_or_email) || empty($password)) {
                $error_message = 'Please enter both username/email and password.';
            } else {
                try {
                    // --- Critical Fix: Use the correct method from the User model ---
                    // Attempt to find the user by username or email using the model
                    $user = $this->userModel->findByUsernameOrEmail($username_or_email);

                    // --- Critical Fix: Verify user exists and password is correct ---
                    if ($user && password_verify($password, $user['password'])) {
                        // --- Critical Fix: Check if the user account is active ---
                        if ($user['status'] == 1) { // Check if account is active (1 = Active)
                            // Login successful - set session variables
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role'] = $user['role'];

                            // --- Critical Fix: Redirect based on role ---
                            // Determine the redirect URL based on the user's role
                            $redirect_url = $this->getDashboardUrl($user['role']);

                            // Check if this is an API call (expects JSON response)
                            $is_api_call = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;

                            if ($is_api_call) {
                                // For API calls, return JSON success response
                                header('Content-Type: application/json');
                                echo json_encode([
                                    'success' => true,
                                    'message' => 'Login successful.',
                                    'data' => [
                                        'user_id' => $user['id'],
                                        'username' => $user['username'],
                                        'role' => $user['role'],
                                        'redirect_url' => $redirect_url // Suggest redirect URL to the client
                                    ]
                                ]);
                                exit;
                            } else {
                                // For regular form submissions, perform HTTP redirect
                                header("Location: " . $redirect_url);
                                exit;
                            }
                        } else {
                            $error_message = 'Your account is inactive. Please contact administration.';
                        }
                    } else {
                        $error_message = 'Invalid username/email or password.';
                    }
                } catch (PDOException $e) {
                    // Log the error for debugging (do not expose to user)
                    error_log("AuthController login error: " . $e->getMessage());
                    $error_message = 'Database error during login. Please try again later.';
                }
            }
        }

        // If not a POST request or login failed, load the login view
        // Check if this is an API call (expects JSON response)
        $is_api_call = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;

        if ($is_api_call) {
            // For API calls, return JSON error response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $error_message ?: 'Invalid request method. Only POST is allowed.'
            ]);
            exit;
        } else {
            // For regular page requests, load the login view
            $content = $this->getLoginContent($error_message);
            include '../views/layouts/guest.php'; // Use the guest layout for login page
        }
    }

    /**
     * Handles the signup process.
     * This method is called by signup.php and api/auth/signup.php
     */
    public function signup() {
        // Check if the user is already logged in
        if (isLoggedIn()) {
            // Redirect based on role
            $this->redirectToDashboard($_SESSION['role']);
            return; // Stop execution after redirect
        }

        $success_message = '';
        $error_message = '';

        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Get input data (from $_POST if form submission, or JSON if API call)
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            // Sanitize and retrieve input data
            $first_name = trim($input['first_name'] ?? '');
            $last_name = trim($input['last_name'] ?? '');
            $email = trim($input['email'] ?? '');
            $ph_no = trim($input['ph_no'] ?? '');
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';
            $confirm_password = $input['confirm_password'] ?? '';
            $gender = $input['gender'] ?? '';
            $role = $input['role'] ?? 'user'; // Default role is 'user'

            // Basic validation
            $errors = [];
            if (empty($first_name) || empty($last_name) || empty($email) || empty($ph_no) || empty($username) || empty($password) || empty($confirm_password) || empty($gender)) {
                $errors[] = 'All fields are required.';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format.';
            }

            if (strlen($ph_no) !== 10 || !ctype_digit($ph_no)) {
                $errors[] = 'Phone number must be 10 digits.';
            }

            if ($password !== $confirm_password) {
                $errors[] = 'Passwords do not match.';
            }

            if (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters long.';
            }

            if (!in_array($role, ['admin', 'waiter', 'user'])) {
                $errors[] = 'Invalid role selected.';
            }

            if (!in_array($gender, ['Male', 'Female', 'Other'])) {
                $errors[] = 'Invalid gender selected.';
            }

            // Check for existing username/email
            if (empty($errors)) {
                try {
                    if ($this->userModel->usernameExists($username)) {
                        $errors[] = 'Username already exists.';
                    } elseif ($this->userModel->emailExists($email)) {
                        $errors[] = 'Email already exists.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database error during signup validation. Please try again later.';
                    error_log("AuthController signup validation error: " . $e->getMessage());
                }
            }

            if (empty($errors)) {
                try {
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Prepare data array for the model
                    $new_user_data = [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'ph_no' => $ph_no,
                        'username' => $username,
                        'password' => $hashed_password, // Store the hashed password
                        'status' => 1, // Default active status
                        'role' => $role,
                        'gender' => $gender
                    ];

                    // Attempt to create the user using the model
                    $new_user_id = $this->userModel->create($new_user_data);

                    if ($new_user_id) {
                        $success_message = 'Registration successful! You can now log in.';
                        // Check if this is an API call (expects JSON response)
                        $is_api_call = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;

                        if ($is_api_call) {
                            // For API calls, return JSON success response
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success' => true,
                                'message' => $success_message,
                                'data' => ['user_id' => $new_user_id]
                            ]);
                            exit;
                        } else {
                            // For regular form submissions, redirect to login or show success on signup page
                            // header("Location: ../views/auth/login.php?success=registered"); // Optional redirect
                            // exit;
                        }
                    } else {
                        $error_message = 'Failed to create user. Please try again.';
                    }
                } catch (PDOException $e) {
                    // --- TEMPORARY DEBUGGING: Log the full error message ---
                    $error_message = 'Database error during login: ' . $e->getMessage(); // TEMPORARY: Show full error
                    error_log("AuthController login error (Detailed): " . $e->getMessage()); // Log the detailed error
                    // --- END TEMPORARY DEBUGGING ---
                    // In production, revert to a generic message:
                    // $error_message = 'Database error during login. Please try again later.';
                    // error_log("AuthController login error: " . $e->getMessage());
                }
            } else {
                $error_message = implode('<br>', $errors);
            }
        }

        // Load the signup view
        // Check if this is an API call (expects JSON response)
        $is_api_call = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;

        if ($is_api_call) {
            // For API calls, return JSON error response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $error_message ?: 'Invalid request method. Only POST is allowed.'
            ]);
            exit;
        } else {
            // For regular page requests, load the signup view
            $content = $this->getSignupContent($success_message, $error_message);
            include '../views/layouts/guest.php'; // Use the guest layout for signup page
        }
    }

    /**
     * Handles the logout process.
     * This method is called by logout.php and api/auth/logout.php
     */
    public function logout() {
        // Start the session (ensure it's active)
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Unset all session variables
        session_unset();

        // Destroy the session
        session_destroy();

        // Regenerate session ID for security (optional but recommended)
        // session_regenerate_id(true); // Not needed after destroy

        // Check if this is an API call (expects JSON response)
        $is_api_call = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;

        if ($is_api_call) {
            // For API calls, return JSON success response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Logout successful.',
                'data' => ['redirect_url' => '../views/auth/login.php'] // Suggest redirect URL to the client
            ]);
            exit;
        } else {
            // For regular page requests, redirect to the login page
            header("Location: ../views/auth/login.php");
            exit;
        }
    }

    // --- Helper Methods ---

    /**
     * Redirects the user to their role-specific dashboard.
     *
     * @param string $role The user's role ('admin', 'waiter', 'user').
     */
    private function redirectToDashboard($role) {
        $redirect_url = $this->getDashboardUrl($role);
        header("Location: " . $redirect_url);
        exit;
    }

    /**
     * Gets the URL for the user's role-specific dashboard.
     *
     * @param string $role The user's role ('admin', 'waiter', 'user').
     * @return string The dashboard URL.
     */
    private function getDashboardUrl($role) {
        switch ($role) {
            case 'admin':
                return '../views/dashboard/admin/index.php';
            case 'waiter':
                return '../views/dashboard/waiter/index.php';
            case 'user':
            default:
                return '../views/dashboard/user/index.php';
        }
    }

    /**
     * Generates the HTML content for the login page.
     *
     * @param string $error_message Any error message to display.
     * @return string The HTML content.
     */
    private function getLoginContent($error_message = '') {
        $html = "<h2>Login to Mess Management System</h2>";

        if ($error_message) {
            $html .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . htmlspecialchars($error_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        }

        $html .= "
            <form method='post' action='../controllers/AuthController.php?action=login'>
                <div class='mb-3'>
                    <label for='username' class='form-label'>Username or Email:</label>
                    <input type='text' class='form-control' id='username' name='username' value='" . htmlspecialchars($_POST['username'] ?? '') . "' required>
                </div>
                <div class='mb-3'>
                    <label for='password' class='form-label'>Password:</label>
                    <input type='password' class='form-control' id='password' name='password' required>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Login</button>
                </div>
            </form>
            <div class='text-center mt-3'>
                <a href='../views/auth/forgot_password.php'>Forgot Password?</a> |
                <a href='../views/auth/signup.php'>Sign Up</a>
            </div>
        ";

        return $html;
    }

    /**
     * Generates the HTML content for the signup page.
     *
     * @param string $success_message Any success message to display.
     * @param string $error_message Any error message to display.
     * @return string The HTML content.
     */
    private function getSignupContent($success_message = '', $error_message = '') {
        $html = "<h2>Sign Up for Mess Management System</h2>";

        if ($success_message) {
            $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        }
        if ($error_message) {
            $html .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>"; // Don't escape HTML for error messages as they might contain <br>
        }

        $html .= "
            <form method='post' action='../controllers/AuthController.php?action=signup'>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='first_name' class='form-label'>First Name:</label>
                        <input type='text' class='form-control' id='first_name' name='first_name' value='" . htmlspecialchars($_POST['first_name'] ?? '') . "' required>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='last_name' class='form-label'>Last Name:</label>
                        <input type='text' class='form-control' id='last_name' name='last_name' value='" . htmlspecialchars($_POST['last_name'] ?? '') . "' required>
                    </div>
                </div>
                <div class='mb-3'>
                    <label for='email' class='form-label'>Email:</label>
                    <input type='email' class='form-control' id='email' name='email' value='" . htmlspecialchars($_POST['email'] ?? '') . "' required>
                </div>
                 <div class='mb-3'>
                    <label for='ph_no' class='form-label'>Phone Number:</label>
                    <input type='tel' class='form-control' id='ph_no' name='ph_no' value='" . htmlspecialchars($_POST['ph_no'] ?? '') . "' maxlength='10' required>
                </div>
                <div class='mb-3'>
                    <label for='username' class='form-label'>Username:</label>
                    <input type='text' class='form-control' id='username' name='username' value='" . htmlspecialchars($_POST['username'] ?? '') . "' required>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='password' class='form-label'>Password:</label>
                        <input type='password' class='form-control' id='password' name='password' required>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='confirm_password' class='form-label'>Confirm Password:</label>
                        <input type='password' class='form-control' id='confirm_password' name='confirm_password' required>
                    </div>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='gender' class='form-label'>Gender:</label>
                        <select class='form-select' id='gender' name='gender' required>
                            <option value='' " . (($_POST['gender'] ?? '') === '' ? 'selected' : '') . ">Select Gender</option>
                            <option value='Male' " . (($_POST['gender'] ?? '') === 'Male' ? 'selected' : '') . ">Male</option>
                            <option value='Female' " . (($_POST['gender'] ?? '') === 'Female' ? 'selected' : '') . ">Female</option>
                            <option value='Other' " . (($_POST['gender'] ?? '') === 'Other' ? 'selected' : '') . ">Other</option>
                        </select>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='role' class='form-label'>Role:</label>
                        <select class='form-select' id='role' name='role'>
                            <option value='user' " . (($_POST['role'] ?? '') === 'user' ? 'selected' : '') . ">User</option>
                            <option value='waiter' " . (($_POST['role'] ?? '') === 'waiter' ? 'selected' : '') . ">Waiter</option>
                            <!-- Admin signup might be restricted in a real application -->
                            <option value='admin' " . (($_POST['role'] ?? '') === 'admin' ? 'selected' : '') . ">Admin</option>
                        </select>
                    </div>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Sign Up</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='../views/auth/login.php' class='btn btn-secondary'>Back to Login</a>
            </div>
        ";

        return $html;
    }
}

// --- Routing ---
// This part handles direct access to the controller file (e.g., via URL)
// It determines which method to call based on the 'action' parameter
$action = $_GET['action'] ?? '';

$authController = new AuthController();

// Call the appropriate method based on the action
switch ($action) {
    case 'login':
        $authController->login();
        break;
    case 'signup':
        $authController->signup();
        break;
    case 'logout':
        $authController->logout();
        break;
    default:
        // Default to login if action is invalid or not provided
        $authController->login();
        break;
}

?>