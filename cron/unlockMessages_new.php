<?php
// Cron job to unlock messages when their scheduled time arrives
// Run this script via CLI: php cron/unlockMessages.php
// Add to crontab: * * * * * /usr/bin/php /path/to/gamon/cron/unlockMessages.php >> /var/log/gamon_unlock.log 2>&1

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/NotificationController.php';

class UnlockScheduler {
    private $conn;
    private $notificationController;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->notificationController = new NotificationController();
        
        if (!$this->conn) {
            throw new Exception("Database connection failed!");
        }
    }

    public function run() {
        echo "[" . date('Y-m-d H:i:s') . "] Starting message unlock process...\n";
        
        try {
            // 1. Find locked messages that are past due
            $sql = "SELECT id, receiver_id, title, scheduled_open_at 
                    FROM messages 
                    WHERE status = 'locked' 
                    AND scheduled_open_at <= NOW()
                    ORDER BY scheduled_open_at ASC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $count = count($messages);
            echo "Found {$count} messages to unlock.\n";

            if ($count > 0) {
                $this->unlockMessages($messages);
            } else {
                echo "✓ No messages to unlock at this time.\n";
            }

            // 2. Cleanup old notifications and audit logs
            $this->performCleanup();
            
        } catch (Exception $e) {
            echo "✗ Error in unlock process: " . $e->getMessage() . "\n";
            error_log("Cron unlock error: " . $e->getMessage());
            throw $e;
        }

        echo "[" . date('Y-m-d H:i:s') . "] Message unlock process completed.\n";
    }

    private function unlockMessages($messages) {
        // Begin transaction for atomicity
        $this->conn->beginTransaction();
        
        try {
            $successCount = 0;
            
            foreach ($messages as $msg) {
                // 1. Update message status
                $updateQuery = "UPDATE messages SET status = 'unlocked', updated_at = NOW() WHERE id = :id";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->execute([':id' => $msg['id']]);
                
                // 2. Create notification for receiver
                $notificationResult = $this->notificationController->createUnlockNotification($msg['id']);
                
                if ($notificationResult['status']) {
                    echo "✓ Unlocked message ID {$msg['id']} '{$msg['title']}' for user {$msg['receiver_id']}\n";
                    $successCount++;
                } else {
                    echo "⚠ Unlocked message ID {$msg['id']} but failed to create notification: {$notificationResult['message']}\n";
                }
                
                // 3. Log audit trail
                $this->logUnlockAction($msg['id']);
            }
            
            $this->conn->commit();
            echo "✓ Successfully processed {$successCount} of " . count($messages) . " messages.\n";
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Failed to unlock messages: " . $e->getMessage());
        }
    }

    private function logUnlockAction($message_id) {
        try {
            $auditQuery = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                          VALUES (NULL, 'CRON_UNLOCK_MESSAGE', 'messages', :record_id, :old_values, :new_values, 'CRON_SERVER', 'GAMON_UNLOCK_SCHEDULER')";
            
            $auditStmt = $this->conn->prepare($auditQuery);
            $auditStmt->execute([
                ':record_id' => $message_id,
                ':old_values' => json_encode(['status' => 'locked']),
                ':new_values' => json_encode(['status' => 'unlocked', 'updated_at' => date('Y-m-d H:i:s')])
            ]);
        } catch (Exception $e) {
            // Don't fail the main process for logging issues
            echo "⚠ Audit log warning for message {$message_id}: " . $e->getMessage() . "\n";
        }
    }

    private function performCleanup() {
        try {
            // 1. Delete old audit logs (keep last 90 days)
            $cleanupQuery = "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
            $cleanupStmt = $this->conn->prepare($cleanupQuery);
            $cleanupStmt->execute();
            $deleted_logs = $cleanupStmt->rowCount();
            
            if ($deleted_logs > 0) {
                echo "✓ Cleaned up {$deleted_logs} old audit logs.\n";
            }

            // 2. Delete old read notifications (keep last 30 days)
            $notifCleanupQuery = "DELETE FROM notifications 
                                 WHERE is_read = 1 
                                 AND read_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $notifCleanupStmt = $this->conn->prepare($notifCleanupQuery);
            $notifCleanupStmt->execute();
            $deleted_notifs = $notifCleanupStmt->rowCount();
            
            if ($deleted_notifs > 0) {
                echo "✓ Cleaned up {$deleted_notifs} old read notifications.\n";
            }

        } catch (Exception $e) {
            echo "⚠ Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

// Script execution
try {
    $scheduler = new UnlockScheduler();
    $scheduler->run();
    exit(0);
} catch (Exception $e) {
    echo "✗ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>