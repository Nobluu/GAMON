-- Quick script to add admin columns and users to existing database
-- Step 1: Check database name first
SHOW DATABASES;

-- Step 2: Use your database (replace with your actual database name)
-- USE capsule_db;  -- If your database is different, change this

-- Step 3: Add columns one by one to avoid errors
ALTER TABLE users ADD COLUMN role ENUM('user', 'admin', 'superadmin') DEFAULT 'user';
ALTER TABLE users ADD COLUMN status ENUM('active', 'blocked', 'suspended') DEFAULT 'active';
ALTER TABLE users ADD COLUMN last_login DATETIME NULL;
ALTER TABLE users ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Step 4: Set existing users as 'user' role
UPDATE users SET role = 'user' WHERE role IS NULL OR role = '';

-- Step 5: Insert admin users (without emoji, just text)
INSERT INTO users (name, email, password_hash, profile_picture, role) VALUES 
('Admin User', 'admin@gamon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin.png', 'admin'),
('Super Admin', 'superadmin@gamon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin.png', 'superadmin');

-- Step 6: Verify the results
SELECT id, name, email, role, status FROM users;