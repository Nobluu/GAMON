<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "🔧 Adding sample users to database...\n";
    
    // Check if users table exists
    $tableCheck = $conn->prepare("SHOW TABLES LIKE 'users'");
    $tableCheck->execute();
    
    if ($tableCheck->rowCount() == 0) {
        echo "❌ Table 'users' not found!\n";
        exit;
    }
    
    // Add admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO users (name, email, password_hash, role, status, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE name = VALUES(name)
    ");
    
    $stmt->execute(['Super Admin', 'admin@gamon.com', $adminPassword, 'superadmin', 'active']);
    echo "✅ Super Admin added: admin@gamon.com\n";
    
    // Add regular users
    $users = [
        ['Daffa Rahman', 'daffa@gamon.com', 'user123', 'user'],
        ['Admin GAMON', 'admin2@gamon.com', 'admin123', 'admin'],
        ['Budi Santoso', 'budi@test.com', 'user123', 'user'],
        ['Siti Nurhaliza', 'siti@test.com', 'user123', 'user'],
        ['Ahmad Rahman', 'ahmad@test.com', 'user123', 'user'],
        ['Maya Sari', 'maya@test.com', 'user123', 'user']
    ];
    
    foreach ($users as $userData) {
        $userPassword = password_hash($userData[2], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password_hash, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");
        $stmt->execute([$userData[0], $userData[1], $userPassword, $userData[3], 'active']);
        echo "✅ User added: {$userData[1]} ({$userData[3]})\n";
    }
    
    // Check final count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];
    
    echo "\n🎉 Setup completed! Total users: {$total}\n";
    echo "Admin login: admin@gamon.com / admin123\n";
    echo "User login: daffa@gamon.com / user123\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>