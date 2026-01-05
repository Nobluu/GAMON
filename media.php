<?php
require_once 'controllers/AuthController.php';
require_once 'controllers/MediaController.php';

// Authenticate user
$auth = new AuthController();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

// Get media ID from URL
$media_id = $_GET['id'] ?? null;

if (!$media_id || !is_numeric($media_id)) {
    http_response_code(400);
    echo "Invalid media ID";
    exit;
}

// Initialize media controller and serve file
$mediaController = new MediaController();
$mediaController->serveFile($media_id, $_SESSION['user_id']);
?>