<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "=== NOTIFICATIONS TABLE STRUCTURE ===\n";
    $stmt = $conn->prepare("DESCRIBE notifications");
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($structure as $field) {
        echo "{$field['Field']} - {$field['Type']}\n";
    }
    
    echo "\n=== SAMPLE NOTIFICATION DATA ===\n";
    $stmt2 = $conn->prepare("SELECT * FROM notifications LIMIT 1");
    $stmt2->execute();
    $sample = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    if ($sample) {
        print_r($sample);
    } else {
        echo "No notifications found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>