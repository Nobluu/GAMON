<?php
require_once __DIR__ . '/../config/database.php';

class MoodController {
    private $conn;

    public function __construct() {
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
            
            if ($this->conn === null) {
                throw new Exception("Failed to establish database connection");
            }
        } catch (Exception $e) {
            error_log("MoodController connection error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all available moods
     */
    public function getAllMoods() {
        try {
            if ($this->conn === null) {
                throw new Exception("Database connection is null");
            }
            
            $query = "SELECT id, name, emoji, color, description FROM moods ORDER BY name ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return [
                'status' => true, 
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (PDOException $e) {
            error_log("Error fetching moods: " . $e->getMessage());
            return [
                'status' => false, 
                'message' => 'Failed to fetch moods'
            ];
        }
    }

    /**
     * Get mood by ID
     */
    public function getMoodById($mood_id) {
        try {
            $query = "SELECT id, name, emoji, color, description FROM moods WHERE id = :mood_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':mood_id', $mood_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $mood = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($mood) {
                return [
                    'status' => true, 
                    'data' => $mood
                ];
            }
            
            return [
                'status' => false, 
                'message' => 'Mood not found'
            ];
        } catch (PDOException $e) {
            error_log("Error fetching mood: " . $e->getMessage());
            return [
                'status' => false, 
                'message' => 'Failed to fetch mood'
            ];
        }
    }

    /**
     * Create new mood (admin function)
     */
    public function createMood($name, $emoji, $color = '#6b7280', $description = null) {
        try {
            // Sanitize inputs
            $name = htmlspecialchars(strip_tags($name));
            $emoji = htmlspecialchars($emoji);
            $color = htmlspecialchars($color);
            $description = $description ? htmlspecialchars(strip_tags($description)) : null;

            // Validate color format (basic hex validation)
            if (!preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
                return [
                    'status' => false, 
                    'message' => 'Invalid color format. Use hex format like #ff0000'
                ];
            }

            $query = "INSERT INTO moods (name, emoji, color, description) VALUES (:name, :emoji, :color, :description)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':emoji', $emoji);
            $stmt->bindParam(':color', $color);
            $stmt->bindParam(':description', $description);
            
            if ($stmt->execute()) {
                return [
                    'status' => true, 
                    'message' => 'Mood created successfully',
                    'mood_id' => $this->conn->lastInsertId()
                ];
            }
            
            return [
                'status' => false, 
                'message' => 'Failed to create mood'
            ];
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                return [
                    'status' => false, 
                    'message' => 'Mood name already exists'
                ];
            }
            error_log("Error creating mood: " . $e->getMessage());
            return [
                'status' => false, 
                'message' => 'Failed to create mood'
            ];
        }
    }

    /**
     * Update existing mood (admin function)
     */
    public function updateMood($mood_id, $name, $emoji, $color, $description = null) {
        try {
            // Sanitize inputs
            $name = htmlspecialchars(strip_tags($name));
            $emoji = htmlspecialchars($emoji);
            $color = htmlspecialchars($color);
            $description = $description ? htmlspecialchars(strip_tags($description)) : null;

            // Validate color format
            if (!preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
                return [
                    'status' => false, 
                    'message' => 'Invalid color format. Use hex format like #ff0000'
                ];
            }

            $query = "UPDATE moods SET name = :name, emoji = :emoji, color = :color, description = :description WHERE id = :mood_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':mood_id', $mood_id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':emoji', $emoji);
            $stmt->bindParam(':color', $color);
            $stmt->bindParam(':description', $description);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return [
                        'status' => true, 
                        'message' => 'Mood updated successfully'
                    ];
                } else {
                    return [
                        'status' => false, 
                        'message' => 'Mood not found or no changes made'
                    ];
                }
            }
            
            return [
                'status' => false, 
                'message' => 'Failed to update mood'
            ];
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                return [
                    'status' => false, 
                    'message' => 'Mood name already exists'
                ];
            }
            error_log("Error updating mood: " . $e->getMessage());
            return [
                'status' => false, 
                'message' => 'Failed to update mood'
            ];
        }
    }

    /**
     * Delete mood (admin function) - only if not used in messages
     */
    public function deleteMood($mood_id) {
        try {
            // Check if mood is being used in messages
            $checkQuery = "SELECT COUNT(*) as count FROM messages WHERE mood_id = :mood_id";
            $stmt = $this->conn->prepare($checkQuery);
            $stmt->bindParam(':mood_id', $mood_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                return [
                    'status' => false, 
                    'message' => 'Cannot delete mood that is being used in messages'
                ];
            }

            $query = "DELETE FROM moods WHERE id = :mood_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':mood_id', $mood_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return [
                        'status' => true, 
                        'message' => 'Mood deleted successfully'
                    ];
                } else {
                    return [
                        'status' => false, 
                        'message' => 'Mood not found'
                    ];
                }
            }
            
            return [
                'status' => false, 
                'message' => 'Failed to delete mood'
            ];
        } catch (PDOException $e) {
            error_log("Error deleting mood: " . $e->getMessage());
            return [
                'status' => false, 
                'message' => 'Failed to delete mood'
            ];
        }
    }
}
?>