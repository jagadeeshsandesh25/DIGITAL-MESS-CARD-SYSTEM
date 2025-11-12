<?php
// models/Feedback.php

class Feedback {
    private $conn;
    private $table_name = "feedback"; // Match the table name from the schema

    // Database columns
    public $id;
    public $user_id; // FK to user
    public $message; // Text
    public $rating; // Int (1-5) or NULL
    public $created_at; // Timestamp

    public function __construct($db) {
        $this->conn = $db;
    }

    // Method to find all feedback, joining with user for context
    public function findAll($orderBy = 'created_at', $orderDir = 'DESC') {
        $query = "
            SELECT f.id, f.user_id, u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
                   f.message, f.rating, f.created_at
            FROM " . $this->table_name . " f
            LEFT JOIN user u ON f.user_id = u.id
            ORDER BY f." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to find feedback by its ID, joining with user for full context
    public function findById($id) {
        $query = "
            SELECT f.id, f.user_id, u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name, u.email as user_email,
                   f.message, f.rating, f.created_at
            FROM " . $this->table_name . " f
            LEFT JOIN user u ON f.user_id = u.id
            WHERE f.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

     // Method to find feedback by user ID
    public function findByUserId($user_id) {
        $query = "
            SELECT f.id, f.user_id, u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
                   f.message, f.rating, f.created_at
            FROM " . $this->table_name . " f
            LEFT JOIN user u ON f.user_id = u.id
            WHERE f.user_id = :user_id
            ORDER BY f.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to create a new feedback record
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " (user_id, message, rating, created_at) VALUES (:user_id, :message, :rating, :created_at)";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':message', $data['message']);
        $stmt->bindParam(':rating', $data['rating'], PDO::PARAM_INT); // Can be NULL
        $stmt->bindParam(':created_at', $data['created_at']); // Should be a valid datetime string, e.g., date('Y-m-d H:i:s')

        try {
            if ($stmt->execute()) {
                return $this->conn->lastInsertId(); // Return the new feedback's ID on success
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Feedback create error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure
    }

    // Method to update an existing feedback record (Less common, but included for completeness)
    // Be careful with updates to user feedback.
    public function update($data) {
        // Typically, only the message or rating might be updated by the user/admin.
        // Let's assume we only allow updating message and rating.
        $query = "UPDATE " . $this->table_name . " SET message = :message, rating = :rating WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':message', $data['message']);
        $stmt->bindParam(':rating', $data['rating'], PDO::PARAM_INT); // Can be NULL
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);

        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return true; // Indicate success and that a row was updated
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Feedback update error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows updated
    }

    // Method to delete a feedback record by its ID (Often restricted for data retention)
    public function delete($id) {
        // WARNING: Deleting user feedback might violate data retention policies or break referential integrity if other tables depend on it.
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
            error_log("Feedback delete error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows deleted
    }

    // Add other methods as needed, e.g., getAverageRating(), getFeedbackByRating($rating), etc.
}
?>