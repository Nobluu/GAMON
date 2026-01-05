<?php
require_once 'config/database.php';

// Enhanced Music Generator untuk Mood GAMON v2.0
// Script ini akan generate file musik dengan variasi melodi yang lebih unik

echo "🎵 GAMON Enhanced Mood Music Generator v2.0\n";
echo "=============================================\n\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get all moods from database
    $stmt = $conn->query("SELECT id, name, emoji, music_file FROM moods ORDER BY id");
    $moods = $stmt->fetchAll();
    
    echo "📋 Found " . count($moods) . " moods in database\n\n";
    
    // Enhanced music patterns for different mood types
    $moodPatterns = [
        // Happy/Energetic moods - Major scales with upbeat patterns
        'bahagia' => [
            'key' => 'C', 'tempo' => 120, 'scale' => 'major', 'baseFreq' => 261.63,
            'pattern' => [0,2,4,2,1,3,4,0], 'rhythm' => [1,1,2,1,1,1,2,2], 'instruments' => ['sine','square']
        ],
        'senang' => [
            'key' => 'G', 'tempo' => 140, 'scale' => 'major', 'baseFreq' => 392.00,
            'pattern' => [0,4,7,4,2,5,7,0], 'rhythm' => [2,1,1,1,2,1,1,4], 'instruments' => ['sine','triangle']
        ],
        'antusias' => [
            'key' => 'D', 'tempo' => 160, 'scale' => 'major', 'baseFreq' => 293.66,
            'pattern' => [0,2,4,7,4,2,5,0], 'rhythm' => [1,1,1,2,1,1,2,2], 'instruments' => ['square','sawtooth']
        ],
        'bersemangat' => [
            'key' => 'E', 'tempo' => 150, 'scale' => 'major', 'baseFreq' => 329.63,
            'pattern' => [0,3,5,7,5,3,6,0], 'rhythm' => [1,1,2,1,1,1,1,4], 'instruments' => ['sine','square']
        ],
        
        // Love/Romantic moods - Gentle melodies
        'cinta' => [
            'key' => 'F', 'tempo' => 80, 'scale' => 'major', 'baseFreq' => 349.23,
            'pattern' => [0,2,4,5,4,2,1,0], 'rhythm' => [4,2,2,4,2,2,2,4], 'instruments' => ['sine','triangle']
        ],
        'romantic' => [
            'key' => 'A', 'tempo' => 75, 'scale' => 'major', 'baseFreq' => 440.00,
            'pattern' => [0,4,2,5,4,1,2,0], 'rhythm' => [3,2,2,3,2,2,2,4], 'instruments' => ['triangle','sine']
        ],
        'rindu' => [
            'key' => 'Am', 'tempo' => 70, 'scale' => 'minor', 'baseFreq' => 220.00,
            'pattern' => [0,2,5,7,5,2,3,0], 'rhythm' => [4,3,2,3,2,3,2,4], 'instruments' => ['sine']
        ],
        
        // Calm/Peaceful moods - Slow and gentle
        'tenang' => [
            'key' => 'C', 'tempo' => 70, 'scale' => 'major', 'baseFreq' => 261.63,
            'pattern' => [0,1,2,4,2,1,0,0], 'rhythm' => [4,4,4,4,4,4,4,4], 'instruments' => ['sine']
        ],
        'santai' => [
            'key' => 'G', 'tempo' => 80, 'scale' => 'major', 'baseFreq' => 392.00,
            'pattern' => [0,2,1,4,1,2,0,0], 'rhythm' => [3,3,3,3,3,3,3,3], 'instruments' => ['triangle']
        ],
        'lega' => [
            'key' => 'F', 'tempo' => 85, 'scale' => 'major', 'baseFreq' => 349.23,
            'pattern' => [0,1,3,2,4,2,1,0], 'rhythm' => [2,2,4,2,2,4,2,4], 'instruments' => ['sine','triangle']
        ],
        
        // Sad/Melancholic moods - Minor scales with slow patterns
        'sedih' => [
            'key' => 'Am', 'tempo' => 60, 'scale' => 'minor', 'baseFreq' => 220.00,
            'pattern' => [0,2,3,5,3,2,1,0], 'rhythm' => [4,2,4,4,4,2,2,8], 'instruments' => ['sine']
        ],
        'kecewa' => [
            'key' => 'Dm', 'tempo' => 65, 'scale' => 'minor', 'baseFreq' => 293.66,
            'pattern' => [0,1,3,2,5,2,1,0], 'rhythm' => [4,4,2,4,2,4,4,8], 'instruments' => ['triangle']
        ],
        'cemas' => [
            'key' => 'F#m', 'tempo' => 95, 'scale' => 'minor', 'baseFreq' => 369.99,
            'pattern' => [0,1,2,1,3,1,2,0], 'rhythm' => [1,1,1,1,2,1,1,4], 'instruments' => ['square']
        ],
        
        // Spiritual/Reflective moods
        'berdoa' => [
            'key' => 'F', 'tempo' => 70, 'scale' => 'major', 'baseFreq' => 349.23,
            'pattern' => [0,4,7,5,4,2,1,0], 'rhythm' => [6,2,4,4,2,4,2,8], 'instruments' => ['sine','triangle']
        ],
        'bersyukur' => [
            'key' => 'C', 'tempo' => 100, 'scale' => 'major', 'baseFreq' => 261.63,
            'pattern' => [0,2,4,7,4,5,2,0], 'rhythm' => [2,2,4,2,2,2,4,4], 'instruments' => ['sine','triangle']
        ],
        'berharap' => [
            'key' => 'G', 'tempo' => 90, 'scale' => 'major', 'baseFreq' => 392.00,
            'pattern' => [0,1,4,5,7,5,2,0], 'rhythm' => [3,2,2,3,2,2,3,4], 'instruments' => ['triangle','sine']
        ],
        
        // Energetic/Adventure moods
        'adventurous' => [
            'key' => 'E', 'tempo' => 130, 'scale' => 'major', 'baseFreq' => 329.63,
            'pattern' => [0,3,7,5,2,6,4,0], 'rhythm' => [1,2,1,1,2,1,2,2], 'instruments' => ['square','sawtooth']
        ],
        'accomplished' => [
            'key' => 'D', 'tempo' => 120, 'scale' => 'major', 'baseFreq' => 293.66,
            'pattern' => [0,4,7,9,7,4,2,0], 'rhythm' => [2,1,2,2,2,1,2,4], 'instruments' => ['sine','square']
        ],
        'bangga' => [
            'key' => 'C', 'tempo' => 110, 'scale' => 'major', 'baseFreq' => 261.63,
            'pattern' => [0,2,5,7,9,7,4,0], 'rhythm' => [2,2,2,2,2,2,2,4], 'instruments' => ['sine','triangle']
        ],
        
        // Nostalgic/Dream moods
        'nostalgia' => [
            'key' => 'Em', 'tempo' => 85, 'scale' => 'minor', 'baseFreq' => 329.63,
            'pattern' => [0,3,2,5,7,5,2,0], 'rhythm' => [4,2,3,2,3,2,3,4], 'instruments' => ['sine']
        ],
        'bermimpi' => [
            'key' => 'Am', 'tempo' => 70, 'scale' => 'minor', 'baseFreq' => 220.00,
            'pattern' => [0,2,5,3,7,3,2,0], 'rhythm' => [3,3,4,3,4,3,3,6], 'instruments' => ['triangle','sine']
        ],
        'reflektif' => [
            'key' => 'Dm', 'tempo' => 75, 'scale' => 'minor', 'baseFreq' => 293.66,
            'pattern' => [0,1,3,5,3,1,2,0], 'rhythm' => [4,3,3,4,3,3,3,6], 'instruments' => ['sine']
        ],
        
        // Default
        'default' => [
            'key' => 'C', 'tempo' => 100, 'scale' => 'major', 'baseFreq' => 261.63,
            'pattern' => [0,2,4,5,4,2,1,0], 'rhythm' => [2,2,2,2,2,2,2,4], 'instruments' => ['sine']
        ]
    ];
    
    $generatedCount = 0;
    $totalMoods = count($moods);
    
    foreach ($moods as $mood) {
        $moodKey = strtolower(str_replace([' ', '.mp3', '.wav'], ['_', '', ''], $mood['music_file']));
        $pattern = $moodPatterns[$moodKey] ?? $moodPatterns['default'];
        
        echo "🎼 Generating: {$mood['emoji']} {$mood['name']} ({$mood['music_file']})... ";
        
        // Generate enhanced melody with unique patterns
        $melodyData = generateEnhancedMelody($pattern, 30); // 30 seconds duration
        
        // Create WAV file
        $wavData = createEnhancedWav($melodyData);
        
        // Save to file
        $filePath = "uploads/music/moods/" . str_replace('.mp3', '.wav', $mood['music_file']);
        
        if (file_put_contents($filePath, $wavData)) {
            echo "✅ Generated\n";
            $generatedCount++;
        } else {
            echo "❌ Failed\n";
        }
    }
    
    echo "\n🎉 Enhanced music generation completed!\n";
    echo "✅ Generated: $generatedCount unique melodies\n";
    echo "📁 Location: uploads/music/moods/\n";
    echo "🎵 Each mood now has distinct musical characteristics!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

