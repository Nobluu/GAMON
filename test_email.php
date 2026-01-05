<?php
// Test Email Configuration
require_once 'config/email.php';

echo "<h2>🔧 Email Configuration Test</h2>";

// Check configuration status
$status = EmailSender::getConfigStatus();
echo "<div style='padding: 10px; margin: 10px 0; border-left: 4px solid #f25c5c; background: #f9f9f9;'>";
echo "<strong>Status:</strong> " . $status['status'] . "<br>";
echo "<strong>Message:</strong> " . $status['message'];
echo "</div>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $test_email = $_POST['test_email'];
    
    if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        echo "<h3>🚀 Mengirim Test Email...</h3>";
        
        $subject = "🕰️ Test Email - GAMON Time Capsule";
        $message = "
        <h2>Test Email Berhasil!</h2>
        <p>Ini adalah test email dari sistem GAMON Time Capsule.</p>
        <p><strong>Waktu:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p><strong>Status:</strong> Production Mode Active ✅</p>
        <p>Email kapsul waktu sekarang akan benar-benar terkirim ke penerima!</p>
        ";
        
        $result = EmailSender::sendHTMLEmail($test_email, $subject, $message);
        
        if ($result === true) {
            echo "<div style='padding: 10px; margin: 10px 0; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 5px;'>";
            echo "✅ <strong>Email berhasil dikirim ke:</strong> $test_email";
            echo "</div>";
        } else if (is_string($result)) {
            echo "<div style='padding: 10px; margin: 10px 0; background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; border-radius: 5px;'>";
            echo "⚠️ <strong>Email disimpan ke file (SMTP gagal):</strong><br>";
            echo "File: " . basename($result);
            echo "<br><a href='" . EmailSender::viewLastEmail() . "' target='_blank'>Lihat Email</a>";
            echo "</div>";
        } else {
            echo "<div style='padding: 10px; margin: 10px 0; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 5px;'>";
            echo "❌ <strong>Gagal mengirim email</strong>";
            echo "</div>";
        }
    } else {
        echo "<div style='padding: 10px; margin: 10px 0; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 5px;'>";
        echo "❌ <strong>Email tidak valid</strong>";
        echo "</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Email Configuration</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin: 15px 0; }
        input[type="email"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #f25c5c; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #e04848; }
    </style>
</head>
<body>
    
    <form method="POST">
        <div class="form-group">
            <label><strong>📧 Test Email Address:</strong></label>
            <input type="email" name="test_email" placeholder="masukkan-email@gmail.com" required>
        </div>
        <div class="form-group">
            <button type="submit">🚀 Kirim Test Email</button>
        </div>
    </form>
    
    <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h3>💡 Cara Setup Gmail SMTP:</h3>
        <ol>
            <li>Edit file <code>config/email.php</code></li>
            <li>Ganti <code>your-email@gmail.com</code> dengan email Anda</li>
            <li>Buat App Password di Google Account settings</li>
            <li>Masukkan App Password ke <code>$smtp_password</code></li>
            <li>Test email di halaman ini</li>
        </ol>
    </div>
    
    <?php if (EmailSender::getLastEmailPath()): ?>
    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <strong>📁 Last Email:</strong> 
        <a href="<?= EmailSender::viewLastEmail() ?>" target="_blank">Lihat Email Terakhir</a>
    </div>
    <?php endif; ?>
    
</body>
</html>