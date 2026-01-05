<?php
require_once 'config/database.php';
require_once 'config/email.php';
require_once 'controllers/Auth.php';
require_once 'controllers/FriendController.php';
require_once 'helpers/NavHelper.php';

$auth = new Auth();
$auth->requireLogin();

if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
$database = new Database();
$conn = $database->getConnection();
$friendController = new FriendController();

$success = '';
$error = '';

// Get user's friends for dropdown
$friends_result = $friendController->getFriends($_SESSION['user_id']);
$friends = $friends_result['status'] ? $friends_result['data'] : [];

// Email sending function
function sendEmailNotification($to_email, $sender_name, $title, $unlock_date, $message_link) {
    $subject = "🕰️ Anda mendapat Time Capsule Message dari " . $sender_name;
    $message = "
    <html>
    <head><title>GAMON - Time Capsule Message</title></head>
    <body style='font-family: Arial, sans-serif; background: #f7fafc; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);'>
            <h2 style='color: #f25c5c; text-align: center; margin-bottom: 20px;'>🕰️ GAMON Time Capsule</h2>
            
            <p>Halo!</p>
            
            <p>Anda mendapat <strong>Time Capsule Message</strong> dari <strong>{$sender_name}</strong>:</p>
            
            <div style='background: #fef4f1; padding: 20px; border-radius: 10px; border-left: 5px solid #f25c5c; margin: 20px 0;'>
                <h3 style='color: #374151; margin-top: 0;'>{$title}</h3>
                <p style='color: #6b7280; margin-bottom: 0;'>Pesan akan terbuka pada: <strong>" . date('d F Y, H:i', strtotime($unlock_date)) . " WIB</strong></p>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$message_link}' style='background: linear-gradient(135deg, #f25c5c, #ff7b7b); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block;'>📖 Buka Pesan</a>
            </div>
            
            <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
            
            <p style='color: #6b7280; font-size: 14px; text-align: center;'>
                GAMON adalah aplikasi Time Capsule untuk mengirim pesan ke masa depan.<br>
                <a href='http://localhost/gamon/register.php' style='color: #f25c5c;'>Daftar sekarang</a> untuk membuat pesan Anda sendiri!
            </p>
        </div>
    </body>
    </html>
    ";
    
    return EmailSender::sendHTMLEmail($to_email, $subject, $message, $sender_name);
}

if ($_POST) {
    $receiver_email = trim($_POST['receiver_email']);
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $unlock_date = $_POST['unlock_date'];
    $mood_id = $_POST['mood_id'] ?? null;
    
    // Validate input
    if (empty($receiver_email) || empty($title) || empty($content) || empty($unlock_date)) {
        $error = "Semua field harus diisi!";
    } else {
        // Check if receiver exists (internal user only)
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$receiver_email]);
        $receiver = $stmt->fetch();
        
        if (!$receiver) {
            $error = "Pengguna dengan email $receiver_email tidak ditemukan! Pastikan email yang dimasukkan adalah pengguna terdaftar GAMON.";
        } else if ($receiver['id'] == $user['id']) {
            $error = "Anda tidak bisa mengirim pesan ke diri sendiri. Gunakan halaman Create Capsule.";
        } else {
                // Check if they are friends (optional - add warning if not friends)
                $are_friends = $friendController->areFriends($_SESSION['user_id'], $receiver['id']);
                $friendship_warning = '';
                if (!$are_friends) {
                    $friendship_warning = " (Catatan: Anda belum berteman dengan pengguna ini)";
                }
                
                // Create capsule for internal user
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO capsules (user_id, title, message, mood_id, unlock_date, public_sharing) 
                        VALUES (?, ?, ?, ?, ?, TRUE)
                    ");
                    
                    $result = $stmt->execute([
                        $receiver['id'], // Capsule milik receiver
                        $title,
                        $content,
                        $mood_id,
                        date('Y-m-d H:i:s', strtotime($unlock_date))
                    ]);
                    
                    if ($result) {
                        $capsule_id = $conn->lastInsertId();
                        
                        // Create notification for the receiver
                        require_once 'controllers/NotificationController.php';
                        $notificationController = new NotificationController();
                        $notificationResult = $notificationController->createFriendMessageNotification($capsule_id, $receiver['id']);
                        
                        $success = "Capsule berhasil dibuat untuk " . htmlspecialchars($receiver['name']) . " (" . htmlspecialchars($receiver_email) . ")" . $friendship_warning;
                        
                        if ($notificationResult['status']) {
                            $success .= "<br>🔔 Notifikasi telah dikirim kepada penerima!";
                        }
                        
                        // Clear form
                        $_POST = [];
                    } else {
                        $error = "Gagal mengirim pesan. Silakan coba lagi.";
                    }
                    
                } catch (PDOException $e) {
                    $error = "Error: " . $e->getMessage();
                }
            }
    }
}

