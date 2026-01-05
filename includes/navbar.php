<?php
// Pastikan user sudah login
if (!isset($user) || !$user) {
    header('Location: login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
/* Universal reset untuk semua link */
a {
    text-decoration: none !important;
}

a:hover, a:focus, a:active {
    text-decoration: none !important;
}

.header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(242, 92, 92, 0.1);
    padding: 1rem 0;
    position: sticky;
    top: 0;
    z-index: 100;
    margin-bottom: 2rem;
}

.nav {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 2rem;
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: #f25c5c;
    text-decoration: none !important;
}

.logo-img {
    width: 50px;
    height: 50px;
    object-fit: contain;
}

.nav-links {
    display: flex;
    gap: 1.5rem;
    align-items: center;
    flex: 1;
    justify-content: center;
}

.nav-links a {
    text-decoration: none !important;
    color: #6b7280;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    padding: 0.75rem 1.25rem;
    border-radius: 12px;
    white-space: nowrap;
    position: relative;
}

.nav-links a:hover { 
    color: #f25c5c !important; 
    background: rgba(242, 92, 92, 0.1);
    transform: translateY(-1px);
    text-decoration: none !important;
}

.nav-links a.active {
    color: #f25c5c !important;
    background: rgba(242, 92, 92, 0.15);
    font-weight: 600;
    text-decoration: none !important;
}

.user-menu {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f25c5c, #ff7b7b);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: white;
    text-decoration: none !important;
    overflow: hidden;
    transition: all 0.3s ease;
    font-weight: 600;
}

.user-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(242, 92, 92, 0.3);
    text-decoration: none !important;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.user-name {
    color: #374151;
    font-weight: 500;
    font-size: 0.9rem;
}

/* Notification Styles */
.notification-container {
    position: relative;
    display: flex;
    align-items: center;
}

.notification-btn {
    background: linear-gradient(135deg, rgba(242, 92, 92, 0.05), rgba(242, 92, 92, 0.1));
    color: #f25c5c;
    border: 1px solid rgba(242, 92, 92, 0.2);
    border-radius: 12px;
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    font-size: 1rem;
}

.notification-btn:hover {
    background: linear-gradient(135deg, rgba(242, 92, 92, 0.15), rgba(242, 92, 92, 0.2));
    border-color: #f25c5c;
    color: #dc2626;
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(242, 92, 92, 0.2);
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: linear-gradient(135deg, #dc2626, #ef4444);
    color: white;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notification-dropdown {
    position: absolute;
    top: calc(100% + 10px);
    left: -10px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(242, 92, 92, 0.1);
    min-width: 320px;
    max-width: 400px;
    max-height: 500px;
    overflow: hidden;
    z-index: 1000;
    display: none;
    animation: dropdownFadeIn 0.3s ease;
}

.notification-dropdown.show {
    display: block;
}

@keyframes dropdownFadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.notification-header {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, rgba(242, 92, 92, 0.05), rgba(242, 92, 92, 0.02));
}

.notification-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
}

.mark-all-read {
    background: none;
    border: none;
    color: #f25c5c;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.mark-all-read:hover {
    background: rgba(242, 92, 92, 0.1);
}

.notification-list {
    max-height: 300px;
    overflow-y: auto;
}

.notification-item {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    gap: 0.75rem;
}

.notification-item:hover {
    background: rgba(242, 92, 92, 0.02);
}

.notification-item.unread {
    background: rgba(242, 92, 92, 0.05);
    border-left: 3px solid #f25c5c;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f25c5c, #ff7b7b);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.25rem;
    line-height: 1.3;
}

.notification-message {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.4;
    margin-bottom: 0.25rem;
}

.notification-time {
    font-size: 0.75rem;
    color: #9ca3af;
}

.notification-loading {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}

.notification-empty {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}

.notification-footer {
    padding: 0.75rem;
    border-top: 1px solid #f3f4f6;
    background: #f9fafb;
}

.view-all-btn {
    display: block;
    text-align: center;
    color: #f25c5c;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.view-all-btn:hover {
    color: #dc2626;
    text-decoration: none;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .nav-links {
        gap: 1rem;
    }
    
    .nav-links a {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }
}

@media (max-width: 900px) {
    .nav-links {
        gap: 0.75rem;
    }
    
    .nav-links a {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }
    
    .nav {
        padding: 0 1rem;
    }
}

@media (max-width: 768px) {
    .nav-links {
        display: none;
    }
    
    .user-menu {
        gap: 0.5rem;
    }
    
    .user-name {
        display: none;
    }
}
</style>

<header class="header">
    <nav class="nav">
        <a href="dashboard.php" class="logo">
            <img src="logo_gamon.png" alt="GAMON" class="logo-img">
        </a>
        <div class="nav-links">
            <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Beranda</a>
            <a href="create-message.php" class="<?php echo ($current_page == 'create-message.php') ? 'active' : ''; ?>">Buat Kapsul</a>
            <a href="view-message.php" class="<?php echo ($current_page == 'view-message.php') ? 'active' : ''; ?>">Kapsul Saya</a>
            <a href="send-to-friend.php" class="<?php echo ($current_page == 'send-to-friend.php') ? 'active' : ''; ?>">Kirim ke Teman</a>
            <a href="friend-messages.php" class="<?php echo ($current_page == 'friend-messages.php') ? 'active' : ''; ?>">Pesan Teman</a>
            <a href="friends.php" class="<?php echo ($current_page == 'friends.php') ? 'active' : ''; ?>">Kelola Teman</a>
            <a href="calendar.php" class="<?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>">Kalender</a>
            <?php if (isset($user['role']) && in_array($user['role'], ['admin', 'superadmin'])): ?>
                <a href="admin/dashboard.php" style="background: linear-gradient(135deg, #667eea, #764ba2) !important; color: white !important; border-radius: 12px; padding: 0.75rem 1.25rem; font-weight: 600;">
                    🔱 Admin
                </a>
            <?php endif; ?>
        </div>
        <div class="user-menu">
            <!-- Notification Bell -->
            <div class="notification-container">
                <button class="notification-btn" id="notificationBell" title="Notifikasi">
                    <span style="font-size: 1.2rem;">🔔</span>
                    <span class="notification-badge" id="notificationCount" style="display: none;">0</span>
                </button>
                
                <!-- Notification Dropdown -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h4>🔔 Notifikasi</h4>
                        <button class="mark-all-read" id="markAllRead" title="Tandai semua sudah dibaca">
                            <i class="fas fa-check-double"></i>
                        </button>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-loading">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </div>
                    </div>
                    <div class="notification-footer">
                        <a href="notifications.php" class="view-all-btn">Lihat Semua</a>
                    </div>
                </div>
            </div>
            
            <a href="profile.php" class="user-avatar" title="Lihat Profile">
                <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile">
                <?php else: ?>
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                <?php endif; ?>
            </a>
            <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
            
        </div>
    </nav>
</header>

<script>
// Enhanced functionality 
document.addEventListener('DOMContentLoaded', function() {
    // Initialize notification system
    initNotificationSystem();
});

// Notification System
let notificationUpdateInterval;

function initNotificationSystem() {
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const markAllRead = document.getElementById('markAllRead');

    if (!notificationBell) return;

    // Toggle dropdown
    notificationBell.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleNotificationDropdown();
    });

    // Mark all as read
    markAllRead.addEventListener('click', function() {
        markAllNotificationsRead();
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.notification-container')) {
            closeNotificationDropdown();
        }
    });

    // Start checking for notifications
    updateNotificationCount();
    notificationUpdateInterval = setInterval(updateNotificationCount, 30000); // Check every 30 seconds

    // Also check for newly unlocked capsules
    setInterval(checkUnlockedCapsules, 60000); // Check every minute
}

