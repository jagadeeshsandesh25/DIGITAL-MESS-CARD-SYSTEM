<?php
// models/Menu.php

class Menu {
    private $conn;
    private $table_name = "menu"; // Match the table name from the schema

    // Database columns
    public $id;
    public $menu_type; // Enum: Veg, Non-veg
    public $description; // Text
    public $category; // Enum: Breakfast, Lunch, Dinner
    public $created_at; // Timestamp

    public function __construct($db) {
        $this->conn = $db;
    }

    // Method to find all menu items, ordered by category, type, creation time
    public function findAll($orderBy = 'category', $orderDir = 'ASC') {
        $query = "
            SELECT id, menu_type, description, category, created_at
            FROM " . $this->table_name . "
            ORDER BY " . $orderBy . " " . $orderDir . ", menu_type ASC, created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to find a menu item by its ID
    public function findById($id) {
        $query = "SELECT id, menu_type, description, category, created_at FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Method to create a new menu item
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " (menu_type, description, category) VALUES (:menu_type, :description, :category)";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':menu_type', $data['menu_type']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':category', $data['category']);

        try {
            if ($stmt->execute()) {
                return $this->conn->lastInsertId(); // Return the new menu item's ID on success
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Menu create error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure
    }

    // Method to update an existing menu item
    public function update($data) {
        $query = "UPDATE " . $this->table_name . " SET menu_type = :menu_type, description = :description, category = :category WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':menu_type', $data['menu_type']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':category', $data['category']);
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);

        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return true; // Indicate success and that a row was updated
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Menu update error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows updated
    }

    // Method to delete a menu item by its ID
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
            // This might fail due to foreign key constraints if items are ordered
            error_log("Menu delete error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows deleted
    }

    // Add other methods as needed, e.g., findByCategory($category), findByType($type), search($term), etc.
}
?>