// Get moods for selection
$stmt = $conn->query("SELECT id, name, emoji, music_file, music_name, music_duration FROM moods ORDER BY name");
$moods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Log mood count
error_log("Moods loaded: " . count($moods) . " items");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Kirim Pesan ke Teman - GAMON</title>
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
        
        .form-container {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            box-shadow: 0 20px 40px rgba(242, 92, 92, 0.1);
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
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(242, 92, 92, 0.2);
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }
        
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #f25c5c;
            box-shadow: 0 0 0 3px rgba(242, 92, 92, 0.1);
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
            padding: 12px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(242, 92, 92, 0.3);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .help-text {
            font-size: 14px;
            color: #6b7280;
            margin-top: 0.5rem;
        }
        
        .mood-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        
        .mood-option {
            position: relative;
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border: 2px solid rgba(242, 92, 92, 0.1);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .mood-option:hover {
            border-color: rgba(242, 92, 92, 0.3);
            background: rgba(242, 92, 92, 0.05);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(242, 92, 92, 0.15);
        }
        
        .mood-option input[type="radio"]:checked + .mood-emoji + span,
        .mood-option:has(input[type="radio"]:checked) {
            border-color: #f25c5c;
            background: rgba(242, 92, 92, 0.1);
            color: #f25c5c;
            font-weight: 600;
        }
        
        .mood-option input[type="radio"] {
            margin-right: 10px;
            accent-color: #f25c5c;
        }
        
        .mood-emoji {
            font-size: 1.8rem;
            margin-right: 10px;
            font-family: 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif;
            line-height: 1;
        }
        
        .mood-music-icon {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        .mood-option:hover .mood-music-icon {
            opacity: 1;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }
        
        .mood-music-icon {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        .mood-option:hover .mood-music-icon {
            opacity: 1;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
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
            .form-group { margin-bottom: 1rem; }
        }
    </style>
</head>
<?php include 'includes/navbar.php'; ?>
<body>
    
    <div class="container">
        <h1 class="page-title">💌 Kirim Pesan ke Teman</h1>
        <div class="form-container">
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ <?= $success ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">

                
                <div class="form-group">
                    <label for="receiver_email" class="form-label">
                        👥 Email Penerima (Pengguna GAMON)
                    </label>
                    
                    <!-- Friends Dropdown -->
                    <?php if (!empty($friends)): ?>
                            <select id="friends_dropdown" class="form-input" onchange="selectFriend()">
                                <option value="">-- Pilih dari teman Anda --</option>
                                <?php foreach ($friends as $friend): ?>
                                    <option value="<?= htmlspecialchars($friend['friend_email']) ?>" 
                                            data-name="<?= htmlspecialchars($friend['friend_name']) ?>">
                                        <?= htmlspecialchars($friend['friend_name']) ?> (<?= htmlspecialchars($friend['friend_email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">
                                Pilih teman dari daftar atau masukkan email secara manual di bawah
                            </div>
                        <?php else: ?>
                            <div style="background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                                <strong>💡 Tips:</strong> Anda belum memiliki teman. 
                                <a href="friends.php" style="color: #f25c5c; text-decoration: underline;">Tambah teman</a> 
                                untuk memudahkan pengiriman pesan!
                            </div>
                        <?php endif; ?>
                    
                    <!-- Manual Email Input -->
                    <input type="email" id="receiver_email" name="receiver_email" class="form-input" 
                           required placeholder="contoh@email.com" 
                           value="<?= htmlspecialchars($_POST['receiver_email'] ?? '') ?>"
                           style="margin-top: 0.5rem;">
                    <div class="help-text">
                        💡 Masukkan email pengguna GAMON yang terdaftar
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="title" class="form-label">Judul Pesan</label>
                    <input type="text" id="title" name="title" class="form-input" 
                           required placeholder="Judul pesan Anda..." 
                           value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="content" class="form-label">Isi Pesan</label>
                    <textarea id="content" name="content" class="form-textarea" 
                              required placeholder="Tulis pesan Anda di sini..."><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="unlock_date" class="form-label">Kapan Pesan Dibuka?</label>
                    <input type="datetime-local" id="unlock_date" name="unlock_date" class="form-input" 
                           required min="<?= date('Y-m-d\TH:i') ?>" 
                           value="<?= $_POST['unlock_date'] ?? '' ?>">
                    <div class="help-text">Pilih tanggal dan waktu kapan penerima dapat membuka pesan ini</div>
                </div>
                
                <?php if (!empty($moods)): ?>
                <!-- Mood Count: <?= count($moods) ?> -->
                <div class="form-group">
                    <label class="form-label">Mood (Opsional)</label>
                    <div class="mood-grid">
                        <?php foreach ($moods as $mood): ?>
                            <label class="mood-option"
                                   data-music="<?php echo $mood['music_file'] ?? ''; ?>"
                                   data-music-name="<?php echo $mood['music_name'] ?? ''; ?>">
                                <input type="radio" name="mood_id" value="<?= $mood['id'] ?>"
                                       <?= ($_POST['mood_id'] ?? '') == $mood['id'] ? 'checked' : '' ?>>
                                <span class="mood-emoji"><?= $mood['emoji'] ?: '😊' ?></span>
                                <span><?= htmlspecialchars($mood['name'] ?: 'Mood') ?></span>
                                <?php if (!empty($mood['music_file'])): ?>
                                    <span class="mood-music-icon" title="<?php echo htmlspecialchars($mood['music_name'] ?? 'Musik mood'); ?>">🎵</span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>                <?php else: ?>
                <!-- No moods found - fallback -->
                <div class="form-group">
                    <label class="form-label">Mood (Opsional)</label>
                    <div class="mood-grid">
                        <label class="mood-option">
                            <input type="radio" name="mood_id" value="1">
                            <span class="mood-emoji">😊</span>
                            <span>Bahagia</span>
                        </label>
                        <label class="mood-option">
                            <input type="radio" name="mood_id" value="2">
                            <span class="mood-emoji">😐</span>
                            <span>Biasa saja</span>
                        </label>
                    </div>
                </div>                <?php endif; ?>
                
                <button type="submit" class="submit-btn">
                    🚀 Kirim Pesan
                </button>
            </form>
            
            <script>
                function selectFriend() {
                    const friendsDropdown = document.getElementById('friends_dropdown');
                    const emailInput = document.getElementById('receiver_email');
                    
                    if (friendsDropdown && friendsDropdown.value) {
                        emailInput.value = friendsDropdown.value;
                        
                        // Show selected friend name in a subtle way
                        const selectedOption = friendsDropdown.options[friendsDropdown.selectedIndex];
                        const friendName = selectedOption.dataset.name;
                        if (friendName) {
                            emailInput.style.background = '#e6f3e6';
                            emailInput.title = 'Terpilih: ' + friendName;
                            setTimeout(() => {
                                emailInput.style.background = '';
                            }, 2000);
                        }
                    }
                }
            </script>
            
            <!-- Mood Music Player -->
            <audio id="moodMusicPlayer" loop>
                <source src="" type="audio/wav">
                <source src="" type="audio/mpeg">
                Browser tidak mendukung audio.
            </audio>

            <script>
                // Mood Music Player
                const moodMusicPlayer = document.getElementById('moodMusicPlayer');

                // Function to play mood music
                function playMoodMusic(musicFile, musicName) {
                    if (musicFile && musicFile.trim() !== '') {
                        // Stop current music
                        moodMusicPlayer.pause();
                        moodMusicPlayer.currentTime = 0;
                        
                        // Set new music source
                        moodMusicPlayer.innerHTML = `
                            <source src="/gamon/uploads/music/moods/${musicFile}" type="audio/wav">
                            <source src="/gamon/uploads/music/moods/${musicFile.replace('.wav', '.mp3')}" type="audio/mpeg">
                        `;
                        
                        moodMusicPlayer.load();
                        moodMusicPlayer.volume = 0.3; // Set volume to 30%
                        
                        moodMusicPlayer.play().catch(e => {
                            console.log('Could not play music:', e);
                        });
                    } else {
                        // Stop music if no file
                        moodMusicPlayer.pause();
                    }
                }

                // Add event listeners to mood options
                document.addEventListener('DOMContentLoaded', function() {
                    const moodOptions = document.querySelectorAll('.mood-option');
                    
                    moodOptions.forEach(option => {
                        const radio = option.querySelector('input[type="radio"]');
                        
                        option.addEventListener('click', function() {
                            const musicFile = this.dataset.music;
                            const musicName = this.dataset.musicName;
                            
                            // Play music when mood is selected
                            if (radio.checked || !radio.checked) {
                                playMoodMusic(musicFile, musicName);
                            }
                        });
                    });
                });
            </script>
        </div>
    </div>
</body>
</html>