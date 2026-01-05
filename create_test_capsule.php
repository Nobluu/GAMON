<?php
require_once 'controllers/Auth.php';
require_once 'controllers/Capsule.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    die('Please login first. <a href="login.php">Login</a>');
}

$user = $auth->getCurrentUser();
$capsuleController = new Capsule();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test_capsule'])) {
    $testData = [
        'title' => 'Test Capsule - In Delivery',
        'message' => 'This is a test capsule that should be deletable because it\'s still in delivery and not unlocked yet.',
        'mood_id' => 1, // Happy mood
        'unlock_date' => date('Y-m-d H:i:s', strtotime('+1 week')), // Unlock in 1 week
        'email_notification' => true,
        'public_sharing' => false,
        'auto_backup' => true
    ];
    
    $result = $capsuleController->createCapsule($user['id'], $testData);
    
    if ($result['success']) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "✅ Test capsule created successfully!";
        echo "</div>";
    } else {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "❌ Error creating test capsule: " . $result['message'];
        echo "</div>";
    }
}

echo "<h2>Create Test Capsule for Delete Feature</h2>";
echo "<p>This will create a test capsule that can be deleted (unlock date in the future).</p>";

echo '<form method="POST">';
echo '<button type="submit" name="create_test_capsule" style="background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Create Test Capsule</button>';
echo '</form>';

echo "<br><br>";
echo "<a href='test_delete_capsule.php'>Test Delete Feature</a> | ";
echo "<a href='dashboard.php'>Back to Dashboard</a>";
?>