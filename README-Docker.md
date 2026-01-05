# 🕰️ GAMON - Aplikasi Pesan Kapsul Waktu

## 🐳 Menjalankan dengan Docker

### Prasyarat
- Docker
- Docker Compose

### Langkah-langkah Instalasi

1. **Clone atau download project ini**
   ```bash
   cd gamon
   ```

2. **Jalankan aplikasi dengan Docker**
   ```bash
   docker-compose up --build -d
   ```

3. **Akses aplikasi**
   - **Aplikasi utama**: http://localhost:9080
   - **phpMyAdmin**: http://localhost:9081
     - Server: `db`
     - Username: `root`
     - Password: `root_password`

### Layanan yang Berjalan

- **Web Server**: PHP 8.2 + Apache (Port 8080)
- **Database**: MySQL 8.0 (Port 3306)
- **phpMyAdmin**: Management database (Port 8081)

### Perintah Docker Berguna

```bash
# Lihat status container
docker-compose ps

# Lihat logs
docker-compose logs -f web
docker-compose logs -f db

# Masuk ke container web
docker-compose exec web bash

# Masuk ke database
docker-compose exec db mysql -u root -p

# Restart layanan
docker-compose restart

# Hentikan aplikasi
docker-compose down

# Reset database (hapus data)
docker-compose down -v
docker-compose up --build -d
```

### Konfigurasi Environment

Aplikasi akan otomatis mendeteksi apakah berjalan di Docker atau development lokal:

- **Docker**: Menggunakan environment variables dari docker-compose.yml
- **Lokal**: Menggunakan konfigurasi XAMPP (localhost, root, tanpa password)

### Fitur Aplikasi

1. **Registrasi & Login**: Sistem autentikasi pengguna
2. **Buat Kapsul**: Tulis pesan untuk masa depan
3. **Dashboard**: Lihat statistik dan kapsul terbaru  
4. **Kapsul Terkunci/Terbuka**: Kelola kapsul berdasarkan waktu
5. **Upload Gambar**: Lampirkan foto ke kapsul
6. **Mood Tracker**: Pilih mood saat membuat kapsul

### Troubleshooting

**Jika database tidak terhubung:**
```bash
docker-compose down
docker-compose up --build -d
```

**Jika port sudah digunakan:**
Edit `docker-compose.yml` dan ubah port:
```yaml
ports:
  - "9080:80"  # Ubah 9080 ke port lain
```

**Reset complete:**
```bash
docker-compose down -v
docker system prune -f
docker-compose up --build -d
```

### Development Mode

Untuk development, file code di host akan otomatis sync dengan container berkat volume mapping di docker-compose.yml.

Selamat menggunakan GAMON! 🚀