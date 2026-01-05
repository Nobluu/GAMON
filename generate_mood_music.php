<?php
require_once 'config/database.php';

// Auto Music Generator untuk Mood GAMON
// Script ini akan generate file musik sederhana untuk setiap mood

echo "🎵 GAMON Mood Music Auto Generator\n";
echo "==================================\n\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get all moods from database
    $stmt = $conn->query("SELECT id, name, emoji, music_file FROM moods ORDER BY id");
    $moods = $stmt->fetchAll();
    
    echo "📋 Found " . count($moods) . " moods in database\n\n";
    
    // Music characteristics for different mood types
    $moodMusicData = [
        // Happy/Positive moods
        'bahagia' => ['key' => 'C', 'tempo' => 120, 'scale' => 'major', 'baseFreq' => 261.63],
        'senang' => ['key' => 'G', 'tempo' => 140, 'scale' => 'major', 'baseFreq' => 392.00],
        'antusias' => ['key' => 'D', 'tempo' => 160, 'scale' => 'major', 'baseFreq' => 293.66],
        'bersemangat' => ['key' => 'E', 'tempo' => 150, 'scale' => 'major', 'baseFreq' => 329.63],
        'bersyukur' => ['key' => 'F', 'tempo' => 100, 'scale' => 'major', 'baseFreq' => 349.23],
        
        // Love/Romantic moods  
        'cinta' => ['key' => 'A', 'tempo' => 80, 'scale' => 'major', 'baseFreq' => 440.00],
        'romantic' => ['key' => 'F', 'tempo' => 75, 'scale' => 'major', 'baseFreq' => 349.23],
        'rindu' => ['key' => 'Am', 'tempo' => 70, 'scale' => 'minor', 'baseFreq' => 220.00],
        
        // Calm/Peaceful moods
        'tenang' => ['key' => 'C', 'tempo' => 70, 'scale' => 'major', 'baseFreq' => 261.63],
        'santai' => ['key' => 'G', 'tempo' => 80, 'scale' => 'major', 'baseFreq' => 392.00],
        'lega' => ['key' => 'C', 'tempo' => 90, 'scale' => 'major', 'baseFreq' => 261.63],
        'peaceful' => ['key' => 'F', 'tempo' => 65, 'scale' => 'major', 'baseFreq' => 349.23],
        
        // Sad/Melancholic moods
        'sedih' => ['key' => 'Am', 'tempo' => 60, 'scale' => 'minor', 'baseFreq' => 220.00],
        'kecewa' => ['key' => 'Dm', 'tempo' => 65, 'scale' => 'minor', 'baseFreq' => 293.66],
        'cemas' => ['key' => 'F#m', 'tempo' => 95, 'scale' => 'minor', 'baseFreq' => 369.99],
        'takut' => ['key' => 'Bm', 'tempo' => 90, 'scale' => 'minor', 'baseFreq' => 246.94],
        
        // Nostalgic/Reflective moods
        'nostalgia' => ['key' => 'Em', 'tempo' => 85, 'scale' => 'minor', 'baseFreq' => 329.63],
        'reflektif' => ['key' => 'Am', 'tempo' => 75, 'scale' => 'minor', 'baseFreq' => 220.00],
        'bermimpi' => ['key' => 'C', 'tempo' => 70, 'scale' => 'major', 'baseFreq' => 261.63],
        
        // Default untuk mood yang tidak ada mapping khusus
        'default' => ['key' => 'C', 'tempo' => 100, 'scale' => 'major', 'baseFreq' => 261.63]
    ];
    
    $generatedCount = 0;
    $totalMoods = count($moods);
    
    foreach ($moods as $mood) {
        $moodKey = strtolower(str_replace([' ', '.mp3'], ['_', ''], $mood['music_file']));
        $musicData = $moodMusicData[$moodKey] ?? $moodMusicData['default'];
        
        echo "🎼 Generating: {$mood['emoji']} {$mood['name']} ({$mood['music_file']})... ";
        
        // Generate simple melody data (basic implementation)
        $melodyData = generateSimpleMelody($musicData, 30); // 30 seconds duration
        
        // Create simple WAV file data
        $wavData = createSimpleWav($melodyData, $musicData);
        
        // Save to file
        $filePath = "uploads/music/moods/" . $mood['music_file'];
        
        // Convert to actual MP3 would need external library, for now save as WAV
        $wavPath = str_replace('.mp3', '.wav', $filePath);
        
        if (file_put_contents($wavPath, $wavData)) {
            echo "✅ Generated ($wavPath)\n";
            $generatedCount++;
        } else {
            echo "❌ Failed\n";
        }
        
        // Show progress
        $progress = round(($generatedCount / $totalMoods) * 100, 1);
        echo "   Progress: $progress% ($generatedCount/$totalMoods)\n";
    }
    
    echo "\n🎉 Music generation completed!\n";
    echo "✅ Generated: $generatedCount files\n";
    echo "📁 Location: uploads/music/moods/\n";
    echo "🔄 Format: WAV (can be converted to MP3 using external tools)\n";
    echo "\n💡 Note: Files are basic melodies. For better quality, consider using:\n";
    echo "   - FFmpeg for MP3 conversion\n";
    echo "   - Professional audio software for enhancement\n";
    echo "   - Free music libraries for better quality tracks\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

