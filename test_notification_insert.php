<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "🧪 Testing corrected notification insert...\n";
    
    // Test with correct structure
    $testInsert = $conn->prepare("INSERT INTO notifications (user_id, capsule_id, type, title, message) VALUES (1, 999, 'friend_request', 'Test Friend Request', 'Test message for friend request')");
    $result = $testInsert->execute();
    
    if ($result) {
        echo "✅ Test insert successful!\n";
        
        // Get the inserted record
        $lastId = $conn->lastInsertId();
        $getTest = $conn->prepare("SELECT * FROM notifications WHERE id = ?");
        $getTest->execute([$lastId]);
        $testRecord = $getTest->fetch(PDO::FETCH_ASSOC);
        
        echo "Inserted record:\n";
        print_r($testRecord);
        
        // Clean up
        $deleteTest = $conn->prepare("DELETE FROM notifications WHERE id = ?");
        $deleteTest->execute([$lastId]);
        echo "\n✅ Test record cleaned up\n";
        
    } else {
        echo "❌ Test insert failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>