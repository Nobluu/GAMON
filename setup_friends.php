<?php
// Check and setup friends tables
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check existing tables
    $stmt = $conn->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Current Tables:</h2>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Check if friends tables exist
    $friendshipExists = in_array('friendships', $tables);
    $friendNotifExists = in_array('friend_notifications', $tables);
    $friendCodeExists = false;
    
    // Check if friend_code column exists
    try {
        $stmt = $conn->query('DESCRIBE users');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            if ($column['Field'] === 'friend_code') {
                $friendCodeExists = true;
                break;
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error checking users table: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>Friends System Status:</h2>";
    echo "<ul>";
    echo "<li>friendships table: " . ($friendshipExists ? "✅ EXISTS" : "❌ MISSING") . "</li>";
    echo "<li>friend_notifications table: " . ($friendNotifExists ? "✅ EXISTS" : "❌ MISSING") . "</li>";
    echo "<li>friend_code column: " . ($friendCodeExists ? "✅ EXISTS" : "❌ MISSING") . "</li>";
    echo "</ul>";
    
    if (!$friendshipExists || !$friendNotifExists || !$friendCodeExists) {
        echo "<h2>⚠️ Setup Required</h2>";
        echo "<p>Some tables/columns are missing. Creating them now...</p>";
        
        // Create friendships table
        if (!$friendshipExists) {
            $sql = "CREATE TABLE IF NOT EXISTS friendships (
                id INT AUTO_INCREMENT PRIMARY KEY,
                requester_id INT NOT NULL,
                addressee_id INT NOT NULL,
                status ENUM('pending', 'accepted', 'declined', 'blocked') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_requester (requester_id),
                INDEX idx_addressee (addressee_id),
                INDEX idx_status (status),
                INDEX idx_friendship_pair (requester_id, addressee_id),
                
                FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (addressee_id) REFERENCES users(id) ON DELETE CASCADE,
                
                UNIQUE KEY unique_friendship (requester_id, addressee_id),
                CONSTRAINT no_self_friend CHECK (requester_id != addressee_id)
            )";
            $conn->exec($sql);
            echo "<p>✅ Created friendships table</p>";
        }
        
        // Create friend_notifications table
        if (!$friendNotifExists) {
            $sql = "CREATE TABLE IF NOT EXISTS friend_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('friend_request', 'friend_accepted', 'friend_declined') NOT NULL,
                from_user_id INT NOT NULL,
                friendship_id INT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_user_notifications (user_id, is_read),
                INDEX idx_type (type),
                INDEX idx_created_at (created_at),
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (friendship_id) REFERENCES friendships(id) ON DELETE CASCADE
            )";
            $conn->exec($sql);
            echo "<p>✅ Created friend_notifications table</p>";
        }
        
        // Add friend_code column
        if (!$friendCodeExists) {
            $sql = "ALTER TABLE users ADD COLUMN friend_code VARCHAR(8) UNIQUE AFTER email";
            $conn->exec($sql);
            echo "<p>✅ Added friend_code column</p>";
            
            // Generate friend codes for existing users
            $stmt = $conn->query("SELECT id FROM users WHERE friend_code IS NULL");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($users as $user) {
                $code = strtoupper(substr(md5(uniqid()), 0, 8));
                $updateStmt = $conn->prepare("UPDATE users SET friend_code = ? WHERE id = ?");
                $updateStmt->execute([$code, $user['id']]);
            }
            echo "<p>✅ Generated friend codes for existing users</p>";
        }
        
        // Create view
        $sql = "CREATE OR REPLACE VIEW user_friends AS
        SELECT 
            u1.id as user_id,
            u2.id as friend_id,
            u2.name as friend_name,
            u2.email as friend_email,
            u2.friend_code as friend_code,
            u2.profile_picture as friend_profile_picture,
            f.created_at as friendship_date,
            'accepted' as friendship_status
        FROM friendships f
        JOIN users u1 ON (f.requester_id = u1.id OR f.addressee_id = u1.id)
        JOIN users u2 ON (f.requester_id = u2.id OR f.addressee_id = u2.id)
        WHERE f.status = 'accepted' 
        AND u1.id != u2.id";
        $conn->exec($sql);
        echo "<p>✅ Created user_friends view</p>";
        
        echo "<h2>🎉 Setup Complete!</h2>";
        echo "<p><a href='friends.php'>Go to Friends Page</a></p>";
    } else {
        echo "<h2>✅ All Good!</h2>";
        echo "<p>Friends system is properly set up. <a href='friends.php'>Go to Friends Page</a></p>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Friends System Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h2 { color: #333; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>🔧 Friends System Setup Check</h1>
</body>
</html>