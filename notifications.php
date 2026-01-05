<?php
require_once 'controllers/Auth.php';
require_once 'controllers/NotificationController.php';
require_once 'helpers/NavHelper.php';

$auth = new Auth();
$auth->requireLogin();

if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
$notificationController = new NotificationController();

// Handle mark as read action
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $result = $notificationController->markNotificationAsRead($_POST['notification_id'], $user['id']);
    header('Location: notifications.php');
    exit;
}

// Handle mark all as read action
if (isset($_POST['mark_all_read'])) {
    $notificationController->markAllAsRead($user['id']);
    header('Location: notifications.php');
    exit;
}

// Get filters from URL
$is_read_filter = isset($_GET['read']) ? ($_GET['read'] === '1' ? 1 : 0) : null;
$page = (int)($_GET['page'] ?? 1);
$limit = 15;
$offset = ($page - 1) * $limit;

// Get notifications
$notifications_result = $notificationController->getUserNotifications($user['id'], $is_read_filter, $limit, $offset);
$notifications = $notifications_result['status'] ? $notifications_result['data'] : [];
$pagination = $notifications_result['pagination'] ?? null;

// Get unread count
$unread_result = $notificationController->getUnreadCount($user['id']);
$unread_count = $unread_result['status'] ? $unread_result['count'] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - GAMON</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            background: rgba(255, 255, 255, 0.8);
            padding: 2rem;
            border-radius: 20px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        
        .page-header-subtitle {
            color: #6b7280;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        
        .page-header-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }
        
        .filter-tab {
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid rgba(242, 92, 92, 0.2);
            background: white;
            color: #6b7280;
        }
        
        .filter-tab:hover {
            border-color: #f25c5c;
            background: rgba(242, 92, 92, 0.05);
        }
        
        .filter-tab.active {
            background: #f25c5c;
            color: white;
            border-color: #f25c5c;
        }
        
        .notifications-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .notification-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            transition: transform 0.3s ease;
            position: relative;
        }
        
        .notification-card:hover {
            transform: translateY(-2px);
        }
        
        .notification-card.unread {
            border-left: 4px solid #f25c5c;
            background: rgba(242, 92, 92, 0.02);
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .notification-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .type-capsule {
            background: rgba(242, 92, 92, 0.1);
            color: #f25c5c;
        }
        
        .type-friend {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .notification-message {
            color: #1f2937;
            font-size: 1rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }
        
        .notification-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }
        
        .notification-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .notification-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
        }
        
        .btn-secondary {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-icon {
            padding: 0.5rem;
            border-radius: 8px;
            border: none;
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-icon:hover {
            background: rgba(242, 92, 92, 0.1);
            color: #f25c5c;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .pagination a {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .pagination a.active {
            background: #f25c5c;
            color: white;
        }
        
        .pagination a:not(.active) {
            background: white;
            color: #6b7280;
            border: 1px solid rgba(242, 92, 92, 0.2);
        }
        
        .pagination a:not(.active):hover {
            background: rgba(242, 92, 92, 0.05);
            border-color: #f25c5c;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>🔔 Notifikasi</h1>
            <p class="page-header-subtitle">
                <?php if ($unread_count > 0): ?>
                    Anda memiliki <?php echo $unread_count; ?> notifikasi yang belum dibaca
                <?php else: ?>
                    ✨ Semua notifikasi sudah dibaca!
                <?php endif; ?>
            </p>
            
            <div class="page-header-actions">
                <?php if ($unread_count > 0): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="mark_all_read" class="btn btn-primary">
                            📋 Tandai Semua Dibaca
                        </button>
                    </form>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-secondary">
                    ← Kembali ke Dashboard
                </a>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="notifications.php" class="filter-tab <?php echo $is_read_filter === null ? 'active' : ''; ?>">
                📜 Semua
            </a>
            <a href="notifications.php?read=0" class="filter-tab <?php echo $is_read_filter === 0 ? 'active' : ''; ?>">
                🆕 Belum Dibaca
            </a>
            <a href="notifications.php?read=1" class="filter-tab <?php echo $is_read_filter === 1 ? 'active' : ''; ?>">
                ✅ Sudah Dibaca
            </a>
        </div>

        <!-- Notifications List -->
        <?php if (!empty($notifications)): ?>
            <div class="notifications-grid">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                        <div class="notification-header">
                            <div>
                                <span class="notification-type <?php echo strpos($notification['type'], 'friend') !== false ? 'type-friend' : 'type-capsule'; ?>">
                                    <?php if (strpos($notification['type'], 'friend') !== false): ?>
                                        👥 Teman
                                    <?php else: ?>
                                        📦 Kapsul
                                    <?php endif; ?>
                                </span>
                                <?php if (!$notification['is_read']): ?>
                                    <span style="color: #f25c5c; font-weight: 600; font-size: 0.75rem; margin-left: 0.5rem;">• BARU</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!$notification['is_read']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <input type="hidden" name="notification_type" value="<?php echo $notification['type']; ?>">
                                    <button type="submit" name="mark_read" class="btn-icon" title="Tandai sudah dibaca">
                                        ✓
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-message">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </div>
                        
                        <div class="notification-meta">
                            <?php if (!empty($notification['title'])): ?>
                                <span>📧 <?php echo htmlspecialchars($notification['title']); ?></span>
                            <?php endif; ?>
                            <span>🕐 <?php echo date('d M Y, H:i', strtotime($notification['created_at'])); ?></span>
                        </div>
                        
                        <div class="notification-actions">
                            <?php if (strpos($notification['type'], 'friend') !== false): ?>
                                <?php if ($notification['type'] === 'friend_request'): ?>
                                    <a href="friends.php" class="btn btn-success">
                                        👤 Kelola Permintaan
                                    </a>
                                <?php else: ?>
                                    <a href="friends.php" class="btn btn-primary">
                                        👥 Lihat Teman
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($notification['capsule_id'])): ?>
                                    <a href="view-message.php?id=<?php echo $notification['capsule_id']; ?>" class="btn btn-primary">
                                        👁️ Lihat Pesan
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    🔕
                </div>
                <h3>Tidak Ada Notifikasi</h3>
                <p>
                    <?php if ($is_read_filter !== null): ?>
                        Tidak ditemukan notifikasi yang <?php echo $is_read_filter ? 'sudah dibaca' : 'belum dibaca'; ?>.
                    <?php else: ?>
                        Anda belum memiliki notifikasi apapun. Ketika kapsul waktu Anda terbuka, notifikasinya akan muncul di sini!
                    <?php endif; ?>
                </p>
                <?php if ($is_read_filter !== null): ?>
                    <a href="notifications.php" class="btn btn-primary">Lihat Semua Notifikasi</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($pagination && $pagination['total'] > $limit): ?>
            <div class="pagination">
                <?php 
                $total_pages = ceil($pagination['total'] / $limit);
                $current_page = $page;
                
                // Build query string for pagination links
                $query_params = [];
                if ($is_read_filter !== null) $query_params['read'] = $is_read_filter;
                
                // Previous page
                if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?>&<?php echo http_build_query($query_params); ?>" class="">
                        ←
                    </a>
                <?php endif;
                
                // Page numbers
                for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($query_params); ?>" 
                       class="<?php echo $i === $current_page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                
                // Next page
                if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>&<?php echo http_build_query($query_params); ?>" class="">
                        →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>