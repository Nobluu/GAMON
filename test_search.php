<?php
session_start();
require_once 'controllers/FriendController.php';

echo "<h2>Test Pencarian Teman</h2>";

// Set test user_id
$_SESSION['user_id'] = 1; // Gunakan user ID yang valid

$friendController = new FriendController();

echo "<h3>1. Test Database Connection</h3>";
try {
    $friendController = new FriendController();
    echo "<p style='color: green;'>✓ FriendController berhasil dibuat</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h3>2. Test Search Function</h3>";

// Test dengan query yang berbeda
$testQueries = ['test', 'admin', 'user'];

foreach ($testQueries as $query) {
    echo "<h4>Mencari: '$query'</h4>";
    
    try {
        // Test SQL query manual dulu
        $database = new Database();
        $conn = $database->getConnection();
        
        echo "<p><strong>Testing manual SQL query:</strong></p>";
        $searchTerm = "%{$query}%";
        
        // Simple query first without friendships join
        $sql = "SELECT u.id, u.name, u.email
                FROM users u
                WHERE u.id != ? AND u.name LIKE ?
                ORDER BY u.name ASC
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_SESSION['user_id'], $searchTerm, 5]);
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p style='color: green;'>✓ Manual SQL berhasil, found: " . count($users) . " users</p>";
        if (!empty($users)) {
            echo "<ul>";
            foreach ($users as $user) {
                echo "<li>" . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ")</li>";
            }
            echo "</ul>";
        }
        
        echo "<p><strong>Testing via FriendController:</strong></p>";
        $result = $friendController->searchUsers($query, $_SESSION['user_id'], 5);
        
        if ($result['status']) {
            echo "<p style='color: green;'>✓ Search berhasil</p>";
            echo "<p>Hasil ditemukan: " . count($result['data']) . " pengguna</p>";
            
            if (!empty($result['data'])) {
                echo "<ul>";
                foreach ($result['data'] as $user) {
                    echo "<li>" . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ") - Status: " . $user['friendship_status'] . "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>Tidak ada pengguna yang ditemukan</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Search gagal: " . ($result['message'] ?? 'Unknown error') . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}

echo "<h3>3. Database Info</h3>";
try {
    // Test query users table
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    echo "<p>Total users di database: $userCount</p>";
    
    $stmt = $conn->query("SELECT name, email FROM users LIMIT 5");
    $users = $stmt->fetchAll();
    echo "<p>Sample users:</p><ul>";
    foreach ($users as $user) {
        echo "<li>" . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ")</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error getting database info: " . $e->getMessage() . "</p>";
}
?>