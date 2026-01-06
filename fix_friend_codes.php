<?php
require_once 'config/database.php';

echo "<h2>Friend Code Setup</h2>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h3>1. Check friend_code column</h3>";
    
    // Check if friend_code column exists
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'friend_code'");
    
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: orange;'>friend_code column does not exist. Creating...</p>";
        
        // Add friend_code column
        $conn->exec("ALTER TABLE users ADD COLUMN friend_code VARCHAR(10) UNIQUE");
        echo "<p style='color: green;'>✓ friend_code column added!</p>";
    } else {
        echo "<p style='color: green;'>✓ friend_code column exists</p>";
    }
    
    echo "<h3>2. Check users without friend codes</h3>";
    
    // Check users without friend codes
    $stmt = $conn->query("SELECT id, name, friend_code FROM users WHERE friend_code IS NULL OR friend_code = ''");
    $usersWithoutCode = $stmt->fetchAll();
    
    echo "<p>Users without friend code: " . count($usersWithoutCode) . "</p>";
    
    if (count($usersWithoutCode) > 0) {
        echo "<h3>3. Generating friend codes...</h3>";
        
        foreach ($usersWithoutCode as $user) {
            // Generate unique friend code
            $attempts = 0;
            do {
                $friendCode = strtoupper(substr(uniqid(), -6));
                
                // Check if code already exists
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE friend_code = ?");
                $checkStmt->execute([$friendCode]);
                $exists = $checkStmt->rowCount() > 0;
                
                $attempts++;
                if ($attempts > 10) {
                    $friendCode = strtoupper(substr(uniqid() . rand(10,99), -8));
                    break;
                }
            } while ($exists);
            
            // Update user with new friend code
            $updateStmt = $conn->prepare("UPDATE users SET friend_code = ? WHERE id = ?");
            if ($updateStmt->execute([$friendCode, $user['id']])) {
                echo "<p>✓ Generated code <strong>$friendCode</strong> for {$user['name']}</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to generate code for {$user['name']}</p>";
            }
        }
    }
    
    echo "<h3>4. All Users Friend Codes</h3>";
    $stmt = $conn->query("SELECT id, name, friend_code FROM users ORDER BY name");
    $allUsers = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Friend Code</th></tr>";
    foreach ($allUsers as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['name']}</td>";
        echo "<td><strong>{$user['friend_code']}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<p style='color: blue;'>✅ Friend code setup completed!</p>";
    echo "<p><a href='friends.php'>Go back to Friends page</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>