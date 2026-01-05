<?php
// Run this script via CLI: php cron/sendNotifications.php

require_once __DIR__ . '/../controllers/NotificationController.php';

$notifier = new NotificationController();
$notifier->sendPendingNotifications();
?>
