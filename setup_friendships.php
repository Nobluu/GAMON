<?php
require_once 'config/database.php';

echo "<h2>Create Friendships Table</h2>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Create friendships table
    $sql = "CREATE TABLE IF NOT EXISTS friendships (
        id INT PRIMARY KEY AUTO_INCREMENT,
        requester_id INT NOT NULL,
        addressee_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'declined', 'blocked') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (addressee_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_friendship (requester_id, addressee_id)
    )";
    
    if ($conn->exec($sql) !== false) {
        echo "<p style='color: green;'>✓ Friendships table created successfully!</p>";
    }
    
    // Check if friend_code column exists in users table
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'friend_code'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: orange;'>Adding friend_code column to users table...</p>";
        
        $conn->exec("ALTER TABLE users ADD COLUMN friend_code VARCHAR(10) UNIQUE");
        
        // Generate friend codes for existing users
        $stmt = $conn->query("SELECT id FROM users WHERE friend_code IS NULL OR friend_code = ''");
        $users = $stmt->fetchAll();
        
        foreach ($users as $user) {
            $friendCode = strtoupper(substr(uniqid(), -6));
            $updateStmt = $conn->prepare("UPDATE users SET friend_code = ? WHERE id = ?");
            $updateStmt->execute([$friendCode, $user['id']]);
        }
        
        echo "<p style='color: green;'>✓ Friend codes generated for all users!</p>";
    }
    
    echo "<p style='color: blue;'>Database setup completed! You can now test the search function.</p>";
    echo "<a href='test_search.php'>Test Search Function</a> | ";
    echo "<a href='friends.php'>Go to Friends Page</a>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>