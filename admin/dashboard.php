<?php
require_once '../controllers/Auth.php';
require_once '../controllers/Capsule.php';

$auth = new Auth();
$auth->requireAdmin();

if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: ../login.php');
    exit;
}

$user = $auth->getCurrentUser();
$capsuleController = new Capsule();

// Get system statistics
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Total users
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $totalUsers = $stmt->fetch()['total'];
    
    // Total capsules
    $stmt = $db->query("SELECT COUNT(*) as total FROM capsules");
    $totalCapsules = $stmt->fetch()['total'];
    
    // Unlocked capsules today
    $stmt = $db->query("SELECT COUNT(*) as total FROM capsules WHERE DATE(unlocked_at) = CURDATE()");
    $unlockedToday = $stmt->fetch()['total'];
    
    // New users this month
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $newUsersThisMonth = $stmt->fetch()['total'];
    
    // Recent activity
    $stmt = $db->prepare("
        SELECT c.title, u.name as user_name, c.created_at, c.unlock_date, c.is_unlocked
        FROM capsules c 
        JOIN users u ON c.user_id = u.id 
        ORDER BY c.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recentCapsules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent users
    $stmt = $db->prepare("
        SELECT name, email, created_at, role, last_login
        FROM users 
        WHERE status = 'active'
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $totalUsers = $totalCapsules = $unlockedToday = $newUsersThisMonth = 0;
    $recentCapsules = $recentUsers = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - GAMON</title>
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

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #718096;
            font-size: 0.875rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .stat-change {
            font-size: 0.875rem;
            margin-top: 0.5rem;
            color: #38a169;
        }

        .content-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-title {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .activity-meta {
            font-size: 0.875rem;
            color: #718096;
        }

        .activity-status {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-unlocked {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-locked {
            background: #fed7d7;
            color: #742a2a;
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

        .nav-button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
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
            .content-section {
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
            <a href="settings.php" class="nav-button">⚙️ Settings</a>
        </div>

        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($totalUsers) ?></div>
                <div class="stat-label">Total Active Users</div>
                <div class="stat-change">+<?= $newUsersThisMonth ?> this month</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?= number_format($totalCapsules) ?></div>
                <div class="stat-label">Total Capsules</div>
                <div class="stat-change">All time messages</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?= number_format($unlockedToday) ?></div>
                <div class="stat-label">Unlocked Today</div>
                <div class="stat-change">Messages opened today</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?= count($recentUsers) ?></div>
                <div class="stat-label">Recent Activity</div>
                <div class="stat-change">Active users</div>
            </div>
        </div>

        <div class="content-section">
            <div class="section-card">
                <h2 class="section-title">📝 Recent Capsules</h2>
                <?php if (!empty($recentCapsules)): ?>
                    <?php foreach ($recentCapsules as $capsule): ?>
                        <div class="activity-item">
                            <div>
                                <div class="activity-title"><?= htmlspecialchars($capsule['title']) ?></div>
                                <div class="activity-meta">
                                    by <?= htmlspecialchars($capsule['user_name']) ?> • 
                                    Created <?= date('M j, Y', strtotime($capsule['created_at'])) ?>
                                </div>
                            </div>
                            <span class="activity-status <?= $capsule['is_unlocked'] ? 'status-unlocked' : 'status-locked' ?>">
                                <?= $capsule['is_unlocked'] ? 'Unlocked' : 'Locked' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="activity-meta">No recent capsules found.</p>
                <?php endif; ?>
            </div>

            <div class="section-card">
                <h2 class="section-title">👥 Recent Users</h2>
                <?php if (!empty($recentUsers)): ?>
                    <?php foreach ($recentUsers as $recentUser): ?>
                        <div class="activity-item">
                            <div>
                                <div class="activity-title"><?= htmlspecialchars($recentUser['name']) ?></div>
                                <div class="activity-meta">
                                    <?= htmlspecialchars($recentUser['email']) ?> • 
                                    Joined <?= date('M j, Y', strtotime($recentUser['created_at'])) ?>
                                </div>
                            </div>
                            <span class="activity-status status-unlocked">
                                <?= strtoupper($recentUser['role']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="activity-meta">No recent users found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>