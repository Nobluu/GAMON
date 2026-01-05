-- Complete database fix for message upload functionality
-- This script creates the messages table and message_media table if they don't exist
-- and ensures compatibility between capsules and messages functionality

USE capsule_db;

-- Create messages table (if it doesn't exist)
-- This is needed by MessageController.php
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    mood_id INT,
    scheduled_open_at DATETIME NOT NULL,
    is_anonymous TINYINT(1) DEFAULT 0,
    visibility ENUM('private', 'shared') DEFAULT 'private',
    status ENUM('locked', 'unlocked', 'opened') DEFAULT 'locked',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    opened_at DATETIME NULL,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mood_id) REFERENCES moods(id) ON DELETE SET NULL,
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_scheduled (scheduled_open_at),
    INDEX idx_status (status)
);

-- Create message_media table for file uploads in messages
CREATE TABLE IF NOT EXISTS message_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_type ENUM('image', 'video', 'audio', 'document') NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_message_media (message_id)
);

-- Create notifications table (if it doesn't exist)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_id INT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
);

-- Create audit_logs table for security logging
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created (created_at)
);

-- Insert default moods if they don't exist
INSERT IGNORE INTO moods (id, name, emoji, color) VALUES 
(1, 'Bahagia', '😊', '#FFD700'),
(2, 'Biasa Saja', '😐', '#808080'),
(3, 'Sedih', '😔', '#4682B4'),
(4, 'Marah', '😡', '#DC143C'),
(5, 'Bersemangat', '🤩', '#FF6347'),
(6, 'Cinta', '😍', '#FF69B4'),
(7, 'Takut', '😨', '#8A2BE2'),
(8, 'Terkejut', '😲', '#32CD32');

-- Create a test admin user (password: admin123)
INSERT IGNORE INTO users (id, name, email, password_hash, role) VALUES 
(1, 'Test Admin', 'admin@gamon.app', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

SHOW TABLES;
SELECT 'Database structure created/updated successfully!' as result;