# 🎵 Mood Music System - GAMON

Sistem musik untuk setiap mood di aplikasi GAMON. Musik akan diputar saat user memilih mood di halaman buat kapsul dan saat melihat kapsul yang sudah dibuat.

## 📁 Struktur Direktori

```
uploads/music/moods/
├── bahagia.mp3
├── senang.mp3  
├── antusias.mp3
├── bersemangat.mp3
├── bersyukur.mp3
├── cinta.mp3
├── bangga.mp3
├── lega.mp3
├── tenang.mp3
├── optimis.mp3
├── biasa_saja.mp3
├── bingung.mp3
├── ragu.mp3
├── lelah.mp3
├── fokus.mp3
├── santai.mp3
├── sedih.mp3
├── kecewa.mp3
├── cemas.mp3
├── gugup.mp3
├── stres.mp3
├── marah.mp3
├── takut.mp3
├── penasaran.mp3
├── berharap.mp3
├── bermimpi.mp3
├── berdoa.mp3
├── rindu.mp3
├── nostalgia.mp3
├── petualangan.mp3
├── kreatif.mp3
├── produktif.mp3
├── reflektif.mp3
├── celebrasi.mp3
├── romantic.mp3
└── [mood lainnya].mp3
```

## 🎼 Spesifikasi File Musik

- **Format**: MP3 (recommended)
- **Durasi**: 2-5 menit (akan loop otomatis)
- **Kualitas**: 128kbps - 320kbps
- **Volume**: Disesuaikan agar tidak terlalu keras (akan auto-set ke 30%)
- **Ukuran**: Maksimal 10MB per file untuk performa optimal

## 🎨 Rekomendasi Genre per Kategori Mood

### Mood Positif (Bahagia, Senang, Antusias)
- Upbeat acoustic
- Light pop instrumental
- Happy jazz
- Cheerful electronic

### Mood Energetic (Bersemangat, Bangga, Fokus)
- Motivational orchestral
- Electronic beats
- Rock instrumental
- Epic music

### Mood Tenang (Tenang, Lega, Santai)
- Ambient music
- Soft piano
- Nature sounds
- Meditation music

### Mood Romantis (Cinta, Romantic, Rindu)
- Soft jazz
- Piano ballads
- String quartet
- Acoustic love songs

### Mood Reflektif (Nostalgia, Reflektif, Bermimpi)
- Vintage music
- Soft classical
- Indie folk
- Contemplative piano

### Mood Sedih (Sedih, Kecewa, Cemas)
- Melancholic piano
- Slow strings
- Rain sounds
- Minor key compositions

## 🔧 Cara Upload Musik

1. **Siapkan file musik** sesuai dengan nama mood di database
2. **Convert ke MP3** jika belum (gunakan tools seperti Audacity/FFmpeg)
3. **Upload ke folder** `uploads/music/moods/`
4. **Test di aplikasi** dengan memilih mood tersebut

## 💡 Tips

- Pastikan nama file **persis sama** dengan kolom `music_file` di database
- Gunakan musik **bebas copyright** atau yang sudah dibeli lisensinya
- Test volume musik sebelum upload
- Pertimbangkan mood yang ingin diciptakan saat memilih musik

## 🎮 Fitur yang Tersedia

✅ **Auto-play** saat memilih mood di halaman buat kapsul  
✅ **Auto-play** saat membuka detail kapsul  
✅ **Volume control** otomatis (30%)  
✅ **Loop music** untuk durasi yang lebih panjang  
✅ **Browser-friendly** dengan graceful fallback  
✅ **Mobile responsive** dan kompatibel semua device  

## 📊 Statistik Database

Total mood dengan musik: **50+ mood**  
Format yang didukung: **MP3**  
Auto-loop: **✓ Ya**  
Volume default: **30%**

---

💡 **Tip**: Untuk testing cepat, copy file musik apapun dan rename sesuai dengan nama mood yang ada di database!