function generateSimpleMelody($musicData, $duration) {
    $sampleRate = 44100;
    $samples = $sampleRate * $duration;
    $data = [];
    
    // Basic scale frequencies based on key
    $scales = [
        'major' => [1.0, 9/8, 5/4, 4/3, 3/2, 5/3, 15/8, 2.0],
        'minor' => [1.0, 9/8, 6/5, 4/3, 3/2, 8/5, 9/5, 2.0]
    ];
    
    $scale = $scales[$musicData['scale']] ?? $scales['major'];
    $baseFreq = $musicData['baseFreq'];
    $tempo = $musicData['tempo'];
    
    // Note duration in samples
    $noteDuration = $sampleRate * 60 / $tempo; // quarter note duration
    
    for ($i = 0; $i < $samples; $i++) {
        $time = $i / $sampleRate;
        
        // Determine which note to play
        $noteIndex = floor($time / ($noteDuration / $sampleRate)) % count($scale);
        $frequency = $baseFreq * $scale[$noteIndex];
        
        // Generate simple sine wave with envelope
        $amplitude = 0.3;
        $noteTime = fmod($time, $noteDuration / $sampleRate);
        $envelope = min(1.0, $noteTime * 10) * max(0.0, 1.0 - $noteTime * 2); // Simple ADSR
        
        $sample = $amplitude * sin(2 * M_PI * $frequency * $time) * $envelope;
        
        // Add some harmony
        $harmonyFreq = $baseFreq * $scale[($noteIndex + 2) % count($scale)];
        $sample += 0.2 * sin(2 * M_PI * $harmonyFreq * $time) * $envelope;
        
        $data[] = $sample;
    }
    
    return $data;
}

function createSimpleWav($data, $musicData) {
    $sampleRate = 44100;
    $bitsPerSample = 16;
    $channels = 1;
    $dataSize = count($data) * 2; // 16-bit = 2 bytes
    $fileSize = 36 + $dataSize;
    
    // WAV header
    $header = pack('V', 0x46464952); // 'RIFF'
    $header .= pack('V', $fileSize);
    $header .= pack('V', 0x45564157); // 'WAVE'
    $header .= pack('V', 0x20746d66); // 'fmt '
    $header .= pack('V', 16); // PCM chunk size
    $header .= pack('v', 1); // Audio format (PCM)
    $header .= pack('v', $channels);
    $header .= pack('V', $sampleRate);
    $header .= pack('V', $sampleRate * $channels * $bitsPerSample / 8);
    $header .= pack('v', $channels * $bitsPerSample / 8);
    $header .= pack('v', $bitsPerSample);
    $header .= pack('V', 0x61746164); // 'data'
    $header .= pack('V', $dataSize);
    
    // Convert float samples to 16-bit integers
    $binaryData = '';
    foreach ($data as $sample) {
        $intSample = (int)($sample * 32767);
        $intSample = max(-32768, min(32767, $intSample));
        $binaryData .= pack('s', $intSample);
    }
    
    return $header . $binaryData;
}

?>