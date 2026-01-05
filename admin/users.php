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
        case 'update_status':
            $result = $adminController->updateUserStatus($_POST['user_id'], $_POST['status']);
            echo json_encode($result);
            exit;
            
        case 'update_role':
            if (!$auth->isSuperAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
            $result = $adminController->updateUserRole($_POST['user_id'], $_POST['role']);
            echo json_encode($result);
            exit;
            
        case 'delete_user':
            if (!$auth->isSuperAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
            $result = $adminController->deleteUser($_POST['user_id']);
            echo json_encode($result);
            exit;
    }
}

// Get users with pagination and search
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 15;

$usersResult = $adminController->getAllUsers($page, $limit, $search);
$users = $usersResult['users'] ?? [];
$totalPages = $usersResult['pages'] ?? 1;
$total = $usersResult['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - GAMON Admin</title>
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
            align-items: center;
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

        .search-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.875rem;
            width: 300px;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
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

        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .users-table th {
            background: #f7fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
        }

        .users-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .users-table tr:hover {
            background: #f7fafc;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
        }

        .user-details h4 {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .user-details p {
            font-size: 0.875rem;
            color: #718096;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-blocked {
            background: #fed7d7;
            color: #742a2a;
        }

        .status-suspended {
            background: #fbb6ce;
            color: #702459;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-user {
            background: #bee3f8;
            color: #2c5282;
        }

        .role-admin {
            background: #fbb6ce;
            color: #702459;
        }

        .role-superadmin {
            background: #d6f5d6;
            color: #22543d;
        }

        .actions {
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
            max-width: 400px;
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
            .search-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                width: 100%;
            }
            
            .admin-navigation {
                flex-wrap: wrap;
            }
            
            .users-table {
                font-size: 0.875rem;
            }
            
            .users-table th,
            .users-table td {
                padding: 0.5rem;
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
            <a href="users.php" class="nav-button active">👥 Users</a>
            <a href="capsules.php" class="nav-button">💌 Capsules</a>
            <a href="analytics.php" class="nav-button">📈 Analytics</a>
            <a href="settings.php" class="nav-button">⚙️ Settings</a>
        </div>

        <div class="content-card">
            <div class="page-header">
                <h1 class="page-title">👥 User Management</h1>
                <div class="search-controls">
                    <form method="GET" action="users.php" style="display: flex; gap: 1rem;">
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="Search users..." 
                            value="<?= htmlspecialchars($search) ?>"
                        >
                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                    </form>
                </div>
            </div>

            <p style="color: #718096; margin-bottom: 1rem;">
                Total: <?= number_format($total) ?> users
            </p>

            <table class="users-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Capsules</th>
                        <th>Last Login</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem;">
                                <div>
                                    <h3 style="color: #666; margin-bottom: 1rem;">👥 Tidak Ada Data User</h3>
                                    <p style="color: #888;">
                                        <?php if (!empty($search)): ?>
                                            Tidak ditemukan user dengan kata kunci "<?= htmlspecialchars($search) ?>"
                                        <?php else: ?>
                                            Belum ada user yang terdaftar di sistem.
                                        <?php endif; ?>
                                    </p>
                                    <p style="color: #999; font-size: 0.875rem; margin-top: 1rem;">
                                        Total users: <?= $total ?> | Search: "<?= htmlspecialchars($search) ?>"
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $userData): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($userData['name'], 0, 2)) ?>
                                    </div>
                                    <div class="user-details">
                                        <h4><?= htmlspecialchars($userData['name']) ?></h4>
                                        <p><?= htmlspecialchars($userData['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="role-badge role-<?= $userData['role'] ?>">
                                    <?= strtoupper($userData['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $userData['status'] ?>">
                                    <?= strtoupper($userData['status']) ?>
                                </span>
                            </td>
                            <td><?= number_format($userData['capsule_count']) ?></td>
                            <td>
                                <?= $userData['last_login'] 
                                    ? date('M j, Y', strtotime($userData['last_login'])) 
                                    : 'Never' ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($userData['created_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <?php if ($userData['id'] != $user['id']): ?>
                                        <button 
                                            class="btn-small btn-warning" 
                                            onclick="toggleStatus(<?= $userData['id'] ?>, '<?= $userData['status'] ?>')"
                                        >
                                            <?= $userData['status'] === 'active' ? '🚫 Block' : '✅ Activate' ?>
                                        </button>
                                        
                                        <?php if ($auth->isSuperAdmin()): ?>
                                            <button 
                                                class="btn-small btn-danger" 
                                                onclick="confirmDelete(<?= $userData['id'] ?>, '<?= htmlspecialchars($userData['name']) ?>')"
                                            >
                                                🗑️ Delete
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="users.php?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="page-link">← Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="users.php?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
                       class="page-link <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="users.php?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="page-link">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Confirm Action</h3>
            </div>
            <div class="modal-body">
                <p id="modalMessage">Are you sure?</p>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="hideModal()">Cancel</button>
                <button class="btn btn-danger" id="confirmBtn">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        function toggleStatus(userId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'blocked' : 'active';
            const action = newStatus === 'blocked' ? 'block' : 'activate';
            
            if (confirm(`Are you sure you want to ${action} this user?`)) {
                performAction('update_status', { user_id: userId, status: newStatus });
            }
        }

        function confirmDelete(userId, userName) {
            document.getElementById('modalTitle').textContent = 'Delete User';
            document.getElementById('modalMessage').textContent = `Are you sure you want to delete user "${userName}"? This action cannot be undone and will delete all their capsules.`;
            document.getElementById('confirmBtn').onclick = function() {
                performAction('delete_user', { user_id: userId });
                hideModal();
            };
            showModal();
        }

        function performAction(action, data) {
            const formData = new FormData();
            formData.append('action', action);
            
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            fetch('users.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Action failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }

        function showModal() {
            document.getElementById('confirmModal').classList.add('show');
        }

        function hideModal() {
            document.getElementById('confirmModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('confirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal();
            }
        });
    </script>
</body>
</html>