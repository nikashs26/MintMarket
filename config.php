<?php
/**
 * MintMarket Configuration and Database Connection
 */

// Error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_CONNECTION', 'sqlite'); // 'mysql' or 'sqlite'

// MySQL Config
define('DB_HOST', 'localhost');
define('DB_NAME', 'mintmarket');
define('DB_USER', 'mintmarket');
define('DB_PASS', 'SecurePassword123!');
define('DB_CHARSET', 'utf8mb4');

// SQLite Config
define('DB_FILE', __DIR__ . '/mintmarket.sqlite');

// Blockchain Configuration
define('MINING_DIFFICULTY', 4);
define('BLOCK_REWARD', 0.1);

/**
 * Database Class (Singleton Pattern)
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            if (DB_CONNECTION === 'sqlite') {
                $dsn = "sqlite:" . DB_FILE;
                $this->pdo = new PDO($dsn, null, null, $options);
                // Enable foreign keys for SQLite
                $this->pdo->exec("PRAGMA foreign_keys = ON;");
            } else {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            }
        } catch (PDOException $e) {
            // In production, log this error instead of showing it
            die("Database Connection Failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {}
}
?>
