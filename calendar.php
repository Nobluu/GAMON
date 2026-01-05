<?php
require_once 'config/database.php';
require_once 'controllers/Auth.php';
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

// Get current month and year
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Navigation for prev/next month
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Get capsules for current month that will unlock
$stmt = $conn->prepare("
    SELECT DATE(unlock_date) as unlock_day, COUNT(*) as message_count,
           GROUP_CONCAT(title SEPARATOR '|') as titles
    FROM capsules 
    WHERE user_id = ? 
    AND MONTH(unlock_date) = ? 
    AND YEAR(unlock_date) = ?
    AND unlock_date >= NOW()
    GROUP BY DATE(unlock_date)
");
$stmt->execute([$_SESSION['user_id'], $currentMonth, $currentYear]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative array for easy lookup
$messagesByDate = [];
foreach ($messages as $msg) {
    $messagesByDate[$msg['unlock_day']] = [
        'count' => $msg['message_count'],
        'titles' => explode('|', $msg['titles'])
    ];
}

// Calendar generation
$firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
$monthName = date('F Y', $firstDay);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay); // 0 = Sunday

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GAMON - Kalender Pesan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
            line-height: 1.6;
        }





        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            text-decoration: none;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(242, 92, 92, 0.3);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .welcome-section {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .nav-btn {
            background: rgba(242, 92, 92, 0.9);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 15px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .nav-btn:hover {
            background: #f25c5c;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(242, 92, 92, 0.3);
        }
        
        .month-nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .month-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .calendar-container {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(242, 92, 92, 0.1);
            box-shadow: 0 20px 40px rgba(242, 92, 92, 0.1);
        }
        
        .calendar {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .calendar th, .calendar td {
            width: 14.28%;
            height: 100px;
            border: 1px solid rgba(242, 92, 92, 0.1);
            text-align: center;
            vertical-align: top;
            position: relative;
        }
        
        .calendar th {
            background: rgba(242, 92, 92, 0.1);
            height: 50px;
            font-weight: 600;
            color: #f25c5c;
        }
        
        .calendar td {
            background: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .calendar td:hover {
            background: rgba(242, 92, 92, 0.05);
            transform: scale(1.02);
        }
        
        .day-number {
            position: absolute;
            top: 8px;
            left: 10px;
            font-weight: 600;
            color: #374151;
        }
        
        .has-messages {
            background: linear-gradient(135deg, rgba(242, 92, 92, 0.2), rgba(255, 123, 123, 0.2)) !important;
        }
        
        .has-messages:hover {
            background: linear-gradient(135deg, rgba(242, 92, 92, 0.3), rgba(255, 123, 123, 0.3)) !important;
            transform: scale(1.05);
        }
        
        .message-indicator {
            position: absolute;
            bottom: 8px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(242, 92, 92, 0.3);
        }
        
        .empty-day {
            color: #d1d5db;
            background: rgba(255, 255, 255, 0.3);
        }
        
        .today {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)) !important;
            border: 2px solid #10b981;
        }
        
        .today .day-number {
            color: #10b981;
            font-weight: 700;
        }
        
        .past-date {
            background: rgba(255, 255, 255, 0.4);
            color: #9ca3af;
        }
        
        .tooltip {
            position: absolute;
            background: linear-gradient(135deg, #374151, #4b5563);
            color: white;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            z-index: 1000;
            max-width: 250px;
            display: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
        }
        
        .legend {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            color: #6b7280;
        }
        
        .legend-color {
            width: 24px;
            height: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 1024px) {
            .nav-links { gap: 2rem; }
            .nav-links a { 
                padding: 0.5rem 1rem; 
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .nav { 
                padding: 0 1rem; 
                flex-direction: column;
                gap: 1rem;
            }
            .nav-links { 
                gap: 1.5rem; 
                flex-wrap: wrap;
                justify-content: center;
            }
            .nav-links a { 
                padding: 0.5rem 0.75rem; 
                font-size: 0.85rem;
            }
            .user-menu { 
                order: -1;
                gap: 0.5rem;
            }
            .calendar-grid { font-size: 0.8rem; }
            .day-cell { padding: 0.25rem; }
        }
    </style>
</head>
<?php include 'includes/navbar.php'; ?>
<body>
    
    <div class="container">
        <div class="welcome-section">
            <div class="calendar-header">
                <div class="month-nav">
                    <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="nav-btn">‹ Sebelumnya</a>
                    <h1 class="month-title"><?= $monthName ?></h1>
                    <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="nav-btn">Selanjutnya ›</a>
                </div>
            </div>
        </div>
        
        <div class="calendar-container">
            <table class="calendar">
            <thead>
                <tr>
                    <th>Minggu</th>
                    <th>Senin</th>
                    <th>Selasa</th>
                    <th>Rabu</th>
                    <th>Kamis</th>
                    <th>Jumat</th>
                    <th>Sabtu</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $currentDate = 1;
                $today = date('Y-m-d');
                
                // First week
                echo '<tr>';
                for ($i = 0; $i < 7; $i++) {
                    if ($i < $dayOfWeek) {
                        echo '<td class="empty-day"></td>';
                    } else {
                        $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $currentDate);
                        $hasMessages = isset($messagesByDate[$dateStr]);
                        $isToday = $dateStr === $today;
                        $isPast = $dateStr < $today;
                        
                        $class = '';
                        if ($isToday) $class .= ' today';
                        elseif ($isPast) $class .= ' past-date';
                        if ($hasMessages) $class .= ' has-messages';
                        
                        echo '<td class="' . $class . '" data-date="' . $dateStr . '">';
                        echo '<div class="day-number">' . $currentDate . '</div>';
                        
                        if ($hasMessages) {
                            $count = $messagesByDate[$dateStr]['count'];
                            echo '<div class="message-indicator">' . $count . ' message' . ($count > 1 ? 's' : '') . '</div>';
                        }
                        
                        echo '</td>';
                        $currentDate++;
                    }
                }
                echo '</tr>';
                
                // Remaining weeks
                while ($currentDate <= $daysInMonth) {
                    echo '<tr>';
                    for ($i = 0; $i < 7 && $currentDate <= $daysInMonth; $i++) {
                        $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $currentDate);
                        $hasMessages = isset($messagesByDate[$dateStr]);
                        $isToday = $dateStr === $today;
                        $isPast = $dateStr < $today;
                        
                        $class = '';
                        if ($isToday) $class .= ' today';
                        elseif ($isPast) $class .= ' past-date';
                        if ($hasMessages) $class .= ' has-messages';
                        
                        echo '<td class="' . $class . '" data-date="' . $dateStr . '">';
                        echo '<div class="day-number">' . $currentDate . '</div>';
                        
                        if ($hasMessages) {
                            $count = $messagesByDate[$dateStr]['count'];
                            echo '<div class="message-indicator">' . $count . ' message' . ($count > 1 ? 's' : '') . '</div>';
                        }
                        
                        echo '</td>';
                        $currentDate++;
                    }
                    
                    // Fill remaining empty cells
                    while ($i < 7) {
                        echo '<td class="empty-day"></td>';
                        $i++;
                    }
                    echo '</tr>';
                }
                ?>
            </tbody>
            </table>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #10b981, #059669);"></div>
                    <span>Today</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, rgba(242, 92, 92, 0.2), rgba(255, 123, 123, 0.2));"></div>
                    <span>Has Messages</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: rgba(255, 255, 255, 0.4);"></div>
                    <span>Past Dates</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="tooltip" id="tooltip"></div>
    
    <script>
        // Message data for tooltips
        const messageData = <?= json_encode($messagesByDate) ?>;
        const tooltip = document.getElementById('tooltip');
        
        document.querySelectorAll('.calendar td[data-date]').forEach(cell => {
            cell.addEventListener('mouseenter', function(e) {
                const date = this.dataset.date;
                if (messageData[date]) {
                    const data = messageData[date];
                    let content = `<strong>${data.count} message(s) on this date:</strong><br>`;
                    data.titles.forEach(title => {
                        content += `• ${title.substring(0, 30)}${title.length > 30 ? '...' : ''}<br>`;
                    });
                    
                    tooltip.innerHTML = content;
                    tooltip.style.display = 'block';
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = (rect.left + rect.width / 2) + 'px';
                    tooltip.style.top = (rect.top - 10) + 'px';
                }
            });
            
            cell.addEventListener('mouseleave', function() {
                tooltip.style.display = 'none';
            });
            
            cell.addEventListener('click', function() {
                const date = this.dataset.date;
                if (messageData[date]) {
                    window.location.href = `view-date-messages.php?date=${date}`;
                }
            });
        });
    </script>
</body>
</html>