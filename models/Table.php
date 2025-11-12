<?php
// models/Table.php

class Table {
    private $conn;
    private $table_name = "tabels"; // Match the table name from the schema (note the typo 'tabels')

    // Database columns
    public $id;
    public $t_qr; // Int
    public $t_name; // Enum: A-J
    public $t_s_w; // Int
    public $time; // Timestamp
    public $transaction_mode; // Enum: Cash, UPI, Card
    public $user_id; // FK to user (likely the waiter or customer initiating the order)
    public $card_id; // FK to card
    public $menu_ordered; // Text

    public function __construct($db) {
        $this->conn = $db;
    }

    // Method to find all table records, joining with user (waiter/customer) and card for context
    public function findAll($orderBy = 'time', $orderDir = 'DESC') {
        $query = "
            SELECT t.id, t.t_qr, t.t_name, t.t_s_w, t.time, t.transaction_mode, t.user_id, t.card_id, t.menu_ordered,
                   w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name, -- Waiter/Customer linked via t.user_id
                   c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   cu.username as customer_username, cu.first_name as customer_first_name, cu.last_name as customer_last_name -- Customer linked via card's user_id
            FROM " . $this->table_name . " t
            LEFT JOIN user w ON t.user_id = w.id -- Get waiter/customer via tabels.user_id
            LEFT JOIN card c ON t.card_id = c.id -- Get card details
            LEFT JOIN user cu ON c.user_id = cu.id -- Get customer via card's user_id
            ORDER BY t." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to find a table record by its ID, joining with user (waiter/customer) and card for full context
    public function findById($id) {
        $query = "
            SELECT t.id, t.t_qr, t.t_name, t.t_s_w, t.time, t.transaction_mode, t.user_id, t.card_id, t.menu_ordered,
                   w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name, w.email as waiter_email, -- Waiter/Customer linked via t.user_id
                   c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   cu.username as customer_username, cu.first_name as customer_first_name, cu.last_name as customer_last_name, cu.email as customer_email, cu.ph_no as customer_ph_no -- Customer linked via card's user_id
            FROM " . $this->table_name . " t
            LEFT JOIN user w ON t.user_id = w.id -- Get waiter/customer via tabels.user_id
            LEFT JOIN card c ON t.card_id = c.id -- Get card details
            LEFT JOIN user cu ON c.user_id = cu.id -- Get customer via card's user_id
            WHERE t.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Method to find table records by user ID (e.g., orders taken by a specific waiter or placed by a specific customer)
    public function findByUserId($user_id, $orderBy = 'time', $orderDir = 'DESC') {
        $query = "
            SELECT t.id, t.t_qr, t.t_name, t.t_s_w, t.time, t.transaction_mode, t.user_id, t.card_id, t.menu_ordered,
                   w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name, -- Waiter/Customer linked via t.user_id
                   c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   cu.username as customer_username, cu.first_name as customer_first_name, cu.last_name as customer_last_name -- Customer linked via card's user_id
            FROM " . $this->table_name . " t
            LEFT JOIN user w ON t.user_id = w.id -- Get waiter/customer via tabels.user_id
            LEFT JOIN card c ON t.card_id = c.id -- Get card details
            LEFT JOIN user cu ON c.user_id = cu.id -- Get customer via card's user_id
            WHERE t.user_id = :user_id -- Filter by the user ID (waiter/customer)
            ORDER BY t." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

     // Method to find table records by card ID
    public function findByCardId($card_id, $orderBy = 'time', $orderDir = 'DESC') {
        $query = "
            SELECT t.id, t.t_qr, t.t_name, t.t_s_w, t.time, t.transaction_mode, t.user_id, t.card_id, t.menu_ordered,
                   w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name, -- Waiter/Customer linked via t.user_id
                   c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   cu.username as customer_username, cu.first_name as customer_first_name, cu.last_name as customer_last_name -- Customer linked via card's user_id
            FROM " . $this->table_name . " t
            LEFT JOIN user w ON t.user_id = w.id -- Get waiter/customer via tabels.user_id
            LEFT JOIN card c ON t.card_id = c.id -- Get card details
            LEFT JOIN user cu ON c.user_id = cu.id -- Get customer via card's user_id
            WHERE t.card_id = :card_id -- Filter by the card ID
            ORDER BY t." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to create a new table record
    // Note: Creating a table record often involves linking to existing user, card, potentially affecting card balance if it's an order finalization.
    // This method just inserts the record with the provided data.
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " (t_qr, t_name, t_s_w, time, transaction_mode, user_id, card_id, menu_ordered) VALUES (:t_qr, :t_name, :t_s_w, :time, :transaction_mode, :user_id, :card_id, :menu_ordered)";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':t_qr', $data['t_qr'], PDO::PARAM_INT);
        $stmt->bindParam(':t_name', $data['t_name']);
        $stmt->bindParam(':t_s_w', $data['t_s_w'], PDO::PARAM_INT);
        $stmt->bindParam(':time', $data['time']); // Should be a valid datetime string
        $stmt->bindParam(':transaction_mode', $data['transaction_mode']);
        $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT); // Waiter/Customer ID
        $stmt->bindParam(':card_id', $data['card_id'], PDO::PARAM_INT); // Card ID
        $stmt->bindParam(':menu_ordered', $data['menu_ordered']); // Text description of order

        try {
            if ($stmt->execute()) {
                return $this->conn->lastInsertId(); // Return the new record's ID on success
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Table create error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure
    }

    // Method to update an existing table record
    public function update($data) {
        // Typically, updating a table record might involve changing the assigned waiter, transaction mode, or finalizing the order (menu_ordered).
        // Be careful with updates that might affect financial calculations if menu_ordered changes post-creation.
        $query = "UPDATE " . $this->table_name . " SET t_qr = :t_qr, t_name = :t_name, t_s_w = :t_s_w, time = :time, transaction_mode = :transaction_mode, user_id = :user_id, card_id = :card_id, menu_ordered = :menu_ordered WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':t_qr', $data['t_qr'], PDO::PARAM_INT);
        $stmt->bindParam(':t_name', $data['t_name']);
        $stmt->bindParam(':t_s_w', $data['t_s_w'], PDO::PARAM_INT);
        $stmt->bindParam(':time', $data['time']); // Should be a valid datetime string
        $stmt->bindParam(':transaction_mode', $data['transaction_mode']);
        $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT); // Waiter/Customer ID
        $stmt->bindParam(':card_id', $data['card_id'], PDO::PARAM_INT); // Card ID
        $stmt->bindParam(':menu_ordered', $data['menu_ordered']); // Text description of order
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT); // Record ID to update

        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return true; // Indicate success and that a row was updated
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Table update error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows updated
    }

    // Method to delete a table record by its ID
    public function delete($id) {
        // WARNING: Deleting a table record might break referential integrity if other tables depend on it (e.g., waiter_orders, user_details_tabel).
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
            error_log("Table delete error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows deleted
    }

    // Add other methods as needed, e.g., findByTableName($t_name), findRecentOrders($limit), etc.
}
?>