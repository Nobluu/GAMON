<?php
require_once __DIR__ . '/../config/database.php';

class MessageController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Create a new capsule message
     */
    public function createMessage($sender_id, $receiver_email, $title, $content, $mood_id, $scheduled_open_at, $is_anonymous = 0, $visibility = 'private') {
        try {
            // 1. Validate inputs
            if (empty($title) || empty($content) || empty($receiver_email) || empty($scheduled_open_at)) {
                return ['status' => false, 'message' => 'Missing required fields.'];
            }

            // 2. Validate scheduled time (must be in future)
            $now = new DateTime();
            $open_time = new DateTime($scheduled_open_at);
            if ($open_time <= $now) {
                return ['status' => false, 'message' => 'Scheduled time must be in the future.'];
            }

            // 3. Find Receiver ID
            $receiver_id = $this->getUserByEmail($receiver_email);
            if (!$receiver_id) {
                return ['status' => false, 'message' => 'Receiver email not found.'];
            }

            // 4. Validate mood exists
            if (!$this->validateMoodExists($mood_id)) {
                return ['status' => false, 'message' => 'Invalid mood selected.'];
            }

            // 5. Allow self-messaging for personal capsules
            // if ($receiver_id == $sender_id && $visibility !== 'private') {
            //     return ['status' => false, 'message' => 'You can only send private capsules to yourself.'];
            // }

            // 6. Sanitize inputs
            $title = htmlspecialchars(strip_tags($title));
            $content = htmlspecialchars($content); // Keep some HTML for rich text
            
            // 7. Insert Message
            $query = "INSERT INTO messages (sender_id, receiver_id, title, content, mood_id, scheduled_open_at, is_anonymous, visibility, status) 
                      VALUES (:sender_id, :receiver_id, :title, :content, :mood_id, :scheduled_open_at, :is_anonymous, :visibility, 'locked')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':sender_id', $sender_id, PDO::PARAM_INT);
            $stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':mood_id', $mood_id, PDO::PARAM_INT);
            $stmt->bindParam(':scheduled_open_at', $scheduled_open_at);
            $stmt->bindParam(':is_anonymous', $is_anonymous, PDO::PARAM_INT);
            $stmt->bindParam(':visibility', $visibility);

            if ($stmt->execute()) {
                $message_id = $this->conn->lastInsertId();
                
                // Log the action
                $this->logAudit($sender_id, 'CREATE_MESSAGE', 'messages', $message_id, null, [
                    'title' => $title,
                    'receiver_id' => $receiver_id,
                    'scheduled_open_at' => $scheduled_open_at
                ]);

                return [
                    'status' => true, 
                    'message' => 'Capsule successfully created! It will unlock on ' . $scheduled_open_at,
                    'message_id' => $message_id
                ];
            }

            return ['status' => false, 'message' => 'Failed to create capsule.'];
        } catch (PDOException $e) {
            error_log("Error creating message: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to create capsule.'];
        }
    }

    /**
     * Get messages for user with filtering and pagination
     */
    public function getMessages($user_id, $status = null, $mood_id = null, $limit = 20, $offset = 0, $search = null) {
        try {
            $conditions = ["(m.sender_id = :user_id OR m.receiver_id = :user_id)"];
            $params = [':user_id' => $user_id];

            // Add status filter
            if ($status && in_array($status, ['locked', 'unlocked', 'opened'])) {
                $conditions[] = "m.status = :status";
                $params[':status'] = $status;
            }

            // Add mood filter
            if ($mood_id && is_numeric($mood_id)) {
                $conditions[] = "m.mood_id = :mood_id";
                $params[':mood_id'] = $mood_id;
            }

            // Add search filter
            if ($search) {
                $conditions[] = "(MATCH(m.title, m.content) AGAINST(:search IN NATURAL LANGUAGE MODE) OR m.title LIKE :search_like OR m.content LIKE :search_like)";
                $params[':search'] = $search;
                $params[':search_like'] = '%' . $search . '%';
            }

            $whereClause = implode(' AND ', $conditions);

            $query = "SELECT m.*, 
                             mood.name as mood_name, mood.emoji as mood_emoji, mood.color as mood_color,
                             s.name as sender_name, s.email as sender_email,
                             r.name as receiver_name, r.email as receiver_email,
                             (SELECT COUNT(*) FROM message_media mm WHERE mm.message_id = m.id) as media_count
                      FROM messages m 
                      JOIN moods mood ON m.mood_id = mood.id
                      JOIN users s ON m.sender_id = s.id 
                      JOIN users r ON m.receiver_id = r.id 
                      WHERE $whereClause
                      ORDER BY m.created_at DESC 
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind all parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Process messages for display
            foreach ($messages as &$message) {
                $message = $this->processMessageForDisplay($message, $user_id);
            }

            // Get total count for pagination
            $countQuery = "SELECT COUNT(*) as total FROM messages m WHERE $whereClause";
            $countStmt = $this->conn->prepare($countQuery);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'status' => true,
                'data' => $messages,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ];
        } catch (PDOException $e) {
            error_log("Error fetching messages: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to fetch messages.'];
        }
    }

    /**
     * Get user messages with simple pagination (alias for getMessages)
     */
    public function getUserMessages($user_id, $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            $result = $this->getMessages($user_id, null, null, $limit, $offset);
            
            if ($result['status']) {
                return [
                    'status' => true,
                    'messages' => $result['data'] ?? [],
                    'pagination' => $result['pagination'] ?? []
                ];
            } else {
                return ['status' => false, 'messages' => [], 'message' => $result['message']];
            }
        } catch (Exception $e) {
            error_log("Error in getUserMessages: " . $e->getMessage());
            return ['status' => false, 'messages' => [], 'message' => 'Failed to fetch user messages.'];
        }
    }

    /**
     * Get single message details with security check
     */
    public function getMessage($message_id, $user_id) {
        try {
            $query = "SELECT m.*, 
                             mood.name as mood_name, mood.emoji as mood_emoji, mood.color as mood_color,
                             s.name as sender_name, s.email as sender_email,
                             r.name as receiver_name, r.email as receiver_email
                      FROM messages m 
                      JOIN moods mood ON m.mood_id = mood.id
                      JOIN users s ON m.sender_id = s.id 
                      JOIN users r ON m.receiver_id = r.id 
                      WHERE m.id = :message_id LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $message = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$message) {
                return ['status' => false, 'message' => 'Message not found.'];
            }

            // Security: Check if user is sender or receiver
            if ($message['sender_id'] != $user_id && $message['receiver_id'] != $user_id) {
                return ['status' => false, 'message' => 'Unauthorized access.'];
            }

            // Process message for display
            $message = $this->processMessageForDisplay($message, $user_id);
            
            // Get media files
            $message['media'] = $this->getMessageMedia($message_id);

            return ['status' => true, 'data' => $message];
        } catch (PDOException $e) {
            error_log("Error fetching message: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to fetch message.'];
        }
    }

    /**
     * Open/mark message as read (only for receivers when unlocked)
     */
    public function openMessage($message_id, $user_id) {
        try {
            // Get message first
            $result = $this->getMessage($message_id, $user_id);
            if (!$result['status']) {
                return $result;
            }

            $message = $result['data'];

            // Only receiver can open message
            if ($message['receiver_id'] != $user_id) {
                return ['status' => false, 'message' => 'Only the receiver can open this message.'];
            }

            // Message must be unlocked
            if ($message['status'] !== 'unlocked') {
                return ['status' => false, 'message' => 'Message is not yet available to open.'];
            }

            // Update message status to opened
            $query = "UPDATE messages SET status = 'opened', opened_at = NOW() WHERE id = :message_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Log the action
                $this->logAudit($user_id, 'OPEN_MESSAGE', 'messages', $message_id, 
                    ['status' => 'unlocked'], 
                    ['status' => 'opened', 'opened_at' => date('Y-m-d H:i:s')]
                );

                return ['status' => true, 'message' => 'Message opened successfully.'];
            }

            return ['status' => false, 'message' => 'Failed to open message.'];
        } catch (PDOException $e) {
            error_log("Error opening message: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to open message.'];
        }
    }

    /**
     * Get message media files
     */
    public function getMessageMedia($message_id) {
        try {
            $query = "SELECT * FROM message_media WHERE message_id = :message_id ORDER BY uploaded_at ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching message media: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Process message for secure display based on user role and lock status
     */
    private function processMessageForDisplay($message, $current_user_id) {
        // Handle anonymous messages
        if ($message['is_anonymous'] && $message['receiver_id'] == $current_user_id) {
            $message['sender_name'] = 'Anonymous';
            $message['sender_email'] = 'anonymous@gamon.app';
        }

        // Lock Logic: Check if content should be hidden
        $now = new DateTime();
        $scheduled_time = new DateTime($message['scheduled_open_at']);
        
        // For receivers: hide content if still locked by time or status
        if ($message['receiver_id'] == $current_user_id) {
            if ($now < $scheduled_time || $message['status'] === 'locked') {
                $time_left = $this->getTimeRemaining($scheduled_time);
                $message['content'] = "🔒 This capsule will unlock in " . $time_left;
                $message['is_locked_for_display'] = true;
            } else {
                $message['is_locked_for_display'] = false;
            }
        } else {
            // For senders: always show content
            $message['is_locked_for_display'] = false;
        }

        // Add user role context
        $message['user_role'] = ($message['sender_id'] == $current_user_id) ? 'sender' : 'receiver';

        return $message;
    }

    /**
     * Get time remaining until unlock
     */
    private function getTimeRemaining($target_time) {
        $now = new DateTime();
        $diff = $now->diff($target_time);
        
        if ($diff->days > 0) {
            return $diff->days . ' days, ' . $diff->h . ' hours';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hours, ' . $diff->i . ' minutes';
        } else {
            return $diff->i . ' minutes';
        }
    }

    /**
     * Validate if mood exists
     */
    private function validateMoodExists($mood_id) {
        try {
            $query = "SELECT id FROM moods WHERE id = :mood_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':mood_id', $mood_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get user ID by email
     */
    private function getUserByEmail($email) {
        try {
            $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row['id'];
            }
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get a message by ID with user permission check
     */
    public function getMessageById($message_id, $user_id) {
        try {
            $query = "SELECT m.*, 
                             mood.name as mood_name, mood.emoji as mood_emoji, mood.color as mood_color,
                             s.name as sender_name, s.email as sender_email,
                             r.name as receiver_name, r.email as receiver_email
                      FROM messages m 
                      LEFT JOIN moods mood ON m.mood_id = mood.id
                      JOIN users s ON m.sender_id = s.id 
                      JOIN users r ON m.receiver_id = r.id 
                      WHERE m.id = :message_id AND (m.sender_id = :user_id OR m.receiver_id = :user_id)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($message) {
                return $this->processMessageForDisplay($message, $user_id);
            }
            return null;
        } catch (PDOException $e) {
            error_log("Error getting message: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a message (only if sender and not yet opened)
     */
    public function updateMessage($message_id, $user_id, $title, $content, $mood_id, $scheduled_open_at) {
        try {
            // First check if user can edit this message
            $message = $this->getMessageById($message_id, $user_id);
            if (!$message) {
                return ['status' => false, 'message' => 'Pesan tidak ditemukan atau Anda tidak memiliki akses.'];
            }

            // Only sender can edit
            if ($message['sender_id'] != $user_id) {
                return ['status' => false, 'message' => 'Hanya pengirim yang dapat mengedit pesan.'];
            }

            // Cannot edit if message is already opened
            if ($message['status'] === 'opened') {
                return ['status' => false, 'message' => 'Pesan yang sudah dibuka tidak dapat diedit.'];
            }

            // Cannot edit if message is unlocked (ready to be opened)
            $now = new DateTime();
            $scheduled_time = new DateTime($message['scheduled_open_at']);
            if ($scheduled_time <= $now && $message['status'] === 'unlocked') {
                return ['status' => false, 'message' => 'Pesan yang sudah dapat dibuka tidak dapat diedit.'];
            }

            // Validate new scheduled time
            $new_scheduled_time = new DateTime($scheduled_open_at);
            if ($new_scheduled_time <= $now) {
                return ['status' => false, 'message' => 'Waktu buka harus di masa depan.'];
            }

            // Validate mood exists
            if (!$this->validateMoodExists($mood_id)) {
                return ['status' => false, 'message' => 'Mood tidak valid.'];
            }

            // Sanitize inputs
            $title = htmlspecialchars(strip_tags($title));
            $content = htmlspecialchars($content);

            // Store old values for audit log
            $old_values = [
                'title' => $message['title'],
                'content' => $message['content'],
                'mood_id' => $message['mood_id'],
                'scheduled_open_at' => $message['scheduled_open_at']
            ];

            // Update message
            $query = "UPDATE messages SET 
                        title = :title, 
                        content = :content, 
                        mood_id = :mood_id, 
                        scheduled_open_at = :scheduled_open_at,
                        updated_at = CURRENT_TIMESTAMP
                      WHERE id = :message_id AND sender_id = :user_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':mood_id', $mood_id, PDO::PARAM_INT);
            $stmt->bindParam(':scheduled_open_at', $scheduled_open_at);
            $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                // Log audit trail
                $new_values = [
                    'title' => $title,
                    'content' => $content,
                    'mood_id' => $mood_id,
                    'scheduled_open_at' => $scheduled_open_at
                ];
                $this->logAudit($user_id, 'UPDATE', 'messages', $message_id, $old_values, $new_values);

                return ['status' => true, 'message' => 'Pesan berhasil diperbarui.'];
            } else {
                return ['status' => false, 'message' => 'Gagal memperbarui pesan.'];
            }

        } catch (PDOException $e) {
            error_log("Error updating message: " . $e->getMessage());
            return ['status' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }

    /**
     * Delete a message (only if sender and not yet opened)
     */
    public function deleteMessage($message_id, $user_id) {
        try {
            // First check if user can delete this message
            $message = $this->getMessageById($message_id, $user_id);
            if (!$message) {
                return ['status' => false, 'message' => 'Pesan tidak ditemukan atau Anda tidak memiliki akses.'];
            }

            // Only sender can delete
            if ($message['sender_id'] != $user_id) {
                return ['status' => false, 'message' => 'Hanya pengirim yang dapat menghapus pesan.'];
            }

            // Cannot delete if message is already opened
            if ($message['status'] === 'opened') {
                return ['status' => false, 'message' => 'Pesan yang sudah dibuka tidak dapat dihapus.'];
            }

            // Cannot delete if message is unlocked (ready to be opened)
            $now = new DateTime();
            $scheduled_time = new DateTime($message['scheduled_open_at']);
            if ($scheduled_time <= $now && $message['status'] === 'unlocked') {
                return ['status' => false, 'message' => 'Pesan yang sudah dapat dibuka tidak dapat dihapus.'];
            }

            // Begin transaction
            $this->conn->beginTransaction();

            try {
                // Delete message media first
                $media_query = "SELECT filename, file_path FROM message_media WHERE message_id = :message_id";
                $media_stmt = $this->conn->prepare($media_query);
                $media_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
                $media_stmt->execute();
                $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);

                // Delete media records
                $delete_media_query = "DELETE FROM message_media WHERE message_id = :message_id";
                $delete_media_stmt = $this->conn->prepare($delete_media_query);
                $delete_media_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
                $delete_media_stmt->execute();

                // Delete related notifications
                $delete_notif_query = "DELETE FROM notifications WHERE related_id = :message_id AND type LIKE '%message%'";
                $delete_notif_stmt = $this->conn->prepare($delete_notif_query);
                $delete_notif_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
                $delete_notif_stmt->execute();

                // Delete the message
                $delete_query = "DELETE FROM messages WHERE id = :message_id AND sender_id = :user_id";
                $delete_stmt = $this->conn->prepare($delete_query);
                $delete_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
                $delete_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                
                if ($delete_stmt->execute() && $delete_stmt->rowCount() > 0) {
                    // Commit transaction
                    $this->conn->commit();

                    // Delete physical media files
                    foreach ($media_files as $file) {
                        $file_path = __DIR__ . '/../' . $file['file_path'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }

                    // Log audit trail
                    $this->logAudit($user_id, 'DELETE', 'messages', $message_id, $message, null);

                    return ['status' => true, 'message' => 'Pesan "' . $message['title'] . '" berhasil dihapus.'];
                } else {
                    $this->conn->rollback();
                    return ['status' => false, 'message' => 'Gagal menghapus pesan.'];
                }

            } catch (Exception $e) {
                $this->conn->rollback();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("Error deleting message: " . $e->getMessage());
            return ['status' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }

    /**
     * Check if user can edit/delete a message
     */
    public function canModifyMessage($message_id, $user_id) {
        try {
            $message = $this->getMessageById($message_id, $user_id);
            if (!$message) {
                return false;
            }

            // Only sender can modify
            if ($message['sender_id'] != $user_id) {
                return false;
            }

            // Cannot modify if already opened
            if ($message['status'] === 'opened') {
                return false;
            }

            // Cannot modify if unlocked and ready to open
            $now = new DateTime();
            $scheduled_time = new DateTime($message['scheduled_open_at']);
            if ($scheduled_time <= $now && $message['status'] === 'unlocked') {
                return false;
            }

            return true;

        } catch (PDOException $e) {
            error_log("Error checking message modify permission: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log audit trail
     */
    private function logAudit($user_id, $action, $table_name, $record_id = null, $old_values = null, $new_values = null) {
        try {
            $query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                      VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':table_name', $table_name);
            $stmt->bindParam(':record_id', $record_id, PDO::PARAM_INT);
            $stmt->bindParam(':old_values', $old_values ? json_encode($old_values) : null);
            $stmt->bindParam(':new_values', $new_values ? json_encode($new_values) : null);
            $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
            $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
            
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error logging audit: " . $e->getMessage());
        }
    }
}
?>
