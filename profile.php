<?php
require_once 'config/database.php';
require_once 'controllers/Auth.php';
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

$message = '';
$messageType = '';

// Handle session deletion
if (isset($_GET['delete_session']) && $_GET['delete_session']) {
    $sessionToDelete = $_GET['delete_session'];
    try {
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE id = ? AND user_email = ?");
        $stmt->execute([$sessionToDelete, $user['email']]);
        
        $message = 'Sesi berhasil dihapus!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Gagal menghapus sesi: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle logout from all devices
if (isset($_GET['logout_all']) && $_GET['logout_all'] === '1') {
    try {
        // Keep current session, delete others
        $currentSessionId = $_SESSION['session_db_id'] ?? '';
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_email = ? AND id != ?");
        $stmt->execute([$user['email'], $currentSessionId]);
        
        $deletedCount = $stmt->rowCount();
        $message = "Berhasil keluar dari $deletedCount perangkat lain!";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Gagal logout dari semua perangkat: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get user's active sessions
$activeSessions = $auth->getUserActiveSessions($user['email']);

// Handle delete profile picture
if (isset($_GET['delete_photo']) && $_GET['delete_photo'] === '1') {
    try {
        // Delete file if exists
        if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
            unlink($user['profile_picture']);
        }
        
        // Update database
        $stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Update session
        unset($_SESSION['user_profile_picture']);
        
        $message = 'Foto profil berhasil dihapus!';
        $messageType = 'success';
        
        // Refresh user data
        $user = $auth->getCurrentUser();
    } catch (Exception $e) {
        $message = 'Gagal menghapus foto profil: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $profile_picture = null;
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
        $fileName = $_FILES['profile_picture']['name'];
        $fileSize = $_FILES['profile_picture']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        if (in_array($fileExtension, $allowedExtensions) && $fileSize <= $maxFileSize) {
            $newFileName = 'profile_' . $user['id'] . '_' . time() . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // Delete old profile picture if exists
                if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                    unlink($user['profile_picture']);
                }
                $profile_picture = $destPath;
            } else {
                $message = 'Gagal mengupload foto profil.';
                $messageType = 'error';
            }
        } else {
            $message = 'File tidak valid. Gunakan JPG, PNG, GIF, atau WEBP maksimal 5MB.';
            $messageType = 'error';
        }
    }
    
    // Update user data
    if (empty($message)) {
        try {
            if ($profile_picture) {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$name, $email, $profile_picture, $user['id']]);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$name, $email, $user['id']]);
            }
            
            // Update session
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            if ($profile_picture) {
                $_SESSION['user_profile_picture'] = $profile_picture;
            }
            
            $message = 'Profile berhasil diperbarui!';
            $messageType = 'success';
            
            // Refresh user data
            $user = $auth->getCurrentUser();
        } catch (PDOException $e) {
            $message = 'Gagal memperbarui profile: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - GAMON</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(242, 92, 92, 0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
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
            text-decoration: none;
        }

        .logo-img {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        .nav-links {
            display: flex;
            gap: 2.5rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #6b7280;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover { color: #f25c5c; }

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
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .profile-container {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(242, 92, 92, 0.1);
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

        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid rgba(242, 92, 92, 0.1);
            justify-content: space-between;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 2rem;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #f87171) !important;
            color: white !important;
            padding: 0.75rem 1.5rem !important;
            border-radius: 12px !important;
            text-decoration: none !important;
            font-weight: 600 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3) !important;
        }

        .logout-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4) !important;
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

        .form-input, .form-file {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(242, 92, 92, 0.2);
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-input:focus, .form-file:focus {
            outline: none;
            border-color: #f25c5c;
            box-shadow: 0 0 0 3px rgba(242, 92, 92, 0.1);
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
            box-shadow: 0 8px 20px rgba(242, 92, 92, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .help-text {
            font-size: 14px;
            color: #6b7280;
            margin-top: 0.5rem;
        }

        .delete-btn {
            background: #ef4444;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .delete-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .nav { 
                padding: 0 1rem; 
                flex-direction: column;
                gap: 1rem;
            }
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<?php include 'includes/navbar.php'; ?>
<body>
    <div class="container">
        <div class="profile-container">
            <h1 class="page-title">Profile Saya</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>">
                    <?= $messageType === 'success' ? '✅' : '❌' ?> <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="profile-header">
                <div class="profile-avatar">
                    <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                        <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile">
                    <?php else: ?>
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div style="flex: 1;">
                    <h2><?= htmlspecialchars($user['name']) ?></h2>
                    <p style="color: #6b7280;"><?= htmlspecialchars($user['email']) ?></p>
                    <p style="color: #9ca3af; font-size: 14px;">Member since <?= date('d M Y', strtotime($user['created_at'])) ?></p>
                </div>
                <div>
                    <a href="dashboard.php?logout=1" 
                       onclick="return confirm('Yakin ingin keluar dari akun?')"
                       class="logout-btn">
                        🚪 Keluar
                    </a>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name" class="form-label">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="form-input" 
                           required value="<?= htmlspecialchars($user['name']) ?>">
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           required value="<?= htmlspecialchars($user['email']) ?>">
                </div>

                <div class="form-group">
                    <label for="profile_picture" class="form-label">Foto Profile</label>
                    
                    <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                        <div class="current-photo" style="margin-bottom: 1rem; display: flex; align-items: center; gap: 1rem;">
                            <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Current Profile" 
                                 style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(242, 92, 92, 0.2);">
                            <div>
                                <p style="font-weight: 600; color: #374151;">Foto saat ini</p>
                                <a href="?delete_photo=1" class="delete-btn" 
                                   onclick="return confirm('Yakin ingin menghapus foto profil?')">
                                    🗑️ Hapus Foto
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <input type="file" id="profile_picture" name="profile_picture" 
                           class="form-file" accept="image/*">
                    <div class="help-text">
                        Format yang didukung: JPG, PNG, GIF, WEBP. Maksimal 5MB.
                        <?php if (!empty($user['profile_picture'])): ?>
                            <br>Upload foto baru untuk mengganti foto yang ada.
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    💾 Simpan Perubahan
                </button>
            </form>
        </div>

        <!-- Active Sessions Section -->
        <?php if (!empty($activeSessions)): ?>
        <div class="profile-container" style="margin-top: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2 class="page-title" style="font-size: 2rem; margin-bottom: 0;">🖥️ Sesi Aktif</h2>
                <?php if (count($activeSessions) > 1): ?>
                    <a href="?logout_all=1" 
                       onclick="return confirm('Yakin ingin keluar dari semua perangkat lain? Anda harus login ulang di perangkat tersebut.')"
                       style="background: #ef4444; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease;">
                        🚪 Keluar dari Semua Perangkat
                    </a>
                <?php endif; ?>
            </div>
            
            <div style="margin-bottom: 1rem; color: #6b7280;">
                Berikut adalah daftar perangkat yang sedang login dengan akun Anda:
            </div>
            
            <?php foreach ($activeSessions as $session): ?>
                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: between; align-items: start; gap: 1rem;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span style="font-size: 1.2rem;">
                                    <?php if (strpos($session['user_agent'], 'Mobile') !== false): ?>
                                        📱
                                    <?php elseif (strpos($session['user_agent'], 'iPhone') !== false): ?>
                                        📱
                                    <?php else: ?>
                                        🖥️
                                    <?php endif; ?>
                                </span>
                                <strong>
                                    <?php if (strpos($session['user_agent'], 'Chrome') !== false): ?>
                                        Chrome Browser
                                    <?php elseif (strpos($session['user_agent'], 'Firefox') !== false): ?>
                                        Firefox Browser  
                                    <?php elseif (strpos($session['user_agent'], 'Safari') !== false): ?>
                                        Safari Browser
                                    <?php else: ?>
                                        Browser Lainnya
                                    <?php endif; ?>
                                </strong>
                            </div>
                            
                            <div style="font-size: 0.9rem; color: #6b7280; margin-bottom: 0.25rem;">
                                <strong>IP Address:</strong> <?= htmlspecialchars($session['ip_address']) ?>
                            </div>
                            
                            <div style="font-size: 0.9rem; color: #6b7280; margin-bottom: 0.25rem;">
                                <strong>Login:</strong> <?= date('d M Y, H:i', strtotime($session['created_at'])) ?>
                            </div>
                            
                            <div style="font-size: 0.9rem; color: #6b7280;">
                                <strong>Aktivitas Terakhir:</strong> <?= date('d M Y, H:i', strtotime($session['last_activity'])) ?>
                            </div>
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: end;">
                            <?php if ($session['id'] === ($_SESSION['session_db_id'] ?? '')): ?>
                                <span style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                    🟢 Sesi Ini
                                </span>
                            <?php else: ?>
                                <span style="background: #fee2e2; color: #991b1b; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                    🔴 Perangkat Lain
                                </span>
                                <a href="?delete_session=<?= htmlspecialchars($session['id']) ?>" 
                                   onclick="return confirm('Yakin ingin menghapus sesi ini? Perangkat tersebut harus login ulang.')"
                                   style="background: #dc2626; color: white; padding: 0.4rem 0.8rem; border-radius: 6px; text-decoration: none; font-size: 0.75rem; font-weight: 600; transition: all 0.3s ease;"
                                   onmouseover="this.style.background='#b91c1c'"
                                   onmouseout="this.style.background='#dc2626'">
                                    🗑️ Hapus
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="font-size: 0.8rem; color: #9ca3af; margin-top: 0.5rem; font-family: monospace;">
                        Session ID: <?= htmlspecialchars(substr($session['id'], 0, 20)) ?>...
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Session Statistics -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 12px; padding: 1rem; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: #0369a1;">
                        <?= count($activeSessions) ?>
                    </div>
                    <div style="color: #0369a1; font-size: 0.9rem;">
                        Sesi Aktif
                    </div>
                </div>
                
                <div style="background: #f0fdf4; border: 1px solid #22c55e; border-radius: 12px; padding: 1rem; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: #15803d;">
                        <?php
                        $latestActivity = !empty($activeSessions) ? max(array_column($activeSessions, 'last_activity')) : null;
                        if ($latestActivity) {
                            $diffMinutes = round((time() - strtotime($latestActivity)) / 60);
                            echo $diffMinutes < 60 ? $diffMinutes . 'm' : round($diffMinutes / 60) . 'h';
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                    <div style="color: #15803d; font-size: 0.9rem;">
                        Aktivitas Terakhir
                    </div>
                </div>
            </div>

            <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 12px; padding: 1rem; margin-top: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; color: #92400e;">
                    <span>⚠️</span>
                    <strong>Keamanan:</strong>
                </div>
                <div style="color: #92400e; font-size: 0.9rem; margin-top: 0.5rem;">
                    • Jika ada sesi yang tidak Anda kenali, segera ganti password dan keluar dari semua perangkat<br>
                    • Sesi akan otomatis dihapus setelah 7 hari tidak aktif<br>
                    • Selalu logout dari perangkat umum/publik
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>