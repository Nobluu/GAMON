<?php
require_once 'controllers/Auth.php';
require_once 'controllers/Capsule.php';

$auth = new Auth();
$auth->requireLogin();

$user = $auth->getCurrentUser();
$capsuleController = new Capsule();

// Handle AJAX delete request
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

// Get all user's capsules
$allCapsules = $capsuleController->getUserCapsules($user['id']);

// Separate by status
$lockedCapsules = array_filter($allCapsules, function($capsule) {
    return (new DateTime($capsule['unlock_date']) > new DateTime()) && !$capsule['is_unlocked'];
});

$unlockedCapsules = array_filter($allCapsules, function($capsule) {
    return (new DateTime($capsule['unlock_date']) <= new DateTime()) || $capsule['is_unlocked'];
});

// Check for notification
$notification = null;
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kapsul - Capsule</title>
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

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #6b7280;
            font-size: 1.1rem;
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

        .capsule-list {
            display: none;
        }

        .capsule-list.active {
            display: block;
        }

        .capsule-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(242, 92, 92, 0.1);
            transition: all 0.3s ease;
        }

        .capsule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(242, 92, 92, 0.15);
        }

        .capsule-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .capsule-info h3 {
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .capsule-meta {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .capsule-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-view {
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-force-delete {
            background: #dc2626;
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .capsule-content {
            color: #4b5563;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .capsule-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-locked {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .status-unlocked {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
        }

        .warning-box {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #92400e;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #f25c5c;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 2rem;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            color: #e04d4d;
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .capsule-header { flex-direction: column; gap: 1rem; }
            .tabs { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">
            ← Kembali ke Dashboard
        </a>

        <div class="header">
            <h1 class="title">🗑️ Kelola Kapsul</h1>
            <p class="subtitle">Hapus kapsul yang tidak diperlukan</p>
        </div>

        <div class="warning-box">
            ⚠️ <strong>Peringatan:</strong> Penghapusan kapsul bersifat permanen dan tidak dapat dibatalkan. 
            Pastikan Anda benar-benar yakin sebelum menghapus kapsul, terutama yang sudah terbuka.
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('locked')">
                🔒 Kapsul Terkunci (<?= count($lockedCapsules) ?>)
            </button>
            <button class="tab-btn" onclick="showTab('unlocked')">
                🔓 Kapsul Terbuka (<?= count($unlockedCapsules) ?>)
            </button>
        </div>

        <!-- Locked Capsules -->
        <div id="locked" class="capsule-list active">
            <?php if (empty($lockedCapsules)): ?>
                <div class="empty-state">
                    <h3>Tidak ada kapsul terkunci</h3>
                    <p>Semua kapsul Anda sudah terbuka atau Anda belum membuat kapsul.</p>
                </div>
            <?php else: ?>
                <?php foreach ($lockedCapsules as $capsule): ?>
                    <div class="capsule-card">
                        <div class="capsule-header">
                            <div class="capsule-info">
                                <h3><?= htmlspecialchars($capsule['title']) ?></h3>
                                <div class="capsule-meta">
                                    Dibuat: <?= date('d M Y', strtotime($capsule['created_at'])) ?><br>
                                    Akan terbuka: <?= date('d M Y, H:i', strtotime($capsule['unlock_date'])) ?><br>
                                    Mood: <?= $capsule['mood_emoji'] ?? '😐' ?> <?= htmlspecialchars($capsule['mood_name'] ?? 'Netral') ?>
                                </div>
                            </div>
                            <div class="capsule-actions">
                                <?php if ($capsuleController->canDeleteCapsule($capsule['id'], $user['id'])): ?>
                                    <button onclick="deleteCapsule(<?= $capsule['id'] ?>)" 
                                            data-title="<?= htmlspecialchars($capsule['title'], ENT_QUOTES) ?>"
                                            class="btn btn-delete">
                                        🗑️ Hapus
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="capsule-content">
                            <?= htmlspecialchars(substr($capsule['message'], 0, 150)) ?><?= strlen($capsule['message']) > 150 ? '...' : '' ?>
                        </div>
                        <div class="capsule-status status-locked">
                            🔒 Terkunci
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Unlocked Capsules -->
        <div id="unlocked" class="capsule-list">
            <?php if (empty($unlockedCapsules)): ?>
                <div class="empty-state">
                    <h3>Tidak ada kapsul terbuka</h3>
                    <p>Belum ada kapsul yang terbuka untuk dihapus.</p>
                </div>
            <?php else: ?>
                <?php foreach ($unlockedCapsules as $capsule): ?>
                    <div class="capsule-card">
                        <div class="capsule-header">
                            <div class="capsule-info">
                                <h3><?= htmlspecialchars($capsule['title']) ?></h3>
                                <div class="capsule-meta">
                                    Dibuat: <?= date('d M Y', strtotime($capsule['created_at'])) ?><br>
                                    Dibuka: <?= date('d M Y, H:i', strtotime($capsule['unlocked_at'] ?? $capsule['unlock_date'])) ?><br>
                                    Mood: <?= $capsule['mood_emoji'] ?? '😐' ?> <?= htmlspecialchars($capsule['mood_name'] ?? 'Netral') ?>
                                </div>
                            </div>
                            <div class="capsule-actions">
                                <a href="capsule-detail.php?id=<?= $capsule['id'] ?>" class="btn btn-view">
                                    👁️ Lihat
                                </a>
                                <button onclick="forceDeleteCapsule(<?= $capsule['id'] ?>)" 
                                        data-title="<?= htmlspecialchars($capsule['title'], ENT_QUOTES) ?>"
                                        class="btn btn-force-delete"
                                        title="Hapus kapsul yang sudah terbuka (permanen)">
                                    🗑️ Hapus
                                </button>
                            </div>
                        </div>
                        <div class="capsule-content">
                            <?= htmlspecialchars(substr($capsule['message'], 0, 150)) ?><?= strlen($capsule['message']) > 150 ? '...' : '' ?>
                        </div>
                        <div class="capsule-status status-unlocked">
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

        function deleteCapsule(capsuleId) {
            const deleteButton = event.target;
            const capsuleTitle = deleteButton.getAttribute('data-title') || 'Kapsul ini';
            
            if (!confirm(`Apakah Anda yakin ingin menghapus kapsul "${capsuleTitle}"?\n\nKapsul yang sudah dihapus tidak dapat dikembalikan.`)) {
                return;
            }

            executeDelete('delete_capsule', capsuleId, deleteButton);
        }

        function forceDeleteCapsule(capsuleId) {
            const deleteButton = event.target;
            const capsuleTitle = deleteButton.getAttribute('data-title') || 'Kapsul ini';
            
            if (!confirm(`⚠️ PERINGATAN: Anda akan menghapus kapsul yang sudah terbuka!\n\nKapsul: "${capsuleTitle}"\n\nKapsul yang sudah terbuka biasanya mengandung kenangan berharga. Apakah Anda yakin ingin menghapusnya secara permanen?\n\nTindakan ini TIDAK dapat dibatalkan!`)) {
                return;
            }

            if (!confirm('Konfirmasi sekali lagi: Hapus kapsul yang sudah terbuka secara PERMANEN?')) {
                return;
            }

            executeDelete('force_delete_capsule', capsuleId, deleteButton);
        }

        function executeDelete(action, capsuleId, deleteButton) {
            const originalText = deleteButton.innerHTML;
            deleteButton.innerHTML = '⏳ Menghapus...';
            deleteButton.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${action}&capsule_id=${capsuleId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    
                    const capsuleCard = deleteButton.closest('.capsule-card');
                    capsuleCard.style.transition = 'all 0.3s ease';
                    capsuleCard.style.opacity = '0';
                    capsuleCard.style.transform = 'translateX(-100%)';
                    
                    setTimeout(() => {
                        capsuleCard.remove();
                        
                        const currentTab = document.querySelector('.capsule-list.active');
                        const remainingCapsules = currentTab.querySelectorAll('.capsule-card');
                        if (remainingCapsules.length === 0) {
                            location.reload();
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
                alert('Terjadi kesalahan saat menghapus kapsul');
                deleteButton.innerHTML = originalText;
                deleteButton.disabled = false;
            });
        }
    </script>
</body>
</html>