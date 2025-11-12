<?php
// models/WaiterOrder.php

class WaiterOrder {
    private $conn;
    private $table_name = "waiter_orders"; // Match the table name from the schema

    // Database columns
    public $id;
    public $waiter_id; // FK to user (where role = 'waiter')
    public $order_id;  // FK to tabels
    public $status;    // Enum: preparing, served, cancelled
    public $served_at;  // Timestamp

    public function __construct($db) {
        $this->conn = $db;
    }

    // Method to find all waiter orders, joining with user (waiter) and tabels (order) for context
    public function findAll($orderBy = 'id', $orderDir = 'ASC') {
        $query = "
            SELECT wo.id, wo.waiter_id, w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name,
                   wo.order_id, t.t_name as table_name, t.t_qr as table_qr, t.time as order_time, t.menu_ordered,
                   wo.status, wo.served_at
            FROM " . $this->table_name . " wo
            LEFT JOIN user w ON wo.waiter_id = w.id
            LEFT JOIN tabels t ON wo.order_id = t.id
            ORDER BY wo." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to find waiter orders by waiter ID, joining with user (waiter) and tabels (order) for context
    public function findByWaiterId($waiter_id, $orderBy = 'id', $orderDir = 'ASC') {
         $query = "
            SELECT wo.id, wo.waiter_id, w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name,
                   wo.order_id, t.t_name as table_name, t.t_qr as table_qr, t.time as order_time, t.menu_ordered,
                   wo.status, wo.served_at
            FROM " . $this->table_name . " wo
            LEFT JOIN user w ON wo.waiter_id = w.id
            LEFT JOIN tabels t ON wo.order_id = t.id
            WHERE wo.waiter_id = :waiter_id
            ORDER BY wo." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':waiter_id', $waiter_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to find a waiter order by its ID, joining with user (waiter) and tabels (order) for full context
    public function findById($id) {
        $query = "
            SELECT wo.id, wo.waiter_id, w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name,
                   wo.order_id, t.t_name as table_name, t.t_qr as table_qr, t.time as order_time, t.menu_ordered, t.transaction_mode,
                   wo.status, wo.served_at
            FROM " . $this->table_name . " wo
            LEFT JOIN user w ON wo.waiter_id = w.id
            LEFT JOIN tabels t ON wo.order_id = t.id
            WHERE wo.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Method to create a new waiter order record
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " (waiter_id, order_id, status, served_at) VALUES (:waiter_id, :order_id, :status, :served_at)";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':waiter_id', $data['waiter_id'], PDO::PARAM_INT);
        $stmt->bindParam(':order_id', $data['order_id'], PDO::PARAM_INT);
        $stmt->bindParam(':status', $data['status']); // Should be one of 'preparing', 'served', 'cancelled'
        $stmt->bindParam(':served_at', $data['served_at']); // Can be NULL

        try {
            if ($stmt->execute()) {
                return $this->conn->lastInsertId(); // Return the new waiter order's ID on success
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("WaiterOrder create error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure
    }

    // Method to update an existing waiter order record
    public function update($data) {
        // Typically, the main updates would be changing status (to 'served', 'cancelled') and setting served_at.
        $query = "UPDATE " . $this->table_name . " SET status = :status, served_at = :served_at WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':status', $data['status']); // Should be one of 'preparing', 'served', 'cancelled'
        $stmt->bindParam(':served_at', $data['served_at']); // Can be NULL or a timestamp
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);

        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return true; // Indicate success and that a row was updated
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("WaiterOrder update error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows updated
    }

    // Method to delete a waiter order record by its ID (Less common, but included for completeness)
    public function delete($id) {
        // WARNING: Deleting a waiter order assignment might break referential integrity.
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
            error_log("WaiterOrder delete error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows deleted
    }

    // Add other methods as needed, e.g., findByOrderId($order_id), findByStatus($status), etc.
}
?>