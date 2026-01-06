<?php
echo "<h2>Simple Debug Test</h2>";

try {
    echo "<p>1. Testing basic database connection...</p>";
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    echo "<p style='color: green;'>✓ Database connected</p>";

    echo "<p>2. Testing users table...</p>";
    $stmt = $conn->query("SELECT id, name, email FROM users LIMIT 3");
    $users = $stmt->fetchAll();
    echo "<p style='color: green;'>✓ Users table accessible, found " . count($users) . " users</p>";

    echo "<p>3. Testing friendships table...</p>";
    try {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM friendships");
        $count = $stmt->fetch()['count'];
        echo "<p style='color: green;'>✓ Friendships table exists with $count records</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Friendships table error: " . $e->getMessage() . "</p>";
        
        // Try to create the table
        echo "<p>Attempting to create friendships table...</p>";
        $sql = "CREATE TABLE friendships (
            id INT PRIMARY KEY AUTO_INCREMENT,
            requester_id INT NOT NULL,
            addressee_id INT NOT NULL,
            status ENUM('pending', 'accepted', 'declined', 'blocked') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->exec($sql) !== false) {
            echo "<p style='color: green;'>✓ Friendships table created!</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to create friendships table</p>";
        }
    }

    echo "<p>4. Testing simple search query without joins...</p>";
    $searchTerm = "%admin%";
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE name LIKE ? LIMIT 3");
    $stmt->execute([$searchTerm]);
    $results = $stmt->fetchAll();
    echo "<p style='color: green;'>✓ Simple search works, found " . count($results) . " users</p>";
    
    foreach ($results as $user) {
        echo "<li>" . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ")</li>";
    }

    echo "<p>5. Testing FriendController basic instantiation...</p>";
    require_once 'controllers/FriendController.php';
    $friendController = new FriendController();
    echo "<p style='color: green;'>✓ FriendController created successfully</p>";

    echo "<p>6. Manual simple search test...</p>";
    $user_id = 1;
    $query = "admin";
    
    // Very simple version first
    $searchTerm = "%{$query}%";
    $sql = "SELECT u.id, u.name, u.email 
            FROM users u 
            WHERE u.id != ? AND u.name LIKE ? 
            LIMIT 3";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id, $searchTerm]);
    $users = $stmt->fetchAll();
    
    echo "<p style='color: green;'>✓ Manual simple search successful, found " . count($users) . " users</p>";
    foreach ($users as $user) {
        echo "<li>" . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ")</li>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>ERROR: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>