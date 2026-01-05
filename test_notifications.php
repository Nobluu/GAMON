<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔔 Test Notifikasi - GAMON</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .btn { padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; display: inline-block; cursor: pointer; border: none; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔔 Test Sistem Notifikasi</h1>
        
        <?php
        require_once 'controllers/Auth.php';
        require_once 'controllers/NotificationController.php';
        
        $auth = new Auth();
        
        if (!$auth->isLoggedIn()) {
            echo "<div class='status error'>❌ Anda harus login untuk test notifikasi. <a href='login.php'>Login disini</a></div>";
            exit;
        }

        $user = $auth->getCurrentUser();
        $notificationController = new NotificationController();
        
        echo "<div class='status info'>";
        echo "<strong>User:</strong> " . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ")<br>";
        echo "<strong>User ID:</strong> " . $user['id'];
        echo "</div>";

        // Handle actions
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            switch ($action) {
                case 'create_test_notification':
                    // Create a test notification manually
                    try {
                        $stmt = $notificationController->conn ?? null;
                        if (!$stmt) {
                            $database = new Database();
                            $conn = $database->getConnection();
                        } else {
                            $conn = $stmt;
                        }
                        
                        $query = "INSERT INTO notifications (user_id, type, title, message, action_url, priority, created_at) 
                                  VALUES (?, 'system_announcement', '🧪 Test Notification', 'Ini adalah notifikasi test yang dibuat secara manual untuk menguji sistem notifikasi.', 'test_notifications.php', 'normal', NOW())";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$user['id']]);
                        
                        echo "<div class='status success'>✅ Test notification berhasil dibuat!</div>";
                    } catch (Exception $e) {
                        echo "<div class='status error'>❌ Error: " . $e->getMessage() . "</div>";
                    }
                    break;
                
                case 'check_unlock':
                    $result = $notificationController->checkAndCreateUnlockNotifications();
                    if ($result['status']) {
                        echo "<div class='status success'>✅ Cek unlock selesai. Notifikasi dibuat: " . $result['notifications_created'] . "</div>";
                    } else {
                        echo "<div class='status error'>❌ Error cek unlock</div>";
                    }
                    break;
                
                case 'clear_notifications':
                    try {
                        $database = new Database();
                        $conn = $database->getConnection();
                        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                        $stmt->execute([$user['id']]);
                        echo "<div class='status success'>✅ Semua notifikasi ditandai sudah dibaca</div>";
                    } catch (Exception $e) {
                        echo "<div class='status error'>❌ Error: " . $e->getMessage() . "</div>";
                    }
                    break;
            }
        }

        // Display current notifications
        echo "<h3>📋 Notifikasi Saat Ini</h3>";
        $notificationResult = $notificationController->getUserNotifications($user['id'], null, 10, 0);
        
        if ($notificationResult['status'] && !empty($notificationResult['data'])) {
            foreach ($notificationResult['data'] as $notif) {
                $readStatus = $notif['is_read'] ? '✅ Dibaca' : '🔔 Belum dibaca';
                $priority = $notif['priority'] ?? 'normal';
                $priorityColor = $priority === 'high' ? 'red' : ($priority === 'low' ? 'gray' : 'blue');
                
                echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px 0; border-radius: 5px; background: " . ($notif['is_read'] ? '#f8f9fa' : '#fff3cd') . ";'>";
                echo "<strong>" . htmlspecialchars($notif['title']) . "</strong> ";
                echo "<span style='color: $priorityColor; font-size: 0.8em; background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 3px;'>$priority</span><br>";
                echo "<small>" . htmlspecialchars($notif['message']) . "</small><br>";
                echo "<em style='color: #666; font-size: 0.8em;'>$readStatus | " . $notif['created_at'] . "</em>";
                echo "</div>";
            }
        } else {
            echo "<div class='status info'>📭 Tidak ada notifikasi</div>";
        }

        // Get unread count
        $countResult = $notificationController->getUnreadCount($user['id']);
        if ($countResult['status']) {
            echo "<div class='status info'><strong>Notifikasi belum dibaca:</strong> " . $countResult['count'] . "</div>";
        }
        ?>
        
        <h3>🧪 Test Actions</h3>
        <form method="POST" style="margin: 10px 0;">
            <button type="submit" name="action" value="create_test_notification" class="btn btn-primary">
                🔔 Buat Test Notification
            </button>
        </form>
        
        <form method="POST" style="margin: 10px 0;">
            <button type="submit" name="action" value="check_unlock" class="btn btn-success">
                🔓 Cek Capsule Unlock
            </button>
        </form>
        
        <form method="POST" style="margin: 10px 0;">
            <button type="submit" name="action" value="clear_notifications" class="btn btn-danger">
                ✅ Tandai Semua Sudah Dibaca
            </button>
        </form>

        <hr>
        <h3>📋 Quick Navigation</h3>
        <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
        <a href="notifications.php" class="btn btn-primary">Lihat Semua Notifikasi</a>
        <a href="api/notifications.php?action=count" class="btn btn-primary" target="_blank">Test API Count</a>
        <a href="api/notifications.php?action=recent" class="btn btn-primary" target="_blank">Test API Recent</a>
        
    </div>

    <script>
        // Auto refresh setiap 10 detik untuk melihat perubahan real-time
        setTimeout(() => {
            window.location.reload();
        }, 10000);
    </script>
</body>
</html>