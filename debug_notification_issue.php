<?php
require_once 'controllers/Auth.php';
require_once 'controllers/NotificationController.php';
require_once 'config/database.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$notificationController = new NotificationController();

echo "<h2>🔍 Debug Notification Issue</h2>";
echo "<p><strong>User ID:</strong> " . $user['id'] . "</p>";

echo "<h3>📊 Unread Count Check</h3>";
$unread_result = $notificationController->getUnreadCount($user['id']);
echo "<pre>";
print_r($unread_result);
echo "</pre>";

echo "<h3>📋 Get User Notifications</h3>";
$notifications_result = $notificationController->getUserNotifications($user['id'], null, 10, 0);
echo "<pre>";
print_r($notifications_result);
echo "</pre>";

echo "<h3>🔍 Raw Database Check</h3>";
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h4>All notifications for user:</h4>";
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $all_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($all_notifications) . " total notifications:</p>";
    echo "<pre>";
    print_r($all_notifications);
    echo "</pre>";
    
    echo "<h4>Unread notifications count:</h4>";
    $unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $unread_stmt->execute([$user['id']]);
    $unread_count = $unread_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Direct unread count: " . $unread_count['count'] . "</p>";
    
    echo "<h4>Check table structure:</h4>";
    $desc_stmt = $conn->prepare("DESCRIBE notifications");
    $desc_stmt->execute();
    $table_structure = $desc_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Notifications table structure:</p>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($table_structure as $field) {
        echo "<tr>";
        echo "<td>{$field['Field']}</td>";
        echo "<td>{$field['Type']}</td>";
        echo "<td>{$field['Null']}</td>";
        echo "<td>{$field['Key']}</td>";
        echo "<td>{$field['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>