function updateNotificationCount() {
    fetch('api/notifications.php?action=count')
        .then(response => response.json())
        .then(data => {
            if (data.status) {
                const badge = document.getElementById('notificationCount');
                const bell = document.getElementById('notificationBell');
                
                if (data.count > 0) {
                    badge.textContent = data.count > 99 ? '99+' : data.count;
                    badge.style.display = 'flex';
                    bell.classList.add('has-notifications');
                } else {
                    badge.style.display = 'none';
                    bell.classList.remove('has-notifications');
                }
            }
        })
        .catch(error => console.log('Error fetching notification count:', error));
}

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    const isVisible = dropdown.classList.contains('show');
    
    if (isVisible) {
        closeNotificationDropdown();
    } else {
        showNotificationDropdown();
    }
}

function showNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.add('show');
    loadRecentNotifications();
}

function closeNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.remove('show');
}

function loadRecentNotifications() {
    const notificationList = document.getElementById('notificationList');
    notificationList.innerHTML = '<div class="notification-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

    fetch('api/notifications.php?action=recent&limit=5')
        .then(response => response.json())
        .then(data => {
            if (data.status && data.notifications) {
                displayNotifications(data.notifications);
            } else {
                notificationList.innerHTML = '<div class="notification-empty">📭 Tidak ada notifikasi baru</div>';
            }
        })
        .catch(error => {
            console.log('Error loading notifications:', error);
            notificationList.innerHTML = '<div class="notification-empty">❌ Gagal memuat notifikasi</div>';
        });
}

