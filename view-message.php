<?php
require_once 'controllers/Auth.php';
require_once 'controllers/Capsule.php';
require_once 'controllers/MessageController.php';
require_once 'helpers/NavHelper.php';

$auth = new Auth();
$auth->requireLogin();

if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
$capsuleController = new Capsule();
$messageController = new MessageController();

// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_message') {
    header('Content-Type: application/json');
    
    $message_id = $_POST['message_id'] ?? null;
    if (!$message_id) {
        echo json_encode(['status' => false, 'message' => 'ID pesan tidak valid']);
        exit;
    }
    
    $result = $messageController->deleteMessage($message_id, $user['id']);
    echo json_encode($result);
    exit;
}

// Handle AJAX capsule delete requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action']) && $_POST['action'] === 'delete_capsule') {
        $capsule_id = $_POST['capsule_id'] ?? null;
        if (!$capsule_id) {
            echo json_encode(['success' => false, 'message' => 'ID kapsul tidak valid']);
            exit;
        }
        
        $result = $capsuleController->deleteCapsule($capsule_id, $user['id']);
        echo json_encode($result);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'force_delete_capsule') {
        $capsule_id = $_POST['capsule_id'] ?? null;
        if (!$capsule_id) {
            echo json_encode(['success' => false, 'message' => 'ID kapsul tidak valid']);
            exit;
        }
        
        $result = $capsuleController->forceDeleteCapsule($capsule_id, $user['id']);
        echo json_encode($result);
        exit;
    }
}

// Check for notification from session
$notification = null;
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']); // Clear notification after reading
}

// Get user's capsules
$allCapsules = $capsuleController->getUserCapsules($user['id']);

// Get user's messages (sent and received)
$allMessages = $messageController->getUserMessages($user['id'], 1, 100); // Get first 100 messages

// Separate locked and unlocked capsules
$lockedCapsules = array_filter($allCapsules, function($capsule) {
    return (new DateTime($capsule['unlock_date']) > new DateTime());
});
$unlockedCapsules = array_filter($allCapsules, function($capsule) {
    return (new DateTime($capsule['unlock_date']) <= new DateTime());
});

