<?php
// Test upload functionality
session_start();

// Mock user session for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Test user ID
}

require_once 'config/database.php';
require_once 'controllers/MediaController.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['test_upload'])) {
        echo "<h3>Testing File Upload</h3>";
        echo "<pre>";
        echo "File Info:\n";
        print_r($_FILES['test_upload']);
        echo "\n";
        
        // Test MediaController upload
        $mediaController = new MediaController();
        
        // Create a test message ID (you may need to use an existing one)
        $test_message_id = 1; // Change this to an existing message ID
        
        if ($_FILES['test_upload']['name']) {
            $result = $mediaController->uploadFile($test_message_id, $_FILES['test_upload'], $_SESSION['user_id']);
            echo "Upload Result:\n";
            print_r($result);
        }
        echo "</pre>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Upload</title>
</head>
<body>
    <h2>Test Image Upload</h2>
    <form method="POST" enctype="multipart/form-data">
        <p>
            <label>Select Image:</label><br>
            <input type="file" name="test_upload" accept="image/*" required>
        </p>
        <p>
            <button type="submit">Test Upload</button>
        </p>
    </form>
    
    <h3>Debug Info:</h3>
    <ul>
        <li>Upload directory exists: <?php echo is_dir('uploads/') ? 'YES' : 'NO'; ?></li>
        <li>Upload directory writable: <?php echo is_writable('uploads/') ? 'YES' : 'NO'; ?></li>
        <li>PHP upload_max_filesize: <?php echo ini_get('upload_max_filesize'); ?></li>
        <li>PHP post_max_size: <?php echo ini_get('post_max_size'); ?></li>
        <li>Session user_id: <?php echo $_SESSION['user_id'] ?? 'NOT SET'; ?></li>
    </ul>
</body>
</html>