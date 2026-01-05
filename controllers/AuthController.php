<?php
require_once __DIR__ . '/../config/database.php';

class AuthController {
    private $conn;

    public function __construct() {
        // Start session first before any database operations
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function register($name, $email, $password) {
        // 1. Sanitize Input
        $name = htmlspecialchars(strip_tags($name));
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        // 2. Validate Email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => 'Invalid email format'];
        }

        // 3. Check if email exists
        $checkQuery = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return ['status' => false, 'message' => 'Email already registered'];
        }

        // 4. Hash Password
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // 5. Insert User
        $query = "INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash);

        if ($stmt->execute()) {
            return ['status' => true, 'message' => 'Registration successful! Please login.'];
        }

        return ['status' => false, 'message' => 'Registration failed. Please try again.'];
    }

    public function login($email, $password) {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        $query = "SELECT id, name, password_hash FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['password_hash'])) {
                // Set Session
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['name'];
                return ['status' => true, 'message' => 'Login successful'];
            }
        }

        return ['status' => false, 'message' => 'Invalid email or password'];
    }

    public function logout() {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }

    public function requireLogin() {
        if (!$this->isAuthenticated()) {
            header("Location: login.php");
            exit;
        }
    }
}
?>
