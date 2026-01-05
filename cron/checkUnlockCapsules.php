<?php
// Cron job untuk mengecek dan membuka kapsul serta membuat notifikasi
// Jalankan setiap menit: * * * * * php /path/to/gamon/cron/checkUnlockCapsules.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/NotificationController.php';
require_once __DIR__ . '/../config/timezone.php';

class CapsuleUnlockChecker {
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
        $startTime = date('Y-m-d H:i:s');
        echo "[$startTime] Starting capsule unlock check process...\n";
        
        try {
            // Check for newly unlocked capsules and create notifications
            $result = $this->notificationController->checkAndCreateUnlockNotifications();
            
            if ($result['status']) {
                $count = $result['notifications_created'];
                echo "✓ Successfully created $count unlock notifications\n";
                
                // Log ke file untuk monitoring
                $this->logActivity($count);
                
            } else {
                echo "⚠ Failed to check unlock notifications\n";
                error_log("Capsule unlock check failed");
            }

        } catch (Exception $e) {
            echo "✗ Error in unlock check process: " . $e->getMessage() . "\n";
            error_log("Cron unlock check error: " . $e->getMessage());
            throw $e;
        }

        $endTime = date('Y-m-d H:i:s');
        echo "[$endTime] Capsule unlock check process completed.\n\n";
    }

    private function logActivity($notificationCount) {
        $logFile = __DIR__ . '/../logs/unlock_activity.log';
        $logDir = dirname($logFile);
        
        // Create logs directory if not exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = date('Y-m-d H:i:s') . " - Created $notificationCount unlock notifications\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Method untuk membersihkan notifikasi lama (opsional)
     */
    public function cleanupOldNotifications() {
        echo "Cleaning up old notifications...\n";
        $result = $this->notificationController->deleteOldNotifications(30); // 30 hari
        
        if ($result['status']) {
            echo "✓ " . $result['message'] . "\n";
        } else {
            echo "⚠ Failed to cleanup old notifications\n";
        }
    }
}

// Jalankan script
try {
    $checker = new CapsuleUnlockChecker();
    $checker->run();
    
    // Cleanup old notifications sekali per hari (bisa ditambahkan condition)
    if (date('H:i') === '01:00') { // Run cleanup at 1 AM
        $checker->cleanupOldNotifications();
    }
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    error_log("FATAL: Capsule unlock cron error - " . $e->getMessage());
    exit(1);
}
?>