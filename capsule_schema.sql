-- Capsule App Database Schema
-- Drop database if exists and create new one
DROP DATABASE IF EXISTS capsule_db;
CREATE DATABASE capsule_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE capsule_db;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255) NULL,
    role ENUM('user', 'admin', 'superadmin') DEFAULT 'user',
    status ENUM('active', 'blocked', 'suspended') DEFAULT 'active',
    last_login DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Moods table
CREATE TABLE moods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    color VARCHAR(7) DEFAULT '#f25c5c',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Capsules table
CREATE TABLE capsules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    mood_id INT,
    unlock_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_unlocked BOOLEAN DEFAULT FALSE,
    unlocked_at DATETIME NULL,
    email_notification BOOLEAN DEFAULT TRUE,
    public_sharing BOOLEAN DEFAULT FALSE,
    auto_backup BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mood_id) REFERENCES moods(id) ON DELETE SET NULL,
    INDEX idx_user_unlock (user_id, unlock_date),
    INDEX idx_unlock_date (unlock_date),
    INDEX idx_is_unlocked (is_unlocked)
);

-- Media attachments table
CREATE TABLE capsule_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    capsule_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    caption TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (capsule_id) REFERENCES capsules(id) ON DELETE CASCADE,
    INDEX idx_capsule_media (capsule_id)
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    capsule_id INT,
    type VARCHAR(50) NOT NULL, -- 'unlock', 'reminder', 'system'
    title VARCHAR(255) NOT NULL,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (capsule_id) REFERENCES capsules(id) ON DELETE CASCADE,
    INDEX idx_user_notifications (user_id, is_read),
    INDEX idx_created_at (created_at)
);

-- User sessions table (optional for better session management)
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
);

-- Insert default moods
INSERT INTO moods (name, emoji, color) VALUES
('Happy', '😊', '#f39c12'),
('Excited', '🎉', '#e74c3c'),
('Nostalgic', '🌅', '#9b59b6'),
('Hopeful', '🌟', '#f1c40f'),
('Grateful', '🙏', '#27ae60'),
('Love', '💕', '#e91e63'),
('Nervous', '😰', '#95a5a6'),
('Adventurous', '✈️', '#3498db'),
('Peaceful', '🧘', '#1abc9c'),
('Determined', '💪', '#e67e22'),
('Curious', '🤔', '#34495e'),
('Creative', '🎨', '#9c88ff'),
('Accomplished', '🎓', '#16a085'),
('Reflective', '🤔', '#7f8c8d'),
('Optimistic', '☀️', '#f39c12');

-- Create demo users (password is 'demo123' for all)
INSERT INTO users (name, email, password_hash, profile_picture, role) VALUES 
('Sarah Johnson', 'demo@capsule.app', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '👩‍💼', 'user'),
('Admin User', 'admin@gamon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '👨‍💻', 'admin'),
('Super Admin', 'superadmin@gamon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '🔱', 'superadmin');

-- Insert some demo capsules for the demo user
INSERT INTO capsules (user_id, title, message, mood_id, unlock_date, is_unlocked, email_notification, public_sharing) VALUES
(1, 'Letter to My 30th Birthday Self', 'Dear Future Me,\n\nI''m writing this on a quiet Sunday evening in January 2024. I just turned 28, and I can''t believe how much has changed in the past few years.\n\nRight now, I''m working as a software developer at a startup, and while the hours are long, I genuinely love what I do. I hope by the time you read this, you''ve either grown into a senior position or maybe even started your own company like we''ve always dreamed.\n\nI''ve been dating Sarah for about 8 months now, and I think she might be the one. We talk about the future a lot - maybe by now you''re engaged or even married? I hope you remember how nervous I was to introduce her to Mom and Dad, and how perfectly it went.\n\nWith love and hope,\nPast You', 4, '2026-03-15 12:00:00', FALSE, TRUE, FALSE),

(1, 'New Year Resolutions Check-in', 'This year I promised myself I would learn French and run a marathon. Let''s see how I did!', 4, '2025-12-31 23:59:59', FALSE, TRUE, FALSE),

(1, 'Graduation Day Memories', 'I DID IT!\n\nI''m sitting in my cap and gown right now, about 2 hours after the ceremony ended. I can''t stop smiling.\n\n4 years of Computer Science, countless all-nighters, debugging sessions that made me question my life choices, but I made it through. GPA: 3.7. I''m actually proud of that.\n\nToday was perfect.', 13, '2024-12-15 18:00:00', TRUE, TRUE, TRUE),

(1, 'First Day at New Job', 'Starting my dream job tomorrow. I''m scared but excited about this new chapter in my life.', 7, '2026-01-15 09:00:00', FALSE, TRUE, FALSE),

(1, 'Travel Plans for Europe', 'Planning my solo trip across Europe. Here are all the places I want to visit and the memories I hope to make.', 8, '2025-05-01 10:00:00', TRUE, TRUE, TRUE);

-- Insert some demo notifications
INSERT INTO notifications (user_id, capsule_id, type, title, message) VALUES
(1, 3, 'unlock', 'Capsule Unlocked!', 'Your "Graduation Day Memories" capsule has been unlocked and is ready to read.'),
(1, 5, 'unlock', 'Capsule Unlocked!', 'Your "Travel Plans for Europe" capsule has been unlocked and is ready to read.'),
(1, 1, 'reminder', 'Upcoming Unlock', 'Your "Letter to My 30th Birthday Self" capsule will unlock in 3 months.');

-- Update unlock status for demo capsules that should be unlocked
UPDATE capsules SET is_unlocked = TRUE, unlocked_at = NOW() WHERE unlock_date <= NOW();