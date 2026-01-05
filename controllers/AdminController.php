<?php
require_once __DIR__ . '/../config/database.php';

class AdminController {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // User Management Methods
    public function getAllUsers($page = 1, $limit = 20, $search = '') {
        try {
            $offset = ($page - 1) * $limit;
            $searchWhere = '';
            $searchParams = [];
            
            if (!empty($search)) {
                $searchWhere = " WHERE (name LIKE ? OR email LIKE ?)";
                $searchParams = ["%$search%", "%$search%"];
            }
            
            // Get total count
            $countStmt = $this->db->prepare("SELECT COUNT(*) as total FROM users" . $searchWhere);
            $countStmt->execute($searchParams);
            $total = $countStmt->fetch()['total'];
            
            // Get users with pagination
            $stmt = $this->db->prepare("
                SELECT id, name, email, role, status, last_login, created_at,
                       (SELECT COUNT(*) FROM capsules WHERE user_id = users.id) as capsule_count
                FROM users 
                $searchWhere
                ORDER BY created_at DESC 
                LIMIT $limit OFFSET $offset
            ");
            
            $stmt->execute($searchParams);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'users' => $users,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function updateUserStatus($userId, $status) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $userId]);
            
            return ['success' => true, 'message' => 'User status updated successfully'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function updateUserRole($userId, $role) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$role, $userId]);
            
            return ['success' => true, 'message' => 'User role updated successfully'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteUser($userId) {
        try {
            // Start transaction
            $this->db->beginTransaction();
            
            // Delete related records first (due to foreign key constraints)
            $this->db->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$userId]);
            $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$userId]);
            
            // Delete user capsules and their media
            $this->db->prepare("
                DELETE cm FROM capsule_media cm 
                JOIN capsules c ON cm.capsule_id = c.id 
                WHERE c.user_id = ?
            ")->execute([$userId]);
            
            $this->db->prepare("DELETE FROM capsules WHERE user_id = ?")->execute([$userId]);
            
            // Finally delete the user
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'User deleted successfully'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Capsule Management Methods
    public function getAllCapsules($page = 1, $limit = 20, $search = '', $status = '') {
        try {
            $offset = ($page - 1) * $limit;
            $whereConditions = [];
            $params = [];
            
            if (!empty($search)) {
                $whereConditions[] = "(c.title LIKE ? OR c.message LIKE ? OR u.name LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if (!empty($status)) {
                if ($status === 'locked') {
                    $whereConditions[] = "c.unlock_date > NOW()";
                } elseif ($status === 'unlocked') {
                    $whereConditions[] = "c.unlock_date <= NOW()";
                }
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM capsules c 
                JOIN users u ON c.user_id = u.id 
                $whereClause
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get capsules with pagination
            $stmt = $this->db->prepare("
                SELECT c.id, c.title, c.message, c.unlock_date, c.created_at, c.is_unlocked,
                       u.name as user_name, u.email as user_email,
                       m.name as mood_name, m.emoji as mood_emoji,
                       (SELECT COUNT(*) FROM capsule_media WHERE capsule_id = c.id) as media_count
                FROM capsules c 
                JOIN users u ON c.user_id = u.id 
                LEFT JOIN moods m ON c.mood_id = m.id
                $whereClause
                ORDER BY c.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $capsules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'capsules' => $capsules,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteCapsule($capsuleId) {
        try {
            // Start transaction
            $this->db->beginTransaction();
            
            // Delete related media files first
            $stmt = $this->db->prepare("SELECT filename FROM capsule_media WHERE capsule_id = ?");
            $stmt->execute([$capsuleId]);
            $mediaFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete media records
            $this->db->prepare("DELETE FROM capsule_media WHERE capsule_id = ?")->execute([$capsuleId]);
            
            // Delete notifications related to this capsule
            $this->db->prepare("DELETE FROM notifications WHERE capsule_id = ?")->execute([$capsuleId]);
            
            // Delete the capsule
            $this->db->prepare("DELETE FROM capsules WHERE id = ?")->execute([$capsuleId]);
            
            $this->db->commit();
            
            // Delete physical media files
            foreach ($mediaFiles as $filename) {
                $filePath = __DIR__ . '/../uploads/media/' . $filename;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            return ['success' => true, 'message' => 'Capsule deleted successfully'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Analytics Methods
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // Total users
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
            $stats['total_users'] = $stmt->fetch()['count'];
            
            // Total capsules
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM capsules");
            $stats['total_capsules'] = $stmt->fetch()['count'];
            
            // Locked capsules
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM capsules WHERE unlock_date > NOW()");
            $stats['locked_capsules'] = $stmt->fetch()['count'];
            
            // Unlocked capsules
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM capsules WHERE unlock_date <= NOW()");
            $stats['unlocked_capsules'] = $stmt->fetch()['count'];
            
            // New users this month
            $stmt = $this->db->query("
                SELECT COUNT(*) as count FROM users 
                WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
                AND YEAR(created_at) = YEAR(CURRENT_DATE())
            ");
            $stats['new_users_month'] = $stmt->fetch()['count'];
            
            // Capsules unlocked today
            $stmt = $this->db->query("
                SELECT COUNT(*) as count FROM capsules 
                WHERE DATE(unlocked_at) = CURDATE()
            ");
            $stats['unlocked_today'] = $stmt->fetch()['count'];
            
            return ['success' => true, 'stats' => $stats];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getActivityChart($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as count
                FROM capsules 
                WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'data' => $chartData];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Mood Management Methods
    public function getAllMoods() {
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, COUNT(c.id) as usage_count 
                FROM moods m 
                LEFT JOIN capsules c ON m.id = c.mood_id 
                GROUP BY m.id 
                ORDER BY m.name
            ");
            $stmt->execute();
            $moods = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'moods' => $moods];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function createMood($name, $emoji, $color) {
        try {
            $stmt = $this->db->prepare("INSERT INTO moods (name, emoji, color) VALUES (?, ?, ?)");
            $stmt->execute([$name, $emoji, $color]);
            
            return ['success' => true, 'message' => 'Mood created successfully', 'id' => $this->db->lastInsertId()];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function updateMood($id, $name, $emoji, $color) {
        try {
            $stmt = $this->db->prepare("UPDATE moods SET name = ?, emoji = ?, color = ? WHERE id = ?");
            $stmt->execute([$name, $emoji, $color, $id]);
            
            return ['success' => true, 'message' => 'Mood updated successfully'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteMood($id) {
        try {
            // Check if mood is being used
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM capsules WHERE mood_id = ?");
            $stmt->execute([$id]);
            $usageCount = $stmt->fetch()['count'];
            
            if ($usageCount > 0) {
                return ['success' => false, 'message' => "Cannot delete mood. It's being used by $usageCount capsules."];
            }
            
            $stmt = $this->db->prepare("DELETE FROM moods WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Mood deleted successfully'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>