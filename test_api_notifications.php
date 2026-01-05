<?php
// Test API notifications endpoint
require_once 'controllers/Auth.php';
require_once 'controllers/NotificationController.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo "Not logged in. Please login first.\n";
    exit;
}

$user = $auth->getCurrentUser();
$notificationController = new NotificationController();

echo "=== TESTING API NOTIFICATIONS ===\n";
echo "User ID: {$user['id']}\n\n";

// Test 1: Get unread count
echo "1. Testing getUnreadCount:\n";
$count_result = $notificationController->getUnreadCount($user['id']);
print_r($count_result);
echo "\n";

// Test 2: Get recent notifications
echo "2. Testing getRecentNotifications:\n";
$recent_result = $notificationController->getRecentNotifications($user['id'], 5);
print_r($recent_result);
echo "\n";

// Test 3: Test API endpoint directly
echo "3. Testing API endpoint behavior:\n";
$_GET['action'] = 'recent';
$_GET['limit'] = '5';

ob_start();
include 'api/notifications.php';
$api_output = ob_get_clean();

echo "API Output:\n";
echo $api_output;
echo "\n";

echo "=== TESTING COMPLETE ===\n";
?>