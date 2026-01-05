-- Fix admin password hash
-- Update admin users dengan password hash yang benar untuk 'demo123'

UPDATE users SET password_hash = '$2y$10$A.GV.ncRABcPpl6GfDUpb.erJlI1we6nTErpVa1p3r91DIUUeao6y' WHERE email = 'admin@gamon.com';

UPDATE users SET password_hash = '$2y$10$A.GV.ncRABcPpl6GfDUpb.erJlI1we6nTErpVa1p3r91DIUUeao6y' WHERE email = 'superadmin@gamon.com';

-- Verify the fix
SELECT id, name, email, role, status FROM users WHERE email LIKE '%gamon.com';