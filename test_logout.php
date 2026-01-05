<?php
// Test file untuk memeriksa logout functionality
require_once 'controllers/Auth.php';

echo "<h2>Test Logout Functionality</h2>";

// Test 1: Check if logout parameter works
echo "<h3>Test 1: Logout Parameter Detection</h3>";
if (isset($_GET['logout'])) {
    echo "✅ Logout parameter detected: " . $_GET['logout'] . "<br>";
    
    // Test the Auth logout function
    $auth = new Auth();
    
    // Mock a session for testing
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Test User';
    $_SESSION['user_email'] = 'test@example.com';
    
    echo "Before logout - Session user_id: " . ($_SESSION['user_id'] ?? 'not set') . "<br>";
    
    // Call logout
    $auth->logout();
    
    echo "After logout - Session user_id: " . ($_SESSION['user_id'] ?? 'not set') . "<br>";
    echo "✅ Logout function executed successfully<br>";
} else {
    echo "❌ No logout parameter found. <br>";
    echo "<a href='?logout=1'>Test logout link</a><br>";
}

echo "<h3>Test 2: Current Auth State</h3>";
try {
    $auth = new Auth();
    if ($auth->isLoggedIn()) {
        $user = $auth->getCurrentUser();
        echo "✅ User is logged in: " . htmlspecialchars($user['name']) . "<br>";
        echo "<a href='?logout=1' style='background: red; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Test Logout</a>";
    } else {
        echo "❌ User is not logged in<br>";
        echo "<a href='login.php'>Go to login</a>";
    }
} catch (Exception $e) {
    echo "❌ Error checking auth state: " . $e->getMessage() . "<br>";
}

echo "<br><br><a href='dashboard.php'>Back to Dashboard</a>";
?>