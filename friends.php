<?php
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
$friendController = new FriendController();

// Handle friend actions
$action_message = '';
$action_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_friend']) && !empty($_POST['friend_identifier'])) {
            $friend_identifier = trim($_POST['friend_identifier']);
            
            $result = $friendController->sendFriendRequest($_SESSION['user_id'], $friend_identifier);
            $action_message = $result['message'];
            $action_type = $result['status'] ? 'success' : 'error';
        }
        
        if (isset($_POST['accept_request'])) {
            $result = $friendController->acceptFriendRequest($_POST['friendship_id'], $_SESSION['user_id']);
            $action_message = $result['message'];
            $action_type = $result['status'] ? 'success' : 'error';
        }
        
        if (isset($_POST['decline_request'])) {
            $result = $friendController->declineFriendRequest($_POST['friendship_id'], $_SESSION['user_id']);
            $action_message = $result['message'];
            $action_type = $result['status'] ? 'success' : 'error';
        }
        
        if (isset($_POST['remove_friend'])) {
            $result = $friendController->removeFriend($_POST['friendship_id'], $_SESSION['user_id']);
            $action_message = $result['message'];
            $action_type = $result['status'] ? 'success' : 'error';
        }
        
    } catch (Exception $e) {
        $action_message = "Terjadi kesalahan: " . $e->getMessage();
        $action_type = 'error';
    }
}

// Get data
$friends_result = $friendController->getFriends($_SESSION['user_id']);
$friends = $friends_result['status'] ? $friends_result['data'] : [];

$requests_result = $friendController->getPendingRequests($_SESSION['user_id']);
$pending_requests = $requests_result['status'] ? $requests_result['data'] : [];

$user_friend_code = $friendController->getUserFriendCode($_SESSION['user_id']);

