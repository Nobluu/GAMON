<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>🔧 Setup User Data untuk Admin Panel</h2>";
    
    // Check if users table exists
    $tableCheck = $conn->prepare("SHOW TABLES LIKE 'users'");
    $tableCheck->execute();
    
    if ($tableCheck->rowCount() == 0) {
        echo "<p style='color: red;'>❌ Tabel 'users' tidak ditemukan! Silakan jalankan setup database terlebih dahulu.</p>";
        exit;
    }
    
    // Check existing users
    $userCheck = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $userCheck->execute();
    $userCount = $userCheck->fetch()['total'];
    
    echo "<p>📊 Jumlah user saat ini: <strong>$userCount</strong></p>";
    
    if ($userCount == 0) {
        echo "<h3>➕ Menambahkan sample users...</h3>";
        
        // Add admin user
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password_hash, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute(['Admin GAMON', 'admin@gamon.com', $adminPassword, 'admin', 'active']);
        echo "<p>✅ Admin user ditambahkan: admin@gamon.com (password: admin123)</p>";
        
        // Add regular users
        $users = [
            ['Daffa User', 'daffa@test.com', 'user123'],
            ['Budi Santoso', 'budi@test.com', 'user123'],
            ['Siti Nurhaliza', 'siti@test.com', 'user123'],
            ['Ahmad Rahman', 'ahmad@test.com', 'user123']
        ];
        
        foreach ($users as $userData) {
            $userPassword = password_hash($userData[2], PASSWORD_DEFAULT);
            $stmt->execute([$userData[0], $userData[1], $userPassword, 'user', 'active']);
            echo "<p>✅ User ditambahkan: {$userData[1]} (password: {$userData[2]})</p>";
        }
        
        echo "<p style='color: green; font-weight: bold;'>🎉 Sample users berhasil ditambahkan!</p>";
    } else {
        echo "<h3>👥 Data User yang Ada:</h3>";
        $stmt = $conn->prepare("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table style='border-collapse: collapse; width: 100%; margin: 1rem 0;'>";
        echo "<tr style='background: #f5f5f5;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>ID</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Name</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Email</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Role</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Status</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Created</th>";
        echo "</tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['id']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['name']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['email']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'><strong>{$user['role']}</strong></td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['status']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<hr><p><a href='admin/users.php' style='color: #667eea; font-weight: bold;'>🔗 Buka Admin Users Panel</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>