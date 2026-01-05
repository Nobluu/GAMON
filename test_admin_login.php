<?php
// Test script untuk generate password hash dan test login
echo "<h2>Password Hash Generator & Login Test</h2>";

// Generate password hash untuk 'demo123'
$password = 'demo123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Password Hash untuk 'demo123':</h3>";
echo "<code>$hash</code><br><br>";

// Test verifikasi
$isValid = password_verify('demo123', $hash);
echo "<h3>Test Verifikasi:</h3>";
echo $isValid ? "✅ Password hash VALID" : "❌ Password hash INVALID";
echo "<br><br>";

// Test koneksi database dan cek admin users
try {
    require_once 'config/db.php';
    $db = Database::getInstance()->getConnection();
    
    echo "<h3>Database Connection:</h3>";
    echo "✅ Database connected<br><br>";
    
    // Cek struktur tabel users
    echo "<h3>Users Table Structure:</h3>";
    $stmt = $db->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})<br>";
    }
    echo "<br>";
    
    // Cek admin users
    echo "<h3>Admin Users in Database:</h3>";
    $stmt = $db->prepare("SELECT id, name, email, role, status FROM users WHERE email LIKE '%gamon.com'");
    $stmt->execute();
    $adminUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($adminUsers)) {
        echo "❌ No admin users found<br><br>";
        
        // Insert admin users dengan hash yang benar
        echo "<h3>Creating Admin Users:</h3>";
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password_hash, role, status) VALUES 
            (?, ?, ?, ?, ?),
            (?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            'Admin User', 'admin@gamon.com', $hash, 'admin', 'active',
            'Super Admin', 'superadmin@gamon.com', $hash, 'superadmin', 'active'
        ]);
        
        if ($result) {
            echo "✅ Admin users created successfully<br>";
        } else {
            echo "❌ Failed to create admin users<br>";
        }
    } else {
        echo "✅ Found admin users:<br>";
        foreach ($adminUsers as $admin) {
            echo "- {$admin['name']} ({$admin['email']}) - Role: {$admin['role']}, Status: {$admin['status']}<br>";
        }
    }
    echo "<br>";
    
    // Test login dengan admin credentials
    echo "<h3>Testing Admin Login:</h3>";
    require_once 'controllers/Auth.php';
    $auth = new Auth();
    
    $loginResult = $auth->login('admin@gamon.com', 'demo123');
    if ($loginResult['success']) {
        echo "✅ Admin login successful<br>";
        $user = $auth->getCurrentUser();
        echo "- User ID: {$user['id']}<br>";
        echo "- Name: {$user['name']}<br>";
        echo "- Role: {$user['role']}<br>";
    } else {
        echo "❌ Admin login failed: {$loginResult['message']}<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 5px; }
    h3 { color: #555; }
    code { background: #f4f4f4; padding: 5px; border-radius: 3px; font-family: monospace; }
</style>