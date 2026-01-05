<?php
require_once 'controllers/Auth.php';
require_once 'controllers/NotificationController.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo "Not logged in";
    exit;
}

$user = $auth->getCurrentUser();
$notificationController = new NotificationController();

echo "<h2>🔍 Debug Notifikasi</h2>";
echo "<p><strong>User ID:</strong> " . $user['id'] . "</p>";
echo "<p><strong>User Name:</strong> " . $user['name'] . "</p>";

echo "<h3>📊 Test getUnreadCount()</h3>";
$unread_result = $notificationController->getUnreadCount($user['id']);
echo "<pre>";
print_r($unread_result);
echo "</pre>";

echo "<h3>📋 Test getUserNotifications()</h3>";
$notifications_result = $notificationController->getUserNotifications($user['id'], null, 10, 0);
echo "<pre>";
print_r($notifications_result);
echo "</pre>";

echo "<h3>🔍 Raw Query Check</h3>";
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h4>Check notifications table:</h4>";
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $direct_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($direct_notifications) . " notifications in notifications table:</p>";
    echo "<pre>";
    print_r($direct_notifications);
    echo "</pre>";
    
    echo "<h4>Check friend_notifications table:</h4>";
    $stmt2 = $conn->prepare("SELECT * FROM friend_notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt2->execute([$user['id']]);
    $friend_notifications = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($friend_notifications) . " notifications in friend_notifications table:</p>";
    echo "<pre>";
    print_r($friend_notifications);
    echo "</pre>";
    
    echo "<h4>Combined Count Query:</h4>";
    $count_query = "SELECT 
        (SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0) +
        (SELECT COUNT(*) FROM friend_notifications WHERE user_id = ? AND is_read = 0) 
        as total_unread";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute([$user['id'], $user['id']]);
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Direct count query result:</p>";
    echo "<pre>";
    print_r($count_result);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<p><a href='notifications.php'>← Back to Notifications</a></p>";
echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>