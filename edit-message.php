<?php
require_once 'controllers/Auth.php';
require_once 'controllers/MessageController.php';
require_once 'controllers/Capsule.php';

$auth = new Auth();
$auth->requireLogin();

$user = $auth->getCurrentUser();
$messageController = new MessageController();
$capsuleController = new Capsule();

// Get message ID from URL
$message_id = $_GET['id'] ?? null;

if (!$message_id) {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'ID pesan tidak valid.'];
    header('Location: view-message.php');
    exit;
}

// Get message details
$message = $messageController->getMessageById($message_id, $user['id']);

if (!$message) {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'Pesan tidak ditemukan.'];
    header('Location: view-message.php');
    exit;
}

// Check if user can edit
if (!$messageController->canModifyMessage($message_id, $user['id'])) {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'Anda tidak dapat mengedit pesan ini.'];
    header('Location: view-message.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $mood_id = $_POST['mood_id'] ?? null;
    $scheduled_open_at = $_POST['scheduled_open_at'] ?? '';

    // Validate inputs
    if (empty($title) || empty($content) || empty($scheduled_open_at)) {
        $error = 'Semua field harus diisi.';
    } else {
        $result = $messageController->updateMessage($message_id, $user['id'], $title, $content, $mood_id, $scheduled_open_at);
        
        if ($result['status']) {
            $_SESSION['notification'] = ['type' => 'success', 'message' => $result['message']];
            header('Location: view-message.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Get available moods
$moods = $capsuleController->getAllMoods();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pesan - Capsule</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .title {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #6b7280;
            font-size: 1rem;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(242, 92, 92, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid rgba(242, 92, 92, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #f25c5c;
            background: rgba(255, 255, 255, 0.95);
        }

        .form-textarea {
            min-height: 150px;
            resize: vertical;
        }

        .mood-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .mood-option {
            position: relative;
        }

        .mood-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .mood-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem 0.5rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.6);
            border: 2px solid rgba(242, 92, 92, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .mood-input:checked + .mood-label {
            background: rgba(242, 92, 92, 0.1);
            border-color: #f25c5c;
            transform: scale(1.05);
        }

        .mood-emoji {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .mood-name {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid #fecaca;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(242, 92, 92, 0.3);
        }

        .btn-secondary {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
            border: 2px solid rgba(107, 114, 128, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(107, 114, 128, 0.2);
        }

        .current-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .current-info h3 {
            color: #1e40af;
            margin-bottom: 0.5rem;
        }

        .current-info p {
            color: #1e40af;
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">Edit Pesan</h1>
            <p class="subtitle">Perbarui pesan Anda sebelum waktu terbuka</p>
        </div>

        <div class="current-info">
            <h3>📝 Informasi Pesan Saat Ini</h3>
            <p><strong>Judul:</strong> <?= htmlspecialchars($message['title']) ?></p>
            <p><strong>Penerima:</strong> <?= htmlspecialchars($message['receiver_name']) ?> (<?= htmlspecialchars($message['receiver_email']) ?>)</p>
            <p><strong>Waktu Buka:</strong> <?= date('d M Y, H:i', strtotime($message['scheduled_open_at'])) ?></p>
            <p><strong>Status:</strong> <?= ucfirst($message['status']) ?></p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="form-container">
            <div class="form-group">
                <label for="title" class="form-label">Judul Pesan *</label>
                <input type="text" 
                       id="title" 
                       name="title" 
                       class="form-input" 
                       value="<?= htmlspecialchars($message['title']) ?>"
                       placeholder="Judul pesan yang menarik..."
                       required>
            </div>

            <div class="form-group">
                <label for="content" class="form-label">Isi Pesan *</label>
                <textarea id="content" 
                          name="content" 
                          class="form-textarea" 
                          placeholder="Tulis pesan Anda di sini..."
                          required><?= htmlspecialchars($message['content']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="scheduled_open_at" class="form-label">Waktu Buka *</label>
                <input type="datetime-local" 
                       id="scheduled_open_at" 
                       name="scheduled_open_at" 
                       class="form-input"
                       value="<?= date('Y-m-d\TH:i', strtotime($message['scheduled_open_at'])) ?>"
                       required>
            </div>

            <div class="form-group">
                <label class="form-label">Mood</label>
                <div class="mood-container">
                    <?php foreach ($moods as $mood): ?>
                        <div class="mood-option">
                            <input type="radio" 
                                   id="mood_<?= $mood['id'] ?>" 
                                   name="mood_id" 
                                   value="<?= $mood['id'] ?>" 
                                   class="mood-input"
                                   <?= ($mood['id'] == $message['mood_id']) ? 'checked' : '' ?>>
                            <label for="mood_<?= $mood['id'] ?>" class="mood-label">
                                <span class="mood-emoji"><?= htmlspecialchars($mood['emoji']) ?></span>
                                <span class="mood-name"><?= htmlspecialchars($mood['name']) ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions">
                <a href="view-message.php" class="btn btn-secondary">
                    ← Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    💾 Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    <script>
        // Set minimum datetime to now
        document.addEventListener('DOMContentLoaded', function() {
            const datetimeInput = document.getElementById('scheduled_open_at');
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            datetimeInput.min = now.toISOString().slice(0, 16);
        });
    </script>
</body>
</html>