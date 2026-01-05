<?php
if (!class_exists('Database')) {
    class Database {
        private $host = '127.0.0.1';
        private $port = '3306';
        private $db_name = 'capsule_db';
        private $username = 'root';
        private $password = '';
        public $conn;

        public function __construct() {
            // Constructor is now public
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
