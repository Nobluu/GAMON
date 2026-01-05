# GAMON - Time Capsule Messaging Application

GAMON (Capsule Message) adalah aplikasi web yang memungkinkan pengguna untuk mengirim pesan yang hanya bisa dibuka pada waktu yang telah ditentukan di masa depan.

## 🚀 Features

### Core Features (MVP)
- ✅ **Authentication & Account Management**
  - User registration, login, logout
  - Secure password hashing (Argon2ID)
  - Rate limiting untuk mencegah brute force
  - Session management yang aman

- ✅ **Time Capsule Messages**
  - Buat pesan dengan title, konten, dan mood
  - Jadwalkan waktu pembukaan di masa depan
  - Pesan terkunci hingga waktu yang ditentukan
  - Status pesan: locked, unlocked, opened

- ✅ **Mood Categories**
  - 8 kategori mood dengan emoji dan warna
  - Filter pesan berdasarkan mood
  - Database mood yang dapat diperluas

- ✅ **Media Upload System**
  - Upload gambar, video, dan audio
  - Validasi tipe file dan ukuran (max 10MB)
  - Penyimpanan file yang aman
  - Metadata tracking

- ✅ **Archive & Search**
  - Daftar pesan dengan pagination
  - Filter berdasarkan status dan mood
  - Pencarian full-text pada title dan konten
  - Sorting berdasarkan tanggal

- ✅ **Notification System**
  - Notifikasi in-app saat pesan unlock
  - Sistem notifikasi yang dapat diperluas
  - Mark as read functionality

- ✅ **Security & Privacy**
  - Scoped access (user hanya bisa akses pesan sendiri)
  - Parameterized queries (SQL injection protection)
  - Input validation dan sanitization
  - File upload security
  - Audit logging
  - CSRF protection ready

## 🛠 Tech Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, Tailwind CSS, Vanilla JavaScript
- **Security**: Argon2ID password hashing, CSRF protection
- **File Handling**: Secure upload with MIME validation
- **Cron Jobs**: Automated message unlocking

## 📋 Requirements

- PHP 8.0 atau lebih tinggi
- MySQL 8.0 atau lebih tinggi
- Apache/Nginx web server
- Composer (optional, untuk dependencies tambahan)
- XAMPP/WAMP (untuk development)

## 🚦 Installation & Setup

### 1. Clone/Download Project
```bash
git clone <repository-url>
cd gamon
```

### 2. Database Setup
```bash
# Start MySQL server
# Akses phpMyAdmin atau MySQL command line

# Import database schema
mysql -u root -p < database.sql

# Atau via phpMyAdmin:
# - Buat database 'gamon_db'
# - Import file database.sql
```

### 3. Configuration
```bash
# Copy environment file
cp .env.example .env

# Edit .env file dengan konfigurasi Anda
# Minimal yang perlu diubah:
DB_HOST=localhost
DB_NAME=gamon_db
DB_USER=root
DB_PASS=your_password
```

### 4. Directory Permissions
```bash
# Pastikan folder uploads dapat ditulis
chmod 755 uploads/
chmod 755 logs/

# Untuk development di Windows/XAMPP, biasanya tidak perlu chmod
```

### 5. Web Server Setup

#### Apache (.htaccess sudah disediakan)
```apache
# Pastikan mod_rewrite aktif
# File .htaccess sudah dikonfigurasi dengan security headers
```

#### Nginx (optional)
```nginx
server {
    listen 80;
    server_name gamon.local;
    root /path/to/gamon;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location /uploads {
        location ~ \.php$ {
            deny all;
        }
    }
}
```

### 6. Cron Jobs Setup (Production)

#### Linux/Unix
```bash
# Edit crontab
crontab -e

# Tambahkan jobs berikut:
# Unlock messages setiap menit
* * * * * /usr/bin/php /path/to/gamon/cron/unlockMessages_new.php >> /var/log/gamon_unlock.log 2>&1

# Send notifications setiap 5 menit
*/5 * * * * /usr/bin/php /path/to/gamon/cron/sendNotifications_new.php >> /var/log/gamon_notifications.log 2>&1
```

#### Windows (Task Scheduler)
```cmd
# Buat task baru di Task Scheduler
# Program: C:\xampp\php\php.exe
# Arguments: C:\xampp\htdocs\gamon\cron\unlockMessages_new.php
# Schedule: Every 1 minute
```

### 7. Testing Setup
```bash
# Akses aplikasi di browser
http://localhost/gamon

# Atau jika menggunakan XAMPP:
http://localhost/xampp/gamon

# Register akun baru
# Login dan test fitur-fitur
```

## 📁 Project Structure

