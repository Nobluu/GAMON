<?php
require_once 'controllers/Auth.php';

$auth = new Auth();
$auth->requireLogin();

if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
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
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #6b7280;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover { color: #f25c5c; }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
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
            margin-bottom: 0.5rem;
        }

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

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .nav { padding: 0 1rem; }
            .welcome-title { font-size: 2rem; }
            .stats-grid { grid-template-columns: 1fr; gap: 1rem; }
            .action-buttons { flex-direction: column; }
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="#" class="logo">
                <img src="logo_gamon.png" alt="GAMON" class="logo-img">
            </a>
            <div class="nav-links">
                <a href="dashboard.php">Beranda</a>
                <a href="create-message.php">Create</a>
                <a href="view-message.php">My Capsules</a>
            </div>
            <div class="user-menu">
                <div class="user-avatar">U</div>
                <span style="color: #6b7280; font-weight: 500;"><?php echo htmlspecialchars($user['name']); ?></span>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
            <p class="welcome-subtitle">Your personal time capsule awaits. Create memories for your future self to discover.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-number">0</div>
                <div class="stat-label">Total Capsules</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔒</div>
                <div class="stat-number">0</div>
                <div class="stat-label">Locked Capsules</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔓</div>
                <div class="stat-number">0</div>
                <div class="stat-label">Unlocked Capsules</div>
            </div>
        </div>

        <div class="actions-section">
            <div class="action-buttons">
                <a href="create-message.php" class="btn btn-primary">
                    ✍️ Create New Capsule
                </a>
                <a href="view-message.php" class="btn btn-secondary">
                    📂 Browse My Capsules
                </a>
            </div>
        </div>

        <div class="recent-section">
            <h2 class="section-title">📋 Recent Capsules</h2>
            <div class="empty-state">
                <div class="empty-icon">🕰️</div>
                <p>No capsules yet. <a href="create-message.php" style="color: #f25c5c;">Create your first time capsule!</a></p>
            </div>
        </div>
    </div>
</body>
</html>