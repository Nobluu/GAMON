<?php
require_once 'config/database.php';
require_once 'controllers/Auth.php';
require_once 'controllers/Capsule.php';
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

// Handle AJAX delete request for capsules
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $capsuleId = $_POST['capsule_id'] ?? null;
    if (!$capsuleId) {
        echo json_encode(['success' => false, 'message' => 'ID kapsul tidak valid']);
        exit;
    }
    
    if ($_POST['action'] === 'delete_capsule') {
        $result = $capsuleController->deleteCapsule($capsuleId, $user['id']);
        echo json_encode($result);
        exit;
    } elseif ($_POST['action'] === 'force_delete_capsule') {
        $result = $capsuleController->forceDeleteCapsule($capsuleId, $user['id']);
        echo json_encode($result);
        exit;
    }
}

// Use the proper Database class for Docker compatibility
$database = new Database();
$conn = $database->getConnection();

// Get messages received from friends - using capsules table
$stmt = $conn->prepare("
    SELECT c.*, 
           sender.name as sender_name, sender.email as sender_email,
           mood.emoji as mood_emoji, mood.name as mood_name,
           CASE 
               WHEN c.unlock_date <= NOW() THEN 'unlocked'
               ELSE 'locked'
           END as status,
           TIMESTAMPDIFF(SECOND, NOW(), c.unlock_date) as seconds_left
    FROM capsules c
    LEFT JOIN users sender ON c.user_id = sender.id  
    LEFT JOIN moods mood ON c.mood_id = mood.id
    WHERE c.user_id != ? AND c.public_sharing = TRUE
    ORDER BY c.unlock_date ASC
");
$stmt->execute([$user['id']]);
$received_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get messages sent to friends - using capsules table
$stmt = $conn->prepare("
    SELECT c.*, c.message as content, c.unlock_date as scheduled_open_at,
           '' as receiver_name, '' as receiver_email,
           mood.emoji as mood_emoji, mood.name as mood_name,
           CASE 
               WHEN c.unlock_date <= NOW() THEN 'unlocked'
               ELSE 'locked'
           END as status
    FROM capsules c
    LEFT JOIN moods mood ON c.mood_id = mood.id
    WHERE c.user_id = ?
    ORDER BY c.unlock_date ASC
");
$stmt->execute([$user['id']]);
$sent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all available moods for filtering (including music data)
$moods_stmt = $conn->query("SELECT id, name, emoji, music_file, music_name, music_duration FROM moods ORDER BY name");
$available_moods = $moods_stmt->fetchAll(PDO::FETCH_ASSOC);

function formatTimeLeft($seconds) {
    if ($seconds <= 0) return 'Sudah terbuka';
    
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($days > 0) return "{$days}h {$hours}j {$minutes}m";
    if ($hours > 0) return "{$hours}j {$minutes}m";
    return "{$minutes}m";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan dengan Teman - GAMON</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
            line-height: 1.6;
        }





        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            text-decoration: none;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(242, 92, 92, 0.3);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .container {
           max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .messages-section {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            box-shadow: 0 20px 40px rgba(242, 92, 92, 0.1);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid rgba(242, 92, 92, 0.1);
            padding-bottom: 0.5rem;
        }
        
        .filter-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .filter-controls.sticky {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1rem 2rem;
            margin-bottom: 0;
            border-bottom: 1px solid rgba(242, 92, 92, 0.1);
            box-shadow: 0 4px 20px rgba(242, 92, 92, 0.1);
            animation: slideDown 0.3s ease;
            justify-content: center;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .sticky-placeholder {
            height: 80px;
            display: none;
        }
        
        .sticky-placeholder.active {
            display: block;
        }
        
        .mood-filter {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .filter-controls.sticky .mood-filter {
            gap: 0.3rem;
        }
        
        .filter-controls.sticky .mood-filter-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
        
        .filter-controls.sticky .toggle-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
        
        .mood-filter-btn {
            padding: 0.5rem 1rem;
            border: 2px solid rgba(242, 92, 92, 0.2);
            border-radius: 20px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .mood-filter-btn:hover {
            border-color: #f25c5c;
            background: rgba(242, 92, 92, 0.05);
        }
        
        .mood-filter-btn.active {
            background: #f25c5c;
            color: white;
            border-color: #f25c5c;
        }
        
        .view-toggle {
            display: flex;
            gap: 0.5rem;
        }
        
        .toggle-btn {
            padding: 0.5rem 1rem;
            border: 2px solid rgba(242, 92, 92, 0.2);
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .toggle-btn.active {
            background: #f25c5c;
            color: white;
            border-color: #f25c5c;
        }
        
        .mood-group {
            margin-bottom: 2rem;
            display: none;
        }
        
        .mood-group.active {
            display: block;
        }
        
        .mood-group-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            background: rgba(242, 92, 92, 0.05);
            border-radius: 10px;
            border-left: 4px solid #f25c5c;
        }
        
        .message-card {
            background: rgba(255, 255, 255, 0.6);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 5px solid #f25c5c;
            transition: all 0.3s ease;
        }
        
        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(242, 92, 92, 0.2);
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .message-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #374151;
        }
        
        .message-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-unlocked {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-locked {
            background: #fef3c7;
            color: #92400e;
        }
        
        .message-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 14px;
            color: #6b7280;
        }
        
        .message-content {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
            border: 1px solid rgba(242, 92, 92, 0.1);
        }
        
        .locked-content {
            text-align: center;
            color: #9ca3af;
            font-style: italic;
        }
        
        .countdown {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-top: 10px;
        }
        
        .no-messages {
            text-align: center;
            color: #9ca3af;
            padding: 2rem;
            font-style: italic;
        }
        
        .mood-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(242, 92, 92, 0.1);
            color: #f25c5c;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(242, 92, 92, 0.3);
        }
        
        @media (max-width: 1024px) {
            .nav-links { gap: 1.8rem; }
            .nav-links a { 
                padding: 0.4rem 0.8rem; 
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .nav { 
                padding: 0 1rem; 
                flex-direction: column;
                gap: 1rem;
            }
            .nav-links { 
                gap: 1.5rem; 
                flex-wrap: wrap;
                justify-content: center;
            }
            .nav-links a { 
                padding: 0.5rem 0.75rem; 
                font-size: 0.85rem;
            }
            .user-menu { 
                order: -1;
                gap: 0.5rem;
            }
            .message-card { margin-bottom: 1rem; }
        }
    </style>
</head>
<?php include 'includes/navbar.php'; ?>
<body>
    
    <div class="container">
        <h1 class="page-title">👥 Pesan dengan Teman</h1>
        
        <!-- Filter Controls -->
        <div class="filter-controls" id="filter-controls">
            <div class="view-toggle">
                <button class="toggle-btn active" onclick="setView('all')" id="view-all">📋 Semua</button>
                <button class="toggle-btn" onclick="setView('grouped')" id="view-grouped">🎭 Grup Mood</button>
            </div>
            
            <div class="mood-filter">
                <button class="mood-filter-btn active" onclick="filterByMood('all')" data-mood="all">
                    🎯 Semua Mood
                </button>
                <?php foreach ($available_moods as $mood): ?>
                    <button class="mood-filter-btn" 
                            onclick="filterByMood('<?= $mood['id'] ?>')" 
                            data-mood="<?= $mood['id'] ?>"
                            data-music="<?= htmlspecialchars($mood['music_file'] ?? '') ?>"
                            data-music-name="<?= htmlspecialchars($mood['music_name'] ?? '') ?>">
                        <?= $mood['emoji'] ?> <?= htmlspecialchars($mood['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Placeholder for sticky filter -->
        <div class="sticky-placeholder" id="sticky-placeholder"></div>
        
        <!-- Received Messages -->
        <div class="messages-section">
            <h2 class="section-title">📥 Pesan yang Diterima (<?= count($received_messages) ?>)</h2>
            
            <?php if (empty($received_messages)): ?>
                <div class="no-messages">
                    Belum ada pesan dari teman. Ajak teman Anda untuk bergabung dengan GAMON! 😊
                </div>
            <?php else: ?>
                <?php foreach ($received_messages as $message): ?>
                    <div class="message-card" 
                         data-mood="<?= $message['mood_id'] ?? 'none' ?>"
                         data-type="received"
                         data-mood-name="<?= htmlspecialchars($message['mood_name'] ?? '') ?>">
                        <div class="message-header">
                            <h3 class="message-title"><?= htmlspecialchars($message['title']) ?></h3>
                            <span class="message-status status-<?= $message['status'] ?>">
                                <?= $message['status'] === 'unlocked' ? '🔓 Terbuka' : '🔒 Terkunci' ?>
                            </span>
                        </div>
                        
                        <div class="message-info">
                            <div>
                                <strong>Dari:</strong> <?= htmlspecialchars($message['sender_name']) ?><br>
                                <small><?= htmlspecialchars($message['sender_email']) ?></small>
                            </div>
                            <div>
                                <strong>Dibuka:</strong> <?= date('d M Y, H:i', strtotime($message['unlock_date'])) ?><br>
                                <?php if ($message['mood_emoji']): ?>
                                    <span class="mood-badge">
                                        <?= $message['mood_emoji'] ?> <?= $message['mood_name'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($message['status'] === 'unlocked'): ?>
                            <div class="message-content">
                                <?= nl2br(htmlspecialchars(substr($message['message'], 0, 150))) ?>
                                <?php if (strlen($message['message']) > 150): ?>
                                    <span style="color: #6b7280;">...</span>
                                <?php endif; ?>
                                <div style="margin-top: 1rem; text-align: center;">
                                    <a href="capsule-detail.php?id=<?= $message['id'] ?>" 
                                       style="background: linear-gradient(135deg, #f25c5c, #ff7b7b); color: white; padding: 0.5rem 1rem; border-radius: 10px; text-decoration: none; font-weight: 500; font-size: 0.875rem; display: inline-block;">
                                        📖 Baca Detail Pesan
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="message-content locked-content">
                                🔒 Pesan ini akan terbuka pada <?= date('d F Y \p\u\k\u\l H:i', strtotime($message['unlock_date'])) ?>
                                <div class="countdown">⏰ <?= formatTimeLeft($message['seconds_left']) ?> lagi</div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Sent Messages -->
        <div class="messages-section">
            <h2 class="section-title">📤 Pesan yang Dikirim (<?= count($sent_messages) ?>)</h2>
            
            <?php if (empty($sent_messages)): ?>
                <div class="no-messages">
                    Anda belum mengirim pesan ke teman. <a href="send-to-friend.php" class="action-btn">Kirim Pesan Sekarang</a>
                </div>
            <?php else: ?>
                <?php foreach ($sent_messages as $message): ?>
                    <div class="message-card" 
                         data-mood="<?= $message['mood_id'] ?? 'none' ?>"
                         data-type="sent"
                         data-mood-name="<?= htmlspecialchars($message['mood_name'] ?? '') ?>">
                        <div class="message-header">
                            <h3 class="message-title"><?= htmlspecialchars($message['title']) ?></h3>
                            <span class="message-status status-<?= $message['status'] ?>">
                                <?= $message['status'] === 'unlocked' ? '🔓 Terkirim' : '🔒 Tertunda' ?>
                            </span>
                        </div>
                        
                        <div class="message-info">
                            <div>
                                <strong>Kepada:</strong> <?= htmlspecialchars($message['receiver_name'] ?? 'Unknown') ?><br>
                                <small><?= htmlspecialchars($message['receiver_email'] ?? 'No email') ?></small>
                            </div>
                            <div>
                                <strong>Dibuka:</strong> <?= date('d M Y, H:i', strtotime($message['scheduled_open_at'] ?? $message['unlock_date'])) ?><br>
                                <?php if ($message['mood_emoji']): ?>
                                    <span class="mood-badge">
                                        <?= $message['mood_emoji'] ?> <?= $message['mood_name'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="message-content">
                            <?php $messageText = $message['content'] ?? $message['message'] ?? ''; ?>
                            <?= nl2br(htmlspecialchars(substr($messageText, 0, 150))) ?>
                            <?php if (strlen($messageText) > 150): ?>
                                <span style="color: #6b7280;">...</span>
                            <?php endif; ?>
                            
                            <?php if ($message['status'] === 'unlocked'): ?>
                                <div style="margin-top: 1rem; text-align: center;">
                                    <a href="capsule-detail.php?id=<?= $message['id'] ?>" 
                                       style="background: linear-gradient(135deg, #f25c5c, #ff7b7b); color: white; padding: 0.5rem 1rem; border-radius: 10px; text-decoration: none; font-weight: 500; font-size: 0.875rem; display: inline-block; margin-right: 10px;">
                                        📖 Baca Detail Pesan
                                    </a>
                                    <button onclick="forceDeleteCapsule(<?= $message['id'] ?>)" 
                                            style="background: linear-gradient(135deg, #dc2626, #ef4444); color: white; padding: 0.5rem 1rem; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; font-size: 0.875rem;">
                                        🗑️ Hapus Kapsul
                                    </button>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; margin-top: 10px;">
                                    <small style="color: #9ca3af; margin-bottom: 10px; display: block;">Pesan akan dikirim pada tanggal di atas</small>
                                    <button onclick="deleteCapsule(<?= $message['id'] ?>)" 
                                            style="background: linear-gradient(135deg, #dc2626, #ef4444); color: white; padding: 0.5rem 1rem; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; font-size: 0.875rem;">
                                        🗑️ Hapus Kapsul
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let currentView = 'all';
        let currentMoodFilter = 'all';

        function setView(viewType) {
            currentView = viewType;
            
            // Update button states
            document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(`view-${viewType}`).classList.add('active');
            
            if (viewType === 'all') {
                showAllMessages();
            } else {
                showGroupedMessages();
            }
        }

        function filterByMood(moodId) {
            currentMoodFilter = moodId;
            
            // Update button states
            document.querySelectorAll('.mood-filter-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-mood="${moodId}"]`).classList.add('active');
            
            if (currentView === 'all') {
                showAllMessages();
            } else {
                showGroupedMessages();
            }
        }

        function showAllMessages() {
            // Hide any mood groups
            document.querySelectorAll('.mood-group').forEach(group => {
                group.style.display = 'none';
            });
            
            // Show original sections
            document.querySelectorAll('.messages-section').forEach(section => {
                section.style.display = 'block';
            });
            
            // Filter messages based on mood
            document.querySelectorAll('.message-card').forEach(card => {
                const cardMoodId = card.getAttribute('data-mood');
                if (currentMoodFilter === 'all' || cardMoodId === currentMoodFilter || cardMoodId === 'none') {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function showGroupedMessages() {
            // Hide original sections
            document.querySelectorAll('.messages-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Clear previous groups
            document.querySelectorAll('.mood-group').forEach(group => group.remove());
            
            // Group messages by mood
            const groups = {};
            document.querySelectorAll('.message-card').forEach(card => {
                const moodId = card.getAttribute('data-mood');
                const moodName = card.getAttribute('data-mood-name') || 'Tanpa Mood';
                
                // Filter by current mood filter
                if (currentMoodFilter !== 'all' && moodId !== currentMoodFilter) {
                    return;
                }
                
                if (!groups[moodId]) {
                    groups[moodId] = {
                        name: moodName,
                        cards: []
                    };
                }
                groups[moodId].cards.push(card.cloneNode(true));
            });
            
            // Create mood groups
            const container = document.querySelector('.container');
            Object.keys(groups).forEach(moodId => {
                if (groups[moodId].cards.length === 0) return;
                
                const group = document.createElement('div');
                group.className = 'mood-group active';
                group.innerHTML = `
                    <h3 class="mood-group-title">${groups[moodId].name} (${groups[moodId].cards.length})</h3>
                    <div class="mood-group-messages"></div>
                `;
                
                const messagesContainer = group.querySelector('.mood-group-messages');
                groups[moodId].cards.forEach(card => {
                    messagesContainer.appendChild(card);
                });
                
                container.appendChild(group);
            });
            
            if (Object.keys(groups).length === 0) {
                const noGroup = document.createElement('div');
                noGroup.className = 'mood-group active';
                noGroup.innerHTML = '<div class="no-messages">Tidak ada pesan dengan mood yang dipilih.</div>';
                container.appendChild(noGroup);
            }
        }

        // Sticky filter controls on scroll
        let filterControlsOriginalPos = null;
        
        function handleStickyFilter() {
            const filterControls = document.getElementById('filter-controls');
            const placeholder = document.getElementById('sticky-placeholder');
            
            if (!filterControlsOriginalPos) {
                filterControlsOriginalPos = filterControls.offsetTop;
            }
            
            if (window.scrollY > filterControlsOriginalPos + 100) {
                if (!filterControls.classList.contains('sticky')) {
                    filterControls.classList.add('sticky');
                    placeholder.classList.add('active');
                }
            } else {
                if (filterControls.classList.contains('sticky')) {
                    filterControls.classList.remove('sticky');
                    placeholder.classList.remove('active');
                }
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            showAllMessages();
            
            // Add scroll listener for sticky filter
            window.addEventListener('scroll', handleStickyFilter);
            
            // Set initial position
            setTimeout(() => {
                const filterControls = document.getElementById('filter-controls');
                filterControlsOriginalPos = filterControls.offsetTop;
            }, 100);
        });
        
        // === MOOD MUSIC FUNCTIONALITY ===
        
        // Mood Music Player
        const moodMusicPlayer = document.getElementById('moodMusicPlayer');

        // Function to play mood music
        function playMoodMusic(musicFile, musicName) {
            if (musicFile && musicFile.trim() !== '') {
                // Stop current music
                moodMusicPlayer.pause();
                moodMusicPlayer.currentTime = 0;
                
                // Convert .mp3 extension to .wav since actual files are .wav format
                const actualMusicFile = musicFile.replace('.mp3', '.wav');
                
                // Set new music source with correct path (no /gamon/ prefix)
                moodMusicPlayer.innerHTML = `
                    <source src="uploads/music/moods/${actualMusicFile}" type="audio/wav">
                    <source src="uploads/music/moods/${musicFile}" type="audio/mpeg">
                `;
                
                moodMusicPlayer.load();
                moodMusicPlayer.volume = 0.3; // Set volume to 30%
                
                console.log('Playing mood music:', musicName, '- File:', actualMusicFile);
                
                moodMusicPlayer.play().catch(e => {
                    console.error('Could not play music:', e);
                    console.log('Tried to play:', actualMusicFile);
                });
            } else {
                // Stop music if no file
                moodMusicPlayer.pause();
                console.log('No music file specified');
            }
        }

        // Add click event listeners to mood filter buttons for music
        document.addEventListener('DOMContentLoaded', function() {
            const moodFilterBtns = document.querySelectorAll('.mood-filter-btn[data-music]');
            
            moodFilterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const musicFile = this.getAttribute('data-music');
                    const musicName = this.getAttribute('data-music-name');
                    
                    // Play music when mood filter is clicked
                    if (musicFile && musicFile.trim() !== '') {
                        playMoodMusic(musicFile, musicName);
                    }
                });
            });
        });
        
        // === CAPSULE DELETE FUNCTIONALITY ===
        
        // Delete capsule yang sedang dalam pengiriman atau belum terbuka
        function deleteCapsule(id) {
            if (confirm('Yakin ingin menghapus kapsul ini?')) {
                fetch('controllers/Capsule.php?action=delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
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
        function forceDeleteCapsule(id) {
            const confirmed = confirm('⚠️ PERHATIAN!\n\nKapsul ini sudah terbuka dan mungkin sudah dibaca oleh penerima.\nApakah Anda yakin ingin menghapusnya?\n\nTindakan ini tidak dapat dibatalkan!');
            
            if (confirmed) {
                fetch('controllers/Capsule.php?action=forceDelete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
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
    </script>
    
    <!-- Mood Music Player -->
    <audio id="moodMusicPlayer" loop>
        <source src="" type="audio/wav">
        <source src="" type="audio/mpeg">
        Browser tidak mendukung audio.
    </audio>
</body>
</html>