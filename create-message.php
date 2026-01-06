<?php
require_once 'controllers/Auth.php';
require_once 'controllers/Capsule.php';
require_once 'config/database.php';
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
$database = new Database();
$conn = $database->getConnection();
$message = '';
$messageType = '';

// Get moods from database
try {
    $moods = $capsuleController->getAllMoods();
    // Ensure we have data in the correct format
    if (empty($moods)) {
        throw new Exception("No moods returned from database");
    }
} catch (Exception $e) {
    $moods = [];
    error_log("Error fetching moods: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $message_content = $_POST['message'] ?? '';
    $mood_id = $_POST['mood_id'] ?? 1;
    $unlock_date = $_POST['unlock_date'] ?? '';
    


    // File Upload Handling
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $message = 'Gagal membuat direktori upload.';
                $messageType = 'error';
            }
        }
        
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileSize = $_FILES['image']['size'];
        $fileType = $_FILES['image']['type'];
        
        // Validate file size (max 5MB)
        if ($fileSize > 5 * 1024 * 1024) {
            $message = 'Ukuran file terlalu besar. Maksimum 5MB.';
            $messageType = 'error';
        } else {
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            // Use more secure mime type detection
            $detectedMime = mime_content_type($fileTmpPath);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
            
            if (in_array($fileExtension, $allowedfileExtensions) && in_array($detectedMime, $allowedMimes)) {
                // Generate secure filename
                $newFileName = md5(time() . $fileName . uniqid()) . '.' . $fileExtension;
                $dest_path = $uploadDir . $newFileName;
                
                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    $image_path = $dest_path;
                } else {
                    $message = 'Terjadi kesalahan saat memindahkan file ke direktori upload.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Upload gagal. Jenis file yang diizinkan: ' . implode(',', $allowedfileExtensions);
                $messageType = 'error';
            }
        }
    }

    // Create Capsule if no upload error
    if (empty($message)) {
        // Validate required fields
        if (empty($title) || empty($message_content) || empty($unlock_date)) {
            $message = 'Silakan isi semua kolom yang diperlukan termasuk judul, pesan, dan tanggal buka.';
            $messageType = 'error';
        } else {
            try {
                $user = $auth->getCurrentUser();
                $capsuleData = [
                    'title' => $title,
                    'message' => $message_content,
                    'mood_id' => $mood_id,
                    'unlock_date' => $unlock_date
                ];
                
                $result = $capsuleController->createCapsule($user['id'], $capsuleData);
                
                // Save media to capsule_media table if image was uploaded
                if ($result['success'] && $image_path) {
                    $capsule_id = $result['capsule_id'];
                    $original_filename = $_FILES['image']['name'];
                    $new_filename = basename($image_path);
                    $file_type = $_FILES['image']['type'];
                    $file_size = $_FILES['image']['size'];
                    
                    try {
                        $media_stmt = $conn->prepare("INSERT INTO capsule_media (capsule_id, filename, original_name, file_type, file_size, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $media_stmt->execute([$capsule_id, $new_filename, $original_filename, $file_type, $file_size]);
                    } catch (PDOException $e) {
                        error_log("Error saving media: " . $e->getMessage());
                        $message .= " (Warning: Image uploaded but not saved to database)";
                    }
                }

            if ($result['success']) {
                $upload_info = $image_path ? " dengan gambar" : "";
                $success_message = 'Kapsul berhasil dibuat' . $upload_info . '! Akan dibuka pada ' . date('d M Y, H:i', strtotime($unlock_date));
                
                // Set success notification in session
                $_SESSION['notification'] = [
                    'type' => 'success',
                    'message' => $success_message
                ];
                
                // Clear form data and redirect to Kapsul Saya
                $_POST = [];
                header('Location: view-message.php?created=1');
                exit;
            } else {
                $message = $result['message'] ?? 'Gagal membuat kapsul. Silakan coba lagi.';
                $messageType = 'error';
            }
            } catch (Exception $e) {
                $message = 'Kesalahan: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Kapsul - Capsule</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
            line-height: 1.6;
        }
        .container {
           max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: #6b7280;
        }

        .form-container {
           background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            box-shadow: 0 20px 40px rgba(242, 92, 92, 0.1);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            color: #374151;
            font-weight: 600;
            font-size: 1rem;
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            max-width: 100%;
            padding: 0.75rem;
            border: 2px solid rgba(242, 92, 92, 0.15);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            font-family: inherit;
        }

        .form-textarea {
            height: 80px;
            resize: vertical;
            min-height: 60px;
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: #f25c5c;
            box-shadow: 0 0 0 3px rgba(242, 92, 92, 0.1);
            background: white;
        }

        .form-file {
            padding: 0.75rem;
            border: 2px dashed rgba(242, 92, 92, 0.3);
            border-radius: 12px;
            background: rgba(242, 92, 92, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-file:hover {
            border-color: #f25c5c;
            background: rgba(242, 92, 92, 0.1);
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
            box-shadow: 0 4px 20px rgba(242, 92, 92, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(242, 92, 92, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.8);
            color: #6b7280;
            border: 1px solid rgba(242, 92, 92, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(242, 92, 92, 0.1);
            color: #f25c5c;
        }

        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            text-align: center;
            font-weight: 500;
        }

        .alert.success {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Mood Dropdown Styles */
        .mood-dropdown {
            position: relative;
            width: 100%;
        }

        .dropdown-button {
            width: 100%;
            padding: 16px 20px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .dropdown-button:hover {
            border-color: #f25c5c;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .dropdown-button.active {
            border-color: #f25c5c;
            box-shadow: 0 0 0 3px rgba(242, 92, 92, 0.1);
        }

        .dropdown-content {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            margin-top: 8px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            max-height: 250px;
            overflow-y: auto;
        }

        .dropdown-content.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .mood-item {
            padding: 12px 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #f3f4f6;
        }

        .mood-item:last-child {
            border-bottom: none;
        }

        .mood-item:hover {
            background: #f8fafc;
            padding-left: 20px;
        }

        .mood-emoji {
            font-size: 20px;
            transition: transform 0.2s ease;
            font-family: 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif;
            line-height: 1;
        }

        .mood-item:hover .mood-emoji {
            transform: scale(1.2);
        }

        .mood-text {
            font-weight: 500;
            color: #374151;
        }

        .mood-music-icon {
            color: #f25c5c;
            font-size: 0.8rem;
            margin-left: auto;
            opacity: 0.7;
        }

        .mood-item:hover .mood-music-icon {
            opacity: 1;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .placeholder-text {
            color: #9ca3af;
            font-weight: normal;
        }

        .selected-mood {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            color: #374151;
        }

        .chevron {
            transition: transform 0.3s ease;
            color: #9ca3af;
        }

        .dropdown-button.active .chevron {
            transform: rotate(180deg);
        }

        @media (max-width: 768px) {
            .container { padding: 0 1rem 2rem; }
            .form-container { padding: 2rem 1.5rem; }
            .btn-group { flex-direction: column; }
            .nav { padding: 0 1rem; }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">✍️ Buat Kapsul Baru</h1>
            <p class="page-subtitle">Tulis pesan untuk diri Anda di masa depan</p>
        </div>

        <div class="form-container">
            <?php if ($message): ?>
                <div class="alert <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">📝 Judul Kapsul</label>
                    <input type="text" name="title" class="form-input" required 
                           placeholder="Berikan judul yang bermakna untuk kapsul Anda..." 
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">💭 Pesan Anda</label>
                    <textarea name="message" class="form-textarea" required 
                              placeholder="Tulis pesan Anda untuk masa depan..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">😊 Pilih Mood Anda</label>
                    <div class="mood-dropdown">
                        <div class="dropdown-button" id="moodDropdownButton">
                            <div id="selectedMoodDisplay">
                                <span class="placeholder-text">Bagaimana perasaan Anda hari ini?</span>
                            </div>
                            <i class="chevron">▼</i>
                        </div>

                        <div class="dropdown-content" id="moodDropdownContent">
                            <?php foreach ($moods as $mood): ?>
                                <div class="mood-item" 
                                     data-mood-id="<?php echo $mood['id']; ?>"
                                     data-emoji="<?php echo $mood['emoji']; ?>"
                                     data-text="<?php echo $mood['name']; ?>"
                                     data-music="<?php echo $mood['music_file'] ?? ''; ?>"
                                     data-music-name="<?php echo $mood['music_name'] ?? ''; ?>">
                                    <span class="mood-emoji"><?php echo $mood['emoji']; ?></span>
                                    <span class="mood-text"><?php echo $mood['name']; ?></span>
                                    <?php if (!empty($mood['music_file'])): ?>
                                        <span class="mood-music-icon" title="<?php echo htmlspecialchars($mood['music_name'] ?? 'Musik mood'); ?>">🎵</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Hidden input to store selected mood -->
                        <input type="hidden" name="mood_id" id="selectedMoodId" value="<?php echo $_POST['mood_id'] ?? 1; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">📷 Lampirkan Gambar (Opsional)</label>
                    <input type="file" name="image" class="form-file" accept="image/*">
                    <small style="color: #6b7280; margin-top: 0.5rem; display: block;">
                        Format yang didukung: JPG, PNG, GIF (Ukuran maks: 5MB)
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">⏰ Tanggal & Waktu Buka</label>
                    <input type="datetime-local" name="unlock_date" class="form-input" required 
                           min="<?php echo date('Y-m-d\TH:i'); ?>"
                           value="<?php echo isset($_POST['unlock_date']) ? $_POST['unlock_date'] : ''; ?>">
                    <small style="color: #6b7280; margin-top: 0.5rem; display: block;">
                        Pilih kapan kapsul ini harus dibuka di masa depan
                    </small>
                </div>

                <div class="btn-group">
                    <a href="dashboard.php" class="btn btn-secondary">
                        ← Kembali ke Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary">
                        🕰️ Buat Kapsul
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mood Music Player -->
    <audio id="moodMusicPlayer" loop>
        <!-- Source will be set dynamically -->
    </audio>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const moodDropdownButton = document.getElementById('moodDropdownButton');
            const moodDropdownContent = document.getElementById('moodDropdownContent');
            const selectedMoodDisplay = document.getElementById('selectedMoodDisplay');
            const selectedMoodId = document.getElementById('selectedMoodId');
            const moodItems = document.querySelectorAll('.mood-item');
            const moodMusicPlayer = document.getElementById('moodMusicPlayer');
            
            // Function to play mood music
            function playMoodMusic(musicFile, musicName) {
                if (musicFile && musicFile.trim() !== '') {
                    // Stop current music
                    moodMusicPlayer.pause();
                    moodMusicPlayer.currentTime = 0;
                    
                    // Set new source
                    moodMusicPlayer.innerHTML = `
                        <source src="uploads/music/moods/${musicFile}" type="audio/mpeg">
                        <source src="uploads/music/moods/${musicFile.replace('.mp3', '.wav')}" type="audio/wav">
                    `;
                    
                    // Load and play
                    moodMusicPlayer.load();
                    moodMusicPlayer.volume = 0.3; // Set volume to 30%
                    
                    // Try to play
                    moodMusicPlayer.play().catch(e => {
                        console.log('Mood music autoplay blocked by browser:', e);
                        showMusicNotification(musicName || 'Musik Mood');
                    });
                } else {
                    // Stop music if no file
                    moodMusicPlayer.pause();
                }
            }
            
            // Function to show music notification
            function showMusicNotification(musicName) {
                // Remove existing notification
                const existing = document.querySelector('.music-notification');
                if (existing) existing.remove();
                
                const notification = document.createElement('div');
                notification.className = 'music-notification';
                notification.innerHTML = `
                    <div style="position: fixed; bottom: 20px; left: 20px; background: rgba(242, 92, 92, 0.9); color: white; padding: 10px 15px; border-radius: 12px; font-size: 12px; z-index: 1000; cursor: pointer; box-shadow: 0 4px 12px rgba(242, 92, 92, 0.3);">
                        🎵 ${musicName} - Klik untuk memutar
                    </div>
                `;
                
                notification.addEventListener('click', function() {
                    moodMusicPlayer.play();
                    notification.remove();
                });
                
                document.body.appendChild(notification);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 5000);
            }

            // Toggle dropdown
            moodDropdownButton.addEventListener('click', function(e) {
                e.preventDefault();
                const isOpen = moodDropdownContent.classList.contains('show');
                
                if (isOpen) {
                    moodDropdownContent.classList.remove('show');
                    moodDropdownButton.classList.remove('active');
                } else {
                    moodDropdownContent.classList.add('show');
                    moodDropdownButton.classList.add('active');
                }
            });

            // Handle mood selection
            moodItems.forEach(item => {
                item.addEventListener('click', function() {
                    const emoji = this.dataset.emoji;
                    const text = this.dataset.text;
                    const moodId = this.dataset.moodId;
                    const musicFile = this.dataset.music;
                    const musicName = this.dataset.musicName;

                    // Update selected display
                    selectedMoodDisplay.innerHTML = `
                        <div class="selected-mood">
                            <span style="font-size: 18px;">${emoji}</span>
                            <span>${text}</span>
                            ${musicFile ? '<span style="color: #f25c5c; margin-left: 8px;" title="' + musicName + '">🎵</span>' : ''}
                        </div>
                    `;

                    // Update hidden input
                    selectedMoodId.value = moodId;
                    
                    // Play mood music
                    playMoodMusic(musicFile, musicName);

                    // Close dropdown
                    moodDropdownContent.classList.remove('show');
                    moodDropdownButton.classList.remove('active');
                });
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!moodDropdownButton.contains(event.target) && !moodDropdownContent.contains(event.target)) {
                    moodDropdownContent.classList.remove('show');
                    moodDropdownButton.classList.remove('active');
                }
            });

            // Set initial mood if exists
            if (selectedMoodId.value && selectedMoodId.value !== '') {
                const initialMoodItem = document.querySelector(`[data-mood-id="${selectedMoodId.value}"]`);
                if (initialMoodItem) {
                    const emoji = initialMoodItem.dataset.emoji;
                    const text = initialMoodItem.dataset.text;
                    selectedMoodDisplay.innerHTML = `
                        <div class="selected-mood">
                            <span style="font-size: 18px;">${emoji}</span>
                            <span>${text}</span>
                        </div>
                    `;
                }
            }
        });
    </script>
</body>
</html>