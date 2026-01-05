<?php
require_once __DIR__ . '/../config/database.php';

class NotificationController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Get notifications for user with filtering and pagination (updated for capsules)
     */
    public function getUserNotifications($user_id, $is_read = null, $limit = 20, $offset = 0) {
        try {
            $conditions = ["user_id = ?"];
            $params = [$user_id];

            // Add read filter
            if ($is_read !== null) {
                $conditions[] = "is_read = ?";
                $params[] = $is_read ? 1 : 0;
            }

            $whereClause = implode(' AND ', $conditions);
            
            // Sanitize limit and offset
            $limit = (int)$limit;
            $offset = (int)$offset;

            // Get notifications from the notifications table
            $query = "SELECT * FROM notifications 
                      WHERE $whereClause
                      ORDER BY created_at DESC 
                      LIMIT $limit OFFSET $offset";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count for pagination
            $countQuery = "SELECT COUNT(*) as total FROM notifications WHERE " . $whereClause;
            $countStmt = $this->conn->prepare($countQuery);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'status' => true,
                'data' => $notifications,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ];
        } catch (PDOException $e) {
            error_log("Error fetching notifications: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to fetch notifications.'];
        }
    }

    /**
     * Get unread notification count (updated for capsules)
     */
    public function getUnreadCount($user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'status' => true,
                'count' => (int)$result['count']
            ];
        } catch (PDOException $e) {
            error_log("Error fetching unread count: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to fetch unread count.'];
        }
    }

    /**
     * Mark notification as read (works for both message and friend notifications)
     */
    public function markAsRead($notification_id, $user_id, $notification_type = 'message') {
        try {
            if ($notification_type === 'friend') {
                // Mark friend notification as read
                $query = "UPDATE friend_notifications 
                          SET is_read = 1 
                          WHERE id = :notification_id AND user_id = :user_id";
            } else {
                // Mark regular notification as read
                $query = "UPDATE notifications 
                          SET is_read = 1, read_at = NOW() 
                          WHERE id = :notification_id AND user_id = :user_id";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':notification_id', $notification_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return ['status' => true, 'message' => 'Notification marked as read.'];
                } else {
                    return ['status' => false, 'message' => 'Notification not found.'];
                }
            }
            
            return ['status' => false, 'message' => 'Failed to update notification.'];
        } catch (PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to update notification.'];
        }
    }

    /**
     * Mark all notifications as read for user (includes friend notifications)
     */
    public function markAllAsRead($user_id) {
        try {
            $this->conn->beginTransaction();
            
            // Mark regular notifications as read
            $query1 = "UPDATE notifications 
                       SET is_read = 1, read_at = NOW() 
                       WHERE user_id = :user_id AND is_read = 0";
            
            $stmt1 = $this->conn->prepare($query1);
            $stmt1->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt1->execute();
            $count1 = $stmt1->rowCount();
            
            // Mark friend notifications as read
            $query2 = "UPDATE friend_notifications 
                       SET is_read = 1 
                       WHERE user_id = :user_id AND is_read = 0";
            
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt2->execute();
            $count2 = $stmt2->rowCount();
            
            $this->conn->commit();
            
            $totalCount = $count1 + $count2;
            return ['status' => true, 'message' => "$totalCount notifications marked as read."];
            
        } catch (PDOException $e) {
            $this->conn->rollback();
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to update notifications.'];
        }
    }

    /**
     * Create notification when message is unlocked (legacy)
     */
    public function createMessageUnlockNotification($message_id) {
        try {
            // Get message details
            $query = "SELECT m.receiver_id, m.title, m.sender_id, s.name as sender_name, mood.emoji as mood_emoji
                      FROM messages m 
                      JOIN users s ON m.sender_id = s.id
                      JOIN moods mood ON m.mood_id = mood.id
                      WHERE m.id = :message_id LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$message) {
                return ['status' => false, 'message' => 'Message not found.'];
            }

            // Check if notification already exists (prevent duplicates)
            $checkQuery = "SELECT id FROM notifications 
                           WHERE user_id = :user_id AND message_id = :message_id AND type = 'message_unlocked'";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':user_id', $message['receiver_id'], PDO::PARAM_INT);
            $checkStmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                return ['status' => false, 'message' => 'Notification already exists.'];
            }

            // Create notification
            $title = $message['mood_emoji'] . " Time Capsule Unlocked!";
            $content = "Your capsule \"" . $message['title'] . "\" from " . $message['sender_name'] . " is now ready to open.";

            $insertQuery = "INSERT INTO notifications (user_id, message_id, type, title, content) 
                            VALUES (:user_id, :message_id, 'message_unlocked', :title, :content)";
            
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->bindParam(':user_id', $message['receiver_id'], PDO::PARAM_INT);
            $insertStmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
            $insertStmt->bindParam(':title', $title);
            $insertStmt->bindParam(':content', $content);
            
            if ($insertStmt->execute()) {
                return [
                    'status' => true, 
                    'message' => 'Notification created.',
                    'notification_id' => $this->conn->lastInsertId()
                ];
            }

            return ['status' => false, 'message' => 'Failed to create notification.'];
        } catch (PDOException $e) {
            error_log("Error creating unlock notification: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to create notification.'];
        }
    }

    /**
     * Delete old notifications (cleanup)
     */
    public function deleteOldNotifications($days = 30) {
        try {
            $query = "DELETE FROM notifications 
                      WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $count = $stmt->rowCount();
                return ['status' => true, 'message' => "$count old notifications deleted."];
            }
            
            return ['status' => false, 'message' => 'Failed to delete old notifications.'];
        } catch (PDOException $e) {
            error_log("Error deleting old notifications: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to delete old notifications.'];
        }
    }

    /**
     * Create notification for unlocked capsule
     */
    public function createUnlockNotification($capsule_id, $user_id, $is_friend_message = false) {
        try {
            // Get capsule details
            $stmt = $this->conn->prepare("
                SELECT c.*, u.name as sender_name, m.name as mood_name 
                FROM capsules c 
                LEFT JOIN users u ON c.user_id = u.id 
                LEFT JOIN moods m ON c.mood_id = m.id 
                WHERE c.id = ?
            ");
            $stmt->execute([$capsule_id]);
            $capsule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$capsule) return ['status' => false, 'message' => 'Capsule not found'];

            $title = $is_friend_message 
                ? "💌 Pesan dari " . $capsule['sender_name'] . " sudah terbuka!"
                : "🎉 Kapsul Anda '" . $capsule['title'] . "' sudah terbuka!";
            
            $message = $is_friend_message 
                ? "Pesan dengan mood " . ($capsule['mood_name'] ?? 'Unknown') . " sudah bisa Anda baca sekarang."
                : "Saatnya membuka kapsul yang telah Anda buat dengan mood " . ($capsule['mood_name'] ?? 'Unknown') . ".";
            
            $action_url = $is_friend_message 
                ? "capsule-detail.php?id=" . $capsule_id . "&type=friend"
                : "capsule-detail.php?id=" . $capsule_id;

            $type = $is_friend_message ? 'friend_capsule_unlock' : 'capsule_unlock';

            // Insert notification
            $query = "INSERT INTO notifications (user_id, capsule_id, type, title, message, action_url, priority, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, 'high', NOW())";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $user_id,
                $capsule_id,
                $type,
                $title,
                $message,
                $action_url
            ]);

            return ['status' => true, 'message' => 'Unlock notification created'];
        } catch (PDOException $e) {
            error_log("Error creating unlock notification: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to create notification'];
        }
    }

    /**
     * Create notification for new friend message
     */
    public function createFriendMessageNotification($capsule_id, $recipient_user_id) {
        try {
            // Get message details
            $stmt = $this->conn->prepare("
                SELECT c.*, u.name as sender_name, m.name as mood_name 
                FROM capsules c 
                LEFT JOIN users u ON c.user_id = u.id 
                LEFT JOIN moods m ON c.mood_id = m.id 
                WHERE c.id = ?
            ");
            $stmt->execute([$capsule_id]);
            $capsule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$capsule) return ['status' => false, 'message' => 'Capsule not found'];

            $title = "📨 Pesan baru dari " . $capsule['sender_name'];
            $message = "Anda menerima kapsul baru berjudul '" . $capsule['title'] . "' dengan mood " . ($capsule['mood_name'] ?? 'Unknown') . ".";
            $action_url = "friend-messages.php";

            // Insert notification
            $query = "INSERT INTO notifications (user_id, capsule_id, type, title, message, action_url, priority, created_at) 
                      VALUES (?, ?, 'friend_message_received', ?, ?, ?, 'normal', NOW())";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $recipient_user_id,
                $capsule_id,
                $title,
                $message,
                $action_url
            ]);

            return ['status' => true, 'message' => 'Friend message notification created'];
        } catch (PDOException $e) {
            error_log("Error creating friend message notification: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to create notification'];
        }
    }

    /**
     * Get recent notifications for real-time display (last 5 notifications)
     */
    public function getRecentNotifications($user_id, $limit = 5) {
        try {
            $limit = (int)$limit; // Sanitize limit
            
            $query = "SELECT id, user_id, capsule_id, type, title, message, is_read, created_at
                      FROM notifications 
                      WHERE user_id = ? AND is_read = 0 
                      ORDER BY created_at DESC 
                      LIMIT $limit";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add action_url to each notification
            foreach ($notifications as &$notification) {
                if (strpos($notification['type'], 'friend') !== false) {
                    $notification['action_url'] = 'friends.php';
                } elseif (!empty($notification['capsule_id'])) {
                    $notification['action_url'] = 'view-message.php?id=' . $notification['capsule_id'];
                } else {
                    $notification['action_url'] = 'notifications.php';
                }
            }

            return ['status' => true, 'notifications' => $notifications];
        } catch (PDOException $e) {
            error_log("Error getting recent notifications: " . $e->getMessage());
            return ['status' => false, 'notifications' => [], 'message' => 'Database error'];
        }
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead($notification_id, $user_id) {
        try {
            $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$notification_id, $user_id]);

            return ['status' => true, 'message' => 'Notification marked as read'];
        } catch (PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to mark as read'];
        }
    }

    /**
     * Check for newly unlocked capsules and create notifications
     */
    public function checkAndCreateUnlockNotifications() {
        try {
            // Check for personal capsules that just unlocked
            $query = "SELECT c.id, c.user_id 
                      FROM capsules c 
                      WHERE c.unlock_date <= NOW() 
                      AND c.id NOT IN (
                          SELECT DISTINCT capsule_id 
                          FROM notifications 
                          WHERE type IN ('capsule_unlock', 'friend_capsule_unlock') 
                          AND capsule_id IS NOT NULL
                      )";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $unlocked_capsules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $notifications_created = 0;
            foreach ($unlocked_capsules as $capsule) {
                // Create notification for capsule owner
                $result = $this->createUnlockNotification($capsule['id'], $capsule['user_id'], false);
                if ($result['status']) $notifications_created++;

                // If it's a public capsule, notify friends who should see it
                $friend_stmt = $this->conn->prepare("
                    SELECT DISTINCT f.user_id as friend_id
                    FROM friendships f 
                    JOIN capsules c ON (c.user_id = f.friend_id OR c.user_id = f.user_id)
                    WHERE c.id = ? AND c.public_sharing = 1 
                    AND f.status = 'accepted'
                    AND f.user_id != c.user_id
                ");
                $friend_stmt->execute([$capsule['id']]);
                $friends = $friend_stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($friends as $friend) {
                    $this->createUnlockNotification($capsule['id'], $friend['friend_id'], true);
                    $notifications_created++;
                }
            }

            return ['status' => true, 'notifications_created' => $notifications_created];
        } catch (PDOException $e) {
            error_log("Error checking unlock notifications: " . $e->getMessage());
            return ['status' => false, 'notifications_created' => 0];
        }
    }
}
?>
