<?php
require_once 'controllers/Auth.php';
require_once 'controllers/Capsule.php';

$auth = new Auth();
$auth->requireLogin();

// Redirect admin users to admin dashboard
if ($auth->isAdmin()) {
    header('Location: admin/dashboard.php');
    exit;
}

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

if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

// Get user's capsules
$capsules = $capsuleController->getUserCapsules($user['id']);
$totalCapsules = count($capsules);
$unlockedCapsules = array_filter($capsules, function($c) { return strtotime($c['unlock_date']) <= time(); });
$lockedCapsules = array_filter($capsules, function($c) { return strtotime($c['unlock_date']) > time(); });

// Get all available moods for filtering
$available_moods = $capsuleController->getAllMoods();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Capsule</title>
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

        .welcome-section {
            text-align: center;
            margin-bottom: 3rem;
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        
        .filter-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 4rem;
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
            justify-content: center;
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
            text-align: center;
        }
           
        .welcome-subtitle {
            font-size: 1.1rem;
            color: #6b7280;
            max-width: 600px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(242, 92, 92, 0.1);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #f25c5c;
        }

        .capsule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .capsule-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            transition: transform 0.3s ease;
        }

        .capsule-card:hover {
            transform: translateY(-5px);
        }

        .capsule-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .status-locked {
            background: rgba(255, 193, 7, 0.2);
            color: #b8860b;
        }

        .status-unlocked {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .capsule-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .capsule-date {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .capsule-preview {
            color: #4b5563;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }

        .capsule-actions {
            display: flex;
            gap: 1rem;
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
        }

        .btn-primary {
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
        }

        .btn-secondary {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .btn:hover {
            transform: translateY(-2px);
        }
            margin-bottom: 0.5rem;

        .stat-label {
            color: #6b7280;
            font-weight: 500;
        }

        .actions-section {
            text-align: center;
            margin-bottom: 3rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
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

        .recent-section {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
            .welcome-title { font-size: 2rem; }
            .stats-grid { grid-template-columns: 1fr; gap: 1rem; }
            .action-buttons { flex-direction: column; }
            .user-menu { 
                order: -1;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="welcome-section">
            <h1 class="welcome-title">Selamat datang kembali, <?php echo htmlspecialchars($user['name']); ?>!</h1>
            <p class="welcome-subtitle">Kapsul waktu pribadi Anda menanti. Buatlah kenangan untuk ditemukan oleh diri Anda di masa depan.</p>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-number"><?php echo $totalCapsules; ?></div>
                <div class="stat-label">Total Kapsul</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔒</div>
                <div class="stat-number"><?php echo count($lockedCapsules); ?></div>
                <div class="stat-label">Kapsul Terkunci</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔓</div>
                <div class="stat-number"><?php echo count($unlockedCapsules); ?></div>
                <div class="stat-label">Kapsul Terbuka</div>
            </div>
        </div>
        
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
                    <button class="mood-filter-btn" onclick="filterByMood('<?= $mood['id'] ?>')" data-mood="<?= $mood['id'] ?>">
                        <?= $mood['emoji'] ?> <?= htmlspecialchars($mood['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Placeholder for sticky filter -->
        <div class="sticky-placeholder" id="sticky-placeholder"></div>

        <div class="actions-section">
            <div class="action-buttons">
                <a href="create-message.php" class="btn btn-primary">
                    ✍️ Buat Kapsul Baru
                </a>
                <a href="view-message.php" class="btn btn-secondary">
                    📂 Jelajahi Kapsul Saya
                </a>
            </div>
        </div>

        <div class="recent-section">
            <h2 class="section-title">📋 Kapsul Terbaru</h2>
            <?php if (empty($capsules)): ?>
            <div class="empty-state">
                <div class="empty-icon">🕰️</div>
                <p>Belum ada kapsul. <a href="create-message.php" style="color: #f25c5c;">Buat kapsul waktu pertama Anda!</a></p>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 1rem;">
                <?php foreach ($capsules as $capsule): 
                    $unlockTime = strtotime($capsule['unlock_date']);
                    $isUnlocked = $unlockTime <= time();
                    $timeRemaining = $unlockTime - time();
                ?>
                <div class="capsule-card" style="background: rgba(255, 255, 255, 0.6); border-radius: 15px; padding: 1.5rem; border: 1px solid rgba(242, 92, 92, 0.1); display: flex; justify-content: space-between; align-items: center;"
                     data-mood="<?= $capsule['mood_id'] ?? 'none' ?>"
                     data-mood-name="<?= htmlspecialchars($capsule['mood_name'] ?? 'Tanpa Mood') ?>">
                    <div>
                        <div style="font-weight: 600; color: #374151; margin-bottom: 0.25rem;">
                            <?php echo $isUnlocked ? '🔓' : '🔒'; ?> <?php echo htmlspecialchars($capsule['title']); ?>
                            <?php if (!empty($capsule['mood_emoji'])): ?>
                                <span style="margin-left: 0.5rem;"><?= $capsule['mood_emoji'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="color: #6b7280; font-size: 0.875rem;">
                            <?php if ($isUnlocked): ?>
                                Dibuka pada <?php echo date('d M Y, H:i', $unlockTime); ?>
                            <?php else: ?>
                                Akan dibuka pada <?php echo date('d M Y, H:i', $unlockTime); ?>
                                <?php 
                                $days = floor($timeRemaining / 86400);
                                $hours = floor(($timeRemaining % 86400) / 3600);
                                if ($days > 0) echo " (dalam $days hari)";
                                else if ($hours > 0) echo " (dalam $hours jam)";
                                else echo " (segera!)";
                                ?>
                            <?php endif; ?>
                        </div>
                        <div style="color: #4b5563; margin-top: 0.5rem; font-size: 0.9rem;">
                            <?php echo htmlspecialchars(substr($capsule['message'], 0, 80)) . (strlen($capsule['message']) > 80 ? '...' : ''); ?>
                        </div>
                    </div>
                    <div>
                        <?php if ($isUnlocked): ?>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <a href="capsule-detail.php?id=<?php echo $capsule['id']; ?>" 
                                   style="background: linear-gradient(135deg, #f25c5c, #ff7b7b); color: white; padding: 0.5rem 1rem; border-radius: 10px; text-decoration: none; font-weight: 500; font-size: 0.875rem;">
                                    Baca Pesan
                                </a>
                                <button onclick="forceDeleteCapsule(<?php echo $capsule['id']; ?>)" 
                                        data-title="<?php echo htmlspecialchars($capsule['title'], ENT_QUOTES); ?>"
                                        style="background: #dc2626; color: white; padding: 0.5rem 0.75rem; border: none; border-radius: 8px; font-size: 0.75rem; cursor: pointer; font-weight: 500; transition: all 0.2s;"
                                        onmouseover="this.style.background='#b91c1c'" 
                                        onmouseout="this.style.background='#dc2626'"
                                        title="Hapus kapsul yang sudah terbuka (permanen)">
                                    🗑️ Hapus
                                </button>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <span style="background: rgba(107, 114, 128, 0.1); color: #6b7280; padding: 0.5rem 1rem; border-radius: 10px; font-weight: 500; font-size: 0.875rem;">
                                    Terkunci
                                </span>
                                <?php if ($capsuleController->canDeleteCapsule($capsule['id'], $user['id'])): ?>
                                    <button onclick="deleteCapsule(<?php echo $capsule['id']; ?>)" 
                                            data-title="<?php echo htmlspecialchars($capsule['title'], ENT_QUOTES); ?>"
                                            style="background: #ef4444; color: white; padding: 0.5rem 0.75rem; border: none; border-radius: 8px; font-size: 0.75rem; cursor: pointer; font-weight: 500; transition: all 0.2s;"
                                            onmouseover="this.style.background='#dc2626'" 
                                            onmouseout="this.style.background='#ef4444'">
                                        🗑️ Hapus
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
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
                showAllCapsules();
            } else {
                showGroupedCapsules();
            }
        }

        function filterByMood(moodId) {
            currentMoodFilter = moodId;
            
            // Update button states
            document.querySelectorAll('.mood-filter-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-mood="${moodId}"]`).classList.add('active');
            
            if (currentView === 'all') {
                showAllCapsules();
            } else {
                showGroupedCapsules();
            }
        }

        function showAllCapsules() {
            // Hide any mood groups
            document.querySelectorAll('.mood-group').forEach(group => {
                group.style.display = 'none';
            });
            
            // Show original capsule grid
            const capsuleGrid = document.querySelector('.container > div:last-child');
            if (capsuleGrid) {
                capsuleGrid.style.display = 'block';
            }
            
            // Filter capsules based on mood
            document.querySelectorAll('.capsule-card').forEach(card => {
                const cardMoodId = card.getAttribute('data-mood');
                if (currentMoodFilter === 'all' || cardMoodId === currentMoodFilter || cardMoodId === 'none') {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function showGroupedCapsules() {
            // Hide original capsule grid
            const capsuleGrid = document.querySelector('.container > div:last-child');
            if (capsuleGrid) {
                capsuleGrid.style.display = 'none';
            }
            
            // Clear previous groups
            document.querySelectorAll('.mood-group').forEach(group => group.remove());
            
            // Group capsules by mood
            const groups = {};
            document.querySelectorAll('.capsule-card').forEach(card => {
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
                    <h3 class="mood-group-title">${groups[moodId].name} (${groups[moodId].cards.length} kapsul)</h3>
                    <div class="mood-group-capsules" style="display: grid; gap: 1rem;"></div>
                `;
                
                const capsulesContainer = group.querySelector('.mood-group-capsules');
                groups[moodId].cards.forEach(card => {
                    capsulesContainer.appendChild(card);
                });
                
                container.appendChild(group);
            });
            
            if (Object.keys(groups).length === 0) {
                const noGroup = document.createElement('div');
                noGroup.className = 'mood-group active';
                noGroup.innerHTML = '<div style="text-align: center; padding: 2rem; color: #6b7280;">Tidak ada kapsul dengan mood yang dipilih.</div>';
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
            showAllCapsules();
            
            // Add scroll listener for sticky filter
            window.addEventListener('scroll', handleStickyFilter);
        });

        // Delete capsule function (for locked capsules)
        function deleteCapsule(capsuleId) {
            // Get title from the button's data attribute
            const deleteButton = event.target;
            const capsuleTitle = deleteButton.getAttribute('data-title') || 'Kapsul ini';
            
            if (!confirm(`Apakah Anda yakin ingin menghapus kapsul "${capsuleTitle}"?\n\nKapsul yang sudah dihapus tidak dapat dikembalikan.`)) {
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
                body: `action=delete_capsule&capsule_id=${capsuleId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert(data.message);
                    
                    // Remove the capsule card from DOM
                    const capsuleCard = deleteButton.closest('.capsule-card');
                    capsuleCard.style.transition = 'all 0.3s ease';
                    capsuleCard.style.opacity = '0';
                    capsuleCard.style.transform = 'translateX(-100%)';
                    
                    setTimeout(() => {
                        capsuleCard.remove();
                        
                        // Check if there are no more capsules
                        const remainingCapsules = document.querySelectorAll('.capsule-card');
                        if (remainingCapsules.length === 0) {
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
                alert('Terjadi kesalahan saat menghapus kapsul');
                deleteButton.innerHTML = originalText;
                deleteButton.disabled = false;
            });
        }

        // Force delete capsule function (for unlocked capsules)
        function forceDeleteCapsule(capsuleId) {
            const deleteButton = event.target;
            const capsuleTitle = deleteButton.getAttribute('data-title') || 'Kapsul ini';
            
            if (!confirm(`⚠️ PERINGATAN: Anda akan menghapus kapsul yang sudah terbuka!\n\nKapsul: "${capsuleTitle}"\n\nKapsul yang sudah terbuka biasanya mengandung kenangan berharga. Apakah Anda yakin ingin menghapusnya secara permanen?\n\nTindakan ini TIDAK dapat dibatalkan!`)) {
                return;
            }

            // Double confirmation for opened capsules
            if (!confirm('Konfirmasi sekali lagi: Hapus kapsul yang sudah terbuka secara PERMANEN?')) {
                return;
            }

            // Show loading indicator
            const originalText = deleteButton.innerHTML;
            deleteButton.innerHTML = '⏳ Menghapus...';
            deleteButton.disabled = true;

            // Send force delete request
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
                    // Show success message
                    alert(data.message);
                    
                    // Remove the capsule card from DOM
                    const capsuleCard = deleteButton.closest('.capsule-card');
                    capsuleCard.style.transition = 'all 0.3s ease';
                    capsuleCard.style.opacity = '0';
                    capsuleCard.style.transform = 'translateX(-100%)';
                    
                    setTimeout(() => {
                        capsuleCard.remove();
                        
                        // Check if there are no more capsules
                        const remainingCapsules = document.querySelectorAll('.capsule-card');
                        if (remainingCapsules.length === 0) {
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
                alert('Terjadi kesalahan saat menghapus kapsul');
                deleteButton.innerHTML = originalText;
                deleteButton.disabled = false;
            });
        }

        // Set initial position
        setTimeout(() => {
            const filterControls = document.getElementById('filter-controls');
            filterControlsOriginalPos = filterControls.offsetTop;
        }, 100);
    </script>
</body>
</html>