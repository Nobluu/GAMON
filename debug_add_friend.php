<?php
require_once 'config/database.php';

echo "<h2>Debug Add Friend Function</h2>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h3>1. Check friendships table</h3>";
    
    // Check if friendships table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'friendships'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: red;'>✗ friendships table does NOT exist!</p>";
        echo "<p>Creating friendships table...</p>";
        
        $sql = "CREATE TABLE friendships (
            id INT PRIMARY KEY AUTO_INCREMENT,
            requester_id INT NOT NULL,
            addressee_id INT NOT NULL,
            status ENUM('pending', 'accepted', 'declined', 'blocked') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_requester (requester_id),
            INDEX idx_addressee (addressee_id)
        )";
        
        if ($conn->exec($sql) !== false) {
            echo "<p style='color: green;'>✓ friendships table created successfully!</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to create friendships table</p>";
        }
    } else {
        echo "<p style='color: green;'>✓ friendships table exists</p>";
        
        // Show table structure
        $stmt = $conn->query("DESCRIBE friendships");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Table Structure:</h4>";
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
    }
    
    echo "<h3>2. Test Add Friend Function</h3>";
    
    session_start();
    require_once 'controllers/FriendController.php';
    
    $friendController = new FriendController();
    
    // Test with a known friend code/email
    $stmt = $conn->query("SELECT id, name, email, friend_code FROM users WHERE id != 1 LIMIT 1");
    $testUser = $stmt->fetch();
    
    if ($testUser) {
        echo "<p>Testing add friend with user: <strong>{$testUser['name']}</strong></p>";
        echo "<p>Using friend code: <strong>{$testUser['friend_code']}</strong></p>";
        
        $result = $friendController->sendFriendRequest(1, $testUser['friend_code']);
        
        if ($result['status']) {
            echo "<p style='color: green;'>✓ Add friend successful: {$result['message']}</p>";
        } else {
            echo "<p style='color: red;'>✗ Add friend failed: {$result['message']}</p>";
        }
    } else {
        echo "<p style='color: orange;'>No test users available</p>";
    }
    
    echo "<h3>3. Current Friendships</h3>";
    $stmt = $conn->query("SELECT f.*, 
                                 u1.name as requester_name, 
                                 u2.name as addressee_name 
                          FROM friendships f 
                          JOIN users u1 ON f.requester_id = u1.id 
                          JOIN users u2 ON f.addressee_id = u2.id 
                          ORDER BY f.created_at DESC 
                          LIMIT 10");
    $friendships = $stmt->fetchAll();
    
    if (count($friendships) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Requester</th><th>Addressee</th><th>Status</th><th>Created</th></tr>";
        foreach ($friendships as $friendship) {
            echo "<tr>";
            echo "<td>{$friendship['id']}</td>";
            echo "<td>{$friendship['requester_name']}</td>";
            echo "<td>{$friendship['addressee_name']}</td>";
            echo "<td>{$friendship['status']}</td>";
            echo "<td>{$friendship['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No friendships found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>