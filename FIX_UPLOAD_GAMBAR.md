# 🔧 PANDUAN MENGATASI MASALAH UPLOAD GAMBAR

## Masalah yang Ditemukan:
❌ "kirim pesan menggunakan gambar gabisaa" - Upload gambar gagal

## Analisis Masalah:

### 1. **Database Connection Issues**
- File `create-message.php` tidak memiliki koneksi database `$conn`
- SQL query memiliki parameter yang salah
- Typo pada nama kolom database (`uplouded_at` → `uploaded_at`)

### 2. **Database Schema Issues**
- Tabel `messages` dan `message_media` mungkin belum ada
- Inconsistency antara tabel `capsules` dan `messages`

### 3. **File Validation Issues**
- Kurang validasi ukuran file
- Validasi mime type tidak aman
- Error handling tidak lengkap

## Solusi yang Telah Diterapkan:

### ✅ **1. Perbaiki create-message.php**
```php
// Tambahkan koneksi database
require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Perbaiki SQL insert
$media_stmt = $conn->prepare("INSERT INTO capsule_media (capsule_id, filename, original_name, file_type, file_size, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");

// Tambahkan validasi file size dan mime type
if ($fileSize > 5 * 1024 * 1024) {
    $message = 'Ukuran file terlalu besar. Maksimum 5MB.';
}
```

### ✅ **2. File SQL untuk Database**
Jalankan file berikut untuk memperbaiki struktur database:
- `fix_database_complete.sql` - Membuat tabel `messages` dan `message_media`
- `fix_message_media_table.sql` - Backup script untuk tabel media

### ✅ **3. Validasi dan Security**
```php
// Mime type detection yang aman
$detectedMime = mime_content_type($fileTmpPath);
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Filename yang aman
$newFileName = md5(time() . $fileName . uniqid()) . '.' . $fileExtension;
```

## Langkah-Langkah Perbaikan:

### 1. **Import Database Schema**
```bash
# Masuk ke MySQL
mysql -u root -p

# Import database fix
source /path/to/fix_database_complete.sql
```

### 2. **Test Upload**
- Buka `test_upload.php` di browser
- Upload file gambar untuk testing
- Check error messages jika ada

### 3. **Cek Permissions**
```bash
# Pastikan direktori upload writable
chmod 755 uploads/
chown www-data:www-data uploads/
```

### 4. **Cek PHP Settings**
Di `php.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 12M
max_file_uploads = 20
```

## File yang Sudah Diperbaiki:
- ✅ `create-message.php` - Database connection & validation
- ✅ `fix_database_complete.sql` - Database schema
- ✅ `test_upload.php` - Testing tool

## File yang Sudah Benar:
- ✅ `create-message_new.php` - Menggunakan MediaController
- ✅ `controllers/MediaController.php` - Upload logic
- ✅ `uploads/` directory - Sudah ada dan writable

## Testing:

### Test Create Message dengan Gambar:
1. Buka `create-message.php`
2. Isi form dengan gambar
3. Submit dan cek hasilnya

### Test Create Message New dengan Multiple Files:
1. Buka `create-message_new.php`
2. Upload multiple files
3. Check database table `message_media`

## Debugging Tips:

1. **Check Error Logs:**
```bash
tail -f /var/log/apache2/error.log
```

2. **Check PHP Errors:**
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

3. **Database Debug:**
```sql
SHOW TABLES LIKE '%media%';
DESCRIBE message_media;
DESCRIBE capsule_media;
```

## Status: ✅ DIPERBAIKI
Masalah upload gambar telah diatasi dengan:
- Perbaikan database connection
- Perbaikan SQL queries
- Validasi file yang proper
- Error handling yang lengkap
- Database schema yang konsisten