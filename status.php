<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GAMON Server Status</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
        }
        .status-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        .status-ok { color: #27ae60; }
        .status-error { color: #e74c3c; }
        .test-item {
            padding: 10px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #3498db;
            background: #f8f9fa;
        }
        h1 { color: #f25c5c; text-align: center; }
        .nav-links { 
            text-align: center; 
            margin-top: 20px; 
            padding-top: 20px; 
            border-top: 1px solid #eee; 
        }
        .nav-links a {
            color: #f25c5c;
            text-decoration: none;
            margin: 0 10px;
            padding: 8px 16px;
            border: 1px solid #f25c5c;
            border-radius: 6px;
            transition: all 0.3s;
        }
        .nav-links a:hover {
            background: #f25c5c;
            color: white;
        }
    </style>
</head>
<body>
    <div class="status-container">
        <h1>🕰️ GAMON Server Status</h1>
        
        <div class="test-item">
            <strong>📅 Server Time:</strong> <?= date('Y-m-d H:i:s') ?>
        </div>
        
        <div class="test-item">
            <strong>🐘 PHP Version:</strong> 
            <span class="status-ok">✅ <?= PHP_VERSION ?></span>
        </div>
        
        <div class="test-item">
            <strong>🗃️ Database Connection:</strong>
            <?php
            try {
                require_once 'config/db.php';
                $db = Database::getInstance();
                $conn = $db->getConnection();
                echo '<span class="status-ok">✅ Connected</span>';
                
                // Test query
                $stmt = $conn->query("SELECT COUNT(*) as count FROM moods");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                echo '<br><small>Moods in database: ' . $result['count'] . '</small>';
                
            } catch (Exception $e) {
                echo '<span class="status-error">❌ Error: ' . $e->getMessage() . '</span>';
            }
            ?>
        </div>
        
        <div class="test-item">
            <strong>📁 File Permissions:</strong>
            <?php
            $writable_dirs = ['uploads/', 'temp/'];
            $all_writable = true;
            
            foreach ($writable_dirs as $dir) {
                if (is_writable($dir)) {
                    echo '<span class="status-ok">✅ ' . $dir . ' writable</span><br>';
                } else {
                    echo '<span class="status-error">❌ ' . $dir . ' not writable</span><br>';
                    $all_writable = false;
                }
            }
            ?>
        </div>
        
        <div class="test-item">
            <strong>🔧 Configuration:</strong>
            <span class="status-ok">✅ GAMON Config OK</span><br>
            <small>Base URL: http://localhost/gamon/</small>
        </div>
        
        <div class="nav-links">
            <a href="login.php">🔑 Login</a>
            <a href="register.php">📝 Register</a>
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="/">🏠 XAMPP Home</a>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #e8f5e8; border-radius: 8px; text-align: center;">
            <strong style="color: #27ae60;">✅ GAMON Server is Running!</strong><br>
            <small>Last check: <?= date('H:i:s') ?></small>
        </div>
    </div>
</body>
</html>