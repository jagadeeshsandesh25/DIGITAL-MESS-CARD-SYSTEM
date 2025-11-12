<?php
// models/UserDetails.php

class UserDetails {
    private $conn;
    private $table_name = "user_details_tabel"; // Match the table name from the schema

    // Database columns
    // Note: user_id, card_id, transaction_id, recharge_id are FKs, but the table seems to store snapshots/details at a point in time
    public $id;
    public $user_id; // FK to user
    public $card_id; // FK to card
    public $transaction_id; // FK to transactions
    public $recharge_id; // FK to recharge
    public $present_pswd; // Varchar (likely hashed)
    public $previous_pswd; // Varchar (likely hashed)
    public $username; // Varchar (snapshot of username at time of record?)
    public $contact_details; // Varchar (snapshot of contact details?)
    public $created_at; // Datetime
    public $updated_at; // Datetime

    public function __construct($db) {
        $this->conn = $db;
    }

    // Method to find all user details records, joining with user, card, transaction, and recharge for context
    public function findAll($orderBy = 'created_at', $orderDir = 'DESC') {
        $query = "
            SELECT udt.id, udt.user_id, u.first_name as user_first_name, u.last_name as user_last_name, u.username as user_username,
                   udt.card_id, c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   udt.transaction_id, t.t_time as transaction_time, t.t_type as transaction_type,
                   udt.recharge_id, r.r_time as recharge_time, r.r_type as recharge_type,
                   udt.username as udt_username, udt.contact_details, udt.created_at, udt.updated_at
            FROM " . $this->table_name . " udt
            LEFT JOIN user u ON udt.user_id = u.id
            LEFT JOIN card c ON udt.card_id = c.id
            LEFT JOIN transactions t ON udt.transaction_id = t.id
            LEFT JOIN recharge r ON udt.recharge_id = r.id
            ORDER BY udt." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to find a user details record by its ID, joining with user, card, transaction, and recharge for full context
    public function findById($id) {
        $query = "
            SELECT udt.id, udt.user_id, u.first_name as user_first_name, u.last_name as user_last_name, u.username as user_username, u.email as user_email, u.ph_no as user_ph_no,
                   udt.card_id, c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   udt.transaction_id, t.t_time as transaction_time, t.t_type as transaction_type,
                   udt.recharge_id, r.r_time as recharge_time, r.r_type as recharge_type,
                   udt.present_pswd, udt.previous_pswd, udt.username as udt_username, udt.contact_details, udt.created_at, udt.updated_at
            FROM " . $this->table_name . " udt
            LEFT JOIN user u ON udt.user_id = u.id
            LEFT JOIN card c ON udt.card_id = c.id
            LEFT JOIN transactions t ON udt.transaction_id = t.id
            LEFT JOIN recharge r ON udt.recharge_id = r.id
            WHERE udt.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Method to create a new user details record
    // Note: This table likely stores snapshots. Creating a record manually might not be standard.
    // It's often populated automatically during user creation, password changes, or linked actions.
    public function create($data) {
        // Fields like present_pswd and previous_pswd should ideally be pre-hashed before reaching the model.
        $query = "INSERT INTO " . $this->table_name . " (user_id, card_id, transaction_id, recharge_id, present_pswd, previous_pswd, username, contact_details, created_at, updated_at)
                  VALUES (:user_id, :card_id, :transaction_id, :recharge_id, :present_pswd, :previous_pswd, :username, :contact_details, :created_at, :updated_at)";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        // Intentionally not hashing passwords here, assuming they are pre-hashed
        $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':card_id', $data['card_id'], PDO::PARAM_INT);
        $stmt->bindParam(':transaction_id', $data['transaction_id'], PDO::PARAM_INT);
        $stmt->bindParam(':recharge_id', $data['recharge_id'], PDO::PARAM_INT);
        $stmt->bindParam(':present_pswd', $data['present_pswd']); // Pre-hashed
        $stmt->bindParam(':previous_pswd', $data['previous_pswd']); // Pre-hashed
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':contact_details', $data['contact_details']);
        $stmt->bindParam(':created_at', $data['created_at']); // Should be a valid datetime string
        $stmt->bindParam(':updated_at', $data['updated_at']); // Should be a valid datetime string

        try {
            if ($stmt->execute()) {
                return $this->conn->lastInsertId(); // Return the new record's ID on success
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("UserDetails create error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure
    }

    // Method to update an existing user details record
    // Note: Updating password hashes or linked IDs in a snapshot table is unusual and risky.
    // This is included for completeness but should be used with extreme caution.
    public function update($data) {
        // WARNING: Updating password hashes or FKs in a snapshot table breaks data integrity.
        // Only allow updating non-sensitive, non-FK fields like username/contact_details if absolutely necessary.
        // Let's assume we only allow updating username, contact_details, and updated_at.
        $query = "UPDATE " . $this->table_name . " SET username = :username, contact_details = :contact_details, updated_at = :updated_at WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':contact_details', $data['contact_details']);
        $stmt->bindParam(':updated_at', $data['updated_at']); // Should be a valid datetime string
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);

        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return true; // Indicate success and that a row was updated
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("UserDetails update error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows updated
    }

    // Method to delete a user details record by its ID
    // Note: Deleting historical/audit trail records is highly discouraged.
    public function delete($id) {
        // WARNING: Deleting records from this table might break audit trails or data lineage.
        // Ensure cascading deletes are handled correctly or dependencies are managed.
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id LIMIT 1"; // LIMIT 1 for safety

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return true; // Indicate success and that a row was deleted
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            // This might fail due to foreign key constraints
            error_log("UserDetails delete error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows deleted
    }

    // Add other methods as needed, e.g., findByUserId($user_id), findByCardId($card_id), findRecent($limit), etc.
}
?>