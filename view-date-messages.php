<?php
require_once 'controllers/Auth.php';
require_once 'config/database.php';
require_once 'helpers/NavHelper.php';

$auth = new Auth();
$auth->requireLogin();

if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
$database = new Database();
$conn = $database->getConnection();

$date = $_GET['date'] ?? date('Y-m-d');

// Get capsules for the specific date with mood information
$stmt = $conn->prepare("
    SELECT c.*, 
           u.name as user_name,
           m.emoji as mood_emoji,
           m.name as mood_name,
           CASE 
               WHEN c.unlock_date <= NOW() THEN 'unlocked'
               ELSE 'locked'
           END as status
    FROM capsules c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN moods m ON c.mood_id = m.id
    WHERE c.user_id = ? 
    AND DATE(c.unlock_date) = ?
    ORDER BY c.unlock_date ASC
");
$stmt->execute([$user['id'], $date]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages for <?= htmlspecialchars($date) ?> - GAMON</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: rgba(255, 255, 255, 0.8);
            padding: 2rem;
            border-radius: 20px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
        }
        
        .back-btn {
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(242, 92, 92, 0.3);
        }
        
        .date-title {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .capsule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .message-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            transition: transform 0.3s ease;
        }

        .message-card:hover {
            transform: translateY(-5px);
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .message-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            flex: 1;
            margin-right: 1rem;
        }
        
        .mood-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        
        .capsule-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-unlocked {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .status-locked {
            background: rgba(255, 193, 7, 0.2);
            color: #b8860b;
        }
        
        .message-info {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .message-content {
            background: rgba(255, 255, 255, 0.5);
            padding: 1.5rem;
            border-radius: 15px;
            margin-top: 1rem;
            border: 1px solid rgba(242, 92, 92, 0.1);
            color: #4b5563;
            line-height: 1.6;
        }
        
        .locked-content {
            text-align: center;
            color: #6b7280;
            font-style: italic;
        }
        
        .no-messages {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
        }
        
        .no-messages h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #1f2937;
        }
        
        .no-messages p {
            color: #6b7280;
        }
        
        .unlock-time {
            font-size: 0.75rem;
            color: #dc2626;
            font-weight: 600;
            background: rgba(248, 113, 113, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            display: inline-block;
            margin-top: 1rem;
            border: 1px solid rgba(248, 113, 113, 0.2);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container">
        <div class="header">
            <a href="calendar.php" class="back-btn">← Kembali ke Kalender</a>
            <h1 class="date-title">🗓️ <?= date('F j, Y', strtotime($date)) ?></h1>
        </div>
        
        <?php if (empty($messages)): ?>
            <div class="no-messages">
                <h3>📭 Tidak Ada Pesan</h3>
                <p>Tidak ada pesan yang dijadwalkan untuk dibuka pada tanggal ini.</p>
            </div>
        <?php else: ?>
            <div class="capsule-grid">
                <?php foreach ($messages as $message): ?>
                    <div class="message-card">
                        <div class="message-header">
                            <h3 class="message-title"><?= htmlspecialchars($message['title']) ?></h3>
                            <span class="capsule-status status-<?= $message['status'] ?>">
                                <?= $message['status'] === 'unlocked' ? '🔓 Terbuka' : '🔒 Terkunci' ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($message['mood_emoji']) || !empty($message['mood_name'])): ?>
                            <div class="mood-indicator">
                                <?= $message['mood_emoji'] ?? '😊' ?> 
                                <span><?= htmlspecialchars($message['mood_name'] ?? 'Mood') ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="message-info">
                            <div>
                                <strong>📝 Dibuat oleh:</strong> <?= htmlspecialchars($message['user_name']) ?>
                            </div>
                            <div>
                                <strong>⏰ Waktu Buka:</strong> <?= date('g:i A', strtotime($message['unlock_date'])) ?>
                            </div>
                        </div>
                        
                        <?php if ($message['status'] === 'unlocked'): ?>
                            <div class="message-content">
                                <?= nl2br(htmlspecialchars($message['message'])) ?>
                            </div>
                        <?php else: ?>
                            <div class="message-content locked-content">
                                🔒 Kapsul ini masih terkunci dan akan tersedia pada<br>
                                <strong><?= date('F j, Y \\\\p\\\\a\\\\d\\\\a g:i A', strtotime($message['unlock_date'])) ?> WIB</strong>
                                
                                <?php 
                                $timeLeft = strtotime($message['unlock_date']) - time();
                                if ($timeLeft > 0):
                                    $days = floor($timeLeft / 86400);
                                    $hours = floor(($timeLeft % 86400) / 3600);
                                    $minutes = floor(($timeLeft % 3600) / 60);
                                ?>
                                    <div class="unlock-time">
                                        ⏰ Terbuka dalam: <?= $days ?>h <?= $hours ?>j <?= $minutes ?>m
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>