// Handle search
$search_results = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_result = $friendController->searchUsers($_GET['search'], $_SESSION['user_id']);
    $search_results = $search_result['status'] ? $search_result['data'] : [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Teman - GAMON</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
            line-height: 1.6;
        }



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

        /* Responsive design */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .container {
                padding: 1rem;
            }
            
            .search-form {
                flex-direction: column;
            }
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
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: #6b7280;
        }

        .card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(242, 92, 92, 0.1);
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .notification.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .friend-code-section {
            background: linear-gradient(135deg, rgba(242, 92, 92, 0.05), rgba(255, 123, 123, 0.05));
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
            border: 1px solid rgba(242, 92, 92, 0.1);
        }

        .friend-code {
            font-size: 2rem;
            font-weight: bold;
            color: #f25c5c;
            background: white;
            padding: 1rem 2rem;
            border-radius: 15px;
            display: inline-block;
            margin: 1rem 0;
            letter-spacing: 2px;
            border: 2px dashed #f25c5c;
            box-shadow: 0 5px 15px rgba(242, 92, 92, 0.1);
        }

        .search-section {
            margin-bottom: 2rem;
        }

        .search-form {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .search-input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: 2px solid rgba(242, 92, 92, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #f25c5c;
            box-shadow: 0 0 0 3px rgba(242, 92, 92, 0.1);
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #f87171);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .user-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(242, 92, 92, 0.1);
        }

        .user-avatar-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            overflow: hidden;
        }

        .user-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            flex: 1;
        }

        .friend-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .user-name {
            font-weight: 600;
            color: #374151;
        }

        .user-email {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .user-code {
            background: #e0f2fe;
            color: #0891b2;
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-accepted {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .user-card {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<?php include 'includes/navbar.php'; ?>
<body>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">👥 Kelola Teman</h1>
            <p class="page-subtitle">Tambah teman dan kelola permintaan pertemanan</p>
        </div>

        <?php if ($action_message): ?>
            <div class="notification <?= $action_type ?>">
                <span><?= htmlspecialchars($action_message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Pending Friend Requests -->
        <?php if (!empty($pending_requests) && count($pending_requests) > 0): ?>
            <div class="card">
                <h2 class="section-title">🔔 Permintaan Pertemanan (<?= count($pending_requests) ?>)</h2>
                <?php foreach ($pending_requests as $request): ?>
                    <div class="user-card">
                        <div class="user-avatar-small">
                            <?php if (!empty($request['requester_picture']) && file_exists($request['requester_picture'])): ?>
                                <img src="<?= htmlspecialchars($request['requester_picture']) ?>" alt="Profile">
                            <?php else: ?>
                                <?= strtoupper(substr($request['requester_name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($request['requester_name']) ?></div>
                            <div class="user-email"><?= htmlspecialchars($request['requester_email']) ?></div>
                            <span class="user-code"><?= htmlspecialchars($request['requester_code']) ?></span>
                            <div style="margin-top: 0.5rem; color: #6b7280; font-size: 0.8rem;">
                                <?= date('d M Y, H:i', strtotime($request['created_at'])) ?>
                            </div>
                        </div>
                        <div class="friend-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="friendship_id" value="<?= $request['friendship_id'] ?>">
                                <button type="submit" name="accept_request" class="btn btn-success">Terima</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="friendship_id" value="<?= $request['friendship_id'] ?>">
                                <button type="submit" name="decline_request" class="btn btn-secondary">Tolak</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Friend Code Section -->
        <div class="card">
            <div class="friend-code-section">
                <h3>🔗 Kode Teman Anda</h3>
                <p>Bagikan kode ini kepada teman untuk memudahkan mereka menambahkan Anda</p>
                <div class="friend-code" onclick="copyFriendCode()"><?= $user_friend_code ?></div>
                <small>Klik untuk menyalin kode</small>
            </div>
        </div>

        <!-- Add Friend Section -->
        <div class="card">
            <h2 class="section-title">➕ Tambah Teman</h2>
            <form method="POST" class="search-form">
                <input type="text" 
                       name="friend_identifier" 
                       class="search-input" 
                       placeholder="Masukkan email atau kode teman (contoh: ABC12345)"
                       required>
                <button type="submit" name="add_friend" class="btn btn-primary">Kirim Permintaan</button>
            </form>
        </div>

        <!-- Search Users Section -->
        <div class="card">
            <h2 class="section-title">🔍 Cari Pengguna</h2>
            <form method="GET" class="search-form">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Cari berdasarkan nama, email, atau kode teman"
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit" class="btn btn-secondary">Cari</button>
            </form>
            
            <?php if (!empty($search_results)): ?>
                <div style="margin-top: 1rem;">
                    <?php foreach ($search_results as $user_result): ?>
                        <div class="user-card">
                            <div class="user-avatar-small">
                                <?php if (!empty($user_result['profile_picture']) && file_exists($user_result['profile_picture'])): ?>
                                    <img src="<?= htmlspecialchars($user_result['profile_picture']) ?>" alt="Profile">
                                <?php else: ?>
                                    <?= strtoupper(substr($user_result['name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?= htmlspecialchars($user_result['name']) ?></div>
                                <div class="user-email"><?= htmlspecialchars($user_result['email']) ?></div>
                                <span class="user-code"><?= htmlspecialchars($user_result['friend_code']) ?></span>
                            </div>
                            <div>
                                <?php if ($user_result['friendship_status'] === 'none'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="friend_identifier" value="<?= $user_result['friend_code'] ?>">
                                        <button type="submit" name="add_friend" class="btn btn-primary">Tambah Teman</button>
                                    </form>
                                <?php elseif ($user_result['friendship_status'] === 'pending'): ?>
                                    <span class="status-badge status-pending">Pending</span>
                                <?php elseif ($user_result['friendship_status'] === 'accepted'): ?>
                                    <span class="status-badge status-accepted">✓ Teman</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (isset($_GET['search'])): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <p>Tidak ada pengguna yang ditemukan untuk "<?= htmlspecialchars($_GET['search']) ?>"</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Friends List -->
        <div class="card">
            <h2 class="section-title">👫 Daftar Teman (<?= count($friends) ?>)</h2>
            <?php if (!empty($friends)): ?>
                <?php foreach ($friends as $friend): ?>
                    <div class="user-card">
                        <div class="user-avatar-small">
                            <?php if (!empty($friend['friend_profile_picture']) && file_exists($friend['friend_profile_picture'])): ?>
                                <img src="<?= htmlspecialchars($friend['friend_profile_picture']) ?>" alt="Profile">
                            <?php else: ?>
                                <?= strtoupper(substr($friend['friend_name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($friend['friend_name']) ?></div>
                            <div class="user-email"><?= htmlspecialchars($friend['friend_email']) ?></div>
                            <span class="user-code"><?= htmlspecialchars($friend['friend_code']) ?></span>
                            <div style="margin-top: 0.5rem; color: #6b7280; font-size: 0.8rem;">
                                Teman sejak <?= date('d M Y', strtotime($friend['friendship_date'])) ?>
                            </div>
                        </div>
                        <div>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus teman ini?')">
                                <input type="hidden" name="friendship_id" value="<?= $friend['friendship_id'] ?>">
                                <button type="submit" name="remove_friend" class="btn btn-danger">Hapus Teman</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <p>Anda belum memiliki teman</p>
                    <p>Mulai tambahkan teman menggunakan email atau kode teman mereka</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyFriendCode() {
            const code = '<?= $user_friend_code ?>';
            navigator.clipboard.writeText(code).then(() => {
                alert('Kode teman berhasil disalin: ' + code);
            }).catch(() => {
                // Fallback untuk browser lama
                const textArea = document.createElement('textarea');
                textArea.value = code;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Kode teman berhasil disalin: ' + code);
            });
        }
    </script>
</body>
</html>