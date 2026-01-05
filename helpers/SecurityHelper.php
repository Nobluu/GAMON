<?php

class SecurityHelper {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            case 'string':
            default:
                return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Validate input data
     */
    public static function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Check required
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = ucfirst($field) . ' is required.';
                continue;
            }
            
            // Skip validation if empty and not required
            if (empty($value)) continue;
            
            // Min length
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = ucfirst($field) . ' must be at least ' . $rule['min_length'] . ' characters.';
            }
            
            // Max length
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = ucfirst($field) . ' must not exceed ' . $rule['max_length'] . ' characters.';
            }
            
            // Email validation
            if (isset($rule['email']) && $rule['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = ucfirst($field) . ' must be a valid email address.';
            }
            
            // URL validation
            if (isset($rule['url']) && $rule['url'] && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[$field] = ucfirst($field) . ' must be a valid URL.';
            }
            
            // Numeric validation
            if (isset($rule['numeric']) && $rule['numeric'] && !is_numeric($value)) {
                $errors[$field] = ucfirst($field) . ' must be a number.';
            }
            
            // Date validation
            if (isset($rule['date']) && $rule['date'] && !strtotime($value)) {
                $errors[$field] = ucfirst($field) . ' must be a valid date.';
            }
            
            // Future date validation
            if (isset($rule['future_date']) && $rule['future_date'] && strtotime($value) <= time()) {
                $errors[$field] = ucfirst($field) . ' must be a future date.';
            }
            
            // Custom regex validation
            if (isset($rule['regex']) && !preg_match($rule['regex'], $value)) {
                $errors[$field] = isset($rule['regex_message']) ? $rule['regex_message'] : ucfirst($field) . ' format is invalid.';
            }
        }
        
        return $errors;
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($key, $maxAttempts = 5, $window = 300) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $now = time();
        $attempts = $_SESSION['rate_limit'][$key] ?? [];
        
        // Remove old attempts outside the window
        $attempts = array_filter($attempts, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        // Check if limit exceeded
        if (count($attempts) >= $maxAttempts) {
            return false;
        }
        
        // Add current attempt
        $attempts[] = $now;
        $_SESSION['rate_limit'][$key] = $attempts;
        
        return true;
    }
    
    /**
     * Generate secure random string
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Check password strength
     */
    public static function checkPasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }
        
        return $errors;
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 10485760) {
        $errors = [];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'File size exceeds the maximum allowed size.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'File upload was interrupted.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = 'No file was uploaded.';
                    break;
                default:
                    $errors[] = 'File upload error occurred.';
            }
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds the maximum allowed size of ' . number_format($maxSize / 1024 / 1024, 1) . ' MB.';
        }
        
        // Check MIME type
        $detectedType = mime_content_type($file['tmp_name']);
        if (!empty($allowedTypes) && !in_array($detectedType, $allowedTypes)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes);
        }
        
        // Check for malicious content
        $fileContent = file_get_contents($file['tmp_name'], false, null, 0, 1024);
        if (preg_match('/<\?php|<script|javascript:|vbscript:|onload=|onerror=/i', $fileContent)) {
            $errors[] = 'File contains potentially malicious content.';
        }
        
        return $errors;
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'details' => $details
        ];
        
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Clean filename for security
     */
    public static function cleanFilename($filename) {
        // Remove path information and dots
        $filename = basename($filename);
        $filename = str_replace(['..', '/'], '', $filename);
        
        // Remove potentially dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
}
?>