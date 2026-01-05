<?php
require_once 'controllers/Auth.php';
require_once 'controllers/Capsule.php';
require_once 'helpers/NavHelper.php';
require_once 'config/timezone.php';

$auth = new Auth();
$auth->requireLogin();

if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

$capsuleController = new Capsule();
$user = $auth->getCurrentUser();

// Get capsule ID from URL
$capsuleId = $_GET['id'] ?? null;
if (!$capsuleId) {
    header('Location: view-message.php');
    exit;
}

// Get capsule data
$capsule = $capsuleController->getCapsule($capsuleId, $user['id']);
if (!$capsule) {
    header('Location: view-message.php');
    exit;
}

// Get media files for this capsule
$mediaFiles = $capsuleController->getCapsuleMedia($capsuleId, $user['id']);

// Check if capsule is unlocked with proper timezone handling
$unlockDateTime = new DateTime($capsule['unlock_date']);
$currentDateTime = new DateTime();
$isUnlocked = ($capsule['current_status'] === 'unlocked') || ($currentDateTime >= $unlockDateTime);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($capsule['title']) ?> - GAMON</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.8);
            color: #6b7280;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.95);
            color: #f25c5c;
            transform: translateY(-2px);
        }

        .capsule-detail {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(242, 92, 92, 0.1);
            margin-bottom: 2rem;
        }

        .capsule-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .status-locked {
            background: #fef3c7;
            color: #d97706;
        }

        .status-unlocked {
            background: #d1fae5;
            color: #059669;
        }

        .capsule-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
        }

        .capsule-mood {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(242, 92, 92, 0.1);
            color: #f25c5c;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            margin-bottom: 2rem;
        }

        .mood-music-control {
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            background: rgba(242, 92, 92, 0.1);
            transition: all 0.3s ease;
            margin-left: 8px;
        }

        .mood-music-control:hover {
            background: rgba(242, 92, 92, 0.2);
            transform: scale(1.1);
        }

        .capsule-dates {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: rgba(242, 92, 92, 0.05);
            border-radius: 12px;
        }

        .date-item {
            text-align: center;
        }

        .date-label {
            display: block;
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .date-value {
            font-weight: 600;
            color: #1f2937;
        }

        .capsule-content {
            background: #f9fafb;
            padding: 2rem;
            border-radius: 16px;
            border-left: 4px solid #f25c5c;
            margin-bottom: 2rem;
        }

        .content-locked {
            text-align: center;
            color: #6b7280;
            font-style: italic;
        }

        .content-text {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #1f2937;
            white-space: pre-wrap;
        }

        .media-section {
            margin-top: 2rem;
        }

        .media-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .media-item {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .media-item:hover {
            transform: translateY(-2px);
        }

        .media-preview {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .media-info {
            padding: 1rem;
        }

        .media-filename {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .media-meta {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .no-media {
            text-align: center;
            color: #6b7280;
            padding: 2rem;
            background: #f9fafb;
            border-radius: 12px;
            border: 2px dashed #e5e7eb;
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .capsule-title { font-size: 1.5rem; }
            .capsule-dates { grid-template-columns: 1fr; }
            .nav { flex-direction: column; gap: 1rem; padding: 1rem; }
        }
    </style>
</head>
<?php include 'includes/navbar.php'; ?>
<body>
    <div class="container">
        <a href="view-message.php" class="back-btn">
            ← Kembali ke Daftar Kapsul
        </a>

        <div class="capsule-detail">
            <div class="capsule-status <?= $isUnlocked ? 'status-unlocked' : 'status-locked' ?>">
                <?= $isUnlocked ? '🔓 Terbuka' : '🔒 Terkunci' ?>
            </div>

            <h1 class="capsule-title"><?= htmlspecialchars($capsule['title']) ?></h1>

            <?php if ($capsule['mood_name']): ?>
                <div class="capsule-mood">
                    <?= $capsule['mood_emoji'] ?> <?= htmlspecialchars($capsule['mood_name']) ?>
                    <?php if (!empty($capsule['mood_music_file'])): ?>
                        <span class="mood-music-control" onclick="toggleMoodMusic()" title="<?= htmlspecialchars($capsule['mood_music_name'] ?? 'Musik mood') ?>">
                            <span id="musicIcon">🎵</span>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="capsule-dates">
                <div class="date-item">
                    <span class="date-label">Dibuat</span>
                    <div class="date-value"><?= date('d M Y, H:i', strtotime($capsule['created_at'])) ?></div>
                </div>
                <div class="date-item">
                    <span class="date-label">Dibuka</span>
                    <div class="date-value"><?= date('d M Y, H:i', strtotime($capsule['unlock_date'])) ?></div>
                </div>
            </div>

            <div class="capsule-content">
                <?php if ($isUnlocked): ?>
                    <div class="content-text"><?= nl2br(htmlspecialchars($capsule['message'])) ?></div>
                <?php else: ?>
                    <div class="content-locked">
                        🔒 Kapsul ini masih terkunci. Tunggu sampai <?= date('d M Y, H:i', strtotime($capsule['unlock_date'])) ?> untuk membaca pesan lengkap.
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($mediaFiles) && $isUnlocked): ?>
                <div class="media-section">
                    <h2 class="media-title">📷 Lampiran Media</h2>
                    <div class="media-grid">
                        <?php foreach ($mediaFiles as $media): ?>
                            <div class="media-item">
                                <?php 
                                $filePath = 'uploads/' . $media['filename'];
                                $isImage = strpos($media['file_type'], 'image/') === 0;
                                ?>
                                
                                <?php if ($isImage && file_exists($filePath)): ?>
                                    <img src="<?= htmlspecialchars($filePath) ?>" 
                                         alt="<?= htmlspecialchars($media['original_name']) ?>" 
                                         class="media-preview"
                                         onclick="window.open('<?= htmlspecialchars($filePath) ?>', '_blank')">
                                <?php else: ?>
                                    <div class="media-preview" style="display: flex; align-items: center; justify-content: center; background: #f3f4f6;">
                                        <span style="font-size: 3rem;">📄</span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="media-info">
                                    <div class="media-filename"><?= htmlspecialchars($media['original_name']) ?></div>
                                    <div class="media-meta">
                                        <?= number_format($media['file_size'] / 1024, 1) ?> KB • 
                                        <?= date('d M Y', strtotime($media['uploaded_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif (!empty($mediaFiles) && !$isUnlocked): ?>
                <div class="media-section">
                    <h2 class="media-title">📷 Lampiran Media</h2>
                    <div class="no-media">
                        🔒 <?= count($mediaFiles) ?> file media menunggu kapsul terbuka
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mood Music Player -->
    <?php if (!empty($capsule['mood_music_file'])): ?>
    <audio id="moodMusicPlayer" loop>
        <source src="uploads/music/moods/<?= htmlspecialchars($capsule['mood_music_file']) ?>" type="audio/mpeg">
        <source src="uploads/music/moods/<?= htmlspecialchars(str_replace('.mp3', '.wav', $capsule['mood_music_file'])) ?>" type="audio/wav">
    </audio>
    
    <script>
        let isPlaying = false;
        const musicPlayer = document.getElementById('moodMusicPlayer');
        const musicIcon = document.getElementById('musicIcon');
        
        function toggleMoodMusic() {
            if (isPlaying) {
                musicPlayer.pause();
                musicIcon.textContent = '🎵';
                isPlaying = false;
            } else {
                musicPlayer.volume = 0.3; // Set volume to 30%
                musicPlayer.play().then(() => {
                    musicIcon.textContent = '🔊';
                    isPlaying = true;
                }).catch(e => {
                    console.log('Could not play mood music:', e);
                    alert('Tidak dapat memutar musik mood. File mungkin tidak ditemukan.');
                });
            }
        }
        
        // Auto-play mood music when page loads (if browser allows)
        document.addEventListener('DOMContentLoaded', function() {
            if (musicPlayer) {
                musicPlayer.volume = 0.3;
                musicPlayer.play().then(() => {
                    musicIcon.textContent = '🔊';
                    isPlaying = true;
                }).catch(e => {
                    console.log('Autoplay blocked by browser');
                    // Show notification that music is available
                    showMusicNotification();
                });
            }
        });
        
        function showMusicNotification() {
            const notification = document.createElement('div');
            notification.innerHTML = `
                <div style="position: fixed; bottom: 20px; left: 20px; background: rgba(242, 92, 92, 0.9); color: white; padding: 10px 15px; border-radius: 12px; font-size: 12px; z-index: 1000; cursor: pointer; box-shadow: 0 4px 12px rgba(242, 92, 92, 0.3);">
                    🎵 Musik mood tersedia - Klik untuk memutar
                </div>
            `;
            
            notification.addEventListener('click', function() {
                toggleMoodMusic();
                notification.remove();
            });
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
    </script>
    <?php endif; ?>
</body>
</html>