```
gamon/
├── config/
│   └── database.php          # Database connection
├── controllers/
│   ├── AuthController.php    # Authentication logic
│   ├── MessageController.php # Message CRUD operations
│   ├── MoodController.php    # Mood management
│   ├── MediaController.php   # File upload handling
│   └── NotificationController.php # Notification system
├── cron/
│   ├── unlockMessages_new.php    # Message unlock scheduler
│   └── sendNotifications_new.php # Email notification sender
├── helpers/
│   └── SecurityHelper.php    # Security utilities
├── uploads/                  # File storage (auto-created)
├── logs/                     # Log files (auto-created)
├── assets/
│   ├── css/
│   └── js/
├── docs/
│   ├── database-design.md
│   └── deployment-guide.md
├── .htaccess                 # Apache security config
├── .env.example              # Environment template
├── database.sql              # Database schema
├── index.php                 # Landing page
├── dashboard_new.php         # Main dashboard
├── create-message_new.php    # Create message form
├── view-message.php          # View message details
├── notifications.php         # Notification center
├── media.php                 # Secure file serving
├── login.php                 # Login page
├── register.php              # Registration page
└── README.md                 # This file
```

## 🔧 Configuration Options

### Environment Variables (.env)
```env
# Database
DB_HOST=localhost
DB_NAME=gamon_db
DB_USER=root
DB_PASS=

# Security
JWT_SECRET=change-this-secret-key
SESSION_TIMEOUT=86400
RATE_LIMIT_LOGIN=5

# File Upload
UPLOAD_MAX_SIZE=10485760
UPLOAD_PATH=uploads/

# Email (optional)
SMTP_HOST=localhost
SMTP_PORT=587
SMTP_USERNAME=
SMTP_PASSWORD=
```

## 🔒 Security Features

### Implemented Security Measures
- **Password Security**: Argon2ID hashing
- **SQL Injection Protection**: Parameterized queries
- **XSS Protection**: Input sanitization dan output encoding
- **File Upload Security**: MIME type validation, malicious content detection
- **Rate Limiting**: Brute force protection
- **Access Control**: User-scoped data access
- **Audit Logging**: Security event tracking
- **CSRF Protection**: Token-based (ready to implement)

### Security Headers (.htaccess)
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY  
- X-XSS-Protection: 1; mode=block
- Strict-Transport-Security (HTTPS)
- Content Security Policy (ready)

## 🎯 Usage Guide

### 1. Registration & Login
- Daftar akun baru di `/register.php`
- Login dengan email dan password
- Session akan bertahan sesuai konfigurasi

### 2. Creating Time Capsules
- Klik "Create New Capsule" di dashboard
- Isi form dengan:
  - Recipient email (bisa email sendiri)
  - Title dan content
  - Pilih mood/suasana hati
  - Upload file (optional)
  - Tentukan waktu unlock
- Submit form

### 3. Managing Capsules
- View semua capsule di dashboard
- Filter berdasarkan status (locked/unlocked/opened)
- Filter berdasarkan mood
- Search berdasarkan title/content
- View detail masing-masing capsule

### 4. Notifications
- Terima notifikasi saat capsule unlock
- Mark notifications as read
- View notification history

## 🚀 Production Deployment

### 1. Security Checklist
- [ ] Ubah semua default passwords
- [ ] Enable HTTPS dengan SSL certificate
- [ ] Set environment ke 'production'
- [ ] Disable error display
- [ ] Configure proper file permissions
- [ ] Setup backup strategy
- [ ] Configure monitoring

### 2. Performance Optimization
- [ ] Enable opcode caching (OPcache)
- [ ] Configure database indexing
- [ ] Setup CDN untuk static assets
- [ ] Compress images
- [ ] Enable gzip compression

### 3. Monitoring
- [ ] Setup application monitoring
- [ ] Configure log rotation
- [ ] Monitor cron job execution
- [ ] Database performance monitoring

## 🤝 Contributing

1. Fork the repository
2. Create feature branch
3. Make changes with proper testing
4. Submit pull request

## 📝 License

This project is licensed under MIT License.

## 🐛 Known Issues & Limitations

### Current Limitations
1. **Email Integration**: Saat ini menggunakan mock email sender
2. **Real-time Notifications**: Belum ada WebSocket/SSE untuk real-time updates
3. **Mobile Responsiveness**: Perlu testing lebih lanjut di mobile devices
4. **Bulk Operations**: Belum ada bulk delete/mark as read
5. **User Management**: Belum ada admin panel untuk user management

### Planned Improvements
1. **Email Integration**: PHPMailer/SendGrid integration
2. **Real-time Features**: WebSocket notifications
3. **Mobile App**: React Native/Flutter app
4. **Advanced Search**: Elasticsearch integration
5. **AI Features**: Sentiment analysis, content suggestions

## 📞 Support

Jika ada pertanyaan atau issue:
1. Check documentation di folder `docs/`
2. Create issue di repository
3. Contact developer team

---

**Happy Time Capsule Messaging! 🚀**