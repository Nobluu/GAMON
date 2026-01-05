-- Update notifications system untuk mendukung notifikasi real-time
-- Upgrade schema untuk mendukung friend messages dan unlock notifications

-- Update notifications table jika perlu
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS friend_capsule_id INT NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS priority ENUM('low', 'normal', 'high') DEFAULT 'normal';
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS action_url VARCHAR(255) NULL;

-- Add foreign key untuk friend capsules jika belum ada
SET @exist_fk = 0;
SELECT COUNT(*) INTO @exist_fk 
FROM information_schema.table_constraints 
WHERE constraint_schema = DATABASE() 
AND table_name = 'notifications' 
AND constraint_name = 'fk_notifications_friend_capsule';

SET @sql_fk = IF(@exist_fk = 0, 
    'ALTER TABLE notifications ADD CONSTRAINT fk_notifications_friend_capsule 
     FOREIGN KEY (friend_capsule_id) REFERENCES capsules(id) ON DELETE CASCADE', 
    'SELECT "Foreign key already exists"');

PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- Create notification types yang lebih spesifik
INSERT IGNORE INTO notification_types (type, description) VALUES
('capsule_unlock', 'Kapsul pribadi sudah bisa dibuka'),
('friend_message_received', 'Pesan baru dari teman'),
('friend_capsule_unlock', 'Kapsul dari teman sudah bisa dibuka'),
('capsule_reminder', 'Pengingat kapsul akan terbuka'),
('system_announcement', 'Pengumuman sistem');

-- Update existing notifications to have better structure
UPDATE notifications SET type = 'capsule_unlock' WHERE type = 'unlock' AND friend_capsule_id IS NULL;
UPDATE notifications SET type = 'friend_capsule_unlock' WHERE type = 'unlock' AND friend_capsule_id IS NOT NULL;