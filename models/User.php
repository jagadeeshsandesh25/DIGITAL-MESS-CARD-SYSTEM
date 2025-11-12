<?php
// models/User.php

/**
 * Class User
 *
 * Represents a user in the system.
 * Handles database interactions for the 'user' table.
 */
class User {
    // --- Database Connection & Table Name ---
    private $conn;
    private $table_name = "user"; // Match the table name from the schema

    // --- Database Columns as Properties ---
    // These properties map directly to the columns in the 'user' table
    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $ph_no; // Phone number (VARCHAR(10))
    public $username;
    public $password; // This will hold the *hashed* password
    public $status; // Tinyint(1) - 1 = Active, 0 = Inactive
    public $role; // ENUM('admin', 'waiter', 'user')
    public $gender; // ENUM('Male', 'Female', 'Other')
    public $created_at; // DATETIME

    /**
     * Constructor
     *
     * Initializes the User model with a database connection.
     *
     * @param PDO $db The database connection object.
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Find a user by their username or email.
     *
     * This method queries the 'user' table to find a record where the
     * 'username' or 'email' column matches the provided identifier.
     * It returns the user data as an associative array if found, or false otherwise.
     * Crucially, it selects ALL relevant columns including 'password', 'role', and 'status'.
     *
     * @param string $username_or_email The username or email to search for.
     * @return array|false The user data array or false if not found.
     */
    public function findByUsernameOrEmail($username_or_email) {
        // Prepare the SQL query to select user data by username or email
        // Explicitly list columns to ensure we get the password hash and role
        $query = "SELECT id, first_name, last_name, email, ph_no, username, password, status, role, gender, created_at FROM " . $this->table_name . " WHERE username = :identifier OR email = :identifier LIMIT 1";

        // Prepare the statement
        $stmt = $this->conn->prepare($query);

        // Bind the parameter
        $stmt->bindParam(':identifier', $username_or_email);

        // Execute the query
        $stmt->execute();

        // Fetch the result as an associative array
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find all users, ordered by creation time (newest first).
     *
     * This method queries the 'user' table to retrieve all user records.
     * It returns an array of user data arrays, or false on failure.
     *
     * @param string $orderBy The column to order by (default: 'created_at').
     * @param string $orderDir The order direction (ASC/DESC, default: 'DESC').
     * @return array|false An array of user data arrays or false on failure.
     */
    public function findAll($orderBy = 'created_at', $orderDir = 'DESC') {
        // Prepare the SQL query to select all users
        $query = "SELECT id, first_name, last_name, email, ph_no, username, status, role, gender, created_at FROM " . $this->table_name . " ORDER BY " . $orderBy . " " . $orderDir;

        // Prepare the statement
        $stmt = $this->conn->prepare($query);

        // Execute the query
        $stmt->execute();

        // Fetch all results as an associative array
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a user by their ID.
     *
     * This method queries the 'user' table to find a record by its primary key (id).
     * It returns the user data as an associative array if found, or false otherwise.
     *
     * @param int $id The user ID to search for.
     * @return array|false The user data array or false if not found.
     */
    public function findById($id) {
        // Prepare the SQL query to select user data by ID
        $query = "SELECT id, first_name, last_name, email, ph_no, username, status, role, gender, created_at FROM " . $this->table_name . " WHERE id = :id LIMIT 1";

        // Prepare the statement
        $stmt = $this->conn->prepare($query);

        // Bind the parameter
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        // Execute the query
        $stmt->execute();

        // Fetch the result as an associative array
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new user.
     *
     * This method inserts a new user record into the 'user' table.
     * It expects an associative array with user data.
     * The password should be pre-hashed before calling this method.
     * It returns the new user's ID on success, or false on failure.
     *
     * @param array $data Associative array containing user data.
     *                    Expected keys: first_name, last_name, email, ph_no, username, password, status, role, gender.
     * @return int|false The new user ID or false on failure.
     */
    public function create($data) {
        // Prepare the SQL query to insert a new user
        $query = "INSERT INTO " . $this->table_name . " (first_name, last_name, email, ph_no, username, password, status, role, gender, created_at) VALUES (:first_name, :last_name, :email, :ph_no, :username, :password, :status, :role, :gender, :created_at)";

        // Prepare the statement
        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':last_name', $data['last_name']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':ph_no', $data['ph_no']); // Phone number is VARCHAR(10)
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':password', $data['password']); // This should be the *hashed* password
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT); // Bind as integer (0 or 1)
        $stmt->bindParam(':role', $data['role']);
        $stmt->bindParam(':gender', $data['gender']);
        $stmt->bindParam(':created_at', $data['created_at']); // Should be a valid datetime string

        try {
            // Execute the query
            if ($stmt->execute()) {
                // Return the ID of the newly inserted user
                return $this->conn->lastInsertId();
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("User create error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }

        // Return false if the insertion failed
        return false;
    }

    /**
     * Update an existing user.
     *
     * This method updates an existing user record in the 'user' table.
     * It expects an associative array with user data, including the 'id'.
     * The password should be pre-hashed before calling this method if it's being updated.
     * It returns true on success, or false on failure.
     *
     * @param array $data Associative array containing user data to update.
     *                    Must include 'id'. Other keys: first_name, last_name, email, ph_no, username, password, status, role, gender.
     * @return bool True on success, false on failure.
     */
    public function update($data) {
        // Prepare the SQL query to update an existing user
        // Note: Updating password_hash, username, or email might require special handling or validation
        $query = "UPDATE " . $this->table_name . " SET first_name = :first_name, last_name = :last_name, email = :email, ph_no = :ph_no, username = :username, password = :password, status = :status, role = :role, gender = :gender, created_at = :created_at WHERE id = :id";

        // Prepare the statement
        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':last_name', $data['last_name']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':ph_no', $data['ph_no']); // Phone number is VARCHAR(10)
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':password', $data['password']); // This should be the *hashed* password if updated
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT); // Bind as integer (0 or 1)
        $stmt->bindParam(':role', $data['role']);
        $stmt->bindParam(':gender', $data['gender']);
        $stmt->bindParam(':created_at', $data['created_at']); // Should be a valid datetime string
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT); // Bind the user ID

