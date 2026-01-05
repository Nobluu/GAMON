# 🔔 Sistem Notifikasi Real-Time GAMON

## 📋 Overview
Sistem notifikasi telah berhasil diimplementasikan untuk memberikan notifikasi real-time kepada pengguna mengenai:
- ✅ Kapsul pribadi yang sudah terbuka
- ✅ Pesan baru dari teman
- ✅ Kapsul dari teman yang sudah terbuka
- ✅ Update real-time di semua halaman

## 🏗️ Struktur Sistem

### 1. Database
- **Table**: `notifications`
  - `id` - Primary key
  - `user_id` - ID pengguna penerima notifikasi
  - `capsule_id` - ID kapsul terkait
  - `type` - Jenis notifikasi (capsule_unlock, friend_message_received, friend_capsule_unlock)
  - `title` - Judul notifikasi
  - `message` - Isi pesan notifikasi
  - `action_url` - URL untuk action (opsional)
  - `priority` - Prioritas notifikasi (low, normal, high)
  - `is_read` - Status sudah dibaca atau belum
  - `created_at` - Waktu notifikasi dibuat

### 2. File Structure
```
controllers/
├── NotificationController.php    # Controller utama notifikasi
api/
├── notifications.php            # API endpoint untuk notifikasi
cron/
├── checkUnlockCapsules.php     # Cron job untuk cek unlock
includes/
├── navbar.php                   # Navbar dengan komponen notifikasi
logs/
├── unlock_activity.log          # Log aktivitas unlock
```

## 🔧 Fitur Utama

### 1. **Real-Time Notification Bell** 
- 🔔 Icon bell di navbar dengan badge counter
- 💬 Dropdown dengan list 5 notifikasi terbaru
- ✅ Mark as read functionality
- 🔗 Click to action - redirect ke halaman terkait

### 2. **Automatic Notification Creation**
- 🎉 Saat kapsul pribadi unlock → notifikasi otomatis
- 📨 Saat menerima pesan dari teman → notifikasi otomatis  
- 💌 Saat kapsul dari teman unlock → notifikasi otomatis
- 🔍 Cek otomatis saat login

### 3. **API Endpoints**
- `GET api/notifications.php?action=count` - Get unread count
- `GET api/notifications.php?action=recent&limit=5` - Get recent notifications
- `POST api/notifications.php?action=mark_read` - Mark notification as read
- `GET api/notifications.php?action=check_unlocked` - Check unlocked capsules

### 4. **JavaScript Real-Time Updates**
- ⏱️ Update count setiap 30 detik
- 🔍 Cek unlock setiap 60 detik
- 🎊 Show unlock alert untuk kapsul baru
- 🔄 Auto refresh notification list

## 🚀 Setup & Installation

### 1. **Database Setup**
Jalankan upgrade database:
```bash
# Akses melalui browser
http://localhost/gamon/upgrade_notifications_db.php
```

### 2. **Cron Job Setup** (Opsional)
Untuk auto-check unlock capsules:
```bash
# Tambahkan ke crontab (Linux/Mac)
* * * * * php /path/to/gamon/cron/checkUnlockCapsules.php

# Atau jalankan manual untuk testing
php cron/checkUnlockCapsules.php
```

### 3. **Testing**
Akses halaman test untuk memverifikasi:
```bash
http://localhost/gamon/test_notifications.php
```

## 🎯 Cara Kerja

### 1. **Login Process**
```
User Login → Auth::checkUnlockNotificationsOnLogin() 
           → NotificationController::checkAndCreateUnlockNotifications()
           → Create notifications untuk kapsul yang unlock
```

### 2. **Send Message to Friend**
```
Create Capsule → NotificationController::createFriendMessageNotification()
              → Insert notification for receiver
              → Real-time update di navbar
```

### 3. **Real-Time Updates**
```
JavaScript Timer → API call every 30s → Update badge counter
                → API call every 60s → Check unlocked capsules
                → Show unlock alerts  → Create notifications
```

## 🎨 UI Components

### Notification Bell
- **Location**: Navbar kanan, sebelum logout button
- **Visual**: Bell icon dengan badge merah untuk unread count
- **Interaction**: Click → dropdown dengan recent notifications

### Notification Dropdown
- **Header**: Title + mark all read button
- **Body**: List 5 recent notifications dengan icon dan timestamp
- **Footer**: "Lihat Semua" link ke notifications.php
- **Animation**: Fade in/out dengan smooth transition

### Notification Items
- **Icon**: Emoji berdasarkan type (🎉 🔔 📨 💌)
- **Content**: Title, message, timestamp
- **Visual**: Unread = highlighted background
- **Action**: Click → mark as read + redirect

## 🔍 Testing & Debugging

### Manual Testing
1. **Login** → Cek apakah bell icon muncul
2. **Create test notification** via test_notifications.php
3. **Send message to friend** → Cek notifikasi muncul
4. **Wait for capsule unlock** → Cek notifikasi auto-create
5. **Click notifications** → Cek mark as read + redirect

### Debug Tools
- `test_notifications.php` - Manual testing interface
- `debug_logout.php` - Auth debugging
- Browser console - JavaScript errors
- `logs/unlock_activity.log` - Unlock activity

## 📊 Performance

### Database Queries
- Optimized dengan indexes pada `user_id` dan `is_read`
- Pagination untuk large notification lists
- Cleanup old notifications (30 hari)

### JavaScript
- Efficient polling (30s untuk count, 60s untuk unlock check)
- Cleanup intervals saat page unload
- Minimal DOM manipulation

### Caching
- Badge count di-cache di frontend
- Recent notifications di-refresh hanya saat dropdown dibuka

## 🔐 Security

### Authentication
- Semua API endpoint require login
- User hanya bisa akses notifikasi sendiri
- CSRF protection via Auth class

### Validation
- Input sanitization di semua endpoints
- SQL injection prevention dengan prepared statements
- XSS protection dengan htmlspecialchars

## 🎉 Success Indicators

Sistem notifikasi berhasil jika:
- ✅ Bell icon muncul di navbar semua halaman
- ✅ Badge counter update otomatis
- ✅ Dropdown menampilkan notifikasi
- ✅ Notifikasi dibuat saat kapsul unlock
- ✅ Notifikasi dibuat saat kirim ke teman
- ✅ Mark as read berfungsi
- ✅ Redirect ke action URL bekerja
- ✅ No JavaScript errors di console

## 📞 Support

Jika ada masalah:
1. Cek browser console untuk JavaScript errors
2. Akses `test_notifications.php` untuk debug
3. Cek `logs/unlock_activity.log` untuk cron activity
4. Verify database table `notifications` ada dan terisi

---
**🎊 Sistem Notifikasi GAMON siap digunakan!**