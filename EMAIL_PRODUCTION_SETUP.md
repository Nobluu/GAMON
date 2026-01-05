# 📧 PANDUAN SETUP EMAIL PRODUCTION

## 🚀 Mode Production AKTIF!
Email sekarang akan benar-benar terkirim ke penerima (bukan lagi mode development).

## 🔧 Yang Perlu Dikonfigurasi:

### 1. **Update Credentials Email di config/email.php**
```php
private static $smtp_username = 'your-email@gmail.com'; // Ganti dengan email Anda
private static $smtp_password = 'your-app-password';    // Ganti dengan App Password Gmail
private static $from_email = 'your-email@gmail.com';   // Ganti dengan email Anda
```

### 2. **Setup Gmail App Password** (Recommended)
1. Buka Google Account settings
2. Pilih "Security" → "2-Step Verification"
3. Scroll ke bawah, pilih "App passwords"
4. Generate password untuk "Mail"
5. Copy password dan masukkan ke `$smtp_password`

### 3. **Install PHPMailer** (Optional tapi Recommended)
```bash
composer require phpmailer/phpmailer
```

## ⚙️ Alternatif Provider Email:

### Gmail SMTP:
- Host: smtp.gmail.com
- Port: 587 (TLS) atau 465 (SSL)
- Auth: Required

### SendGrid:
- Host: smtp.sendgrid.net
- Port: 587
- Username: apikey
- Password: [Your SendGrid API Key]

### Mailtrap (Testing):
- Host: smtp.mailtrap.io
- Port: 587
- Username: [Mailtrap username]
- Password: [Mailtrap password]

## 🔍 Status Check:
Buka halaman admin atau jalankan:
```php
$status = EmailSender::getConfigStatus();
print_r($status);
```

## ✅ Yang Sudah Diubah:
- ✅ Mode development = false
- ✅ SMTP configuration ready
- ✅ Fallback ke file jika SMTP gagal
- ✅ Error logging
- ✅ Support PHPMailer dan PHP mail()

## ⚠️ Sebelum Go Live:
1. Test kirim email ke email sendiri
2. Check spam folder
3. Verify email credentials
4. Monitor error logs

Sekarang email kapsul waktu akan benar-benar terkirim ke penerima! 🎯