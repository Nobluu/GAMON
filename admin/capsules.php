<?php
require_once '../controllers/Auth.php';
require_once '../controllers/AdminController.php';

$auth = new Auth();
$auth->requireAdmin();

if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: ../login.php');
    exit;
}

$user = $auth->getCurrentUser();
$adminController = new AdminController();

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'delete_capsule':
            $result = $adminController->deleteCapsule($_POST['capsule_id']);
            echo json_encode($result);
            exit;
    }
}

// Get capsules with pagination and search
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$limit = 20;

$capsulesResult = $adminController->getAllCapsules($page, $limit, $search, $status);
$capsules = $capsulesResult['capsules'] ?? [];
$totalPages = $capsulesResult['pages'] ?? 1;
$total = $capsulesResult['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capsule Management - GAMON Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        .admin-header {
            background: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .admin-nav {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .admin-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }

        .admin-role {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .admin-navigation {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .nav-button {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-button:hover, .nav-button.active {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .content-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filters {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input, .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .search-input {
            width: 300px;
        }

        .search-input:focus, .filter-select:focus {
            border-color: #667eea;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .capsules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .capsule-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .capsule-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .capsule-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .capsule-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .capsule-meta {
            font-size: 0.875rem;
            color: #718096;
        }

        .capsule-status {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-locked {
            background: #fed7d7;
            color: #742a2a;
        }

        .status-unlocked {
            background: #c6f6d5;
            color: #22543d;
        }

        .capsule-message {
            color: #4a5568;
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 1rem 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .capsule-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #718096;
        }

        .user-avatar {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }

        .capsule-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-small {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-danger {
            background: #fed7d7;
            color: #742a2a;
        }

        .btn-danger:hover {
            background: #fbb6ce;
        }

        .btn-info {
            background: #bee3f8;
            color: #2c5282;
        }

        .btn-info:hover {
            background: #90cdf4;
        }

        .mood-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            background: #f7fafc;
            border-radius: 8px;
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .media-indicator {
            background: #e6fffa;
            color: #234e52;
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-link {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: #4a5568;
            transition: all 0.3s ease;
        }

        .page-link:hover, .page-link.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #718096;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: #4a5568;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            margin-bottom: 1rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
        }

        .modal-body {
            margin-bottom: 1.5rem;
        }

        .modal-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .logout-btn {
            background: rgba(239, 68, 68, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.4);
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                width: 100%;
            }
            
            .capsules-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-navigation {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <nav class="admin-nav">
            <a href="dashboard.php" class="admin-logo">
                🔱 GAMON Admin
            </a>
            <div class="admin-user">
                <span><?= htmlspecialchars($user['name']) ?></span>
                <span class="admin-role"><?= strtoupper($user['role']) ?></span>
                <a href="dashboard.php?logout=1" class="logout-btn">Logout</a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="admin-navigation">
            <a href="dashboard.php" class="nav-button">📊 Dashboard</a>
            <a href="users.php" class="nav-button">👥 Users</a>
            <a href="capsules.php" class="nav-button active">💌 Capsules</a>
            <a href="analytics.php" class="nav-button">📈 Analytics</a>
            <a href="settings.php" class="nav-button">⚙️ Settings</a>
        </div>

        <div class="content-card">
            <div class="page-header">
                <h1 class="page-title">💌 Capsule Management</h1>
                <div class="filters">
                    <form method="GET" action="capsules.php" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="Search capsules..." 
                            value="<?= htmlspecialchars($search) ?>"
                        >
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>Locked</option>
                            <option value="unlocked" <?= $status === 'unlocked' ? 'selected' : '' ?>>Unlocked</option>
                        </select>
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    </form>
                </div>
            </div>

            <p style="color: #718096; margin-bottom: 1rem;">
                Total: <?= number_format($total) ?> capsules
            </p>

            <?php if (empty($capsules)): ?>
                <div class="empty-state">
                    <h3>No capsules found</h3>
                    <p>Try adjusting your search or filter criteria.</p>
                </div>
            <?php else: ?>
                <div class="capsules-grid">
                    <?php foreach ($capsules as $capsule): ?>
                    <div class="capsule-card">
                        <div class="capsule-header">
                            <div>
                                <h3 class="capsule-title"><?= htmlspecialchars($capsule['title']) ?></h3>
                                <div class="capsule-meta">
                                    Created <?= date('M j, Y g:i A', strtotime($capsule['created_at'])) ?>
                                </div>
                            </div>
                            <span class="capsule-status <?= strtotime($capsule['unlock_date']) <= time() ? 'status-unlocked' : 'status-locked' ?>">
                                <?= strtotime($capsule['unlock_date']) <= time() ? 'Unlocked' : 'Locked' ?>
                            </span>
                        </div>

                        <?php if ($capsule['mood_name']): ?>
                            <div class="mood-tag">
                                <?= $capsule['mood_emoji'] ?> <?= htmlspecialchars($capsule['mood_name']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($capsule['media_count'] > 0): ?>
                            <div class="media-indicator">
                                📎 <?= $capsule['media_count'] ?> media file(s)
                            </div>
                        <?php endif; ?>

                        <div class="capsule-message">
                            <?= nl2br(htmlspecialchars($capsule['message'])) ?>
                        </div>

                        <div class="capsule-footer">
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?= strtoupper(substr($capsule['user_name'], 0, 2)) ?>
                                </div>
                                <span><?= htmlspecialchars($capsule['user_name']) ?></span>
                            </div>
                            <div class="capsule-actions">
                                <button 
                                    class="btn-small btn-info" 
                                    onclick="viewCapsule(<?= htmlspecialchars(json_encode($capsule)) ?>)"
                                >
                                    👁️ View
                                </button>
                                <button 
                                    class="btn-small btn-danger" 
                                    onclick="confirmDelete(<?= $capsule['id'] ?>, '<?= htmlspecialchars($capsule['title']) ?>')"
                                >
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="capsules.php?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="page-link">← Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="capsules.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" 
                           class="page-link <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="capsules.php?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="page-link">Next →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- View Capsule Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="viewModalTitle">Capsule Details</h3>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="hideModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Delete Capsule</h3>
            </div>
            <div class="modal-body">
                <p id="confirmMessage">Are you sure you want to delete this capsule?</p>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="hideModal('confirmModal')">Cancel</button>
                <button class="btn btn-danger" id="confirmBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
        function viewCapsule(capsule) {
            document.getElementById('viewModalTitle').textContent = capsule.title;
            
            const unlockDate = new Date(capsule.unlock_date);
            const isUnlocked = unlockDate <= new Date();
            
            document.getElementById('viewModalBody').innerHTML = `
                <div style="margin-bottom: 1rem;">
                    <strong>Author:</strong> ${capsule.user_name} (${capsule.user_email})
                </div>
                <div style="margin-bottom: 1rem;">
                    <strong>Created:</strong> ${new Date(capsule.created_at).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}
                </div>
                <div style="margin-bottom: 1rem;">
                    <strong>Unlock Date:</strong> ${unlockDate.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}
                </div>
                <div style="margin-bottom: 1rem;">
                    <strong>Status:</strong> 
                    <span class="capsule-status ${isUnlocked ? 'status-unlocked' : 'status-locked'}">
                        ${isUnlocked ? 'Unlocked' : 'Locked'}
                    </span>
                </div>
                ${capsule.mood_name ? `
                    <div style="margin-bottom: 1rem;">
                        <strong>Mood:</strong> ${capsule.mood_emoji} ${capsule.mood_name}
                    </div>
                ` : ''}
                ${capsule.media_count > 0 ? `
                    <div style="margin-bottom: 1rem;">
                        <strong>Media:</strong> ${capsule.media_count} file(s) attached
                    </div>
                ` : ''}
                <div style="margin-bottom: 1rem;">
                    <strong>Message:</strong>
                    <div style="background: #f7fafc; padding: 1rem; border-radius: 8px; margin-top: 0.5rem; white-space: pre-line;">
                        ${capsule.message}
                    </div>
                </div>
            `;
            
            showModal('viewModal');
        }

        function confirmDelete(capsuleId, capsuleTitle) {
            document.getElementById('confirmMessage').textContent = `Are you sure you want to delete "${capsuleTitle}"? This action cannot be undone.`;
            document.getElementById('confirmBtn').onclick = function() {
                deleteCapsule(capsuleId);
                hideModal('confirmModal');
            };
            showModal('confirmModal');
        }

        function deleteCapsule(capsuleId) {
            const formData = new FormData();
            formData.append('action', 'delete_capsule');
            formData.append('capsule_id', capsuleId);

            fetch('capsules.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Delete failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }

        function showModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function hideModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    hideModal(this.id);
                }
            });
        });
    </script>
</body>
</html>