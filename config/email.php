<?php
// Email Configuration - PRODUCTION MODE
class EmailSender {
    
    // Production email configuration
    private static $smtp_host = 'smtp.gmail.com';
    private static $smtp_port = 587;
    private static $smtp_username = 'your-email@gmail.com'; // Ganti dengan email Anda
    private static $smtp_password = 'your-app-password';    // Ganti dengan App Password Gmail
    private static $from_name = 'GAMON Time Capsule';
    private static $from_email = 'your-email@gmail.com';   // Ganti dengan email Anda
    
    public static function sendHTMLEmail($to_email, $subject, $html_message, $sender_name = 'GAMON') {
        // PRODUCTION MODE - Send real emails using SMTP
        
        // Check if we're in development mode (you can change this to false for production)
        $development_mode = false; // Changed to false for production
        
        if ($development_mode) {
            return self::saveEmailToFile($to_email, $subject, $html_message, $sender_name);
        }
        
        // Try PHPMailer first (recommended for production)
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return self::sendWithPHPMailer($to_email, $subject, $html_message, $sender_name);
        }
        
        // Fallback to PHP mail() function
        return self::sendWithPHPMail($to_email, $subject, $html_message, $sender_name);
    }
    
    private static function sendWithPHPMailer($to_email, $subject, $html_message, $sender_name) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php'; // Adjust path as needed
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = self::$smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::$smtp_username;
            $mail->Password   = self::$smtp_password;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = self::$smtp_port;
            
            // Recipients
            $mail->setFrom(self::$from_email, self::$from_name);
            $mail->addAddress($to_email);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html_message;
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            // Fallback to PHP mail()
            return self::sendWithPHPMail($to_email, $subject, $html_message, $sender_name);
        }
    }
    
    private static function sendWithPHPMail($to_email, $subject, $html_message, $sender_name) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . self::$from_name . " <" . self::$from_email . ">" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $mail_sent = @mail($to_email, $subject, $html_message, $headers);
        
        if (!$mail_sent) {
            error_log("Failed to send email to: $to_email");
            // Save to file as backup
            return self::saveEmailToFile($to_email, $subject, $html_message, $sender_name);
        }
        
        return true;
    }
    
    private static function saveEmailToFile($to_email, $subject, $html_message, $sender_name) {
        // Backup method: save email to file if SMTP fails
        $email_dir = __DIR__ . '/../temp/emails';
        if (!is_dir($email_dir)) {
            mkdir($email_dir, 0777, true);
        }
        
        $email_file = $email_dir . '/email_' . date('Y-m-d_H-i-s') . '_' . md5($to_email) . '.html';
        
        $email_content = "
<!DOCTYPE html>
<html>
<head>
    <title>$subject</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .email-header { background: #f25c5c; color: white; padding: 15px; border-radius: 10px 10px 0 0; }
        .email-body { border: 1px solid #ddd; padding: 20px; border-radius: 0 0 10px 10px; }
        .email-info { background: #ffe6e6; padding: 10px; margin: 10px 0; border-left: 4px solid #f25c5c; }
    </style>
</head>
<body>
    <div class='email-info'>
        <strong>⚠️ EMAIL BACKUP MODE</strong><br>
        <strong>To:</strong> $to_email<br>
        <strong>Subject:</strong> $subject<br>
        <strong>Sent:</strong> " . date('Y-m-d H:i:s') . "<br>
        <strong>Status:</strong> SMTP failed - saved as backup
    </div>
    
    <div class='email-header'>
        <h2>$subject</h2>
    </div>
    
    <div class='email-body'>
        $html_message
    </div>
    
    <div class='email-info'>
        <strong>🔧 SMTP Configuration Required:</strong><br>
        - Update email credentials in config/email.php<br>
        - Install PHPMailer: composer require phpmailer/phpmailer<br>
        - Configure Gmail App Password or other SMTP provider
    </div>
</body>
</html>";
        
        file_put_contents($email_file, $email_content);
        return $email_file;
    }
    
    public static function getLastEmailPath() {
        $email_dir = __DIR__ . '/../temp/emails';
        if (!is_dir($email_dir)) {
            return null;
        }
        
        $files = glob($email_dir . '/email_*.html');
        if (empty($files)) {
            return null;
        }
        
        // Sort by modification time, get the latest
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        return $files[0];
    }
    
    public static function viewLastEmail() {
        $email_path = self::getLastEmailPath();
        if ($email_path) {
            $email_url = str_replace(__DIR__ . '/..', '', $email_path);
            $email_url = str_replace('\\', '/', $email_url);
            return 'http://localhost:9080/gamon' . $email_url;
        }
        return null;
    }
    
    // Configuration checker
    public static function isConfigured() {
        return (self::$smtp_username !== 'your-email@gmail.com' && 
                self::$smtp_password !== 'your-app-password');
    }
    
    // Get configuration status
    public static function getConfigStatus() {
        if (!self::isConfigured()) {
            return [
                'status' => 'needs_config',
                'message' => 'Email credentials need to be configured'
            ];
        }
        
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return [
                'status' => 'ready',
                'message' => 'PHPMailer configured and ready'
            ];
        }
        
        return [
            'status' => 'basic',
            'message' => 'Using PHP mail() function - consider installing PHPMailer'
        ];
    }
}
?>