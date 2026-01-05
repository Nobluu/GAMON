<?php
// Deep debug untuk admin login issue
echo "<h2>Deep Debug Admin Login</h2>";

try {
    require_once 'config/db.php';
    $db = Database::getInstance()->getConnection();
    
    // Cek admin user yang sebenarnya di database
    echo "<h3>1. Data Admin di Database:</h3>";
    $stmt = $db->prepare("SELECT id, name, email, password_hash, role, status FROM users WHERE email = 'admin@gamon.com'");
    $stmt->execute();
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminUser) {
        echo "✅ Admin user found:<br>";
        echo "- ID: {$adminUser['id']}<br>";
        echo "- Name: {$adminUser['name']}<br>";
        echo "- Email: {$adminUser['email']}<br>";
        echo "- Role: {$adminUser['role']}<br>";
        echo "- Status: {$adminUser['status']}<br>";
        echo "- Password Hash: <code>" . substr($adminUser['password_hash'], 0, 50) . "...</code><br><br>";
        
        // Test password verification dengan hash dari database
        echo "<h3>2. Test Password Verification:</h3>";
        $passwordTest = password_verify('demo123', $adminUser['password_hash']);
        echo $passwordTest ? "✅ Password 'demo123' VALID dengan hash di database" : "❌ Password 'demo123' INVALID dengan hash di database";
        echo "<br><br>";
        
        // Test query yang sama dengan Auth.php
        echo "<h3>3. Test Query Auth.php:</h3>";
        $stmt = $db->prepare("
            SELECT id, name, email, password_hash, role, status, last_login 
            FROM users 
            WHERE email = ? AND status = 'active'
        ");
        $stmt->execute(['admin@gamon.com']);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "✅ Query Auth.php berhasil return user<br>";
            echo "- Status: {$user['status']}<br>";
            echo "- Role: {$user['role']}<br>";
            
            // Test password verify lagi
            if (password_verify('demo123', $user['password_hash'])) {
                echo "✅ Password verification BERHASIL<br><br>";
                
                // Manual test session setting
                echo "<h3>4. Manual Session Test:</h3>";
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                echo "✅ Session variables set:<br>";
                echo "- user_id: {$_SESSION['user_id']}<br>";
                echo "- user_name: {$_SESSION['user_name']}<br>";
                echo "- user_email: {$_SESSION['user_email']}<br>";
                echo "- user_role: {$_SESSION['user_role']}<br><br>";
                
            } else {
                echo "❌ Password verification GAGAL<br><br>";
            }
        } else {
            echo "❌ Query Auth.php GAGAL return user<br>";
            echo "Kemungkinan status bukan 'active' atau ada masalah query<br><br>";
        }
        
    } else {
        echo "❌ Admin user tidak ditemukan di database<br><br>";
    }
    
    // Test manual login tanpa Auth class
    echo "<h3>5. Manual Login Test (tanpa Auth class):</h3>";
    $email = 'admin@gamon.com';
    $password = 'demo123';
    
    $stmt = $db->prepare("SELECT id, name, email, password_hash, role, status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] === 'active') {
            echo "✅ Manual login BERHASIL - Admin bisa login<br>";
            echo "🔗 <a href='admin/dashboard.php' target='_blank'>Test Admin Dashboard</a><br>";
        } else {
            echo "❌ User status: {$user['status']} (bukan 'active')<br>";
        }
    } else {
        echo "❌ Manual login GAGAL<br>";
        if (!$user) {
            echo "- User tidak ditemukan<br>";
        } else {
            echo "- Password tidak match<br>";
        }
    }
    
    echo "<br><h3>6. Fix Suggestion:</h3>";
    if ($passwordTest === false) {
        echo "🔧 Update password hash admin dengan hash yang benar:<br>";
        $newHash = password_hash('demo123', PASSWORD_DEFAULT);
        echo "<code>UPDATE users SET password_hash = '$newHash' WHERE email = 'admin@gamon.com';</code><br>";
    } else {
        echo "🔧 Password hash sudah benar. Kemungkinan masalah di Auth.php atau session handling.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 5px; }
    h3 { color: #555; margin-top: 20px; }
    code { background: #f4f4f4; padding: 5px; border-radius: 3px; font-family: monospace; word-break: break-all; }
    a { color: #667eea; font-weight: bold; }
</style>