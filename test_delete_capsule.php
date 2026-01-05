<?php
// Test delete capsule functionality
require_once 'controllers/Auth.php';
require_once 'controllers/Capsule.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    die('Please login first. <a href="login.php">Login</a>');
}

$user = $auth->getCurrentUser();
$capsuleController = new Capsule();

echo "<h2>Test Delete Capsule Feature</h2>";

// Get user's locked capsules that can be deleted
$capsules = $capsuleController->getUserCapsules($user['id']);
$deletableCapsules = array_filter($capsules, function($capsule) use ($capsuleController, $user) {
    return $capsuleController->canDeleteCapsule($capsule['id'], $user['id']);
});

echo "<h3>Your Deletable Capsules (In delivery, not yet opened):</h3>";

if (empty($deletableCapsules)) {
    echo "<p>You have no deletable capsules. Deletable capsules are those that:</p>";
    echo "<ul>";
    echo "<li>Haven't been unlocked yet (is_unlocked = 0)</li>";
    echo "<li>Unlock date is still in the future</li>";
    echo "</ul>";
    
    // Show all capsules for reference
    echo "<h3>All Your Capsules:</h3>";
    foreach ($capsules as $capsule) {
        $unlockTime = strtotime($capsule['unlock_date']);
        $isUnlocked = $capsule['is_unlocked'] == 1;
        $canDelete = $capsuleController->canDeleteCapsule($capsule['id'], $user['id']);
        
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
        echo "<strong>" . htmlspecialchars($capsule['title']) . "</strong><br>";
        echo "Status: " . ($isUnlocked ? "Unlocked ✅" : "Locked 🔒") . "<br>";
        echo "Unlock Date: " . date('Y-m-d H:i:s', $unlockTime) . "<br>";
        echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";
        echo "Can Delete: " . ($canDelete ? "YES ✅" : "NO ❌") . "<br>";
        echo "</div>";
    }
} else {
    foreach ($deletableCapsules as $capsule) {
        $unlockTime = strtotime($capsule['unlock_date']);
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
        echo "<strong>" . htmlspecialchars($capsule['title']) . "</strong><br>";
        echo "Unlock Date: " . date('Y-m-d H:i:s', $unlockTime) . "<br>";
        echo "Message: " . htmlspecialchars(substr($capsule['message'], 0, 100)) . "...<br>";
        echo "<button onclick='testDelete(" . $capsule['id'] . ")' data-title='" . htmlspecialchars($capsule['title'], ENT_QUOTES) . "' style='background: #ef4444; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;'>Delete This Capsule</button>";
        echo "</div>";
    }
}

echo "<br><a href='dashboard.php'>Back to Dashboard</a>";
?>

<script>
function testDelete(capsuleId) {
    const button = event.target;
    const title = button.getAttribute('data-title') || 'this capsule';
    
    if (!confirm('Delete "' + title + '"?')) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete_capsule&capsule_id=' + capsuleId
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting capsule');
    });
}

// Handle AJAX delete request
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_capsule') {
    header('Content-Type: application/json');
    
    $capsuleId = $_POST['capsule_id'] ?? null;
    if (!$capsuleId) {
        echo json_encode(['success' => false, 'message' => 'Invalid capsule ID']);
        exit;
    }
    
    $result = $capsuleController->deleteCapsule($capsuleId, $user['id']);
    echo json_encode($result);
    exit;
}
?>
</script>