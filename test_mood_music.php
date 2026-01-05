<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Test Mood Music</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .mood-filter-btn {
            padding: 10px 15px;
            margin: 5px;
            border: 2px solid #f25c5c;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .mood-filter-btn:hover {
            background: rgba(242, 92, 92, 0.1);
        }
        .mood-filter-btn.active {
            background: #f25c5c;
            color: white;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }
        #audio-controls {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Test Mood Music Functionality</h1>
    
    <div class="test-section">
        <h3>Available Moods with Music:</h3>
        
        <?php
        require 'config/database.php';
        $db = new Database();
        $conn = $db->getConnection();

        // Get all available moods for filtering (including music data)
        $moods_stmt = $conn->query("SELECT id, name, emoji, music_file, music_name, music_duration FROM moods WHERE music_file IS NOT NULL AND music_file != '' ORDER BY name LIMIT 10");
        $available_moods = $moods_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($available_moods as $mood): ?>
            <button class="mood-filter-btn" 
                    onclick="filterByMood('<?= $mood['id'] ?>')" 
                    data-mood="<?= $mood['id'] ?>"
                    data-music="<?= htmlspecialchars($mood['music_file'] ?? '') ?>"
                    data-music-name="<?= htmlspecialchars($mood['music_name'] ?? '') ?>">
                <?= $mood['emoji'] ?> <?= htmlspecialchars($mood['name']) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="test-section">
        <h3>Audio Controls:</h3>
        <div id="audio-controls">
            <button onclick="stopMusic()">🛑 Stop Music</button>
            <button onclick="testVolume()">🔊 Test Volume</button>
            <div id="current-music">No music playing</div>
        </div>
    </div>

    <div class="test-section">
        <h3>Debug Info:</h3>
        <div id="debug-info">
            <div>Console output will show here when you click mood buttons</div>
        </div>
    </div>

    <!-- Mood Music Player -->
    <audio id="moodMusicPlayer" loop controls style="width: 100%; margin-top: 20px;">
        <source src="" type="audio/wav">
        <source src="" type="audio/mpeg">
        Browser tidak mendukung audio.
    </audio>

    <script>
        // Mood Music Player
        const moodMusicPlayer = document.getElementById('moodMusicPlayer');
        const debugInfo = document.getElementById('debug-info');
        const currentMusicDiv = document.getElementById('current-music');

        // Function to play mood music
        function playMoodMusic(musicFile, musicName) {
            if (musicFile && musicFile.trim() !== '') {
                // Stop current music
                moodMusicPlayer.pause();
                moodMusicPlayer.currentTime = 0;
                
                // Set new music source with correct path (no /gamon/ prefix)
                moodMusicPlayer.innerHTML = `
                    <source src="uploads/music/moods/${musicFile}" type="audio/wav">
                    <source src="uploads/music/moods/${musicFile}" type="audio/mpeg">
                `;
                
                moodMusicPlayer.load();
                moodMusicPlayer.volume = 0.3; // Set volume to 30%
                
                const logMsg = `Playing mood music: ${musicName} - File: ${musicFile}`;
                console.log(logMsg);
                debugInfo.innerHTML += `<div>✅ ${logMsg}</div>`;
                currentMusicDiv.textContent = `Now playing: ${musicName} (${musicFile})`;
                
                moodMusicPlayer.play().then(() => {
                    debugInfo.innerHTML += `<div>🎵 Music started playing successfully</div>`;
                }).catch(e => {
                    const errorMsg = `❌ Could not play music: ${e.message}`;
                    console.error(errorMsg);
                    debugInfo.innerHTML += `<div>${errorMsg}</div>`;
                    debugInfo.innerHTML += `<div>🔍 Tried to play: uploads/music/moods/${musicFile}</div>`;
                });
            } else {
                // Stop music if no file
                moodMusicPlayer.pause();
                debugInfo.innerHTML += `<div>⚠️ No music file specified</div>`;
                currentMusicDiv.textContent = 'No music playing';
            }
        }

        function filterByMood(moodId) {
            // Update button states
            document.querySelectorAll('.mood-filter-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-mood="${moodId}"]`).classList.add('active');
            
            const button = document.querySelector(`[data-mood="${moodId}"]`);
            const musicFile = button.getAttribute('data-music');
            const musicName = button.getAttribute('data-music-name');
            
            debugInfo.innerHTML += `<div>🎯 Mood selected: ${moodId} - Music: ${musicFile}</div>`;
            
            // Play music when mood filter is clicked
            if (musicFile && musicFile.trim() !== '') {
                playMoodMusic(musicFile, musicName);
            } else {
                debugInfo.innerHTML += `<div>❌ No music file for this mood</div>`;
            }
        }

        function stopMusic() {
            moodMusicPlayer.pause();
            moodMusicPlayer.currentTime = 0;
            currentMusicDiv.textContent = 'Music stopped';
            debugInfo.innerHTML += `<div>🛑 Music stopped manually</div>`;
        }

        function testVolume() {
            debugInfo.innerHTML += `<div>🔊 Current volume: ${moodMusicPlayer.volume * 100}%</div>`;
        }

        // Clear debug on page load
        document.addEventListener('DOMContentLoaded', function() {
            debugInfo.innerHTML = '<div>🚀 Page loaded, ready to test music</div>';
        });
    </script>
</body>
</html>