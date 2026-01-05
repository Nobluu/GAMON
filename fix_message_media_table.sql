-- Script to create message_media table if it doesn't exist
-- Run this SQL if you're having issues with message upload in create-message_new.php

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

-- Also add these columns to messages table if they don't exist
-- These are referenced in MessageController
ALTER TABLE messages 
ADD COLUMN IF NOT EXISTS is_anonymous TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS visibility ENUM('private', 'shared') DEFAULT 'private';