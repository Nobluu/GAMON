<?php
require_once __DIR__ . '/../config/database.php';

class FriendController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Send friend request
     */
    public function sendFriendRequest($requester_id, $addressee_identifier) {
        try {
            // Find user by email or friend code (handle if friend_code column doesn't exist)
            $columnCheck = $this->conn->query("SHOW COLUMNS FROM users LIKE 'friend_code'");
            $hasFriendCode = $columnCheck->rowCount() > 0;
            
            if ($hasFriendCode) {
                $query = "SELECT id, name, email FROM users 
                          WHERE email = ? OR friend_code = ? 
                          LIMIT 1";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$addressee_identifier, $addressee_identifier]);
            } else {
                $query = "SELECT id, name, email FROM users 
                          WHERE email = ? 
                          LIMIT 1";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$addressee_identifier]);
            }
            
            $addressee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$addressee) {
                return ['status' => false, 'message' => 'User tidak ditemukan.'];
            }

            $addressee_id = $addressee['id'];

            // Check if trying to add self
            if ($requester_id == $addressee_id) {
                return ['status' => false, 'message' => 'Tidak bisa menambahkan diri sendiri sebagai teman.'];
            }

            // Check if friendships table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'friendships'");
            if ($tableCheck->rowCount() == 0) {
                // Create friendships table
                $createSql = "CREATE TABLE friendships (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    requester_id INT NOT NULL,
                    addressee_id INT NOT NULL,
                    status ENUM('pending', 'accepted', 'declined', 'blocked') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $this->conn->exec($createSql);
            }

            // Check if friendship already exists
            $checkQuery = "SELECT id, status FROM friendships 
                           WHERE (requester_id = ? AND addressee_id = ?) 
                           OR (requester_id = ? AND addressee_id = ?)";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([$requester_id, $addressee_id, $addressee_id, $requester_id]);
            
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                switch ($existing['status']) {
                    case 'pending':
                        return ['status' => false, 'message' => 'Permintaan pertemanan sedang pending.'];
                    case 'accepted':
                        return ['status' => false, 'message' => 'Sudah berteman dengan user ini.'];
                    case 'blocked':
                        return ['status' => false, 'message' => 'Tidak dapat mengirim permintaan pertemanan.'];
                    case 'declined':
                    case 'rejected':
                        // Delete the declined friendship so user can send new request
                        $deleteQuery = "DELETE FROM friendships WHERE id = ?";
                        $deleteStmt = $this->conn->prepare($deleteQuery);
                        $deleteStmt->execute([$existing['id']]);
                        break;
                    default:
                        break;
                }
            }

            // Create friend request
            $insertQuery = "INSERT INTO friendships (requester_id, addressee_id, status) 
                            VALUES (?, ?, 'pending')";
            $insertStmt = $this->conn->prepare($insertQuery);
            
            if ($insertStmt->execute([$requester_id, $addressee_id])) {
                $friendship_id = $this->conn->lastInsertId();
                
                // Create notification for the addressee (if notification function exists)
                try {
                    $this->createFriendNotification($addressee_id, 'friend_request', $requester_id, $friendship_id);
                } catch (Exception $e) {
                    // Ignore notification errors for now
                }
                
                return [
                    'status' => true, 
                    'message' => "Permintaan pertemanan berhasil dikirim ke {$addressee['name']}.",
                    'friend_name' => $addressee['name']
                ];
            }

            return ['status' => false, 'message' => 'Gagal mengirim permintaan pertemanan.'];
        } catch (PDOException $e) {
            error_log("Error sending friend request: " . $e->getMessage());
            return ['status' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()];
        }
    }

    /**
     * Accept friend request
     */
    public function acceptFriendRequest($friendship_id, $user_id) {
        try {
            // Verify this user is the addressee
            $query = "UPDATE friendships 
                      SET status = 'accepted', updated_at = NOW() 
                      WHERE id = :friendship_id AND addressee_id = :user_id AND status = 'pending'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':friendship_id', $friendship_id);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                // Get requester info for notification
                $friendQuery = "SELECT f.requester_id, u.name 
                                FROM friendships f 
                                JOIN users u ON f.requester_id = u.id 
                                WHERE f.id = :friendship_id";
                $friendStmt = $this->conn->prepare($friendQuery);
                $friendStmt->bindParam(':friendship_id', $friendship_id);
                $friendStmt->execute();
                $friendInfo = $friendStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($friendInfo) {
                    // Notify requester that request was accepted
                    $this->createFriendNotification($friendInfo['requester_id'], 'friend_accepted', $user_id, $friendship_id);
                }
                
                return [
                    'status' => true, 
                    'message' => 'Permintaan pertemanan diterima!',
                    'friend_name' => $friendInfo['name'] ?? 'Unknown'
                ];
            }

            return ['status' => false, 'message' => 'Permintaan pertemanan tidak ditemukan.'];
        } catch (PDOException $e) {
            error_log("Error accepting friend request: " . $e->getMessage());
            return ['status' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }

    /**
     * Decline friend request
     */
    public function declineFriendRequest($friendship_id, $user_id) {
        try {
            $query = "UPDATE friendships 
                      SET status = 'declined', updated_at = NOW() 
                      WHERE id = :friendship_id AND addressee_id = :user_id AND status = 'pending'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':friendship_id', $friendship_id);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return ['status' => true, 'message' => 'Permintaan pertemanan ditolak.'];
            }

            return ['status' => false, 'message' => 'Permintaan pertemanan tidak ditemukan.'];
        } catch (PDOException $e) {
            error_log("Error declining friend request: " . $e->getMessage());
            return ['status' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }

    /**
     * Remove/unfriend
     */
    public function removeFriend($friendship_id, $user_id) {
        try {
            $query = "DELETE FROM friendships 
                      WHERE id = :friendship_id 
                      AND (requester_id = :user_id OR addressee_id = :user_id) 
                      AND status = 'accepted'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':friendship_id', $friendship_id);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return ['status' => true, 'message' => 'Teman berhasil dihapus.'];
            }

            return ['status' => false, 'message' => 'Pertemanan tidak ditemukan.'];
        } catch (PDOException $e) {
            error_log("Error removing friend: " . $e->getMessage());
            return ['status' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }

    /**
     * Get user's friends list
     */
    public function getFriends($user_id, $limit = 20, $offset = 0) {
        try {
            $query = "SELECT 
                        uf.friend_id,
                        uf.friend_name,
                        uf.friend_email,
                        uf.friend_code,
                        uf.friend_profile_picture,
                        uf.friendship_date,
                        f.id as friendship_id
                      FROM user_friends uf
                      JOIN friendships f ON (
                          (f.requester_id = uf.user_id AND f.addressee_id = uf.friend_id) OR
                          (f.addressee_id = uf.user_id AND f.requester_id = uf.friend_id)
                      )
                      WHERE uf.user_id = :user_id 
                      ORDER BY uf.friendship_date DESC
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM user_friends WHERE user_id = :user_id";
            $countStmt = $this->conn->prepare($countQuery);
            $countStmt->bindParam(':user_id', $user_id);
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'status' => true,
                'data' => $friends,
                'total' => $total
            ];
        } catch (PDOException $e) {
            error_log("Error fetching friends: " . $e->getMessage());
            return ['status' => false, 'message' => 'Gagal mengambil daftar teman.'];
        }
    }

    /**
     * Get pending friend requests (incoming)
     */
    public function getPendingRequests($user_id) {
        try {
            $query = "SELECT 
                        f.id as friendship_id,
                        f.created_at,
                        u.id as requester_id,
                        u.name as requester_name,
                        u.email as requester_email,
                        u.friend_code as requester_code,
                        u.profile_picture as requester_picture
                      FROM friendships f
                      JOIN users u ON f.requester_id = u.id
                      WHERE f.addressee_id = :user_id AND f.status = 'pending'
                      ORDER BY f.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => true,
                'data' => $requests,
                'count' => count($requests)
            ];
        } catch (PDOException $e) {
            error_log("Error fetching friend requests: " . $e->getMessage());
            return ['status' => false, 'message' => 'Gagal mengambil permintaan pertemanan.'];
        }
    }

    /**
     * Search users for friend requests
     */
    public function searchUsers($query, $user_id, $limit = 10) {
        try {
            $searchTerm = "%{$query}%";
            
            // Check if friend_code column exists first
            $columnExists = false;
            try {
                $checkCol = $this->conn->query("SHOW COLUMNS FROM users LIKE 'friend_code'");
                $columnExists = $checkCol->rowCount() > 0;
            } catch (Exception $e) {
                $columnExists = false;
            }
            
            if ($columnExists) {
                $sql = "SELECT 
                            u.id,
                            u.name,
                            u.email,
                            u.friend_code,
                            u.profile_picture,
                            'none' as friendship_status
                        FROM users u
                        WHERE u.id != ? 
                        AND (u.name LIKE ? OR u.email LIKE ? OR u.friend_code LIKE ?)
                        ORDER BY u.name ASC
                        LIMIT ?";
                        
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([(int)$user_id, $searchTerm, $searchTerm, $searchTerm, (int)$limit]);
            } else {
                $sql = "SELECT 
                            u.id,
                            u.name,
                            u.email,
                            '' as friend_code,
                            u.profile_picture,
                            'none' as friendship_status
                        FROM users u
                        WHERE u.id != ? 
                        AND (u.name LIKE ? OR u.email LIKE ?)
                        ORDER BY u.name ASC
                        LIMIT ?";
                        
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([(int)$user_id, $searchTerm, $searchTerm, (int)$limit]);
            }
            
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => true,
                'data' => $users
            ];
        } catch (PDOException $e) {
            error_log("Error searching users: " . $e->getMessage());
            return ['status' => false, 'message' => 'Gagal mencari pengguna: ' . $e->getMessage()];
        }
    }

    /**
     * Create friend notification
     */
    private function createFriendNotification($user_id, $type, $from_user_id, $friendship_id = null) {
        try {
            // Get sender info
            $userQuery = "SELECT name FROM users WHERE id = :user_id";
            $userStmt = $this->conn->prepare($userQuery);
            $userStmt->bindParam(':user_id', $from_user_id);
            $userStmt->execute();
            $sender = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sender) {
                error_log("Sender not found for friend notification");
                return false;
            }

            $messages = [
                'friend_request' => $sender['name'] . ' mengirim permintaan pertemanan kepada Anda.',
                'friend_accepted' => $sender['name'] . ' menerima permintaan pertemanan Anda.',
                'friend_declined' => $sender['name'] . ' menolak permintaan pertemanan Anda.'
            ];

            // Insert into notifications table with correct structure
            // For friend notifications, capsule_id should be NULL
            $insertQuery = "INSERT INTO notifications (user_id, capsule_id, type, title, message) 
                            VALUES (:user_id, NULL, :type, :title, :message)";
            
            $titles = [
                'friend_request' => '👋 Permintaan Pertemanan',
                'friend_accepted' => '🎉 Permintaan Pertemanan Diterima',
                'friend_declined' => '😔 Permintaan Pertemanan Ditolak'
            ];
            
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->bindParam(':user_id', $user_id);
            $insertStmt->bindParam(':type', $type);
            $insertStmt->bindParam(':title', $titles[$type]);
            $insertStmt->bindParam(':message', $messages[$type]);
            
            return $insertStmt->execute();
        } catch (PDOException $e) {
            error_log("Error creating friend notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get friend notifications
     */
    public function getFriendNotifications($user_id, $limit = 10) {
        try {
            $query = "SELECT 
                        fn.*,
                        u.name as from_user_name,
                        u.profile_picture as from_user_picture
                      FROM friend_notifications fn
                      JOIN users u ON fn.from_user_id = u.id
                      WHERE fn.user_id = :user_id
                      ORDER BY fn.created_at DESC
                      LIMIT :limit";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => true,
                'data' => $notifications
            ];
        } catch (PDOException $e) {
            error_log("Error fetching friend notifications: " . $e->getMessage());
            return ['status' => false, 'message' => 'Gagal mengambil notifikasi pertemanan.'];
        }
    }

    /**
     * Check if users are friends
     */
    public function areFriends($user1_id, $user2_id) {
        try {
            $query = "SELECT id FROM friendships 
                      WHERE ((requester_id = :user1 AND addressee_id = :user2) 
                      OR (requester_id = :user2 AND addressee_id = :user1))
                      AND status = 'accepted'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user1', $user1_id);
            $stmt->bindParam(':user2', $user2_id);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error checking friendship: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's friend code
     */
    public function getUserFriendCode($user_id) {
        try {
            // First check if friend_code column exists
            $columnCheck = $this->conn->query("SHOW COLUMNS FROM users LIKE 'friend_code'");
            if ($columnCheck->rowCount() == 0) {
                return null; // Column doesn't exist
            }
            
            $query = "SELECT friend_code FROM users WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int)$user_id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $friendCode = $result ? $result['friend_code'] : null;
            
            // If user doesn't have friend code, generate one
            if (empty($friendCode)) {
                $friendCode = $this->generateFriendCode($user_id);
            }
            
            return $friendCode;
        } catch (PDOException $e) {
            error_log("Error getting friend code: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate unique friend code for user
     */
    private function generateFriendCode($user_id) {
        try {
            $attempts = 0;
            do {
                $friendCode = strtoupper(substr(uniqid(), -6));
                
                // Check if code already exists
                $checkStmt = $this->conn->prepare("SELECT id FROM users WHERE friend_code = ?");
                $checkStmt->execute([$friendCode]);
                $exists = $checkStmt->rowCount() > 0;
                
                $attempts++;
                if ($attempts > 10) {
                    $friendCode = strtoupper(substr(uniqid() . rand(10,99), -8));
                    break;
                }
            } while ($exists);
            
            // Update user with new friend code
            $updateStmt = $this->conn->prepare("UPDATE users SET friend_code = ? WHERE id = ?");
            if ($updateStmt->execute([$friendCode, $user_id])) {
                return $friendCode;
            }
            
            return null;
        } catch (PDOException $e) {
            error_log("Error generating friend code: " . $e->getMessage());
            return null;
        }
    }
}
?>