<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/timezone.php';

class Capsule {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getAllMoods() {
        try {
            $stmt = $this->db->query("SELECT id, name, emoji, color, music_file, music_name, music_duration FROM moods ORDER BY name");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function getMoodWithMusic($moodId) {
        try {
            $stmt = $this->db->prepare("SELECT id, name, emoji, color, music_file, music_name, music_duration FROM moods WHERE id = ?");
            $stmt->execute([$moodId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
    
    public function createCapsule($userId, $data) {
        try {
            // Convert datetime-local format to MySQL datetime format
            $unlockDate = null;
            if (!empty($data['unlock_date'])) {
                $unlockDate = date('Y-m-d H:i:s', strtotime($data['unlock_date']));
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO capsules (
                    user_id, title, message, mood_id, unlock_date, 
                    email_notification, public_sharing, auto_backup
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $userId,
                $data['title'],
                $data['message'],
                $data['mood_id'] ?? null,
                $unlockDate,
                $data['email_notification'] ?? true,
                $data['public_sharing'] ?? false,
                $data['auto_backup'] ?? true
            ]);
            
            if ($result) {
                return ['success' => true, 'capsule_id' => $this->db->lastInsertId()];
            }
            
            return ['success' => false, 'message' => 'Failed to create capsule'];
            
        } catch (PDOException $e) {
            error_log("Database error in createCapsule: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getUserCapsules($userId, $filter = 'all', $limit = null, $offset = 0) {
        try {
            $whereClause = "WHERE c.user_id = ?";
            $params = [$userId];
            
            if ($filter === 'locked') {
                $whereClause .= " AND c.unlock_date > NOW()";
            } elseif ($filter === 'unlocked') {
                $whereClause .= " AND c.unlock_date <= NOW()";
            }
            
            $limitClause = "";
            if ($limit) {
                $limitClause = "LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    c.*,
                    m.name as mood_name,
                    m.emoji as mood_emoji,
                    m.color as mood_color,
                    COUNT(cm.id) as media_count,
                    CASE 
                        WHEN c.unlock_date <= NOW() THEN 'unlocked'
                        ELSE 'locked'
                    END as current_status
                FROM capsules c
                LEFT JOIN moods m ON c.mood_id = m.id
                LEFT JOIN capsule_media cm ON c.id = cm.capsule_id
                {$whereClause}
                GROUP BY c.id
                ORDER BY c.created_at DESC
                {$limitClause}
            ");
            
            $stmt->execute($params);
            $capsules = $stmt->fetchAll();
            
            return $capsules;
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function getCapsule($capsuleId, $userId = null) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.*,
                    m.name as mood_name,
                    m.emoji as mood_emoji,
                    m.color as mood_color,
                    m.music_file as mood_music_file,
                    m.music_name as mood_music_name,
                    m.music_duration as mood_music_duration,
                    COUNT(cm.id) as media_count,
                    CASE 
                        WHEN c.unlock_date <= NOW() THEN 'unlocked'
                        ELSE 'locked'
                    END as current_status
                FROM capsules c
                LEFT JOIN moods m ON c.mood_id = m.id
                LEFT JOIN capsule_media cm ON c.id = cm.capsule_id
                WHERE c.id = ? AND (c.user_id = ? OR c.public_sharing = TRUE)
                GROUP BY c.id
            ");
            
            $stmt->execute([$capsuleId, $userId]);
            $capsule = $stmt->fetch();
            
            if (!$capsule) {
                return null;
            }
            
            return $capsule;
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    public function getUserStats($userId) {
        try {
            $stats = [];
            
            // Total capsules
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM capsules WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['total'] = $stmt->fetchColumn();
            
            // Locked capsules
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as locked 
                FROM capsules 
                WHERE user_id = ? AND unlock_date > NOW()
            ");
            $stmt->execute([$userId]);
            $stats['locked'] = $stmt->fetchColumn();
            
            // Unlocked capsules
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as unlocked 
                FROM capsules 
                WHERE user_id = ? AND unlock_date <= NOW()
            ");
            $stmt->execute([$userId]);
            $stats['unlocked'] = $stmt->fetchColumn();
            
            // Next unlock date
            $stmt = $this->db->prepare("
                SELECT MIN(unlock_date) as next_unlock 
                FROM capsules 
                WHERE user_id = ? AND unlock_date > NOW()
            ");
            $stmt->execute([$userId]);
            $stats['next_unlock'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (PDOException $e) {
            return [
                'total' => 0,
                'locked' => 0, 
                'unlocked' => 0,
                'next_unlock' => null
            ];
        }
    }
    
    public function searchCapsules($userId, $query) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.*,
                    m.name as mood_name,
                    m.emoji as mood_emoji
                FROM capsules c
                LEFT JOIN moods m ON c.mood_id = m.id
                WHERE c.user_id = ? AND (c.title LIKE ? OR c.message LIKE ?)
                ORDER BY c.created_at DESC
            ");
            
            $searchTerm = "%{$query}%";
            $stmt->execute([$userId, $searchTerm, $searchTerm]);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function updateUnlockStatus($userId) {
        try {
            // Update capsules that should be unlocked
            $stmt = $this->db->prepare("
                UPDATE capsules 
                SET is_unlocked = TRUE, unlocked_at = NOW() 
                WHERE user_id = ? AND unlock_date <= NOW() AND is_unlocked = FALSE
            ");
            $stmt->execute([$userId]);
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    public function unlockCapsule($capsuleId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE capsules 
                SET is_unlocked = TRUE, unlocked_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$capsuleId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getNotifications($userId, $limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    n.*,
                    c.title as capsule_title
                FROM notifications n
                LEFT JOIN capsules c ON n.capsule_id = c.id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function getUnreadNotificationCount($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM notifications 
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchColumn();
            
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    public function getCapsuleMedia($capsuleId, $userId) {
        try {
            // Verify capsule ownership first
            $capsule = $this->getCapsule($capsuleId, $userId);
            if (!$capsule) {
                return [];
            }
            
            $stmt = $this->db->prepare("
                SELECT * FROM capsule_media 
                WHERE capsule_id = ? 
                ORDER BY uploaded_at ASC
            ");
            $stmt->execute([$capsuleId]);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function deleteCapsule($capsuleId, $userId) {
        try {
            // First check if capsule exists and belongs to user
            $stmt = $this->db->prepare("
                SELECT id, is_unlocked, unlock_date, title 
                FROM capsules 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$capsuleId, $userId]);
            $capsule = $stmt->fetch();
            
            if (!$capsule) {
                return ['success' => false, 'message' => 'Kapsul tidak ditemukan atau Anda tidak memiliki akses'];
            }
            
            // Check if capsule is still locked (not opened yet) and hasn't reached unlock date
            $currentTime = new DateTime();
            $unlockDate = new DateTime($capsule['unlock_date']);
            
            if ($capsule['is_unlocked'] == 1 || $currentTime >= $unlockDate) {
                return ['success' => false, 'message' => 'Kapsul yang sudah terbuka tidak dapat dihapus'];
            }
            
            return $this->forceDeleteCapsule($capsuleId, $userId);
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Gagal menghapus kapsul: ' . $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function forceDeleteCapsule($capsuleId, $userId) {
        try {
            // Check if capsule exists and belongs to user
            $stmt = $this->db->prepare("
                SELECT id, title 
                FROM capsules 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$capsuleId, $userId]);
            $capsule = $stmt->fetch();
            
            if (!$capsule) {
                return ['success' => false, 'message' => 'Kapsul tidak ditemukan atau Anda tidak memiliki akses'];
            }
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Delete related media files first
            $mediaStmt = $this->db->prepare("SELECT filename FROM capsule_media WHERE capsule_id = ?");
            $mediaStmt->execute([$capsuleId]);
            $mediaFiles = $mediaStmt->fetchAll(PDO::FETCH_COLUMN);
            
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
            
            return ['success' => true, 'message' => 'Kapsul "' . $capsule['title'] . '" berhasil dihapus'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Gagal menghapus kapsul: ' . $e->getMessage()];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    public function canDeleteCapsule($capsuleId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, is_unlocked, unlock_date 
                FROM capsules 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$capsuleId, $userId]);
            $capsule = $stmt->fetch();
            
            if (!$capsule) {
                return false;
            }
            
            // Can delete if not unlocked and unlock date is in the future
            $currentTime = new DateTime();
            $unlockDate = new DateTime($capsule['unlock_date']);
            
            return ($capsule['is_unlocked'] == 0 && $currentTime < $unlockDate);
            
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>