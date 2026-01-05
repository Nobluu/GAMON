<?php
// Test script untuk membuat friend notification
require_once 'config/database.php';
require_once 'controllers/FriendController.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get existing users
    $stmt = $conn->query("SELECT id, name, email FROM users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h1>🧪 Test Friend Notifications</h1>";
    echo "<h2>Available Users:</h2>";
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li>ID: {$user['id']} - {$user['name']} ({$user['email']})</li>";
    }
    echo "</ul>";
    
    if (count($users) >= 2) {
        $user1 = $users[0];
        $user2 = $users[1];
        
        echo "<h2>🚀 Testing Friend Request</h2>";
        echo "<p><strong>{$user1['name']}</strong> sending friend request to <strong>{$user2['name']}</strong>...</p>";
        
        $friendController = new FriendController();
        
        // Send friend request from user1 to user2
        $result = $friendController->sendFriendRequest($user1['id'], $user2['email']);
        
        if ($result['status']) {
            echo "<p style='color: green;'>✅ Friend request sent successfully!</p>";
            echo "<p><strong>Message:</strong> " . $result['message'] . "</p>";
            
            // Check notifications for user2
            echo "<h3>📋 Checking notifications for {$user2['name']}...</h3>";
            
            $notifStmt = $conn->prepare("
                SELECT fn.*, u.name as from_name 
                FROM friend_notifications fn
                JOIN users u ON fn.from_user_id = u.id  
                WHERE fn.user_id = ? 
                ORDER BY fn.created_at DESC
                LIMIT 5
            ");
            $notifStmt->execute([$user2['id']]);
            $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($notifications) {
                echo "<div style='background: #f0f9ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
                foreach ($notifications as $notif) {
                    $read_status = $notif['is_read'] ? '✅ Read' : '🔔 Unread';
                    echo "<div style='border-bottom: 1px solid #ccc; padding: 0.5rem 0;'>";
                    echo "<strong>{$notif['title']}</strong> - {$read_status}<br>";
                    echo "From: {$notif['from_name']}<br>";
                    echo "Content: {$notif['content']}<br>";
                    echo "<small>Created: {$notif['created_at']}</small>";
                    echo "</div>";
                }
                echo "</div>";
            } else {
                echo "<p style='color: orange;'>⚠️ No notifications found</p>";
            }
            
            echo "<h3>🔗 Next Steps:</h3>";
            echo "<ol>";
            echo "<li>Login as <strong>{$user2['name']}</strong></li>";
            echo "<li>Go to <a href='notifications.php' target='_blank'>Notifications page</a></li>";
            echo "<li>You should see the friend request notification</li>";
            echo "<li>Go to <a href='friends.php' target='_blank'>Kelola Teman</a> to accept/decline</li>";
            echo "</ol>";
            
        } else {
            echo "<p style='color: red;'>❌ Failed to send friend request</p>";
            echo "<p><strong>Error:</strong> " . $result['message'] . "</p>";
        }
        
    } else {
        echo "<p style='color: orange;'>⚠️ Need at least 2 users to test friend notifications</p>";
        echo "<p><a href='register.php'>Create more users</a> to test the feature</p>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Friend Notifications Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; max-width: 800px; }
        h1, h2, h3 { color: #333; }
        ul, ol { padding-left: 20px; }
        a { color: #f25c5c; text-decoration: none; }
        a:hover { text-decoration: underline; }
        pre { background: #f8f8f8; padding: 1rem; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
</body>
</html>