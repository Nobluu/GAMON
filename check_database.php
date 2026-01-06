<?php
require_once 'config/database.php';

echo "<h2>Database Structure Check</h2>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h3>1. Check Tables</h3>";
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>Tables in database:</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Check if friendships table exists
    if (in_array('friendships', $tables)) {
        echo "<p style='color: green;'>✓ friendships table exists</p>";
        
        echo "<h3>2. Friendships Table Structure</h3>";
        $stmt = $conn->query("DESCRIBE friendships");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>3. Sample Data</h3>";
        $stmt = $conn->query("SELECT COUNT(*) as count FROM friendships");
        $count = $stmt->fetch()['count'];
        echo "<p>Total friendships: $count</p>";
        
    } else {
        echo "<p style='color: red;'>✗ friendships table does NOT exist!</p>";
        echo "<p>This is the problem! Let me create the friendships table.</p>";
    }
    
    echo "<h3>4. Users Table Check</h3>";
    if (in_array('users', $tables)) {
        $stmt = $conn->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hasUserCode = false;
        echo "<p>Users table columns:</p><ul>";
        foreach ($columns as $col) {
            echo "<li>{$col['Field']} - {$col['Type']}</li>";
            if ($col['Field'] === 'friend_code') {
                $hasUserCode = true;
            }
        }
        echo "</ul>";
        
        if (!$hasUserCode) {
            echo "<p style='color: orange;'>⚠ friend_code column might be missing</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>