<?php
require_once __DIR__ . '/../config/database.php';

class MediaController {
    private $conn;
    private $upload_dir;
    private $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png', 
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/ogg' => 'ogg'
    ];
    private $max_file_size = 10 * 1024 * 1024; // 10MB

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Set upload directory
        $this->upload_dir = __DIR__ . '/../uploads/';
        
        // Create upload directories if they don't exist
        $this->createUploadDirectories();
    }

    /**
     * Upload file and associate with message
     */
    public function uploadFile($message_id, $file, $user_id) {
        try {
            // 1. Validate message ownership
            if (!$this->validateMessageOwnership($message_id, $user_id)) {
                return ['status' => false, 'message' => 'Unauthorized: You can only upload files to your own messages.'];
            }

            // 2. Validate file upload
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return ['status' => false, 'message' => 'No valid file uploaded.'];
            }

            // 3. Check file size
            if ($file['size'] > $this->max_file_size) {
                return ['status' => false, 'message' => 'File size exceeds limit (10MB).'];
            }

            // 4. Validate file type using mime_content_type (more secure than $_FILES['type'])
            $detected_mime = mime_content_type($file['tmp_name']);
            if (!array_key_exists($detected_mime, $this->allowed_types)) {
                return ['status' => false, 'message' => 'File type not allowed. Allowed: images, videos, audio files.'];
            }

            // 5. Generate secure filename
            $file_info = $this->generateSecureFilename($file['name'], $detected_mime);
            $full_path = $this->upload_dir . $file_info['relative_path'];

            // 6. Create directory if needed
            $dir = dirname($full_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 7. Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $full_path)) {
                return ['status' => false, 'message' => 'Failed to save file.'];
            }

            // 8. Save to database
            $media_id = $this->saveFileMetadata($message_id, $file_info, $file['size'], $detected_mime);
            
            if ($media_id) {
                // 9. Log the action
                $this->logAudit($user_id, 'UPLOAD_MEDIA', 'message_media', $media_id, null, [
                    'message_id' => $message_id,
                    'filename' => $file_info['filename'],
                    'file_size' => $file['size']
                ]);

                return [
                    'status' => true,
                    'message' => 'File uploaded successfully.',
                    'media_id' => $media_id,
                    'file_info' => $file_info
                ];
            }

            // Clean up file if database save failed
            unlink($full_path);
            return ['status' => false, 'message' => 'Failed to save file metadata.'];

        } catch (Exception $e) {
            error_log("Error uploading file: " . $e->getMessage());
            return ['status' => false, 'message' => 'File upload failed.'];
        }
    }

    /**
     * Upload multiple files at once
     */
    public function uploadMultipleFiles($message_id, $files, $user_id) {
        $results = [];
        $success_count = 0;
        
        // Handle both single and multiple file uploads
        if (!isset($files['name'][0])) {
            // Single file upload (convert to array format)
            $files = [
                'name' => [$files['name']],
                'type' => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error' => [$files['error']],
                'size' => [$files['size']]
            ];
        }

        for ($i = 0; $i < count($files['name']); $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];

            $result = $this->uploadFile($message_id, $file, $user_id);
            $results[] = $result;
            
            if ($result['status']) {
                $success_count++;
            }
        }

        return [
            'status' => $success_count > 0,
            'message' => "$success_count of " . count($files['name']) . " files uploaded successfully.",
            'results' => $results,
            'success_count' => $success_count,
            'total_count' => count($files['name'])
        ];
    }

    /**
     * Get file by ID (with access control)
     */
    public function getFile($media_id, $user_id) {
        try {
            $query = "SELECT mm.*, m.sender_id, m.receiver_id 
                      FROM message_media mm 
                      JOIN messages m ON mm.message_id = m.id 
                      WHERE mm.id = :media_id LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':media_id', $media_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $media = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$media) {
                return ['status' => false, 'message' => 'File not found.'];
            }

            // Check access permissions
            if ($media['sender_id'] != $user_id && $media['receiver_id'] != $user_id) {
                return ['status' => false, 'message' => 'Unauthorized access.'];
            }

            return ['status' => true, 'data' => $media];

        } catch (PDOException $e) {
            error_log("Error fetching file: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to fetch file.'];
        }
    }

    /**
     * Delete file (only by message owner)
     */
    public function deleteFile($media_id, $user_id) {
        try {
            $result = $this->getFile($media_id, $user_id);
            if (!$result['status']) {
                return $result;
            }

            $media = $result['data'];

            // Only sender can delete files
            if ($media['sender_id'] != $user_id) {
                return ['status' => false, 'message' => 'Only the message sender can delete files.'];
            }

            // Delete from filesystem
            $file_path = $this->upload_dir . $media['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            // Delete from database
            $query = "DELETE FROM message_media WHERE id = :media_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':media_id', $media_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Log the action
                $this->logAudit($user_id, 'DELETE_MEDIA', 'message_media', $media_id, $media, null);
                
                return ['status' => true, 'message' => 'File deleted successfully.'];
            }

            return ['status' => false, 'message' => 'Failed to delete file.'];

        } catch (PDOException $e) {
            error_log("Error deleting file: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to delete file.'];
        }
    }

    /**
     * Serve file with proper headers (for secure file serving)
     */
    public function serveFile($media_id, $user_id) {
        $result = $this->getFile($media_id, $user_id);
        if (!$result['status']) {
            http_response_code(404);
            echo "File not found";
            return;
        }

        $media = $result['data'];
        $file_path = $this->upload_dir . $media['file_path'];

        if (!file_exists($file_path)) {
            http_response_code(404);
            echo "File not found on disk";
            return;
        }

        // Set appropriate headers
        header('Content-Type: ' . $media['mime_type']);
        header('Content-Length: ' . filesize($file_path));
        header('Content-Disposition: inline; filename="' . $media['original_filename'] . '"');
        
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');

        // Output file
        readfile($file_path);
    }

    /**
     * Generate secure filename with directory structure
     */
    private function generateSecureFilename($original_name, $mime_type) {
        $extension = $this->allowed_types[$mime_type];
        $filename = uniqid() . '_' . time() . '.' . $extension;
        
        // Create date-based directory structure
        $date_path = date('Y/m/d');
        $relative_path = $date_path . '/' . $filename;

        return [
            'filename' => $filename,
            'original_filename' => basename($original_name),
            'relative_path' => $relative_path,
            'extension' => $extension
        ];
    }

    /**
     * Save file metadata to database
     */
    private function saveFileMetadata($message_id, $file_info, $file_size, $mime_type) {
        try {
            $file_type = $this->getFileTypeFromMime($mime_type);

            $query = "INSERT INTO message_media (message_id, filename, original_filename, file_path, file_size, mime_type, file_type) 
                      VALUES (:message_id, :filename, :original_filename, :file_path, :file_size, :mime_type, :file_type)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
            $stmt->bindParam(':filename', $file_info['filename']);
            $stmt->bindParam(':original_filename', $file_info['original_filename']);
            $stmt->bindParam(':file_path', $file_info['relative_path']);
            $stmt->bindParam(':file_size', $file_size, PDO::PARAM_INT);
            $stmt->bindParam(':mime_type', $mime_type);
            $stmt->bindParam(':file_type', $file_type);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error saving file metadata: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Determine file type from MIME type
     */
    private function getFileTypeFromMime($mime_type) {
        if (strpos($mime_type, 'image/') === 0) return 'image';
        if (strpos($mime_type, 'video/') === 0) return 'video';
        if (strpos($mime_type, 'audio/') === 0) return 'audio';
        return 'document';
    }

    /**
     * Validate message ownership
     */
    private function validateMessageOwnership($message_id, $user_id) {
        try {
            $query = "SELECT sender_id FROM messages WHERE id = :message_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && $result['sender_id'] == $user_id;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Create upload directories
     */
    private function createUploadDirectories() {
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }

        // Create .htaccess for security (prevent direct script execution)
        $htaccess_path = $this->upload_dir . '.htaccess';
        if (!file_exists($htaccess_path)) {
            $htaccess_content = "Options -Indexes\nOptions -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5\nAddType text/plain .php .phtml .php3 .php4 .php5";
            file_put_contents($htaccess_path, $htaccess_content);
        }
    }

    /**
     * Log audit trail
     */
    private function logAudit($user_id, $action, $table_name, $record_id = null, $old_values = null, $new_values = null) {
        try {
            $query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                      VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':table_name', $table_name);
            $stmt->bindParam(':record_id', $record_id, PDO::PARAM_INT);
            $stmt->bindParam(':old_values', $old_values ? json_encode($old_values) : null);
            $stmt->bindParam(':new_values', $new_values ? json_encode($new_values) : null);
            $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
            $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
            
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error logging audit: " . $e->getMessage());
        }
    }
}
?>