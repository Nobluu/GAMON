<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>🔍 Debug Friend Request Issue</h2>";
    
    echo "<h3>📋 Check notifications table structure:</h3>";
    $desc = $conn->prepare("DESCRIBE notifications");
    $desc->execute();
    $structure = $desc->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f5f5f5;'>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Field</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Type</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Null</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Key</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Default</th>";
    echo "</tr>";
    
    foreach ($structure as $field) {
        echo "<tr>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$field['Field']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$field['Type']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$field['Null']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$field['Key']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$field['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>🔍 Test friend lookup by code 6FEDAA92:</h3>";
    $testCode = '6FEDAA92';
    $query = "SELECT id, name, email, friend_code FROM users WHERE email = ? OR friend_code = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute([$testCode, $testCode]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<p>✅ User found:</p>";
        echo "<pre>";
        print_r($user);
        echo "</pre>";
    } else {
        echo "<p>❌ User not found with code: $testCode</p>";
        echo "<p>Let's check all users with friend codes:</p>";
        
        $allUsers = $conn->prepare("SELECT id, name, email, friend_code FROM users WHERE friend_code IS NOT NULL");
        $allUsers->execute();
        $users = $allUsers->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<pre>";
        print_r($users);
        echo "</pre>";
    }
    
    echo "<h3>🧪 Test notification insert:</h3>";
    try {
        $testInsert = $conn->prepare("INSERT INTO notifications (user_id, notification_type, type, message, sender_name, related_id, created_at) VALUES (1, 'friend', 'friend_request', 'Test message', 'Test User', 1, NOW())");
        $testResult = $testInsert->execute();
        echo $testResult ? "✅ Test insert successful" : "❌ Test insert failed";
        
        if ($testResult) {
            // Delete test record
            $deleteTest = $conn->prepare("DELETE FROM notifications WHERE message = 'Test message' AND sender_name = 'Test User'");
            $deleteTest->execute();
            echo " (and cleaned up)";
        }
        echo "<br>";
    } catch (Exception $e) {
        echo "❌ Test insert error: " . $e->getMessage() . "<br>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>