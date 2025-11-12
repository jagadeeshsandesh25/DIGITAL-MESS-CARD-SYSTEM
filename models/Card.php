<?php
// models/Card.php

class Card {
    private $conn;
    private $table_name = "card"; // Match the table name from the schema

    // Database columns
    public $id;
    public $c_status;
    public $user_id; // Foreign Key to user
    public $recharge_details; // Foreign Key to recharge
    public $created_at;
    public $expired_at;
    public $balance_credits;
    public $total_credits;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Method to find all cards, potentially joining with user for owner info
    public function findAll($orderBy = 'created_at', $orderDir = 'DESC') {
        $query = "
            SELECT c.id, c.c_status, c.user_id, u.first_name, u.last_name, u.username, c.balance_credits, c.total_credits, c.created_at, c.expired_at
            FROM " . $this->table_name . " c
            LEFT JOIN user u ON c.user_id = u.id
            ORDER BY c." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to find a card by its ID, joining with user for owner info
    public function findById($id) {
        $query = "
            SELECT c.id, c.c_status, c.user_id, u.first_name, u.last_name, u.username, c.recharge_details, c.created_at, c.expired_at, c.balance_credits, c.total_credits
            FROM " . $this->table_name . " c
            LEFT JOIN user u ON c.user_id = u.id
            WHERE c.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Method to create a new card
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " (c_status, user_id, recharge_details, balance_credits, total_credits, expired_at) VALUES (:c_status, :user_id, :recharge_details, :balance_credits, :total_credits, :expired_at)";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':c_status', $data['c_status']);
        $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT); // Assuming user_id is provided and valid
        $stmt->bindParam(':recharge_details', $data['recharge_details'], PDO::PARAM_INT); // Can be NULL initially if no recharge yet
        $stmt->bindParam(':balance_credits', $data['balance_credits'], PDO::PARAM_INT);
        $stmt->bindParam(':total_credits', $data['total_credits'], PDO::PARAM_INT);
        $stmt->bindParam(':expired_at', $data['expired_at']); // Can be NULL

        try {
            if ($stmt->execute()) {
                return $this->conn->lastInsertId(); // Return the new card's ID on success
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Card create error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure
    }

    // Method to update an existing card
    public function update($data) {
        $query = "UPDATE " . $this->table_name . " SET c_status = :c_status, balance_credits = :balance_credits, total_credits = :total_credits, expired_at = :expired_at WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':c_status', $data['c_status']);
        $stmt->bindParam(':balance_credits', $data['balance_credits'], PDO::PARAM_INT);
        $stmt->bindParam(':total_credits', $data['total_credits'], PDO::PARAM_INT);
        $stmt->bindParam(':expired_at', $data['expired_at']); // Can be NULL
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);

        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return true; // Indicate success and that a row was updated
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Card update error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows updated
    }

    // Method to delete a card by its ID
    public function delete($id) {
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
            error_log("Card delete error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows deleted
    }

    // Add other methods as needed, e.g., findActiveCards(), findExpiredCards(), etc.
}
?>