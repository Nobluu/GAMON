<?php
require_once 'controllers/Auth.php';
require_once 'controllers/AdminController.php';
require_once 'config/database.php';

$auth = new Auth();
$adminController = new AdminController();

echo "<h2>🔍 Debug Admin Users Data</h2>";

echo "<h3>📊 Raw Database Check</h3>";
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h4>Check users table:</h4>";
    $stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $direct_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($direct_users) . " users in database:</p>";
    echo "<pre>";
    print_r($direct_users);
    echo "</pre>";
    
    echo "<h4>Check AdminController getAllUsers method:</h4>";
    $usersResult = $adminController->getAllUsers(1, 15, '');
    echo "<pre>";
    print_r($usersResult);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h3>🔧 Test Query with Capsule Count</h3>";
try {
    $stmt = $conn->prepare("
        SELECT id, name, email, role, status, last_login, created_at,
               (SELECT COUNT(*) FROM capsules WHERE user_id = users.id) as capsule_count
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $users_with_capsules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Users with capsule count:</p>";
    echo "<pre>";
    print_r($users_with_capsules);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Query Error: " . $e->getMessage() . "</p>";
}

echo "<h3>📋 Database Tables Check</h3>";
try {
    $tables_stmt = $conn->prepare("SHOW TABLES");
    $tables_stmt->execute();
    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>Available tables:</p>";
    echo "<pre>";
    print_r($tables);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Tables Error: " . $e->getMessage() . "</p>";
}
?>