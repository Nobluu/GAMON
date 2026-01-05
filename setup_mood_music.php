<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "🔗 Database connected successfully!\n\n";
    
    // Add music columns to moods table
    $sqls = [
        "ALTER TABLE moods ADD COLUMN music_file VARCHAR(255) NULL AFTER color",
        "ALTER TABLE moods ADD COLUMN music_name VARCHAR(100) NULL AFTER music_file", 
        "ALTER TABLE moods ADD COLUMN music_duration INT NULL AFTER music_name",
        "ALTER TABLE moods ADD COLUMN created_by INT NULL AFTER music_duration",
        "UPDATE moods SET music_file = CONCAT(LOWER(REPLACE(name, ' ', '_')), '.mp3'), music_name = CONCAT('Default ', name, ' Music') WHERE music_file IS NULL"
    ];
    
    foreach ($sqls as $index => $sql) {
        try {
            $conn->exec($sql);
            echo "✅ SQL " . ($index + 1) . " executed successfully\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "⚠️  SQL " . ($index + 1) . " skipped (column already exists)\n";
            } else {
                echo "❌ SQL " . ($index + 1) . " error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Show current moods structure
    echo "\n📋 Current moods data:\n";
    $stmt = $conn->query("SELECT id, name, emoji, color, music_file, music_name FROM moods ORDER BY id");
    $moods = $stmt->fetchAll();
    
    foreach ($moods as $mood) {
        echo sprintf("ID: %d | %s %s | Music: %s\n", 
            $mood['id'], 
            $mood['emoji'], 
            $mood['name'], 
            $mood['music_file'] ?? 'None'
        );
    }
    
    echo "\n🎵 Mood music system setup completed!\n";
    echo "📁 Remember to create: uploads/music/moods/ directory\n";
    echo "🎼 Upload music files with names matching the music_file column\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}