<?php
// controllers/UserController.php

require_once '../config/database.php'; // Adjust path based on where controllers are called from
require_once '../models/User.php'; // Adjust path based on where controllers are called from
require_once '../includes/auth.php'; // Adjust path to include authentication checks

class UserController {

    private $database;
    private $db;
    private $userModel;

    public function __construct() {
        $this->database = new Database();
        $this->db = $this->database->getConnection();
        $this->userModel = new User($this->db); // Pass the database connection to the model
    }

    public function index() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            // Optionally, set an error message and redirect
            // $_SESSION['error'] = "Access denied. Admin privileges required.";
            header("Location: ../views/dashboard/index.php"); // Redirect non-admins
            exit;
        }

        $users = [];
        $error_message = '';

        try {
            // Fetch all users from the model
            $query = "SELECT id, first_name, last_name, email, ph_no, username, status, role, gender, created_at FROM user ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error_message = "Could not load users. Please try again later.";
            error_log("UserController index query error: " . $e->getMessage());
        }

        // Prepare the content for the index view using a helper
        $content = $this->getIndexContent($users, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function create() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $success_message = '';
        $error_message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $ph_no = trim($_POST['ph_no'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $status = isset($_POST['status']) ? 1 : 0; // Checkbox for active status

            // Basic validation
            $errors = [];
            if (empty($first_name) || empty($last_name) || empty($email) || empty($ph_no) || empty($username) || empty($password) || empty($confirm_password) || empty($gender)) {
                $errors[] = "All fields are required.";
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format.";
            }

            if (strlen($ph_no) !== 10 || !is_numeric($ph_no)) {
                $errors[] = "Phone number must be 10 digits.";
            }

            if ($password !== $confirm_password) {
                $errors[] = "Passwords do not match.";
            }

            if (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters long.";
            }

            if (!in_array($role, ['admin', 'waiter', 'user'])) {
                $errors[] = "Invalid role selected.";
            }

            if (!in_array($gender, ['Male', 'Female', 'Other'])) {
                $errors[] = "Invalid gender selected.";
            }

            if (empty($errors)) {
                // Check if username or email already exists using the model
                if ($this->userModel->usernameExists($username)) {
                    $error_message = "Username already exists.";
                } elseif ($this->userModel->emailExists($email)) {
                    $error_message = "Email already exists.";
                } else {
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Prepare data array
                    $new_user_data = [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'ph_no' => $ph_no,
                        'username' => $username,
                        'password' => $hashed_password, // Store the hashed password
                        'status' => $status, // Use the value from the checkbox
                        'role' => $role,
                        'gender' => $gender
                    ];

                    // Attempt to create the user using the model
                    if ($this->userModel->create($new_user_data)) {
                        $success_message = "User created successfully!";
                        // Optionally, redirect to the users list page after successful creation to prevent re-submission on refresh
                        // header("Location: index.php"); // Adjust path
                        // exit;
                    } else {
                        $error_message = "Failed to create user. Please try again.";
                    }
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }

        // Load the create user view
        $content = $this->getCreateContent($success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function edit() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $user_id_to_edit = $_GET['id'] ?? null;
        if (!$user_id_to_edit) {
            // Handle missing ID, maybe redirect or show error
             header("Location: index.php"); // Redirect to list if no ID
             exit;
        }

        $user_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific user's details
        try {
            $query = "SELECT id, first_name, last_name, email, ph_no, username, status, role, gender FROM user WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $user_id_to_edit);
            $stmt->execute();

            $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user_details) {
                $error_message = "User not found.";
            }
        } catch (PDOException $e) {
            $error_message = "Could not load user details. Please try again later.";
            error_log("UserController edit fetch query error: " . $e->getMessage());
        }

        // Process form submission if user details were found
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_details) {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $ph_no = trim($_POST['ph_no'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $new_password = $_POST['new_password'] ?? ''; // Optional new password
            $confirm_new_password = $_POST['confirm_new_password'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $status = isset($_POST['status']) ? 1 : 0; // Checkbox for active status

            // Basic validation (similar to create, but password is optional here)
            $errors = [];
            if (empty($first_name) || empty($last_name) || empty($email) || empty($ph_no) || empty($username) || empty($gender)) {
                $errors[] = "All required fields (except password) must be filled.";
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format.";
            }

            if (strlen($ph_no) !== 10 || !is_numeric($ph_no)) {
                $errors[] = "Phone number must be 10 digits.";
            }

            if (!empty($new_password) || !empty($confirm_new_password)) { // Only validate if a new password was entered
                if ($new_password !== $confirm_new_password) {
                    $errors[] = "New passwords do not match.";
                }
                if (strlen($new_password) < 6) {
                    $errors[] = "New password must be at least 6 characters long.";
                }
            }

            if (!in_array($role, ['admin', 'waiter', 'user'])) {
                $errors[] = "Invalid role selected.";
            }

            if (!in_array($gender, ['Male', 'Female', 'Other'])) {
                $errors[] = "Invalid gender selected.";
            }

            if (empty($errors)) {
                try {
                    // Check if username or email already exists for *another* user
                    $check_query = "SELECT id FROM user WHERE (username = :username OR email = :email) AND id != :current_id LIMIT 1";
                    $check_stmt = $this->db->prepare($check_query);
                    $check_stmt->bindParam(':username', $username);
                    $check_stmt->bindParam(':email', $email);
                    $check_stmt->bindParam(':current_id', $user_id_to_edit); // Exclude current user ID
                    $check_stmt->execute();

                    if ($check_stmt->rowCount() > 0) {
                        $error_message = "Username or Email already exists for another user.";
                    } else {
                        $hashed_password_sql_part = '';
                        $params_for_update = [
                            ':first_name' => $first_name,
                            ':last_name' => $last_name,
                            ':email' => $email,
                            ':ph_no' => $ph_no,
                            ':username' => $username,
                            ':status' => $status,
                            ':role' => $role,
                            ':gender' => $gender,
                            ':id' => $user_id_to_edit
                        ];

                        // If a new password was provided, hash it and add to the update query
                        if (!empty($new_password)) {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $hashed_password_sql_part = ", password = :password";
                            $params_for_update[':password'] = $hashed_password;
                        }

                        // Prepare SQL query to update the user
                        $update_query = "UPDATE user SET first_name = :first_name, last_name = :last_name, email = :email, ph_no = :ph_no, username = :username, status = :status, role = :role, gender = :gender " . $hashed_password_sql_part . " WHERE id = :id";
                        $update_stmt = $this->db->prepare($update_query);

                        if ($update_stmt->execute($params_for_update)) {
                            $success_message = "User updated successfully!";
                            // Optionally, refetch the user details to show updated info
                             $stmt->execute(); // Re-execute the fetch query
                             $user_details = $stmt->fetch(PDO::FETCH_ASSOC); // Update the local variable
                        } else {
                            $error_message = "Failed to update user. Please try again.";
                        }
                    }
                } catch (PDOException $e) {
                    $error_message = "Database error. Please try again later.";
                    error_log("UserController edit update error: " . $e->getMessage());
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }


        // Load the edit user view
        $content = $this->getEditContent($user_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function delete() {
        // Check if user is logged in and is an admin
        requireLogin();
        if (!isAdmin()) {
            header("Location: ../views/dashboard/index.php");
            exit;
        }

        $user_id_to_delete = $_GET['id'] ?? null;
        if (!$user_id_to_delete) {
            // Handle missing ID, maybe redirect or show error
             header("Location: index.php"); // Redirect to list if no ID
             exit;
        }

        $user_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific user's details for confirmation
        try {
            $query = "SELECT id, username FROM user WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $user_id_to_delete);
            $stmt->execute();

            $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user_details) {
                $error_message = "User not found.";
            }
        } catch (PDOException $e) {
            $error_message = "Could not load user details for deletion. Please try again later.";
            error_log("UserController delete fetch query error: " . $e->getMessage());
        }

        // Process deletion if confirmed via POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_details && isset($_POST['confirm_delete'])) {
            try {
                // Prepare SQL query to delete the user record
                // WARNING: Deleting a user might have cascading effects depending on your foreign key constraints!
                // Ensure related data (cards, transactions, etc.) is handled appropriately (deleted or orphaned safely).
                $delete_query = "DELETE FROM user WHERE id = :id LIMIT 1"; // LIMIT 1 for safety
                $delete_stmt = $this->db->prepare($delete_query);
                $delete_stmt->bindParam(':id', $user_id_to_delete);

                if ($delete_stmt->execute() && $delete_stmt->rowCount() > 0) {
                    $success_message = "User deleted successfully!";
                    // Optionally, redirect to the users list page after successful deletion
                    // header("Location: index.php?success=deleted");
                    // exit;
                } else {
                    // This could mean the row didn't exist or a constraint prevented deletion
                    $error_message = "Failed to delete user. It might not exist or be linked to other records that prevent deletion.";
                }
            } catch (PDOException $e) {
                $error_message = "Database error during deletion. Please try again later.";
                error_log("UserController delete error: " . $e->getMessage());
            }
        }


        // Load the delete user confirmation view
        $content = $this->getDeleteContent($user_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    // --- Helper Methods to Generate View Content ---

    private function getIndexContent($users, $error_message = '') {
        $html = "<h2>Manage Users</h2><p>View, edit, and manage all registered users.</p>";

        if ($error_message) {
            $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
        } else {
            // Add a button to create a new user
            $html .= "
            <div class='mb-3'>
                <a href='UserController.php?action=create' class='btn btn-success'>Add New User</a>
            </div>
            ";

            // Check if users exist
            if (!empty($users)) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Gender</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";

                foreach ($users as $user) {
                    $status_badge = $user['status'] == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($user['id']) . "</td>
                                <td>" . htmlspecialchars($user['first_name']) . " " . htmlspecialchars($user['last_name']) . "</td>
                                <td>" . htmlspecialchars($user['username']) . "</td>
                                <td>" . htmlspecialchars($user['email']) . "</td>
                                <td>" . htmlspecialchars($user['ph_no']) . "</td>
                                <td>" . htmlspecialchars($user['role']) . "</td>
                                <td>" . $status_badge . "</td>
                                <td>" . htmlspecialchars($user['gender']) . "</td>
                                <td>" . htmlspecialchars($user['created_at']) . "</td>
                                <td>
                                    <a href='UserController.php?action=view&id=" . $user['id'] . "' class='btn btn-sm btn-info'>View</a>
                                    <a href='UserController.php?action=edit&id=" . $user['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                    <a href='UserController.php?action=delete&id=" . $user['id'] . "' class='btn btn-sm btn-danger'>Delete</a>
                                </td>
                            </tr>
                    ";
                }

                $html .= "
                        </tbody>
                    </table>
                </div>
                ";
            } else {
                $html .= "<p>No users found.</p>";
            }
        }
        return $html;
    }

    private function getCreateContent($success_message = '', $error_message = '') {
        $html = "<h2>Create New User</h2><p>Add a new user account to the system.</p>";

        if ($success_message) {
            $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        }
        if ($error_message) {
            $html .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        }

        $html .= "
            <form method='post' action='../controllers/UserController.php?action=create'>
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
                            <option value='admin' " . (($_POST['role'] ?? '') === 'admin' ? 'selected' : '') . ">Admin</option>
                        </select>
                    </div>
                </div>
                <div class='mb-3 form-check'>
                    <input type='checkbox' class='form-check-input' id='status' name='status' value='1' " . ((isset($_POST['status']) && $_POST['status'] == 1) ? 'checked' : '') . ">
                    <label class='form-check-label' for='status'>Active Status</label>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Create User</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='UserController.php?action=index' class='btn btn-secondary'>Back to Users List</a>
            </div>
        ";

        return $html;
    }

     private function getEditContent($user_details, $success_message = '', $error_message = '') {
        $html = "<h2>Edit User</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='UserController.php?action=index' class='btn btn-secondary'>Back to Users List</a>";
        } elseif ($user_details) {
            $html .= "<p>Editing details for user: <strong>" . htmlspecialchars($user_details['username']) . "</strong></p>";

            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $status_checked = $user_details['status'] ? 'checked' : '';

            $html .= "
            <form method='post' action='../controllers/UserController.php?action=edit&id=" . $user_details['id'] . "'>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='first_name' class='form-label'>First Name:</label>
                        <input type='text' class='form-control' id='first_name' name='first_name' value='" . htmlspecialchars($user_details['first_name']) . "' required>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='last_name' class='form-label'>Last Name:</label>
                        <input type='text' class='form-control' id='last_name' name='last_name' value='" . htmlspecialchars($user_details['last_name']) . "' required>
                    </div>
                </div>
                <div class='mb-3'>
                    <label for='email' class='form-label'>Email:</label>
                    <input type='email' class='form-control' id='email' name='email' value='" . htmlspecialchars($user_details['email']) . "' required>
                </div>
                 <div class='mb-3'>
                    <label for='ph_no' class='form-label'>Phone Number:</label>
                    <input type='tel' class='form-control' id='ph_no' name='ph_no' value='" . htmlspecialchars($user_details['ph_no']) . "' maxlength='10' required>
                </div>
                <div class='mb-3'>
                    <label for='username' class='form-label'>Username:</label>
                    <input type='text' class='form-control' id='username' name='username' value='" . htmlspecialchars($user_details['username']) . "' required>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='new_password' class='form-label'>(Optional) New Password:</label>
                        <input type='password' class='form-control' id='new_password' name='new_password'>
                        <small class='form-text text-muted'>Leave blank to keep the current password.</small>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='confirm_new_password' class='form-label'>(Optional) Confirm New Password:</label>
                        <input type='password' class='form-control' id='confirm_new_password' name='confirm_new_password'>
                    </div>
                </div>
                <div class='row'>
                    <div class='col-md-6 mb-3'>
                        <label for='gender' class='form-label'>Gender:</label>
                        <select class='form-select' id='gender' name='gender' required>
                            <option value='Male' " . ($user_details['gender'] === 'Male' ? 'selected' : '') . ">Male</option>
                            <option value='Female' " . ($user_details['gender'] === 'Female' ? 'selected' : '') . ">Female</option>
                            <option value='Other' " . ($user_details['gender'] === 'Other' ? 'selected' : '') . ">Other</option>
                        </select>
                    </div>
                    <div class='col-md-6 mb-3'>
                        <label for='role' class='form-label'>Role:</label>
                        <select class='form-select' id='role' name='role'>
                            <option value='user' " . ($user_details['role'] === 'user' ? 'selected' : '') . ">User</option>
                            <option value='waiter' " . ($user_details['role'] === 'waiter' ? 'selected' : '') . ">Waiter</option>
                            <option value='admin' " . ($user_details['role'] === 'admin' ? 'selected' : '') . ">Admin</option>
                        </select>
                    </div>
                </div>
                <div class='mb-3 form-check'>
                    <input type='checkbox' class='form-check-input' id='status' name='status' value='1' " . $status_checked . ">
                    <label class='form-check-label' for='status'>Active Status</label>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Update User</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='UserController.php?action=index' class='btn btn-secondary'>Back to Users List</a>
                <a href='UserController.php?action=view&id=" . $user_details['id'] . "' class='btn btn-info'>View User Details</a>
            </div>
            ";
        } else {
            // This case handles when the user ID was provided but the user wasn't found (error_message is set above)
            $html .= "<p>Unable to load user information.</p>";
            $html .= "<a href='UserController.php?action=index' class='btn btn-secondary'>Back to Users List</a>";
        }

        return $html;
    }

    private function getDeleteContent($user_details, $success_message = '', $error_message = '') {
        $html = "<h2>Delete User</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='UserController.php?action=index' class='btn btn-secondary'>Back to Users List</a>";
        } elseif ($user_details) {
            if ($success_message) {
                $html .= "<div class='alert alert-success' role='alert'>" . htmlspecialchars($success_message) . "</div>";
                $html .= "<a href='UserController.php?action=index' class='btn btn-secondary'>Back to Users List</a>";
            } else {
                $html .= "
                <div class='alert alert-warning' role='alert'>
                    <h5>Confirm Deletion</h5>
                    <p>Are you sure you want to delete the user '<strong>" . htmlspecialchars($user_details['username']) . "</strong>' (ID: " . $user_details['id'] . ")?</p>
                    <p><strong>This action cannot be undone.</strong></p>
                </div>
                <form method='post' action='../controllers/UserController.php?action=delete&id=" . $user_details['id'] . "'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($user_details['id']) . "'>
                    <input type='hidden' name='confirm_delete' value='1'>
                    <button type='submit' class='btn btn-danger'>Yes, Delete User</button>
                    <a href='UserController.php?action=index' class='btn btn-secondary'>Cancel</a>
                </form>
                ";
            }
        } else {
            // This case handles when the user ID was provided but the user wasn't found (error_message is set above)
            $html .= "<p>Unable to load user information for deletion.</p>";
            $html .= "<a href='UserController.php?action=index' class='btn btn-secondary'>Back to Users List</a>";
        }

        return $html;
    }

    // --- Placeholder for View method (optional, might just use index with a specific user filter or a dedicated view file) ---
    // public function view() {
    //     requireLogin();
    //     if (!isAdmin()) {
    //         header("Location: ../views/dashboard/index.php");
    //         exit;
    //     }
    //     // Fetch user details and pass to a view-specific content helper
    //     $user_id_to_view = $_GET['id'] ?? null;
    //     // ... fetch logic ...
    //     // $content = $this->getViewContent($user_details);
    //     // include '../views/layouts/app.php';
    // }

}

// --- Routing ---
$action = $_GET['action'] ?? '';

$userController = new UserController();

// Ensure only admin can access user management
if ($action !== 'index' && $action !== 'create' && $action !== 'edit' && $action !== 'delete' && $action !== 'view') {
    // Default or invalid action, redirect to index or show error
    $action = 'index';
}

// Call the appropriate method based on the action
switch ($action) {
    case 'index':
        $userController->index();
        break;
    case 'create':
        $userController->create();
        break;
    case 'edit':
        $userController->edit();
        break;
    case 'delete':
        $userController->delete();
        break;
    // case 'view': // Uncomment if view method is implemented
    //     $userController->view();
    //     break;
    default:
        // Should ideally not be reached due to the check above, but safe fallback
        $userController->index();
        break;
}

?>