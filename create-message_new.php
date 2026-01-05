<?php
require_once 'controllers/AuthController.php';
require_once 'controllers/MessageController.php';
require_once 'controllers/MoodController.php';
require_once 'controllers/MediaController.php';

$auth = new AuthController();
$auth->requireLogin();

$messageController = new MessageController();
try {
    $moodController = new MoodController();
} catch (Exception $e) {
    error_log("MoodController creation failed: " . $e->getMessage());
    $moodController = null;
}
$mediaController = new MediaController();

$message = '';
$messageType = '';

// Get all moods for dropdown
if ($moodController !== null) {
    try {
        $moods_result = $moodController->getAllMoods();
        $moods = $moods_result['status'] ? $moods_result['data'] : [];
    } catch (Exception $e) {
        error_log("Mood loading error: " . $e->getMessage());
        $moods = [];
    }
} else {
    $moods = [];
}

// Always use fallback data if no moods loaded
if (empty($moods)) {
    $moods = [
        ['id' => 1, 'emoji' => '😊', 'name' => 'Bahagia', 'description' => 'Perasaan senang'],
        ['id' => 2, 'emoji' => '😐', 'name' => 'Biasa Saja', 'description' => 'Perasaan netral'],
        ['id' => 3, 'emoji' => '😔', 'name' => 'Sedih', 'description' => 'Perasaan murung'],
        ['id' => 4, 'emoji' => '😡', 'name' => 'Marah', 'description' => 'Perasaan kesal'],
        ['id' => 5, 'emoji' => '🤩', 'name' => 'Bersemangat', 'description' => 'Perasaan antusias']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_email = trim($_POST['receiver_email'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $mood_id = (int)($_POST['mood_id'] ?? 1);
    $scheduled_open_at = $_POST['scheduled_open_at'] ?? '';
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    $visibility = $_POST['visibility'] ?? 'private';

    // Validate inputs
    if (empty($receiver_email) || empty($title) || empty($content) || empty($scheduled_open_at)) {
        $message = 'All fields are required.';
        $messageType = 'error';
    } elseif (!filter_var($receiver_email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email address.';
        $messageType = 'error';
    } else {
        // Create message
        $result = $messageController->createMessage(
            $_SESSION['user_id'],
            $receiver_email,
            $title,
            $content,
            $mood_id,
            $scheduled_open_at,
            $is_anonymous,
            $visibility
        );

        if ($result['status']) {
            $message_id = $result['message_id'];
            $success_message = $result['message'];
            
            // Handle file uploads if any
            if (isset($_FILES['media']) && !empty($_FILES['media']['name'][0])) {
                $uploadResult = $mediaController->uploadMultipleFiles($message_id, $_FILES['media'], $_SESSION['user_id']);
                
                if ($uploadResult['status'] && $uploadResult['success_count'] > 0) {
                    $success_message .= " {$uploadResult['success_count']} file(s) uploaded successfully.";
                } else {
                    $success_message .= " But file upload failed.";
                }
            }
            
            // Set success notification in session
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => $success_message
            ];
            
            // Clear form data and redirect to Kapsul Saya
            $receiver_email = $title = $content = '';
            $scheduled_open_at = '';
            $is_anonymous = 0;
            
            header('Location: view-message.php?created=1');
            exit;
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    }
}

// Get minimum datetime (current time + 1 minute)
$min_datetime = date('Y-m-d\TH:i', strtotime('+1 minute'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Time Capsule - GAMON</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .file-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .file-upload-area:hover {
            border-color: #6366f1;
        }
        .file-upload-area.dragover {
            border-color: #4f46e5;
            background-color: #f0f9ff;
        }
        .mood-dropdown {
            position: relative;
            width: 100%;
        }

        .dropdown-button {
            width: 100%;
            padding: 16px 20px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .dropdown-button:hover {
            border-color: #6366f1;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .dropdown-button.active {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .dropdown-content {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            margin-top: 8px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            max-height: 250px;
            overflow-y: auto;
        }

        .dropdown-content.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .mood-item {
            padding: 12px 16px;
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
            padding-left: 20px;
        }

        .mood-emoji {
            font-size: 20px;
            transition: transform 0.2s ease;
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
            gap: 10px;
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
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-2xl font-bold text-indigo-600">GAMON</a>
                    <span class="ml-2 text-sm text-gray-500">Create Time Capsule</span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-700 hover:text-indigo-600">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Create a Time Capsule</h1>
            <p class="mt-2 text-gray-600">Write a message that will be delivered to someone in the future.</p>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
                <div class="flex items-center">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="bg-white rounded-lg shadow-sm border p-8">
            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                <!-- Recipient -->
                <div>
                    <label for="receiver_email" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-1"></i> Recipient Email
                    </label>
                    <input type="email" id="receiver_email" name="receiver_email" required
                           value="<?php echo htmlspecialchars($receiver_email ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="friend@example.com">
                    <p class="mt-1 text-sm text-gray-500">Who should receive this time capsule?</p>
                </div>

                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-heading mr-1"></i> Capsule Title
                    </label>
                    <input type="text" id="title" name="title" required maxlength="200"
                           value="<?php echo htmlspecialchars($title ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="A meaningful title for your time capsule...">
                </div>

                <!-- Mood Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="fas fa-heart mr-1"></i> Pilih Mood Anda
                    </label>
                    <div class="mood-dropdown">
                        <div class="dropdown-button" id="moodDropdownButton">
                            <div id="selectedMoodDisplay">
                                <span class="placeholder-text">Bagaimana perasaan Anda hari ini?</span>
                            </div>
                            <i class="fas fa-chevron-down chevron"></i>
                        </div>

                        <div class="dropdown-content" id="moodDropdownContent">
                            <?php foreach ($moods as $mood): ?>
                                <div class="mood-item" 
                                     data-mood-id="<?php echo $mood['id']; ?>"
                                     data-emoji="<?php echo $mood['emoji']; ?>"
                                     data-text="<?php echo $mood['name']; ?>">
                                    <span class="mood-emoji"><?php echo $mood['emoji']; ?></span>
                                    <span class="mood-text"><?php echo $mood['name']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Hidden input to store selected mood -->
                        <input type="hidden" name="mood_id" id="selectedMoodId" value="<?php echo $mood_id ?? 1; ?>">
                    </div>
                </div>

                <!-- Content -->
                <div>
                    <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-comment mr-1"></i> Your Message
                    </label>
                    <textarea id="content" name="content" rows="8" required
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                              placeholder="Write your message here... What do you want to tell them in the future?"><?php echo htmlspecialchars($content ?? ''); ?></textarea>
                    <p class="mt-1 text-sm text-gray-500">Express your thoughts, feelings, hopes, or memories.</p>
                </div>

                <!-- File Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-paperclip mr-1"></i> Attachments (Optional)
                    </label>
                    <div class="file-upload-area" id="fileUploadArea">
                        <input type="file" id="media" name="media[]" multiple 
                               accept="image/*,video/*,audio/*"
                               class="hidden">
                        <div class="upload-content">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                            <p class="text-lg font-medium text-gray-700 mb-2">Drop files here or click to upload</p>
                            <p class="text-sm text-gray-500">Support: Images, Videos, Audio (Max 10MB each)</p>
                        </div>
                    </div>
                    <div id="fileList" class="mt-3 space-y-2"></div>
                </div>

                <!-- Schedule -->
                <div>
                    <label for="scheduled_open_at" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-clock mr-1"></i> When Should This Open?
                    </label>
                    <input type="datetime-local" id="scheduled_open_at" name="scheduled_open_at" required
                           min="<?php echo $min_datetime; ?>"
                           value="<?php echo $scheduled_open_at ?? ''; ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="mt-1 text-sm text-gray-500">Choose a date and time in the future when the recipient can open this capsule.</p>
                </div>

                <!-- Options -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Privacy Options</h3>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1"
                               <?php echo ($is_anonymous ?? 0) ? 'checked' : ''; ?>
                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="is_anonymous" class="ml-2 text-sm text-gray-700">
                            Send anonymously (hide your identity from recipient)
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Visibility</label>
                        <select name="visibility" class="px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="private" <?php echo ($visibility ?? 'private') === 'private' ? 'selected' : ''; ?>>Private (only between you and recipient)</option>
                            <option value="shared" <?php echo ($visibility ?? 'private') === 'shared' ? 'selected' : ''; ?>>Shared (visible to others - future feature)</option>
                        </select>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <a href="dashboard.php" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors">
                        <i class="fas fa-paper-plane mr-2"></i>Create Time Capsule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mood selection
        document.querySelectorAll('.mood-option').forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            
            // Set initial state
            if (radio.checked) {
                option.classList.add('selected');
            }
            
            option.addEventListener('click', () => {
                document.querySelectorAll('.mood-option').forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                radio.checked = true;
            });
        });

        // File upload handling
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('media');
        const fileList = document.getElementById('fileList');

        fileUploadArea.addEventListener('click', () => fileInput.click());

        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            displayFileList();
        });

        fileInput.addEventListener('change', displayFileList);

        function displayFileList() {
            fileList.innerHTML = '';
            Array.from(fileInput.files).forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between bg-gray-50 p-3 rounded-md border';
                div.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-file text-gray-400"></i>
                        <div>
                            <div class="text-sm font-medium">${file.name}</div>
                            <div class="text-xs text-gray-500">${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                        </div>
                    </div>
                    <button type="button" class="text-red-500 hover:text-red-700" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                fileList.appendChild(div);
            });
        }

        function removeFile(index) {
            const dt = new DataTransfer();
            const files = Array.from(fileInput.files);
            files.splice(index, 1);
            files.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
            displayFileList();
        }

        // Set minimum datetime to current time + 1 minute
        const now = new Date();
        now.setMinutes(now.getMinutes() + 1);
        const minDateTime = now.toISOString().slice(0, 16);
        document.getElementById('scheduled_open_at').min = minDateTime;

        // Mood Dropdown Functionality
        const moodDropdownButton = document.getElementById('moodDropdownButton');
        const moodDropdownContent = document.getElementById('moodDropdownContent');
        const selectedMoodDisplay = document.getElementById('selectedMoodDisplay');
        const selectedMoodId = document.getElementById('selectedMoodId');
        const moodItems = document.querySelectorAll('.mood-item');

        // Toggle dropdown
        moodDropdownButton.addEventListener('click', function(e) {
            e.preventDefault();
            const isOpen = moodDropdownContent.classList.contains('show');
            
            if (isOpen) {
                moodDropdownContent.classList.remove('show');
                moodDropdownButton.classList.remove('active');
            } else {
                moodDropdownContent.classList.add('show');
                moodDropdownButton.classList.add('active');
            }
        });

        // Handle mood selection
        moodItems.forEach(item => {
            item.addEventListener('click', function() {
                const emoji = this.dataset.emoji;
                const text = this.dataset.text;
                const moodId = this.dataset.moodId;

                // Update selected display
                selectedMoodDisplay.innerHTML = `
                    <div class="selected-mood">
                        <span style="font-size: 18px;">${emoji}</span>
                        <span>${text}</span>
                    </div>
                `;

                // Update hidden input
                selectedMoodId.value = moodId;

                // Close dropdown
                moodDropdownContent.classList.remove('show');
                moodDropdownButton.classList.remove('active');
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!moodDropdownButton.contains(event.target) && !moodDropdownContent.contains(event.target)) {
                moodDropdownContent.classList.remove('show');
                moodDropdownButton.classList.remove('active');
            }
        });

        // Prevent dropdown from closing when clicking inside content
        moodDropdownContent.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        // Set initial mood if exists
        if (selectedMoodId.value) {
            const initialMoodItem = document.querySelector(`[data-mood-id="${selectedMoodId.value}"]`);
            if (initialMoodItem) {
                const emoji = initialMoodItem.dataset.emoji;
                const text = initialMoodItem.dataset.text;
                selectedMoodDisplay.innerHTML = `
                    <div class="selected-mood">
                        <span style="font-size: 18px;">${emoji}</span>
                        <span>${text}</span>
                    </div>
                `;
            }
        }
    </script>
</body>
</html>