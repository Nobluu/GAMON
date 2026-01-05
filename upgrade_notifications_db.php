<?php
// Script untuk upgrade database notifications
require_once 'config/database.php';

echo "<h2>🔧 Database Upgrade untuk Sistem Notifikasi</h2>";

try {
    $database = new Database();
    $conn = $database->getConnection();

    echo "<p>Starting database upgrade...</p>";

    // 1. Check if notifications table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($check_table->rowCount() == 0) {
        echo "<p>❌ Table notifications tidak ditemukan. Membuat table baru...</p>";
        
        $create_table = "
        CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            capsule_id INT,
            type VARCHAR(50) NOT NULL DEFAULT 'unlock',
            title VARCHAR(255) NOT NULL,
            message TEXT,
            action_url VARCHAR(255) NULL,
            priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (capsule_id) REFERENCES capsules(id) ON DELETE CASCADE,
            INDEX idx_user_notifications (user_id, is_read),
            INDEX idx_created_at (created_at)
        )";
        
        $conn->exec($create_table);
        echo "<p>✅ Table notifications berhasil dibuat!</p>";
    } else {
        echo "<p>✅ Table notifications sudah ada</p>";
    }

    // 2. Check and add missing columns
    $columns_to_add = [
        'action_url' => "VARCHAR(255) NULL",
        'priority' => "ENUM('low', 'normal', 'high') DEFAULT 'normal'"
    ];

    foreach ($columns_to_add as $column => $definition) {
        $check_column = $conn->query("SHOW COLUMNS FROM notifications LIKE '$column'");
        if ($check_column->rowCount() == 0) {
            $conn->exec("ALTER TABLE notifications ADD COLUMN $column $definition");
            echo "<p>✅ Column '$column' berhasil ditambahkan</p>";
        } else {
            echo "<p>✅ Column '$column' sudah ada</p>";
        }
    }

    // 3. Update existing notifications to have better type
    $conn->exec("UPDATE notifications SET type = 'capsule_unlock' WHERE type = 'unlock' AND capsule_id IS NOT NULL");
    echo "<p>✅ Type notifikasi existing berhasil diupdate</p>";

    // 4. Create api directory if not exists
    if (!is_dir('api')) {
        mkdir('api', 0755, true);
        echo "<p>✅ Directory 'api' berhasil dibuat</p>";
    } else {
        echo "<p>✅ Directory 'api' sudah ada</p>";
    }

    // 5. Create logs directory if not exists
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
        echo "<p>✅ Directory 'logs' berhasil dibuat</p>";
    } else {
        echo "<p>✅ Directory 'logs' sudah ada</p>";
    }

    echo "<h3>🎉 Database upgrade selesai!</h3>";
    echo "<p><strong>Fitur yang telah ditambahkan:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Sistem notifikasi real-time di navbar</li>";
    echo "<li>✅ Notifikasi untuk kapsul yang terbuka</li>";
    echo "<li>✅ Notifikasi untuk pesan baru dari teman</li>";
    echo "<li>✅ API endpoint untuk notifikasi</li>";
    echo "<li>✅ Cron job untuk cek kapsul unlock</li>";
    echo "<li>✅ Badge notifikasi dengan counter</li>";
    echo "</ul>";

    echo "<p><a href='dashboard.php' style='background: #f25c5c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Kembali ke Dashboard</a></p>";
    echo "<p><a href='test_notifications.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🧪 Test Notifikasi</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>