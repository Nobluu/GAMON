<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            
            // Verify connection is not null
            if ($this->db === null) {
                throw new Exception("Database connection failed - received null connection");
            }
        } catch (Exception $e) {
            error_log("Auth constructor error: " . $e->getMessage());
            throw new Exception("Gagal menghubungkan ke database: " . $e->getMessage());
        }
    }
    
    private function ensureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function register($name, $email, $password) {
        try {
            // Check if user already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Email sudah terdaftar'];
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $this->db->prepare("
                INSERT INTO users (name, email, password_hash) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $email, $hashedPassword]);
            
            return ['success' => true, 'message' => 'Pendaftaran berhasil'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Pendaftaran gagal: ' . $e->getMessage()];
        }
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, email, password_hash, role, status, last_login 
                FROM users 
                WHERE email = ? AND status = 'active'
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Email atau kata sandi tidak valid'];
            }
            
            // Update last login
            $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Start session
            $this->ensureSession();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            
            // Save session to database
            $this->createUserSession($user['id']);
            
            // Check for newly unlocked capsules and create notifications
            $this->checkUnlockNotificationsOnLogin($user['id']);
            
            return ['success' => true, 'user' => $user];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Masuk gagal: ' . $e->getMessage()];
        }
    }
    
    public function logout() {
        $this->ensureSession();
        
        // Remove session from database
        if (isset($_SESSION['session_db_id'])) {
            $this->removeUserSession($_SESSION['session_db_id']);
        }
        
        session_destroy();
        return ['success' => true];
    }
    
    public function isLoggedIn() {
        $this->ensureSession();
        return isset($_SESSION['user_id']);
    }
    
    public function getCurrentUser() {
        $this->ensureSession();
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        // Get fresh user data from database
        try {
            $stmt = $this->db->prepare("SELECT id, name, email, profile_picture, role, status, created_at FROM users WHERE id = ? AND status = 'active'");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                return $user;
            }
        } catch (PDOException $e) {
            // Fallback to session data
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'user',
            'profile_picture' => $_SESSION['user_profile_picture'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    private function createUserSession($userId) {
        try {
            // Get user email
            $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) return;
            
            $sessionId = session_id();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Insert session record
            $stmt = $this->db->prepare("
                INSERT INTO user_sessions (id, user_id, ip_address, user_agent) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$sessionId, $userId, $ipAddress, $userAgent]);
            
            // Store session DB ID in PHP session
            $_SESSION['session_db_id'] = $sessionId;
            
        } catch (PDOException $e) {
            error_log("Failed to create user session: " . $e->getMessage());
        }
    }
    
    private function removeUserSession($sessionId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
        } catch (PDOException $e) {
            error_log("Failed to remove user session: " . $e->getMessage());
        }
    }
    
    public function updateSessionActivity() {
        $this->ensureSession();
        if (isset($_SESSION['session_db_id'])) {
            try {
                $stmt = $this->db->prepare("
                    UPDATE user_sessions 
                    SET last_activity = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['session_db_id']]);
            } catch (PDOException $e) {
                error_log("Failed to update session activity: " . $e->getMessage());
            }
        }
    }
    
    public function getUserActiveSessions($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, user_id, ip_address, user_agent, created_at, last_activity 
                FROM user_sessions 
                WHERE user_id = ? 
                ORDER BY last_activity DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function cleanupExpiredSessions($hoursOld = 24) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM user_sessions 
                WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? HOUR)
            ");
            $stmt->execute([$hoursOld]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Failed to cleanup expired sessions: " . $e->getMessage());
            return 0;
        }
    }
    
    public function logoutFromAllDevices($userId, $keepCurrentSession = true) {
        try {
            if ($keepCurrentSession && isset($_SESSION['session_db_id'])) {
                $stmt = $this->db->prepare("
                    DELETE FROM user_sessions 
                    WHERE user_id = ? AND id != ?
                ");
                $stmt->execute([$userId, $_SESSION['session_db_id']]);
            } else {
                $stmt = $this->db->prepare("
                    DELETE FROM user_sessions 
                    WHERE user_id = ?
                ");
                $stmt->execute([$userId]);
            }
            
            return ['success' => true, 'deleted_count' => $stmt->rowCount()];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteSession($sessionId, $userId) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM user_sessions 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$sessionId, $userId]);
            
            return ['success' => true, 'deleted' => $stmt->rowCount() > 0];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Admin role checking methods
    public function isAdmin() {
        $this->ensureSession();
        return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'superadmin']);
    }
    
    public function isSuperAdmin() {
        $this->ensureSession();
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin';
    }
    
    public function requireAdmin() {
        if (!$this->isLoggedIn()) {
            header('Location: /gamon/login.php');
            exit;
        }
        
        if (!$this->isAdmin()) {
            header('Location: /gamon/dashboard.php?error=access_denied');
            exit;
        }
    }
    
    public function requireSuperAdmin() {
        if (!$this->isLoggedIn()) {
            header('Location: /gamon/login.php');
            exit;
        }
        
        if (!$this->isSuperAdmin()) {
            header('Location: /gamon/dashboard.php?error=access_denied');
            exit;
        }
    }
    
    public function getUserRole() {
        $this->ensureSession();
        return $_SESSION['user_role'] ?? 'user';
    }
    
    /**
     * Check for unlocked capsules when user logs in and create notifications
     */
    private function checkUnlockNotificationsOnLogin($user_id) {
        try {
            require_once __DIR__ . '/NotificationController.php';
            $notificationController = new NotificationController();
            $notificationController->checkAndCreateUnlockNotifications();
        } catch (Exception $e) {
            // Log error but don't break login process
            error_log("Error checking unlock notifications on login: " . $e->getMessage());
        }
    }
}
?>