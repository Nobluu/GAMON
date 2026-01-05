<?php
header('Content-Type: application/json');
require_once 'controllers/Auth.php';
require_once 'controllers/NotificationController.php';

$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode(['status' => false, 'message' => 'Not authenticated']);
    exit;
}

$user = $auth->getCurrentUser();
$notificationController = new NotificationController();

// Handle different actions
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'count':
        // Get unread notification count
        $result = $notificationController->getUnreadCount($user['id']);
        echo json_encode($result);
        break;
    
    case 'recent':
        // Get recent notifications
        $limit = (int)($_GET['limit'] ?? 5);
        $result = $notificationController->getRecentNotifications($user['id'], $limit);
        echo json_encode($result);
        break;
    
    case 'mark_read':
        // Mark notification as read
        if (isset($_POST['notification_id'])) {
            $result = $notificationController->markNotificationAsRead($_POST['notification_id'], $user['id']);
            echo json_encode($result);
        } else {
            echo json_encode(['status' => false, 'message' => 'Missing notification ID']);
        }
        break;
    
    case 'check_unlocked':
        // Check for newly unlocked capsules
        $result = $notificationController->checkAndCreateUnlockNotifications();
        echo json_encode($result);
        break;
    
    default:
        echo json_encode(['status' => false, 'message' => 'Invalid action']);
}
?>