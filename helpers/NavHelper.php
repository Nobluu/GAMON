<?php
require_once __DIR__ . '/../controllers/FriendController.php';

class NavHelper {
    
    /**
     * Get count of pending friend requests for a user
     */
    public static function getPendingFriendRequestsCount($user_id) {
        try {
            $friendController = new FriendController();
            $result = $friendController->getPendingRequests($user_id);
            
            if ($result['status']) {
                return $result['count'] ?? 0;
            }
            
            return 0;
        } catch (Exception $e) {
            error_log("Error getting pending friend requests count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Generate navigation links with friend request badge
     */
    public static function generateNavLinks($current_page = '', $user_id = null) {
        $friend_requests_count = 0;
        if ($user_id) {
            $friend_requests_count = self::getPendingFriendRequestsCount($user_id);
        }
        
        $badge = $friend_requests_count > 0 ? 
            " <span class=\"nav-badge\">{$friend_requests_count}</span>" : '';
        
        $links = [
            'dashboard' => '<a href="dashboard.php"' . ($current_page === 'dashboard' ? ' style="color: #f25c5c;"' : '') . '>Beranda</a>',
            'create-message' => '<a href="create-message.php"' . ($current_page === 'create-message' ? ' style="color: #f25c5c;"' : '') . '>Buat Kapsul</a>',
            'view-message' => '<a href="view-message.php"' . ($current_page === 'view-message' ? ' style="color: #f25c5c;"' : '') . '>Kapsul Saya</a>',
            'send-to-friend' => '<a href="send-to-friend.php"' . ($current_page === 'send-to-friend' ? ' style="color: #f25c5c;"' : '') . '>Kirim ke Teman</a>',
            'friend-messages' => '<a href="friend-messages.php"' . ($current_page === 'friend-messages' ? ' style="color: #f25c5c;"' : '') . '>Pesan Teman</a>',
            'friends' => '<a href="friends.php"' . ($current_page === 'friends' ? ' style="color: #f25c5c;"' : '') . '>Kelola Teman</a>' . $badge,
            'calendar' => '<a href="calendar.php"' . ($current_page === 'calendar' ? ' style="color: #f25c5c;"' : '') . '>Kalender</a>',
        ];
        
        return $links;
    }
    
    /**
     * Get CSS for navigation badge
     */
    public static function getNavBadgeCSS() {
        return '
        .nav-badge {
            background: #ef4444;
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 0.3rem;
            display: inline-block;
            min-width: 1.2rem;
            text-align: center;
            line-height: 1;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }';
    }
}
?>