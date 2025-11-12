<?php
// models/Transaction.php

class Transaction {
    private $conn;
    private $table_name = "transactions"; // Match the table name from the schema

    // Database columns
    public $id;
    public $user_id; // FK to user
    public $t_time; // Timestamp
    public $t_type; // Enum: Card, Cash, UPI
    public $card_id; // FK to card
    public $recharge_id; // FK to recharge

    public function __construct($db) {
        $this->conn = $db;
    }

    // Method to find all transactions, joining with user, card, and recharge for context
    public function findAll($orderBy = 't_time', $orderDir = 'DESC') {
        $query = "
            SELECT t.id, t.user_id, u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
                   t.t_time, t.t_type,
                   t.card_id, c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   t.recharge_id, r.r_time as recharge_time, r.r_type as recharge_type
            FROM " . $this->table_name . " t
            LEFT JOIN user u ON t.user_id = u.id
            LEFT JOIN card c ON t.card_id = c.id
            LEFT JOIN recharge r ON t.recharge_id = r.id
            ORDER BY t." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to find a transaction by its ID, joining with user, card, and recharge for full context
    public function findById($id) {
        $query = "
            SELECT t.id, t.user_id, u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name, u.email as user_email, u.ph_no as user_ph_no,
                   t.t_time, t.t_type,
                   t.card_id, c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   t.recharge_id, r.r_time as recharge_time, r.r_type as recharge_type, r.user_id as recharge_user_id
            FROM " . $this->table_name . " t
            LEFT JOIN user u ON t.user_id = u.id
            LEFT JOIN card c ON t.card_id = c.id
            LEFT JOIN recharge r ON t.recharge_id = r.id
            WHERE t.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Method to find transactions by user ID
    public function findByUserId($user_id, $orderBy = 't_time', $orderDir = 'DESC') {
        $query = "
            SELECT t.id, t.user_id, u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
                   t.t_time, t.t_type,
                   t.card_id, c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   t.recharge_id, r.r_time as recharge_time, r.r_type as recharge_type
            FROM " . $this->table_name . " t
            LEFT JOIN user u ON t.user_id = u.id
            LEFT JOIN card c ON t.card_id = c.id
            LEFT JOIN recharge r ON t.recharge_id = r.id
            WHERE t.user_id = :user_id
            ORDER BY t." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to find transactions by card ID
    public function findByCardId($card_id, $orderBy = 't_time', $orderDir = 'DESC') {
        $query = "
            SELECT t.id, t.user_id, u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
                   t.t_time, t.t_type,
                   t.card_id, c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   t.recharge_id, r.r_time as recharge_time, r.r_type as recharge_type
            FROM " . $this->table_name . " t
            LEFT JOIN user u ON t.user_id = u.id
            LEFT JOIN card c ON t.card_id = c.id
            LEFT JOIN recharge r ON t.recharge_id = r.id
            WHERE t.card_id = :card_id
            ORDER BY t." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to find transactions by recharge ID
    public function findByRechargeId($recharge_id, $orderBy = 't_time', $orderDir = 'DESC') {
        $query = "
            SELECT t.id, t.user_id, u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
                   t.t_time, t.t_type,
                   t.card_id, c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   t.recharge_id, r.r_time as recharge_time, r.r_type as recharge_type
            FROM " . $this->table_name . " t
            LEFT JOIN user u ON t.user_id = u.id
            LEFT JOIN card c ON t.card_id = c.id
            LEFT JOIN recharge r ON t.recharge_id = r.id
            WHERE t.recharge_id = :recharge_id
            ORDER BY t." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':recharge_id', $recharge_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to create a new transaction
    // Note: Creating a transaction often involves linking to existing user, card, and potentially recharge.
    // It might also involve updating card balances or other related tables.
    // This method simply inserts the record with the provided data.
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " (user_id, t_time, t_type, card_id, recharge_id) VALUES (:user_id, :t_time, :t_type, :card_id, :recharge_id)";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':t_time', $data['t_time']); // Should be a valid datetime string
        $stmt->bindParam(':t_type', $data['t_type']);
        $stmt->bindParam(':card_id', $data['card_id'], PDO::PARAM_INT);
        $stmt->bindParam(':recharge_id', $data['recharge_id'], PDO::PARAM_INT); // Can be NULL if not linked to a specific recharge record at creation

        try {
            if ($stmt->execute()) {
                return $this->conn->lastInsertId(); // Return the new transaction's ID on success
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Transaction create error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure
    }

    // Method to update an existing transaction (Less common for financial records, but included for completeness)
    // Be extremely careful with updates to financial data.
    public function update($data) {
        // Typically, only non-financial metadata might be updated, or status.
        // Let's assume we only allow updating t_type and t_time for this example.
        $query = "UPDATE " . $this->table_name . " SET t_type = :t_type, t_time = :t_time WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':t_type', $data['t_type']);
        $stmt->bindParam(':t_time', $data['t_time']); // Allow updating time, though unusual
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);

        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return true; // Indicate success and that a row was updated
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Transaction update error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows updated
    }

    // Method to delete a transaction by its ID (Often restricted for financial records)
    // Be extremely careful with deleting financial records.
    public function delete($id) {
        // WARNING: Deleting a transaction might break referential integrity if other tables depend on it (e.g., user_details_tabel, tabels).
        // Ensure cascading deletes are handled correctly in the database schema or handle dependencies before deletion.
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
            error_log("Transaction delete error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows deleted
    }

    // Add other methods as needed, e.g., findRecent($limit), findByType($t_type), etc.
}
?>