function generateEnhancedMelody($pattern, $duration) {
    $sampleRate = 44100;
    $samples = $sampleRate * $duration;
    $data = [];
    
    // Musical scales
    $scales = [
        'major' => [1.0, 9/8, 5/4, 4/3, 3/2, 5/3, 15/8, 2.0, 9/4, 5/2],
        'minor' => [1.0, 9/8, 6/5, 4/3, 3/2, 8/5, 9/5, 2.0, 9/4, 12/5]
    ];
    
    $scale = $scales[$pattern['scale']] ?? $scales['major'];
    $baseFreq = $pattern['baseFreq'];
    $tempo = $pattern['tempo'];
    $melody = $pattern['pattern'];
    $rhythms = $pattern['rhythm'];
    $instruments = $pattern['instruments'];
    
    // Beat duration in samples
    $beatDuration = $sampleRate * 60 / $tempo;
    
    // Calculate total pattern duration
    $patternBeats = array_sum($rhythms);
    $patternDuration = $patternBeats * $beatDuration / $sampleRate;
    
    for ($i = 0; $i < $samples; $i++) {
        $time = $i / $sampleRate;
        
        // Get position within the repeating pattern
        $patternTime = fmod($time, $patternDuration);
        
        // Find current note in pattern
        $beatPosition = 0;
        $currentNote = 0;
        $currentRhythm = 1;
        $noteStartTime = 0;
        
        for ($n = 0; $n < count($melody); $n++) {
            $noteDuration = $rhythms[$n % count($rhythms)] * $beatDuration / $sampleRate;
            
            if ($patternTime >= $beatPosition && $patternTime < $beatPosition + $noteDuration) {
                $currentNote = $melody[$n];
                $currentRhythm = $rhythms[$n % count($rhythms)];
                $noteStartTime = $beatPosition;
                break;
            }
            
            $beatPosition += $noteDuration;
        }
        
        // Get frequency for current note
        $noteIndex = $currentNote % count($scale);
        $frequency = $baseFreq * $scale[$noteIndex];
        
        // Calculate envelope for current note
        $noteDuration = $currentRhythm * $beatDuration / $sampleRate;
        $noteProgress = ($patternTime - $noteStartTime) / $noteDuration;
        $envelope = generateEnvelope($noteProgress);
        
        // Generate sound based on instrument type
        $sample = 0;
        foreach ($instruments as $instrument) {
            switch ($instrument) {
                case 'sine':
                    $sample += 0.3 * sin(2 * M_PI * $frequency * $time) * $envelope;
                    break;
                case 'triangle':
                    $sample += 0.2 * (2 * asin(sin(2 * M_PI * $frequency * $time)) / M_PI) * $envelope;
                    break;
                case 'square':
                    $sample += 0.15 * (sin(2 * M_PI * $frequency * $time) > 0 ? 1 : -1) * $envelope;
                    break;
                case 'sawtooth':
                    $sample += 0.1 * (2 * (($frequency * $time) - floor(0.5 + $frequency * $time))) * $envelope;
                    break;
            }
        }
        
        // Add some reverb/echo effect
        if ($i > $sampleRate * 0.1) {
            $sample += 0.1 * $data[$i - (int)($sampleRate * 0.1)];
        }
        
        $data[] = max(-1.0, min(1.0, $sample));
    }
    
    return $data;
}

