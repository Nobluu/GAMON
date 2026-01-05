-- Add music support to moods table
-- This script adds music file columns to the moods table

USE capsule_db;

-- Add music file column to moods table
ALTER TABLE moods ADD COLUMN music_file VARCHAR(255) NULL AFTER color;
ALTER TABLE moods ADD COLUMN music_name VARCHAR(100) NULL AFTER music_file;
ALTER TABLE moods ADD COLUMN music_duration INT NULL AFTER music_name; -- duration in seconds
ALTER TABLE moods ADD COLUMN created_by INT NULL AFTER music_duration; -- admin who uploaded the music

-- Add foreign key for created_by
ALTER TABLE moods ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Create music uploads directory structure
-- Note: Create these folders manually:
-- uploads/music/moods/

-- Update existing moods with default music file names (files need to be uploaded)
UPDATE moods SET 
    music_file = CONCAT(LOWER(REPLACE(name, ' ', '_')), '.mp3'),
    music_name = CONCAT('Default ', name, ' Music')
WHERE music_file IS NULL;

-- Example of how music files should be named:
-- happy.mp3, excited.mp3, nostalgic.mp3, hopeful.mp3, grateful.mp3, 
-- love.mp3, nervous.mp3, adventurous.mp3, peaceful.mp3, determined.mp3,
-- curious.mp3, creative.mp3, accomplished.mp3, reflective.mp3

-- Show updated moods table structure
DESCRIBE moods;