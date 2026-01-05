<?php
/**
 * Cron Job: Session Cleanup
 * Jalankan script ini setiap hari untuk membersihkan session yang sudah expired
 * 
 * Cara setup cron job:
 * 0 2 * * * /path/to/php /path/to/cleanup-sessions.php
 * (Jalan setiap hari jam 2 pagi)
 */

require_once __DIR__ . '/../controllers/Auth.php';

echo "🧹 Starting Session Cleanup Job...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$auth = new Auth();

// Clean up sessions older than 7 days
$cleanedCount = $auth->cleanupExpiredSessions(24 * 7); // 7 days

echo "✅ Session cleanup completed!\n";
echo "📊 Cleaned up: $cleanedCount expired sessions\n";

// Log the cleanup
$logEntry = date('Y-m-d H:i:s') . " - Session cleanup: $cleanedCount sessions removed\n";
file_put_contents(__DIR__ . '/session_cleanup.log', $logEntry, FILE_APPEND);

echo "📝 Log written to session_cleanup.log\n";
?>