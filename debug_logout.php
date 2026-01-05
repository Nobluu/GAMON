<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Logout - GAMON</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .btn { padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; display: inline-block; }
        .btn-logout { background: #dc3545; color: white; }
        .btn-primary { background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Logout Functionality</h1>
        
        <?php
        require_once 'controllers/Auth.php';
        
        // Debug informasi
        echo "<div class='status info'>";
        echo "<strong>Debug Info:</strong><br>";
        echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "<br>";
        echo "Query String: " . ($_SERVER['QUERY_STRING'] ?? 'empty') . "<br>";
        echo "Current URL: " . $_SERVER['REQUEST_URI'] . "<br>";
        echo "</div>";
        
        // Handle logout
        if (isset($_GET['logout'])) {
            echo "<div class='status info'>";
            echo "🔄 Logout parameter detected! Processing logout...";
            echo "</div>";
            
            $auth = new Auth();
            try {
                $auth->logout();
                echo "<div class='status success'>";
                echo "✅ Logout successful! Redirecting to login page...";
                echo "</div>";
                
                echo "<script>";
                echo "setTimeout(function() { window.location.href = 'login.php'; }, 2000);";
                echo "</script>";
            } catch (Exception $e) {
                echo "<div class='status error'>";
                echo "❌ Logout error: " . $e->getMessage();
                echo "</div>";
            }
        } else {
            // Check current auth status
            $auth = new Auth();
            echo "<div class='status info'>";
            echo "<strong>Current Authentication Status:</strong><br>";
            
            try {
                if ($auth->isLoggedIn()) {
                    $user = $auth->getCurrentUser();
                    echo "✅ User is logged in<br>";
                    echo "User ID: " . $user['id'] . "<br>";
                    echo "Name: " . htmlspecialchars($user['name']) . "<br>";
                    echo "Email: " . htmlspecialchars($user['email']) . "<br>";
                    echo "Role: " . $user['role'] . "<br>";
                } else {
                    echo "❌ User is not logged in";
                }
            } catch (Exception $e) {
                echo "❌ Auth check error: " . $e->getMessage();
            }
            echo "</div>";
            
            // Test buttons
            echo "<h3>🧪 Test Buttons</h3>";
            
            if ($auth->isLoggedIn()) {
                echo "<a href='?logout=1' class='btn btn-logout' onclick='return confirm(\"Test logout?\")'>🚪 Test Logout</a>";
                echo "<a href='dashboard.php' class='btn btn-primary'>📊 Go to Dashboard</a>";
            } else {
                echo "<a href='login.php' class='btn btn-primary'>🔑 Go to Login</a>";
            }
        }
        ?>
        
        <hr>
        <h3>📋 Quick Navigation</h3>
        <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
        <a href="login.php" class="btn btn-primary">Login Page</a>
        <a href="test_logout.php" class="btn btn-primary">Logout Test</a>
        
    </div>
</body>
</html>