<?php
// Cron job to send email notifications (optional)
// Run this script via CLI: php cron/sendNotifications.php
// Add to crontab: */5 * * * * /usr/bin/php /path/to/gamon/cron/sendNotifications.php >> /var/log/gamon_notifications.log 2>&1

require_once __DIR__ . '/../config/database.php';

class NotificationSender {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if (!$this->conn) {
            throw new Exception("Database connection failed!");
        }
    }

    public function run() {
        echo "[" . date('Y-m-d H:i:s') . "] Starting notification sender...\n";
        
        try {
            // Find recent unread notifications (last 5 minutes to avoid spam)
            $query = "SELECT n.*, u.email, u.name, m.title as message_title
                      FROM notifications n
                      JOIN users u ON n.user_id = u.id
                      JOIN messages m ON n.message_id = m.id
                      WHERE n.is_read = 0 
                      AND n.type = 'message_unlocked'
                      AND n.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                      ORDER BY n.created_at ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $count = count($notifications);
            echo "Found {$count} recent notifications to process.\n";

            if ($count > 0) {
                $this->sendNotifications($notifications);
            } else {
                echo "✓ No notifications to send at this time.\n";
            }
            
        } catch (Exception $e) {
            echo "✗ Error in notification sender: " . $e->getMessage() . "\n";
            error_log("Notification sender error: " . $e->getMessage());
            throw $e;
        }

        echo "[" . date('Y-m-d H:i:s') . "] Notification sender completed.\n";
    }

    private function sendNotifications($notifications) {
        $successCount = 0;
        
        foreach ($notifications as $notification) {
            try {
                // For now, we'll just log what would be sent
                // In production, integrate with PHPMailer, SendGrid, or similar
                
                $emailSent = $this->sendEmail(
                    $notification['email'],
                    $notification['name'],
                    $notification['title'],
                    $notification['content'],
                    $notification['message_title']
                );
                
                if ($emailSent) {
                    echo "✓ Notification sent to {$notification['email']} for message '{$notification['message_title']}'\n";
                    $successCount++;
                } else {
                    echo "⚠ Failed to send notification to {$notification['email']}\n";
                }
                
            } catch (Exception $e) {
                echo "⚠ Error sending to {$notification['email']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "✓ Successfully sent {$successCount} of {$count} notifications.\n";
    }

    private function sendEmail($email, $name, $title, $content, $message_title) {
        // Mock email sending - replace with actual email service
        // Return true/false based on success
        
        // Example with mail() function (not recommended for production)
        /*
        $subject = "GAMON - " . $title;
        $body = "Hi {$name},\n\n{$content}\n\nLog in to GAMON to read your message.\n\nBest regards,\nGAMON Team";
        $headers = "From: noreply@gamon.app\r\n";
        $headers .= "Reply-To: noreply@gamon.app\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        return mail($email, $subject, $body, $headers);
        */
        
        // For development, just log the email
        echo "  [MOCK EMAIL] To: {$email} | Subject: {$title} | Message: {$message_title}\n";
        return true; // Simulate success
    }
}

// Script execution  
try {
    $sender = new NotificationSender();
    $sender->run();
    exit(0);
} catch (Exception $e) {
    echo "✗ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>