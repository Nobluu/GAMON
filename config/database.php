<?php
if (!class_exists('Database')) {
    class Database {
        private $host;
        private $port;
        private $db_name;
        private $username;
        private $password;
        public $conn;

        public function __construct() {
            // Auto-detect environment (Docker vs XAMPP)
            if (getenv('DB_HOST') !== false) {
                // Docker environment
                $this->host = getenv('DB_HOST');
                $this->port = '3306'; // Internal Docker port
                $this->db_name = getenv('DB_NAME') ?: 'capsule_db';
                $this->username = getenv('DB_USER') ?: 'root';
                $this->password = getenv('DB_PASS') ?: '';
            } else {
                // XAMPP local environment
                $this->host = '127.0.0.1';
                $this->port = '3306';
                $this->db_name = 'capsule_db';
                $this->username = 'root';
                $this->password = '';
            }
        }

        public function getConnection() {
            $this->conn = null;
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                $this->conn->exec("set names utf8mb4 collate utf8mb4_unicode_ci");
            } catch(PDOException $exception) {
                // Log error and throw exception instead of returning null
                error_log("Database Connection error: " . $exception->getMessage());
                throw new Exception("Koneksi database gagal: " . $exception->getMessage());
            }
            return $this->conn;
        }
    }
}
?>