        try {
            // Execute the query
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                // Return true if a row was actually updated
                return true;
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("User update error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }

        // Return false if the update failed or no rows were affected
        return false;
    }

    /**
     * Delete a user by their ID.
     *
     * This method deletes a user record from the 'user' table by its primary key (id).
     * It returns true on success, or false on failure.
     * WARNING: This might fail due to foreign key constraints if other tables depend on this user.
     *
     * @param int $id The user ID to delete.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        // Prepare the SQL query to delete a user
        // WARNING: This is likely to fail due to foreign key constraints unless cascade delete is set or related records are handled.
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id LIMIT 1"; // LIMIT 1 for safety

        // Prepare the statement
        $stmt = $this->conn->prepare($query);

        // Bind the parameter
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        try {
            // Execute the query
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                // Return true if a row was actually deleted
                return true;
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            // This might fail due to foreign key constraints
            error_log("User delete error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }

        // Return false if the deletion failed or no rows were affected
        return false;
    }

    /**
     * Check if a username already exists.
     *
     * This method queries the 'user' table to check if a given username is already taken.
     * It returns true if the username exists, false otherwise.
     *
     * @param string $username The username to check.
     * @return bool True if username exists, false otherwise.
     */
    public function usernameExists($username) {
        // Prepare the SQL query to check for existing username
        $query = "SELECT id FROM " . $this->table_name . " WHERE username = :username LIMIT 1";

        // Prepare the statement
        $stmt = $this->conn->prepare($query);

        // Bind the parameter
        $stmt->bindParam(':username', $username);

        // Execute the query
        $stmt->execute();

        // Check if any rows were returned
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if an email already exists.
     *
     * This method queries the 'user' table to check if a given email is already taken.
     * It returns true if the email exists, false otherwise.
     *
     * @param string $email The email to check.
     * @return bool True if email exists, false otherwise.
     */
    public function emailExists($email) {
        // Prepare the SQL query to check for existing email
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";

        // Prepare the statement
        $stmt = $this->conn->prepare($query);

        // Bind the parameter
        $stmt->bindParam(':email', $email);

        // Execute the query
        $stmt->execute();

        // Check if any rows were returned
        return $stmt->rowCount() > 0;
    }

    // Add other methods as needed, e.g., findByRole($role), findActiveUsers(), etc.
}
?>