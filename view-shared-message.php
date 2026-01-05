<?php
require_once 'config/db.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$message_id = $_GET['id'] ?? '';
$token = $_GET['token'] ?? '';
$error = '';
$message = null;

if (empty($message_id) || empty($token)) {
    $error = "Link tidak valid. Parameter ID dan token diperlukan.";
} else {
    // Get message
    $stmt = $conn->prepare("
        SELECT m.*, 
               sender.name as sender_name, sender.email as sender_email,
               receiver.name as receiver_name,
               mood.emoji as mood_emoji, mood.name as mood_name,
               CASE 
                   WHEN m.scheduled_open_at <= NOW() THEN 'unlocked'
                   ELSE 'locked'
               END as status,
               TIMESTAMPDIFF(SECOND, NOW(), m.scheduled_open_at) as seconds_left
        FROM messages m
        LEFT JOIN users sender ON m.sender_id = sender.id
        LEFT JOIN users receiver ON m.receiver_id = receiver.id
        LEFT JOIN moods mood ON m.mood_id = mood.id
        WHERE m.id = ?
    ");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        $error = "Pesan tidak ditemukan.";
    } else {
        // Verify token
        $receiver_email = $message['receiver_email'] ?? $message['sender_email'] ?? '';
        $expected_token = md5($message_id . $receiver_email);
        
        if ($token !== $expected_token) {
            $error = "Token tidak valid. Link mungkin sudah tidak berlaku.";
            $message = null;
        }
    }
}

function formatTimeLeft($seconds) {
    if ($seconds <= 0) return 'Sudah terbuka';
    
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($days > 0) return "{$days} hari {$hours} jam {$minutes} menit";
    if ($hours > 0) return "{$hours} jam {$minutes} menit";
    return "{$minutes} menit";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $message ? htmlspecialchars($message['title']) . ' - GAMON' : 'Error - GAMON' ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
            line-height: 1.6;
            padding: 2rem;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            box-shadow: 0 20px 40px rgba(242, 92, 92, 0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo {
            font-size: 2rem;
            color: #f25c5c;
            margin-bottom: 1rem;
        }
        
        .title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        
        .message-card {
            background: rgba(255, 255, 255, 0.6);
            border-radius: 15px;
            padding: 2rem;
            border-left: 5px solid #f25c5c;
        }
        
        .message-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
            font-size: 14px;
            color: #6b7280;
            background: rgba(242, 92, 92, 0.05);
            padding: 1rem;
            border-radius: 10px;
        }
        
        .message-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-top: 1.5rem;
            border: 1px solid rgba(242, 92, 92, 0.1);
            line-height: 1.8;
        }
        
        .locked-content {
            text-align: center;
            color: #9ca3af;
            font-style: italic;
            padding: 3rem 2rem;
        }
        
        .countdown {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            display: inline-block;
            margin-top: 15px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        
        .status-unlocked {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-locked {
            background: #fef3c7;
            color: #92400e;
        }
        
        .mood-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(242, 92, 92, 0.1);
            color: #f25c5c;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .cta {
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
            padding: 15px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin-top: 2rem;
            transition: all 0.3s ease;
        }
        
        .cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(242, 92, 92, 0.3);
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(242, 92, 92, 0.1);
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🕰️ GAMON</div>
            <div style="color: #6b7280;">Time Capsule Message</div>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <h2>❌ <?= htmlspecialchars($error) ?></h2>
                <p>Silakan periksa kembali link yang Anda terima.</p>
            </div>
        <?php else: ?>
            <div class="message-card">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <span class="status-badge status-<?= $message['status'] ?>">
                        <?= $message['status'] === 'unlocked' ? '🔓 Pesan Terbuka' : '🔒 Pesan Terkunci' ?>
                    </span>
                </div>
                
                <h1 class="title"><?= htmlspecialchars($message['title']) ?></h1>
                
                <div class="message-info">
                    <div>
                        <strong>Dari:</strong> <?= htmlspecialchars($message['sender_name']) ?><br>
                        <small><?= htmlspecialchars($message['sender_email']) ?></small>
                    </div>
                    <div>
                        <strong>Dibuka:</strong> <?= date('d F Y, H:i', strtotime($message['scheduled_open_at'])) ?> WIB<br>
                        <?php if ($message['mood_emoji']): ?>
                            <span class="mood-badge">
                                <?= $message['mood_emoji'] ?> <?= $message['mood_name'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($message['status'] === 'unlocked'): ?>
                    <div class="message-content">
                        <?= nl2br(htmlspecialchars($message['content'])) ?>
                    </div>
                <?php else: ?>
                    <div class="message-content locked-content">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🔒</div>
                        <h3>Pesan ini masih terkunci</h3>
                        <p>Pesan akan terbuka pada:<br>
                        <strong><?= date('d F Y \p\u\k\u\l H:i', strtotime($message['scheduled_open_at'])) ?> WIB</strong></p>
                        
                        <div class="countdown">⏰ <?= formatTimeLeft($message['seconds_left']) ?> lagi</div>
                        
                        <p style="margin-top: 1rem; color: #9ca3af;">
                            Bookmark halaman ini dan kembali setelah waktu tersebut!
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Pesan ini dikirim melalui <strong>GAMON</strong> - Time Capsule Messaging App</p>
            <a href="http://localhost/gamon/register.php" class="cta">🚀 Buat Pesan Time Capsule Sendiri</a>
        </div>
    </div>
    
    <?php if ($message && $message['status'] === 'locked'): ?>
    <script>
        // Auto refresh when message should unlock
        const unlockTime = new Date('<?= date('Y-m-d\TH:i:s', strtotime($message['scheduled_open_at'])) ?>').getTime();
        
        setInterval(function() {
            const now = new Date().getTime();
            if (now >= unlockTime) {
                location.reload();
            }
        }, 60000); // Check every minute
        
        // Update countdown
        setInterval(function() {
            const now = new Date().getTime();
            const distance = unlockTime - now;
            
            if (distance > 0) {
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                
                let countdownText = "⏰ ";
                if (days > 0) countdownText += days + " hari ";
                if (hours > 0) countdownText += hours + " jam ";
                countdownText += minutes + " menit lagi";
                
                document.querySelector('.countdown').textContent = countdownText;
            }
        }, 1000); // Update every second
    </script>
    <?php endif; ?>
</body>
</html>