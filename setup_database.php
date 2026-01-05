<?php
// Database setup script
echo "<h3>Database Setup</h3>";

try {
    // First, connect without specifying database to create it if needed
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3306",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "<p style='color: green;'>✓ Connected to MySQL server</p>";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS capsule_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✓ Database 'capsule_db' created/verified</p>";
    
    // Now connect to the specific database
    $pdo->exec("USE capsule_db");
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        // Create users table
        $createTable = "
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin') DEFAULT 'user',
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            email_verified_at TIMESTAMP NULL,
            profile_image VARCHAR(255),
            bio TEXT,
            birth_date DATE,
            timezone VARCHAR(100) DEFAULT 'Asia/Jakarta'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createTable);
        echo "<p style='color: green;'>✓ Users table created</p>";
        
        // Insert a test user
        $hashedPassword = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Test User', 'test@example.com', $hashedPassword, 'user']);
        echo "<p style='color: green;'>✓ Test user created (email: test@example.com, password: 123456)</p>";
    } else {
        echo "<p style='color: green;'>✓ Users table already exists</p>";
        
        // Show user count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        echo "<p>Current users in database: " . $result['count'] . "</p>";
    }
    
    echo "<h4>Setup completed successfully!</h4>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database setup failed: " . $e->getMessage() . "</p>";
    echo "<h4>Please check:</h4>";
    echo "<ul>";
    echo "<li>XAMPP is installed and running</li>";
    echo "<li>MySQL service is started in XAMPP Control Panel</li>";
    echo "<li>MySQL is running on port 3306</li>";
    echo "<li>MySQL root user has no password (default XAMPP setup)</li>";
    echo "</ul>";
}
?>