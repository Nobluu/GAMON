<?php
// Run this script via CLI: php cron/unlockMessages.php

require_once __DIR__ . '/../config/database.php';

class UnlockScheduler {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function run() {
        echo "[" . date('Y-m-d H:i:s') . "] Starting unlock process...\n";
        
        try {
            // 1. Find locked messages that are past due
            // We select ID to minimize memory usage
            $sql = "SELECT id, receiver_id FROM messages 
                    WHERE status = 'locked' AND open_at <= NOW()";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $count = count($messages);
            echo "Found {$count} messages to unlock.\n";

            if ($count > 0) {
                // 2. Unlock them in a batch or loop
                // Using transaction for safety
                $this->conn->beginTransaction();

                $updateSql = "UPDATE messages SET status = 'unlocked' WHERE id = :id";
                $updateStmt = $this->conn->prepare($updateSql);

                // Prepare notification insertion
                $notifSql = "INSERT INTO notifications (user_id, message_id, scheduled_at, status) 
                             VALUES (:user_id, :message_id, NOW(), 'pending')";
                $notifStmt = $this->conn->prepare($notifSql);

                foreach ($messages as $msg) {
                    // Update Status
                    $updateStmt->execute([':id' => $msg['id']]);

                    // Schedule Notification
                    $notifStmt->execute([
                        ':user_id' => $msg['receiver_id'],
                        ':message_id' => $msg['id']
                    ]);
                }

                $this->conn->commit();
                echo "Successfully unlocked {$count} messages and scheduled notifications.\n";
            }

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}

// Execute
$scheduler = new UnlockScheduler();
$scheduler->run();
?>
