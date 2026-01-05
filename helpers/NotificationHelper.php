<?php
require_once __DIR__ . '/NotificationController.php';

class NotificationHelper {
    
    /**
     * Get unread notification count for navigation badge
     */
    public static function getUnreadCount($user_id) {
        try {
            $notificationController = new NotificationController();
            $result = $notificationController->getUnreadCount($user_id);
            return $result['status'] ? $result['count'] : 0;
        } catch (Exception $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Generate notification badge HTML for navigation
     */
    public static function getNotificationBadge($user_id, $current_page = '') {
        $unread_count = self::getUnreadCount($user_id);
        $active_style = $current_page === 'notifications' ? 'color: #f25c5c;' : '';
        
        $badge_html = '';
        if ($unread_count > 0) {
            $count_display = $unread_count > 9 ? '9+' : $unread_count;
            $badge_html = "<span style='position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; display: flex; align-items: center; justify-content: center; font-weight: bold;'>$count_display</span>";
        }
        
        return "<a href='notifications.php' style='position: relative; $active_style'>🔔 Notifikasi$badge_html</a>";
    }
    
    /**
     * Get navigation links with notification badge
     */
    public static function getNavigationLinks($user_id, $current_page = '') {
        $notification_link = self::getNotificationBadge($user_id, $current_page);
        
        $links = [
            'dashboard' => '<a href="dashboard.php"' . ($current_page === 'dashboard' ? ' style="color: #f25c5c;"' : '') . '>Beranda</a>',
            'create' => '<a href="create-message.php"' . ($current_page === 'create' ? ' style="color: #f25c5c;"' : '') . '>Buat Kapsul</a>',
            'view' => '<a href="view-message.php"' . ($current_page === 'view' ? ' style="color: #f25c5c;"' : '') . '>Kapsul Saya</a>',
            'send' => '<a href="send-to-friend.php"' . ($current_page === 'send' ? ' style="color: #f25c5c;"' : '') . '>Kirim ke Teman</a>',
            'friend_messages' => '<a href="friend-messages.php"' . ($current_page === 'friend_messages' ? ' style="color: #f25c5c;"' : '') . '>Pesan Teman</a>',
            'friends' => '<a href="friends.php"' . ($current_page === 'friends' ? ' style="color: #f25c5c;"' : '') . '>Kelola Teman</a>',
            'calendar' => '<a href="calendar.php"' . ($current_page === 'calendar' ? ' style="color: #f25c5c;"' : '') . '>Kalender</a>',
            'notifications' => $notification_link
        ];
        
        return $links;
    }
}
?>