function displayNotifications(notifications) {
    const notificationList = document.getElementById('notificationList');
    
    if (notifications.length === 0) {
        notificationList.innerHTML = '<div class="notification-empty">📭 Tidak ada notifikasi baru</div>';
        return;
    }

    let html = '';
    notifications.forEach(notification => {
        const timeAgo = formatTimeAgo(notification.created_at);
        const icon = getNotificationIcon(notification.type);
        
        html += `
            <div class="notification-item ${notification.is_read ? '' : 'unread'}" 
                 onclick="handleNotificationClick(${notification.id}, '${notification.action_url || ''}')">
                <div class="notification-icon">${icon}</div>
                <div class="notification-content">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
            </div>
        `;
    });

    notificationList.innerHTML = html;
}

function getNotificationIcon(type) {
    switch (type) {
        case 'capsule_unlock': return '🎉';
        case 'friend_capsule_unlock': return '💌';
        case 'friend_message_received': return '📨';
        case 'friend_request': return '👥';
        case 'friend_accepted': return '✅';
        case 'friend_declined': return '❌';
        case 'capsule_reminder': return '⏰';
        default: return '🔔';
    }
}

function formatTimeAgo(dateString) {
    const now = new Date();
    const then = new Date(dateString);
    const diff = Math.floor((now - then) / 1000); // seconds

    if (diff < 60) return 'Baru saja';
    if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
    if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
    if (diff < 604800) return Math.floor(diff / 86400) + ' hari lalu';
    return Math.floor(diff / 604800) + ' minggu lalu';
}

function handleNotificationClick(notificationId, actionUrl) {
    // Mark as read
    markNotificationAsRead(notificationId);
    
    // Navigate to action URL if exists
    if (actionUrl) {
        // Close dropdown
        closeNotificationDropdown();
        // Navigate
        window.location.href = actionUrl;
    }
}

function markNotificationAsRead(notificationId) {
    const formData = new FormData();
    formData.append('notification_id', notificationId);

    fetch('api/notifications.php?action=mark_read', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status) {
            // Update count
            updateNotificationCount();
        }
    })
    .catch(error => console.log('Error marking notification as read:', error));
}

function markAllNotificationsRead() {
    // This would require additional endpoint, for now just refresh
    updateNotificationCount();
    loadRecentNotifications();
}

function checkUnlockedCapsules() {
    fetch('api/notifications.php?action=check_unlocked')
        .then(response => response.json())
        .then(data => {
            if (data.status && data.notifications_created > 0) {
                // Update notification count if new notifications were created
                updateNotificationCount();
                
                // Show a subtle notification that new messages are unlocked
                showUnlockAlert(data.notifications_created);
            }
        })
        .catch(error => console.log('Error checking unlocked capsules:', error));
}

function showUnlockAlert(count) {
    // Create a temporary alert
    const alertDiv = document.createElement('div');
    alertDiv.className = 'unlock-alert';
    alertDiv.innerHTML = `
        <div style="
            position: fixed;
            top: 80px;
            right: 20px;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(242, 92, 92, 0.3);
            z-index: 9999;
            animation: slideInRight 0.3s ease;
        ">
            🎉 ${count} kapsul baru sudah terbuka!
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Remove after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Clean up interval when page unloads
window.addEventListener('beforeunload', function() {
    if (notificationUpdateInterval) {
        clearInterval(notificationUpdateInterval);
    }
});
</script>