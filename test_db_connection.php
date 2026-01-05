<?php
// Test database connection
echo "<h3>Testing Database Connection</h3>";

try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3306;dbname=capsule_db",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Test if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Users table exists!</p>";
        
        // Show user count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        echo "<p>Users in database: " . $result['count'] . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Users table does not exist!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    
    // Common solutions
    echo "<h4>Possible solutions:</h4>";
    echo "<ul>";
    echo "<li>Make sure XAMPP is running and MySQL service is started</li>";
    echo "<li>Check if database 'capsule_db' exists</li>";
    echo "<li>Verify MySQL is running on port 3306</li>";
    echo "<li>Check MySQL username/password (default XAMPP: root/empty password)</li>";
    echo "</ul>";
}
?>