<?php
// config/database.php

/**
 * Class Database
 *
 * Handles the database connection using PDO.
 * Provides a method to get the connection instance.
 */
class Database {
    // --- Database Configuration Settings ---
    // Update these values to match your local/production environment

    /**
     * Database host (e.g., localhost, 127.0.0.1, your_remote_host)
     * @var string
     */
    private $host = "localhost"; // Default for local development (XAMPP, WAMP, LAMP)

    /**
     * Database name
     * Must match the name of your database (from massscema.pdf)
     * @var string
     */
    private $db_name = "mydatabase"; // IMPORTANT: Match the database name from your schema

    /**
     * Database username
     * Default is often 'root' for local development environments
     * @var string
     */
    private $username = "root"; // Default for XAMPP/WAMP

    /**
     * Database password
     * Default is often empty for local development environments (XAMPP)
     * Set this if you have a password configured for your database user
     * @var string
     */
    private $password = ""; // Default for XAMPP, might be 'root' for WAMP, or a custom password

    /**
     * Database connection object (PDO instance)
     * @var PDO|null
     */
    public $conn;

    /**
     * Database constructor.
     * Initializes the connection property to null.
     */
    public function __construct() {
        $this->conn = null; // Initialize connection as null
    }

    /**
     * Establishes and returns the database connection.
     *
     * Uses PDO for secure database interaction.
     * Sets important PDO attributes for error handling, fetching mode, and prepared statements.
     *
     * @return PDO|null The PDO connection object, or null on failure.
     */
    public function getConnection() {
        $this->conn = null; // Reset connection before attempting new one

        try {
            // --- Construct the Data Source Name (DSN) ---
            // Specifies the database driver (mysql), host, database name, and character set
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";

            // --- Define PDO Options for Security and Performance ---
            $options = [
                // Set PDO to throw exceptions on errors for better debugging and error handling
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                // Set default fetch mode to associative array for easier data handling
                // This means $stmt->fetch() will return an array like ['column_name' => 'value']
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // Disable emulating prepared statements.
                // This forces the use of real prepared statements by the database server,
                // which enhances security against SQL injection attacks.
                PDO::ATTR_EMULATE_PREPARES => false,

                // Optional: Set the connection timeout (in seconds)
                // PDO::ATTR_TIMEOUT => 30,
            ];

            // --- Create the PDO Connection Instance ---
            // Pass the DSN, username, password, and options to the PDO constructor
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

            // Uncomment the line below for debugging connection issues (remove in production)
            // echo "Database connected successfully!";

        } catch (PDOException $exception) {
            // --- Handle Connection Errors Gracefully ---
            // Log the detailed error message for debugging (do NOT display in production)
            error_log("Connection error: " . $exception->getMessage());

            // Display a user-friendly error message
            // In a production environment, you might want to redirect to an error page instead
            echo "Connection failed. Please check your database configuration."; // User-friendly message
        }

        // --- Return the Connection Object ---
        // Returns the PDO instance ($this->conn) if connection was successful, or null if it failed
        return $this->conn;
    }
}

?>