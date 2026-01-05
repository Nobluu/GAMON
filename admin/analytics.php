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

// Get dashboard statistics
$statsResult = $adminController->getDashboardStats();
$stats = $statsResult['stats'] ?? [];

// Get activity chart data
$chartResult = $adminController->getActivityChart(30);
$chartData = $chartResult['data'] ?? [];

// Get mood usage statistics
$moodsResult = $adminController->getAllMoods();
$moods = $moodsResult['moods'] ?? [];

// Process chart data for JavaScript
$chartLabels = [];
$chartValues = [];
foreach ($chartData as $data) {
    $chartLabels[] = date('M j', strtotime($data['date']));
    $chartValues[] = (int)$data['count'];
}

// Calculate additional metrics
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Average capsules per user
    $avgCapsulesStmt = $db->query("
        SELECT AVG(capsule_count) as avg_capsules
        FROM (
            SELECT COUNT(*) as capsule_count 
            FROM capsules 
            GROUP BY user_id
        ) as user_capsules
    ");
    $avgCapsules = round($avgCapsulesStmt->fetch()['avg_capsules'] ?? 0, 1);
    
    // Most active users
    $activeUsersStmt = $db->query("
        SELECT u.name, u.email, COUNT(c.id) as capsule_count
        FROM users u
        LEFT JOIN capsules c ON u.id = c.user_id
        WHERE u.status = 'active'
        GROUP BY u.id
        ORDER BY capsule_count DESC
        LIMIT 5
    ");
    $activeUsers = $activeUsersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent unlock activity
    $recentUnlocksStmt = $db->query("
        SELECT COUNT(*) as count, DATE(unlocked_at) as date
        FROM capsules
        WHERE unlocked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(unlocked_at)
        ORDER BY date DESC
    ");
    $recentUnlocks = $recentUnlocksStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $avgCapsules = 0;
    $activeUsers = [];
    $recentUnlocks = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - GAMON Admin</title>
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

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
            text-align: center;
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
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

        .chart-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .mood-stats {
            display: grid;
            gap: 1rem;
        }

        .mood-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f7fafc;
            border-radius: 8px;
        }

        .mood-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .mood-count {
            background: #667eea;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .activity-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .activity-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            font-size: 0.875rem;
        }

        .user-details h4 {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }

        .user-details p {
            font-size: 0.75rem;
            color: #718096;
        }

        .activity-count {
            background: #e6fffa;
            color: #234e52;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
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
            .chart-section {
                grid-template-columns: 1fr;
            }
            
            .activity-section {
                grid-template-columns: 1fr;
            }
            
            .admin-navigation {
                flex-wrap: wrap;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="analytics.php" class="nav-button active">📈 Analytics</a>
            <a href="settings.php" class="nav-button">⚙️ Settings</a>
        </div>

        <div class="analytics-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?= number_format($stats['total_users'] ?? 0) ?></div>
                <div class="stat-label">Total Active Users</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">💌</div>
                <div class="stat-number"><?= number_format($stats['total_capsules'] ?? 0) ?></div>
                <div class="stat-label">Total Capsules</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🔓</div>
                <div class="stat-number"><?= number_format($stats['unlocked_capsules'] ?? 0) ?></div>
                <div class="stat-label">Unlocked Capsules</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?= $avgCapsules ?></div>
                <div class="stat-label">Avg Capsules per User</div>
            </div>
        </div>

        <div class="chart-section">
            <div class="chart-card">
                <h2 class="chart-title">📈 Capsule Creation Activity (Last 30 Days)</h2>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2 class="chart-title">😊 Mood Usage</h2>
                <div class="mood-stats">
                    <?php foreach ($moods as $mood): ?>
                    <div class="mood-item">
                        <div class="mood-info">
                            <span><?= $mood['emoji'] ?></span>
                            <span><?= htmlspecialchars($mood['name']) ?></span>
                        </div>
                        <span class="mood-count"><?= number_format($mood['usage_count']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="activity-section">
            <div class="activity-card">
                <h2 class="chart-title">🏆 Most Active Users</h2>
                <?php if (!empty($activeUsers)): ?>
                    <?php foreach ($activeUsers as $activeUser): ?>
                    <div class="activity-item">
                        <div class="user-info">
                            <div class="user-avatar">
                                <?= strtoupper(substr($activeUser['name'], 0, 2)) ?>
                            </div>
                            <div class="user-details">
                                <h4><?= htmlspecialchars($activeUser['name']) ?></h4>
                                <p><?= htmlspecialchars($activeUser['email']) ?></p>
                            </div>
                        </div>
                        <span class="activity-count"><?= number_format($activeUser['capsule_count']) ?> capsules</span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #718096; text-align: center; padding: 2rem;">No active users found.</p>
                <?php endif; ?>
            </div>

            <div class="activity-card">
                <h2 class="chart-title">🔓 Recent Unlock Activity</h2>
                <?php if (!empty($recentUnlocks)): ?>
                    <?php foreach ($recentUnlocks as $unlock): ?>
                    <div class="activity-item">
                        <div>
                            <strong><?= date('M j, Y', strtotime($unlock['date'])) ?></strong>
                        </div>
                        <span class="activity-count"><?= number_format($unlock['count']) ?> unlocked</span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #718096; text-align: center; padding: 2rem;">No recent unlock activity.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Activity Chart
        const ctx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Capsules Created',
                    data: <?= json_encode($chartValues) ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>