<?php
// Load timezone configuration
require_once __DIR__ . '/timezone.php';

if (!class_exists('Database')) {
class Database {
    private static $instance = null;
    private $connection;
    
    // Database configuration - supports both Docker and local development
    private $host;
    private $database;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    
    private function __construct() {
        // Check if running in Docker environment
        if (getenv('DB_HOST') !== false) {
            // Docker environment - konek ke XAMPP MySQL
            $this->host = getenv('DB_HOST');
            $this->database = getenv('DB_NAME') ?: 'capsule_db';
            $this->username = getenv('DB_USER') ?: 'root';
            $this->password = getenv('DB_PASS') ?: '';
        } else {
            // Local development (XAMPP/WAMP/etc)
            $this->host = '127.0.0.1';
            $this->database = 'capsule_db';
            $this->username = 'root';
            $this->password = '';
        }
        
        try {
            $dsn = "mysql:host={$this->host};port=3306;dbname={$this->database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new Exception("Koneksi database gagal: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
}

// Helper function for quick access
function getDB() {
    return Database::getInstance()->getConnection();
}
?>