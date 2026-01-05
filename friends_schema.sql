-- Create friends/friendship system for GAMON
-- This enables mutual friend relationships

-- 1. Create friendships table
CREATE TABLE IF NOT EXISTS friendships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    addressee_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'blocked') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_requester (requester_id),
    INDEX idx_addressee (addressee_id),
    INDEX idx_status (status),
    INDEX idx_friendship_pair (requester_id, addressee_id),
    
    -- Foreign key constraints
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (addressee_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Prevent duplicate friend requests and self-friendship
    UNIQUE KEY unique_friendship (requester_id, addressee_id),
    CONSTRAINT no_self_friend CHECK (requester_id != addressee_id)
);

-- 2. Create friend_notifications table (for friend-related notifications)
CREATE TABLE IF NOT EXISTS friend_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('friend_request', 'friend_accepted', 'friend_declined') NOT NULL,
    from_user_id INT NOT NULL,
    friendship_id INT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_user_notifications (user_id, is_read),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at),
    
    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friendship_id) REFERENCES friendships(id) ON DELETE CASCADE
);

-- 3. Add friend_code to users table for easy friend finding
ALTER TABLE users ADD COLUMN friend_code VARCHAR(8) UNIQUE AFTER email;

-- 4. Create function to generate unique friend codes
DELIMITER //
CREATE TRIGGER generate_friend_code 
    BEFORE INSERT ON users 
    FOR EACH ROW 
BEGIN 
    DECLARE code_exists INT DEFAULT 1;
    DECLARE new_code VARCHAR(8);
    
    -- Generate unique 8-character friend code
    WHILE code_exists = 1 DO
        SET new_code = UPPER(SUBSTRING(MD5(CONCAT(RAND(), NOW())), 1, 8));
        SELECT COUNT(*) INTO code_exists FROM users WHERE friend_code = new_code;
    END WHILE;
    
    SET NEW.friend_code = new_code;
END//
DELIMITER ;

-- 5. Update existing users with friend codes
UPDATE users SET friend_code = UPPER(SUBSTRING(MD5(CONCAT(id, email, RAND())), 1, 8)) WHERE friend_code IS NULL;

-- 6. Create view for easy friend listing
CREATE VIEW user_friends AS
SELECT 
    u1.id as user_id,
    u2.id as friend_id,
    u2.name as friend_name,
    u2.email as friend_email,
    u2.friend_code as friend_code,
    u2.profile_picture as friend_profile_picture,
    f.created_at as friendship_date,
    'accepted' as friendship_status
FROM friendships f
JOIN users u1 ON (f.requester_id = u1.id OR f.addressee_id = u1.id)
JOIN users u2 ON (f.requester_id = u2.id OR f.addressee_id = u2.id)
WHERE f.status = 'accepted' 
AND u1.id != u2.id;

-- 7. Create indexes for better performance
CREATE INDEX idx_users_friend_code ON users(friend_code);
CREATE INDEX idx_users_name_email ON users(name, email);