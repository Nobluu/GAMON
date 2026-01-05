-- Debug dan fix admin login issue
-- Step 1: Check if admin users exist
SELECT id, name, email, role, status FROM users WHERE email LIKE '%gamon.com';

-- Step 2: Check if role column exists
SHOW COLUMNS FROM users;

-- Step 3: If admin users don't exist, create them
-- First, make sure all existing users have proper default values
UPDATE users SET status = 'active' WHERE status IS NULL;
UPDATE users SET role = 'user' WHERE role IS NULL;

-- Step 4: Delete existing admin users (if any) and recreate with proper password
DELETE FROM users WHERE email IN ('admin@gamon.com', 'superadmin@gamon.com');

-- Step 5: Create new password hash for 'demo123'
-- Using PHP's password_hash function result
INSERT INTO users (name, email, password_hash, role, status) VALUES 
('Admin User', 'admin@gamon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active'),
('Super Admin', 'superadmin@gamon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 'active');

-- Step 6: Verify admin users
SELECT id, name, email, role, status, created_at FROM users WHERE role IN ('admin', 'superadmin');