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
        case 'create_mood':
            $result = $adminController->createMood(
                $_POST['name'],
                $_POST['emoji'],
                $_POST['color']
            );
            echo json_encode($result);
            exit;
            
        case 'update_mood':
            $result = $adminController->updateMood(
                $_POST['id'],
                $_POST['name'],
                $_POST['emoji'],
                $_POST['color']
            );
            echo json_encode($result);
            exit;
            
        case 'delete_mood':
            $result = $adminController->deleteMood($_POST['id']);
            echo json_encode($result);
            exit;
    }
}

// Get all moods
$moodsResult = $adminController->getAllMoods();
$moods = $moodsResult['moods'] ?? [];

// Get system information
$systemInfo = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time') . 's'
];

// Get storage information
$uploadsDir = '../uploads';
$storageInfo = [
    'total_files' => 0,
    'total_size' => 0
];

if (is_dir($uploadsDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $storageInfo['total_files']++;
            $storageInfo['total_size'] += $file->getSize();
        }
    }
}

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - GAMON Admin</title>
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

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .settings-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .mood-list {
            display: grid;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .mood-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f7fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .mood-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mood-emoji {
            font-size: 1.5rem;
            width: 40px;
            text-align: center;
        }

        .mood-details h4 {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .mood-details p {
            font-size: 0.875rem;
            color: #718096;
        }

        .mood-color {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .mood-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .btn-warning {
            background: #fed7a1;
            color: #744210;
        }

        .btn-warning:hover {
            background: #fbd38d;
        }

        .btn-danger {
            background: #fed7d7;
            color: #742a2a;
        }

        .btn-danger:hover {
            background: #fbb6ce;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 80px 100px auto;
            gap: 1rem;
            align-items: end;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 500;
            color: #2d3748;
            font-size: 0.875rem;
        }

        .form-input {
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            border-color: #667eea;
        }

        .color-input {
            padding: 0.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            width: 100%;
            height: 45px;
            cursor: pointer;
        }

        .system-info {
            display: grid;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f7fafc;
            border-radius: 8px;
        }

        .info-label {
            font-weight: 500;
            color: #4a5568;
        }

        .info-value {
            color: #2d3748;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.875rem;
        }

        .storage-card {
            grid-column: span 2;
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
            max-width: 500px;
            width: 90%;
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

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
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
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .storage-card {
                grid-column: span 1;
            }
            
            .form-grid {
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
            <a href="capsules.php" class="nav-button">💌 Capsules</a>
            <a href="analytics.php" class="nav-button">📈 Analytics</a>
            <a href="settings.php" class="nav-button active">⚙️ Settings</a>
        </div>

        <div id="alertContainer"></div>

        <div class="settings-grid">
            <div class="settings-card">
                <h2 class="card-title">😊 Mood Management</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-input" id="moodName" placeholder="Enter mood name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Emoji</label>
                        <input type="text" class="form-input" id="moodEmoji" placeholder="😊" maxlength="2" style="text-align: center;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Color</label>
                        <input type="color" class="color-input" id="moodColor" value="#667eea">
                    </div>
                    <div class="form-group">
                        <button class="btn btn-primary" onclick="createMood()">➕ Add</button>
                    </div>
                </div>

                <div class="mood-list">
                    <?php foreach ($moods as $mood): ?>
                    <div class="mood-item" data-id="<?= $mood['id'] ?>">
                        <div class="mood-info">
                            <div class="mood-emoji"><?= $mood['emoji'] ?></div>
                            <div class="mood-details">
                                <h4><?= htmlspecialchars($mood['name']) ?></h4>
                                <p><?= $mood['usage_count'] ?> capsules using this mood</p>
                            </div>
                            <div class="mood-color" style="background-color: <?= $mood['color'] ?>"></div>
                        </div>
                        <div class="mood-actions">
                            <button 
                                class="btn btn-warning" 
                                onclick="editMood(<?= htmlspecialchars(json_encode($mood)) ?>)"
                            >
                                ✏️ Edit
                            </button>
                            <button 
                                class="btn btn-danger" 
                                onclick="deleteMood(<?= $mood['id'] ?>, '<?= htmlspecialchars($mood['name']) ?>', <?= $mood['usage_count'] ?>)"
                            >
                                🗑️ Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="settings-card">
                <h2 class="card-title">📋 System Information</h2>
                <div class="system-info">
                    <div class="info-item">
                        <span class="info-label">PHP Version</span>
                        <span class="info-value"><?= $systemInfo['php_version'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Server Software</span>
                        <span class="info-value"><?= $systemInfo['server_software'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Upload Max Size</span>
                        <span class="info-value"><?= $systemInfo['upload_max_filesize'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">POST Max Size</span>
                        <span class="info-value"><?= $systemInfo['post_max_size'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Memory Limit</span>
                        <span class="info-value"><?= $systemInfo['memory_limit'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Max Execution Time</span>
                        <span class="info-value"><?= $systemInfo['max_execution_time'] ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="settings-card storage-card">
            <h2 class="card-title">💾 Storage Information</h2>
            <div class="system-info">
                <div class="info-item">
                    <span class="info-label">Total Files</span>
                    <span class="info-value"><?= number_format($storageInfo['total_files']) ?> files</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Size</span>
                    <span class="info-value"><?= formatBytes($storageInfo['total_size']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Uploads Directory</span>
                    <span class="info-value">/uploads</span>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Mood Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Mood</h3>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editMoodId">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-input" id="editMoodName">
                </div>
                <div class="form-group">
                    <label class="form-label">Emoji</label>
                    <input type="text" class="form-input" id="editMoodEmoji" maxlength="2" style="text-align: center;">
                </div>
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="color" class="color-input" id="editMoodColor">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="hideModal('editModal')">Cancel</button>
                <button class="btn btn-primary" onclick="updateMood()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Delete</h3>
            </div>
            <div class="modal-body">
                <p id="confirmMessage"></p>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="hideModal('confirmModal')">Cancel</button>
                <button class="btn btn-danger" id="confirmBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
        function createMood() {
            const name = document.getElementById('moodName').value.trim();
            const emoji = document.getElementById('moodEmoji').value.trim();
            const color = document.getElementById('moodColor').value;

            if (!name || !emoji) {
                showAlert('Please fill in all fields', 'error');
                return;
            }

            performAction('create_mood', { name, emoji, color })
                .then(() => {
                    document.getElementById('moodName').value = '';
                    document.getElementById('moodEmoji').value = '';
                    document.getElementById('moodColor').value = '#667eea';
                    location.reload();
                });
        }

        function editMood(mood) {
            document.getElementById('editMoodId').value = mood.id;
            document.getElementById('editMoodName').value = mood.name;
            document.getElementById('editMoodEmoji').value = mood.emoji;
            document.getElementById('editMoodColor').value = mood.color;
            showModal('editModal');
        }

        function updateMood() {
            const id = document.getElementById('editMoodId').value;
            const name = document.getElementById('editMoodName').value.trim();
            const emoji = document.getElementById('editMoodEmoji').value.trim();
            const color = document.getElementById('editMoodColor').value;

            if (!name || !emoji) {
                showAlert('Please fill in all fields', 'error');
                return;
            }

            performAction('update_mood', { id, name, emoji, color })
                .then(() => {
                    hideModal('editModal');
                    location.reload();
                });
        }

        function deleteMood(id, name, usageCount) {
            if (usageCount > 0) {
                document.getElementById('confirmMessage').textContent = 
                    `Cannot delete "${name}" because it's being used by ${usageCount} capsules.`;
                document.getElementById('confirmBtn').style.display = 'none';
            } else {
                document.getElementById('confirmMessage').textContent = 
                    `Are you sure you want to delete "${name}"?`;
                document.getElementById('confirmBtn').style.display = 'block';
                document.getElementById('confirmBtn').onclick = function() {
                    performAction('delete_mood', { id })
                        .then(() => {
                            hideModal('confirmModal');
                            location.reload();
                        });
                };
            }
            showModal('confirmModal');
        }

        function performAction(action, data) {
            const formData = new FormData();
            formData.append('action', action);
            
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            return fetch('settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showAlert(result.message || 'Action completed successfully', 'success');
                } else {
                    showAlert(result.message || 'Action failed', 'error');
                    throw new Error(result.message);
                }
                return result;
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred', 'error');
                throw error;
            });
        }

        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            
            container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
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