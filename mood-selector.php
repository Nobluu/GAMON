<?php
require_once 'controllers/Auth.php';
require_once 'controllers/MoodController.php';

$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

$moodController = new MoodController();
$user = $auth->getCurrentUser();

// Get all moods for dropdown
$moods_result = $moodController->getAllMoods();
$moods = $moods_result['status'] ? $moods_result['data'] : [];

// Handle mood selection
$selectedMood = null;
if (isset($_POST['selected_mood'])) {
    $selectedMood = $_POST['selected_mood'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mood Selector - GAMON</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
        }

        .logo-img {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        .mood-dropdown {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .dropdown-button {
            width: 100%;
            padding: 16px 20px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .dropdown-button:hover {
            border-color: #667eea;
            box-shadow: 0 8px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .dropdown-button.active {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .dropdown-content {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            margin-top: 8px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
        }

        .dropdown-content.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .mood-item {
            padding: 16px 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #f3f4f6;
        }

        .mood-item:last-child {
            border-bottom: none;
        }

        .mood-item:hover {
            background: #f8fafc;
            padding-left: 24px;
        }

        .mood-emoji {
            font-size: 24px;
            transition: transform 0.2s ease;
            display: block;
            font-family: 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif;
            line-height: 1;
        }

        .mood-item:hover .mood-emoji {
            transform: scale(1.2);
        }

        .mood-text {
            font-weight: 500;
            color: #374151;
        }

        .placeholder-text {
            color: #9ca3af;
            font-weight: normal;
        }

        .selected-mood {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            color: #374151;
        }

        .chevron {
            transition: transform 0.3s ease;
            color: #9ca3af;
        }

        .dropdown-button.active .chevron {
            transform: rotate(180deg);
        }

        .result-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 24px;
            margin-top: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="container rounded-2xl p-8 w-full max-w-md">
            <!-- Header -->
            <div class="text-center mb-8">
                <img src="logo_gamon.png" alt="GAMON" class="logo-img mx-auto mb-4">
                <h1 class="text-2xl font-bold text-white mb-2">Mood Selector</h1>
                <p class="text-white opacity-80">Pilih mood Anda hari ini</p>
            </div>

            <!-- Mood Dropdown -->
            <div class="mood-dropdown">
                <div class="dropdown-button" id="dropdownButton">
                    <div id="selectedDisplay">
                        <span class="placeholder-text">Bagaimana perasaan Anda hari ini?</span>
                    </div>
                    <i class="fas fa-chevron-down chevron"></i>
                </div>

                <div class="dropdown-content" id="dropdownContent">
                    <?php if (!empty($moods)): ?>
                        <?php foreach ($moods as $mood): ?>
                            <div class="mood-item" data-mood-id="<?= $mood['id'] ?>" data-emoji="<?= $mood['emoji'] ?>" data-text="<?= htmlspecialchars($mood['name']) ?>">
                                <span class="mood-emoji"><?= $mood['emoji'] ?></span>
                                <span class="mood-text"><?= htmlspecialchars($mood['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="mood-item" data-mood-id="" data-emoji="😐" data-text="Tidak ada mood tersedia">
                            <span class="mood-emoji">😐</span>
                            <span class="mood-text">Tidak ada mood tersedia</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Result Display -->
            <div class="result-card hidden" id="resultCard">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Mood Terpilih:</h3>
                <div class="selected-mood text-xl" id="resultDisplay">
                    <!-- Selected mood will appear here -->
                </div>
            </div>

            <!-- Navigation -->
            <div class="text-center mt-8">
                <a href="dashboard.php" class="inline-flex items-center px-6 py-3 bg-white bg-opacity-20 text-white rounded-full hover:bg-opacity-30 transition-all duration-300">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownButton = document.getElementById('dropdownButton');
            const dropdownContent = document.getElementById('dropdownContent');
            const selectedDisplay = document.getElementById('selectedDisplay');
            const resultCard = document.getElementById('resultCard');
            const resultDisplay = document.getElementById('resultDisplay');
            const moodItems = document.querySelectorAll('.mood-item');

            // Toggle dropdown
            dropdownButton.addEventListener('click', function() {
                const isOpen = dropdownContent.classList.contains('show');
                
                if (isOpen) {
                    dropdownContent.classList.remove('show');
                    dropdownButton.classList.remove('active');
                } else {
                    dropdownContent.classList.add('show');
                    dropdownButton.classList.add('active');
                }
            });

            // Handle mood selection
            moodItems.forEach(item => {
                item.addEventListener('click', function() {
                    const emoji = this.dataset.emoji;
                    const text = this.dataset.text;
                    const moodId = this.dataset.moodId;

                    // Update selected display
                    selectedDisplay.innerHTML = `
                        <div class="selected-mood">
                            <span style="font-size: 20px;">${emoji}</span>
                            <span>${text}</span>
                        </div>
                    `;

                    // Update result display
                    resultDisplay.innerHTML = `
                        <span style="font-size: 32px;">${emoji}</span>
                        <span>${text}</span>
                    `;

                    // Show result card
                    resultCard.classList.remove('hidden');

                    // Close dropdown
                    dropdownContent.classList.remove('show');
                    dropdownButton.classList.remove('active');

                    // Add some animation to the result
                    resultCard.style.transform = 'scale(0.9)';
                    resultCard.style.opacity = '0';
                    setTimeout(() => {
                        resultCard.style.transform = 'scale(1)';
                        resultCard.style.opacity = '1';
                        resultCard.style.transition = 'all 0.3s ease';
                    }, 50);
                });
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!dropdownButton.contains(event.target) && !dropdownContent.contains(event.target)) {
                    dropdownContent.classList.remove('show');
                    dropdownButton.classList.remove('active');
                }
            });

            // Prevent dropdown from closing when clicking inside content
            dropdownContent.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        });
    </script>
</body>
</html>