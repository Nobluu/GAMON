<?php
require_once 'controllers/Auth.php';
require_once 'controllers/MessageController.php';
require_once 'controllers/Capsule.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    die('Please login first. <a href="login.php">Login</a>');
}

$user = $auth->getCurrentUser();
$messageController = new MessageController();
$capsuleController = new Capsule();

echo "<h2>Test Edit & Delete Message Feature</h2>";

// Create test message if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test_message'])) {
    // Get receiver email - for testing, we can send to self
    $receiver_email = $user['email'];
    
    $result = $messageController->createMessage(
        $user['id'],
        $receiver_email,
        'Test Message - Editable',
        'This is a test message that can be edited and deleted because it hasn\'t been opened yet.',
        1, // Happy mood
        date('Y-m-d H:i:s', strtotime('+1 week')), // Unlock in 1 week
        0, // Not anonymous
        'private'
    );
    
    if ($result['status']) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "✅ Test message created successfully!";
        echo "</div>";
    } else {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "❌ Error creating test message: " . $result['message'];
        echo "</div>";
    }
}

// Get user's messages
$messages_result = $messageController->getUserMessages($user['id'], 1, 50);
$messages = $messages_result['messages'] ?? [];

// Filter editable messages (locked and sender is current user)
$editableMessages = array_filter($messages, function($message) use ($messageController, $user) {
    return $messageController->canModifyMessage($message['id'], $user['id']);
});

echo "<h3>Your Editable Messages:</h3>";

if (empty($editableMessages)) {
    echo "<p>You have no editable messages. Editable messages are those that:</p>";
    echo "<ul>";
    echo "<li>You sent (you are the sender)</li>";
    echo "<li>Haven't been opened yet</li>";
    echo "<li>Haven't reached their unlock time</li>";
    echo "</ul>";
    
    echo '<form method="POST" style="margin: 20px 0;">';
    echo '<button type="submit" name="create_test_message" style="background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Create Test Message</button>';
    echo '</form>';
    
    // Show all messages for reference
    echo "<h3>All Your Messages:</h3>";
    foreach ($messages as $message) {
        $canModify = $messageController->canModifyMessage($message['id'], $user['id']);
        $isSender = $message['sender_id'] == $user['id'];
        
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
        echo "<strong>" . htmlspecialchars($message['title']) . "</strong><br>";
        echo "Role: " . ($isSender ? "Sender ✅" : "Receiver 📨") . "<br>";
        echo "Status: " . ucfirst($message['status']) . "<br>";
        echo "Unlock Time: " . date('Y-m-d H:i:s', strtotime($message['scheduled_open_at'])) . "<br>";
        echo "Can Edit: " . ($canModify ? "YES ✅" : "NO ❌") . "<br>";
        echo "</div>";
    }
} else {
    foreach ($editableMessages as $message) {
        echo "<div style='border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 8px;'>";
        echo "<strong>" . htmlspecialchars($message['title']) . "</strong><br>";
        echo "To: " . htmlspecialchars($message['receiver_email']) . "<br>";
        echo "Unlock Date: " . date('Y-m-d H:i:s', strtotime($message['scheduled_open_at'])) . "<br>";
        echo "Content: " . htmlspecialchars(substr($message['content'], 0, 100)) . "...<br><br>";
        
        echo "<a href='edit-message.php?id=" . $message['id'] . "' style='background: #3b82f6; color: white; padding: 8px 12px; border-radius: 4px; text-decoration: none; margin-right: 10px;'>✏️ Edit Message</a>";
        
        echo "<button onclick='testDeleteMessage(" . $message['id'] . ", \"" . htmlspecialchars($message['title'], ENT_QUOTES) . "\")' style='background: #ef4444; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer;'>🗑️ Delete Message</button>";
        echo "</div>";
    }
}

echo "<br><a href='view-message.php'>📂 View All Messages & Capsules</a>";
?>

<script>
function testDeleteMessage(messageId, title) {
    if (!confirm('Delete message "' + title + '"?')) return;
    
    fetch('view-message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete_message&message_id=' + messageId
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message || 'Operation completed');
        if (data.status) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting message');
    });
}
</script>