function generateEnvelope($progress) {
    // ADSR envelope: Attack, Decay, Sustain, Release
    if ($progress < 0.1) {
        // Attack
        return $progress * 10;
    } elseif ($progress < 0.3) {
        // Decay
        return 1.0 - (($progress - 0.1) / 0.2) * 0.3;
    } elseif ($progress < 0.8) {
        // Sustain
        return 0.7;
    } else {
        // Release
        return 0.7 * (1.0 - (($progress - 0.8) / 0.2));
    }
}

function createEnhancedWav($data) {
    $sampleRate = 44100;
    $bitsPerSample = 16;
    $channels = 1;
    $dataSize = count($data) * 2;
    $fileSize = 36 + $dataSize;
    
    // WAV header
    $header = pack('V', 0x46464952); // 'RIFF'
    $header .= pack('V', $fileSize);
    $header .= pack('V', 0x45564157); // 'WAVE'
    $header .= pack('V', 0x20746d66); // 'fmt '
    $header .= pack('V', 16);
    $header .= pack('v', 1); // PCM
    $header .= pack('v', $channels);
    $header .= pack('V', $sampleRate);
    $header .= pack('V', $sampleRate * $channels * $bitsPerSample / 8);
    $header .= pack('v', $channels * $bitsPerSample / 8);
    $header .= pack('v', $bitsPerSample);
    $header .= pack('V', 0x61746164); // 'data'
    $header .= pack('V', $dataSize);
    
    // Convert samples to 16-bit
    $binaryData = '';
    foreach ($data as $sample) {
        $intSample = (int)($sample * 32767);
        $intSample = max(-32768, min(32767, $intSample));
        $binaryData .= pack('s', $intSample);
    }
    
    return $header . $binaryData;
}
?>