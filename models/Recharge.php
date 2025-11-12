<?php
// models/Recharge.php

class Recharge {
    private $conn;
    private $table_name = "recharge"; // Match the table name from the schema

    // Database columns
    public $id;
    public $r_type; // Enum: Cash, Card, UPI
    public $user_id; // FK to user
    public $transaction_id; // FK to transactions
    public $r_time; // Timestamp
    public $card_id; // FK to card

    public function __construct($db) {
        $this->conn = $db;
    }

    // Method to find all recharges, joining with user, card, and transaction for context
    public function findAll($orderBy = 'r_time', $orderDir = 'DESC') {
        // Note: The schema defines transaction_id as a composite key with r_time.
        // Fetching transaction details might involve joining with the transactions table on transaction_id.
        // However, the primary goal here is to list recharges.
        $query = "
            SELECT r.id, r.r_type, r.user_id, u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
                   r.card_id, c.balance_credits as card_balance, c.total_credits as card_total,
                   r.transaction_id, t.t_time as transaction_time, -- Get transaction time
                   r.r_time
            FROM " . $this->table_name . " r
            LEFT JOIN user u ON r.user_id = u.id
            LEFT JOIN card c ON r.card_id = c.id
            LEFT JOIN transactions t ON r.transaction_id = t.id -- Join to get transaction time/details
            ORDER BY r." . $orderBy . " " . $orderDir;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to find a recharge by its ID, joining with user, card, and transaction for full context
    public function findById($id) {
        $query = "
            SELECT r.id, r.r_type, r.user_id, u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
                   r.card_id, c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status,
                   r.transaction_id, t.t_type as transaction_type, t.t_time as transaction_time, -- Get transaction details
                   r.r_time
            FROM " . $this->table_name . " r
            LEFT JOIN user u ON r.user_id = u.id
            LEFT JOIN card c ON r.card_id = c.id
            LEFT JOIN transactions t ON r.transaction_id = t.id -- Join to get transaction details
            WHERE r.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Method to create a new recharge
    // This is a simplified version. A real-world scenario involves:
    // 1. Finding/creating the associated transaction record.
    // 2. Updating the card's balance_credits and total_credits.
    // 3. Inserting this recharge record, potentially linking back to the transaction.
    // For this model method, we assume the necessary preliminary steps (transaction creation, card update) are handled by the controller or a service layer,
    // and this method simply inserts the recharge record with the provided data (including potentially the transaction_id).
    public function create($data) {
        // Ensure required fields are present in $data
        // Required: r_type, user_id, card_id
        // transaction_id might be optional or set later, depending on business logic.
        $query = "INSERT INTO " . $this->table_name . " (r_type, user_id, card_id, transaction_id, r_time) VALUES (:r_type, :user_id, :card_id, :transaction_id, :r_time)";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':r_type', $data['r_type']);
        $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':card_id', $data['card_id'], PDO::PARAM_INT);
        // transaction_id might be NULL initially or set based on linked transaction
        $stmt->bindParam(':transaction_id', $data['transaction_id'], PDO::PARAM_INT); // Bind as INT, can handle NULL if passed correctly
        // r_time is typically set automatically by the database (CURRENT_TIMESTAMP), but we can override it if needed
        $r_time_value = $data['r_time'] ?? date('Y-m-d H:i:s'); // Use provided time or current time
        $stmt->bindParam(':r_time', $r_time_value);

        try {
            if ($stmt->execute()) {
                return $this->conn->lastInsertId(); // Return the new recharge's ID on success
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Recharge create error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure
    }

     // Method to update an existing recharge (Less common for financial records, but included for completeness)
    // Be extremely careful with updates to financial data.
    public function update($data) {
        // Typically, only non-financial metadata might be updated, or status.
        // Updating user_id, card_id, transaction_id, or amounts is risky.
        // Let's assume we only allow updating r_type and r_time for this example.
        $query = "UPDATE " . $this->table_name . " SET r_type = :r_type, r_time = :r_time WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind data
        $stmt->bindParam(':r_type', $data['r_type']);
        $stmt->bindParam(':r_time', $data['r_time']); // Allow updating time, though unusual
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);

        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return true; // Indicate success and that a row was updated
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            error_log("Recharge update error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows updated
    }

    // Method to delete a recharge by its ID (Often restricted for financial records)
    // Be extremely careful with deleting financial records.
    public function delete($id) {
        // WARNING: Deleting a recharge might break referential integrity if other tables depend on it.
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
            error_log("Recharge delete error: " . $e->getMessage());
            // Do not return the raw error message to the user
        }
        return false; // Indicate failure or no rows deleted
    }

    // Add other methods as needed, e.g., findByUserId($user_id), findByCardId($card_id), findRecent($limit), etc.
}
?>