// Separate locked and unlocked messages
$lockedMessages = array_filter($allMessages['messages'] ?? [], function($message) {
    return $message['status'] === 'locked';
});
$unlockedMessages = array_filter($allMessages['messages'] ?? [], function($message) {
    return $message['status'] !== 'locked';
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kapsul Saya - Capsule</title>
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
            padding: 0 2rem 3rem;
        }

        .notification {
            margin-bottom: 2rem;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            animation: slideDown 0.5s ease-out;
        }

        .notification.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #b8dacc;
            color: #155724;
        }

        .notification.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .notification-content {
            display: flex;
            align-items: center;
            width: 100%;
            justify-content: space-between;
        }

        .notification-icon {
            font-size: 1.25rem;
            margin-right: 0.75rem;
        }

        .notification-message {
            flex: 1;
            font-weight: 500;
        }

        .notification-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .notification-close:hover {
            opacity: 1;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: #6b7280;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }

        .tab-btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            color: #6b7280;
            border: 1px solid rgba(242, 92, 92, 0.2);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
            box-shadow: 0 4px 20px rgba(242, 92, 92, 0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(242, 92, 92, 0.1);
            color: #f25c5c;
        }

        .capsule-list {
            display: none;
        }

        .capsule-list.active {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .capsule-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .capsule-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(242, 92, 92, 0.15);
        }

        .capsule-card.locked {
            border-left: 4px solid #f59e0b;
        }

        .capsule-card.unlocked {
            border-left: 4px solid #10b981;
        }

        .capsule-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
        }

        .capsule-header {
            margin-bottom: 1rem;
        }

        .capsule-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .capsule-mood {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            background: rgba(242, 92, 92, 0.1);
            border-radius: 20px;
            font-size: 0.9rem;
            color: #f25c5c;
            font-weight: 500;
        }

        .capsule-content {
            color: #6b7280;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .capsule-date {
            font-size: 0.9rem;
            color: #9ca3af;
            border-top: 1px solid rgba(242, 92, 92, 0.1);
            padding-top: 1rem;
        }

        .date-label {
            font-weight: 600;
            color: #374151;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-text {
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .empty-link {
            color: #f25c5c;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .empty-link:hover {
            color: #e04d4d;
        }

        .media-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }

        .message-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(242, 92, 92, 0.1);
            transition: all 0.3s ease;
        }

        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(242, 92, 92, 0.15);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .message-info {
            flex: 1;
        }

        .message-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .message-meta {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .message-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .btn-edit, .btn-delete {
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-edit {
            background: #3b82f6;
            color: white;
        }

        .btn-edit:hover {
            background: #2563eb;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .message-content {
            color: #6b7280;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .message-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .message-status.locked {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .message-status.unlocked {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
        }

        @media (max-width: 768px) {
            .container { padding: 0 1rem 2rem; }
            .nav { padding: 0 1rem; }
            .capsule-list.active {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .tabs { 
                flex-direction: column; 
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <?php if ($notification): ?>
            <div class="notification <?php echo $notification['type']; ?>">
                <div class="notification-content">
                    <span class="notification-icon">
                        <?php echo $notification['type'] === 'success' ? '✅' : '❌'; ?>
                    </span>
                    <span class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></span>
                    <button class="notification-close" onclick="this.parentElement.parentElement.style.display='none'">×</button>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="page-header">
            <h1 class="page-title">📂 Kapsul Saya</h1>
            <p class="page-subtitle">Koleksi kapsul waktu pribadi Anda</p>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('locked')">
                🔒 Kapsul Terkunci (<?php echo count($lockedCapsules); ?>)
            </button>
            <button class="tab-btn" onclick="showTab('unlocked')">
                🔓 Kapsul Terbuka (<?php echo count($unlockedCapsules); ?>)
            </button>
            <button class="tab-btn" onclick="showTab('messages-locked')">
                📨 Pesan Terkunci (<?php echo count($lockedMessages); ?>)
            </button>
            <button class="tab-btn" onclick="showTab('messages-unlocked')">
                📨 Pesan Terbuka (<?php echo count($unlockedMessages); ?>)
            </button>
        </div>

        <!-- Locked Capsules -->
        <div id="locked" class="capsule-list active">
            <?php if (empty($lockedCapsules)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔒</div>
                    <div class="empty-text">Belum ada kapsul terkunci</div>
                    <p><a href="create-message.php" class="empty-link">Buat kapsul waktu pertama Anda!</a></p>
                </div>
            <?php else: ?>
                <?php foreach ($lockedCapsules as $capsule): ?>
                    <div class="capsule-card locked" style="position: relative;">
                        <div class="capsule-status">🔒</div>
                        <div class="capsule-header">
                            <h3 class="capsule-title"><?php echo htmlspecialchars($capsule['title']); ?></h3>
                            <span class="capsule-mood">
                                <?php echo $capsule['mood_emoji']; ?> <?php echo htmlspecialchars($capsule['mood_name']); ?>
                            </span>
                        </div>
                        <div class="capsule-content">
                            <a href="capsule-detail.php?id=<?= $capsule['id'] ?>" style="text-decoration: none; color: inherit;">
                                Kapsul ini tertutup sampai tanggal yang dijadwalkan. Pesan Anda tersimpan aman dan menunggu Anda.
                            </a>
                        </div>
                        <?php 
                        $mediaCount = $capsule['media_count'] ?? 0;
                        if ($mediaCount > 0): ?>
                            <div class="media-indicator">
                                📷 Berisi <?php echo $mediaCount; ?> lampiran
                            </div>
                        <?php endif; ?>
                        <div class="capsule-date">
                            <span class="date-label">Dibuka:</span>
                            <?php echo date('d M Y, H:i', strtotime($capsule['unlock_date'])); ?>
                            <br>
                            <span class="date-label">Dibuat:</span> 
                            <?php echo date('d M Y', strtotime($capsule['created_at'])); ?>
                        </div>
                        <div style="text-align: center; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f0f0f0;">
                            <a href="capsule-detail.php?id=<?= $capsule['id'] ?>" 
                               style="background: linear-gradient(135deg, #f25c5c, #ff7b7b); color: white; padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; margin-right: 10px; font-size: 0.875rem;">
                                📖 Baca Detail
                            </a>
                            <?php if ($capsuleController->canDeleteCapsule($capsule['id'], $user['id'])): ?>
                                <button onclick="deleteCapsule(<?= $capsule['id'] ?>)" 
                                        style="background: linear-gradient(135deg, #dc2626, #ef4444); color: white; padding: 0.5rem 1rem; border: none; border-radius: 8px; cursor: pointer; font-size: 0.875rem;">
                                    🗑️ Hapus
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Unlocked Capsules -->
        <div id="unlocked" class="capsule-list">
            <?php if (empty($unlockedCapsules)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔓</div>
                    <div class="empty-text">Belum ada kapsul yang terbuka</div>
                    <p>Kembali lagi saat kapsul Anda terbuka untuk menemukan pesan Anda!</p>
                </div>
            <?php else: ?>
                <?php foreach ($unlockedCapsules as $capsule): ?>
                    <div class="capsule-card unlocked" style="position: relative;">
                        <div class="capsule-status">🔓</div>
                        <div class="capsule-header">
                            <h3 class="capsule-title"><?php echo htmlspecialchars($capsule['title']); ?></h3>
                            <span class="capsule-mood">
                                <?php echo $capsule['mood_emoji']; ?> <?php echo htmlspecialchars($capsule['mood_name']); ?>
                            </span>
                        </div>
                        <div class="capsule-content">
                            <a href="capsule-detail.php?id=<?= $capsule['id'] ?>" style="text-decoration: none; color: inherit;">
                                <?php echo nl2br(htmlspecialchars(substr($capsule['message'], 0, 200))); ?>
                                <?php if (strlen($capsule['message']) > 200): ?>
                                    <span style="color: #f25c5c;">... <em>baca selengkapnya</em></span>
                                <?php endif; ?>
                            </a>
                        </div>
                        <?php 
                        $mediaCount = $capsule['media_count'] ?? 0;
                        if ($mediaCount > 0): ?>
                            <div class="media-indicator">
                                📷 Berisi <?php echo $mediaCount; ?> lampiran
                            </div>
                        <?php endif; ?>
                        <div class="capsule-date">
                            <span class="date-label">Dibuka:</span> 
                            <?php echo date('d M Y, H:i', strtotime($capsule['unlocked_at'] ?? $capsule['unlock_date'])); ?>
                            <br>
                            <span class="date-label">Dibuat:</span> 
                            <?php echo date('d M Y', strtotime($capsule['created_at'])); ?>
                        </div>
                        <div style="text-align: center; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f0f0f0;">
                            <a href="capsule-detail.php?id=<?= $capsule['id'] ?>" 
                               style="background: linear-gradient(135deg, #f25c5c, #ff7b7b); color: white; padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; margin-right: 10px; font-size: 0.875rem;">
                                📖 Baca Detail
                            </a>
                            <button onclick="forceDeleteCapsule(<?= $capsule['id'] ?>)" 
                                    style="background: linear-gradient(135deg, #dc2626, #ef4444); color: white; padding: 0.5rem 1rem; border: none; border-radius: 8px; cursor: pointer; font-size: 0.875rem;">
                                🗑️ Hapus Kapsul
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Locked Messages -->
        <div id="messages-locked" class="capsule-list">
            <?php if (empty($lockedMessages)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📨</div>
                    <div class="empty-text">Belum ada pesan terkunci</div>
                    <p><a href="create-message.php" class="empty-link">Kirim pesan pertama Anda!</a></p>
                </div>
            <?php else: ?>
                <?php foreach ($lockedMessages as $message): ?>
                    <div class="message-card">
                        <div class="message-header">
                            <div class="message-info">
                                <h3 class="message-title"><?= htmlspecialchars($message['title']) ?></h3>
                                <div class="message-meta">
                                    <div>📤 Untuk: <?= htmlspecialchars($message['receiver_name']) ?> (<?= htmlspecialchars($message['receiver_email']) ?>)</div>
                                    <div>📅 Buka: <?= date('d M Y, H:i', strtotime($message['scheduled_open_at'])) ?></div>
                                    <div>🎭 Mood: <?= $message['mood_emoji'] ?? '😐' ?> <?= htmlspecialchars($message['mood_name'] ?? 'Netral') ?></div>
                                </div>
                            </div>
                            <?php if ($messageController->canModifyMessage($message['id'], $user['id'])): ?>
                                <div class="message-actions">
                                    <a href="edit-message.php?id=<?= $message['id'] ?>" class="btn-edit">
                                        ✏️ Edit
                                    </a>
                                    <button onclick="deleteMessage(<?= $message['id'] ?>)" 
                                            data-title="<?= htmlspecialchars($message['title'], ENT_QUOTES) ?>"
                                            class="btn-delete">
                                        🗑️ Hapus
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="message-content">
                            <?= htmlspecialchars(substr($message['content'], 0, 150)) ?><?= strlen($message['content']) > 150 ? '...' : '' ?>
                        </div>
                        <div class="message-status locked">
                            🔒 Terkunci
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Unlocked Messages -->
        <div id="messages-unlocked" class="capsule-list">
            <?php if (empty($unlockedMessages)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📨</div>
                    <div class="empty-text">Belum ada pesan terbuka</div>
                    <p>Tunggu sampai pesan Anda terbuka!</p>
                </div>
            <?php else: ?>
                <?php foreach ($unlockedMessages as $message): ?>
                    <div class="message-card">
                        <div class="message-header">
                            <div class="message-info">
                                <h3 class="message-title"><?= htmlspecialchars($message['title']) ?></h3>
                                <div class="message-meta">
                                    <div>📤 Untuk: <?= htmlspecialchars($message['receiver_name']) ?> (<?= htmlspecialchars($message['receiver_email']) ?>)</div>
                                    <div>📅 Dibuka: <?= date('d M Y, H:i', strtotime($message['scheduled_open_at'])) ?></div>
                                    <div>🎭 Mood: <?= $message['mood_emoji'] ?? '😐' ?> <?= htmlspecialchars($message['mood_name'] ?? 'Netral') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="message-content">
                            <?= htmlspecialchars($message['content']) ?>
                        </div>
                        <div class="message-status unlocked">
                            🔓 Terbuka
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.capsule-list').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }

        // Delete message function
        function deleteMessage(messageId) {
            const deleteButton = event.target;
            const messageTitle = deleteButton.getAttribute('data-title') || 'pesan ini';
            
            if (!confirm(`Apakah Anda yakin ingin menghapus pesan "${messageTitle}"?\n\nPesan yang sudah dihapus tidak dapat dikembalikan.`)) {
                return;
            }

            // Show loading indicator
            const originalText = deleteButton.innerHTML;
            deleteButton.innerHTML = '⏳ Menghapus...';
            deleteButton.disabled = true;

            // Send delete request
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_message&message_id=${messageId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    // Show success message
                    alert(data.message);
                    
                    // Remove the message card from DOM
                    const messageCard = deleteButton.closest('.message-card');
                    messageCard.style.transition = 'all 0.3s ease';
                    messageCard.style.opacity = '0';
                    messageCard.style.transform = 'translateX(-100%)';
                    
                    setTimeout(() => {
                        messageCard.remove();
                        
                        // Check if there are no more messages in this tab
                        const currentTab = document.querySelector('.capsule-list.active');
                        const remainingMessages = currentTab.querySelectorAll('.message-card');
                        if (remainingMessages.length === 0) {
                            location.reload(); // Reload to show empty state
                        }
                    }, 300);
                    
                } else {
                    alert('Error: ' + data.message);
                    deleteButton.innerHTML = originalText;
                    deleteButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menghapus pesan');
                deleteButton.innerHTML = originalText;
                deleteButton.disabled = false;
            });
        }
        
        // === CAPSULE DELETE FUNCTIONALITY ===
        
        // Delete capsule yang sedang dalam pengiriman atau belum terbuka
        function deleteCapsule(capsuleId) {
            if (confirm('Yakin ingin menghapus kapsul ini?\n\nKapsul yang sedang dalam proses pengiriman akan dihapus dan tidak dapat dikembalikan.')) {
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_capsule&capsule_id=${capsuleId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Kapsul berhasil dihapus!');
                        location.reload(); // Refresh halaman
                    } else {
                        alert(data.message || 'Gagal menghapus kapsul');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menghapus kapsul');
                });
            }
        }

        // Force delete untuk kapsul yang sudah terbuka 
        function forceDeleteCapsule(capsuleId) {
            const confirmed = confirm('⚠️ PERHATIAN!\n\nKapsul ini sudah terbuka dan mungkin sudah dibaca.\nApakah Anda yakin ingin menghapusnya?\n\nTindakan ini tidak dapat dibatalkan!');
            
            if (confirmed) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=force_delete_capsule&capsule_id=${capsuleId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Kapsul berhasil dihapus secara permanen!');
                        location.reload(); // Refresh halaman
                    } else {
                        alert(data.message || 'Gagal menghapus kapsul');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menghapus kapsul');
                });
            }
        }

        // Auto-hide notification after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notification = document.querySelector('.notification');
            if (notification) {
                setTimeout(function() {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateY(-20px)';
                    setTimeout(function() {
                        notification.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>