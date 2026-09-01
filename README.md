# 📋 E-Piket - Sistem Manajemen Guru Piket

Aplikasi web untuk manajemen guru piket di sekolah dengan fitur laporan dan monitoring lengkap.

## 🚀 Fitur Utama

### 📊 Dashboard
- Statistik real-time guru, kelas, mata pelajaran
- Monitoring kehadiran guru hari ini
- Guru piket hari ini
- Absensi siswa hari ini

### 👥 Manajemen Data
- **Guru** - CRUD data guru dengan NIP, mata pelajaran
- **Mata Pelajaran** - Kelola mata pelajaran per kategori
- **Kelas** - Kelola kelas beserta wali kelas
- **Pengguna** - Manajemen akun dan hak akses

### 📅 Jadwal
- **Jadwal Mengajar** - Grid jadwal per hari dan kelas
- **Jadwal Piket** - Jadwal piket guru per hari/minggu

### ✅ Absensi
- **Absensi Guru Mengajar** - Input kehadiran guru saat mengajar per kelas & mata pelajaran (tepat waktu/terlambat/tidak hadir/digantikan)

### 📋 Laporan
- **Laporan Mengajar** - Rekap guru mengajar per kelas dengan filter
- **Laporan Absensi Guru** - Rekap kehadiran guru mengajar per kelas dengan grafik bar
- **Monitoring Kehadiran Guru** - Rekap kehadiran guru per mata pelajaran dengan persentase disiplin

### 🔍 Monitoring
- Status kedatangan guru: Tepat Waktu, Terlambat, Tidak Hadir, Digantikan
- Rekap per mata pelajaran dan per guru
- Persentase kehadiran/kepatuhan

### 🚪 Kelas Kosong (Laporan + Penanganan)
- **Input kejadian**: guru piket mencatat kelas kosong (tanggal, kelas, jam, guru yang seharusnya mengajar, mata pelajaran) — pilih jadwal mengajar dan form terisi otomatis
- **Penyebab**: sakit, izin, dinas luar, lupa jadwal, tanpa keterangan
- **Penanganan**: belum ditangani, diingatkan via WhatsApp, ditelepon, digantikan, didampingi, dilaporkan ke waka kesiswaan
- **Laporan**: filter periode/kelas/penanganan, kartu statistik (total, belum/sudah ditangani, kelas terdampak), cetak & CSV
- Tombol WhatsApp untuk mengingatkan guru yang seharusnya mengajar

### ⚡ AJAX Live (Tanpa Refresh)
- Semua halaman data (Guru, Mapel, Kelas, Jadwal, Jadwal Piket, Absensi, Monitoring, Kelas Kosong, Laporan, Pengguna) berjalan **AJAX**
- Tambah/edit/hapus & filter disubmit lewat `fetch` → data grid & statistik diperbarui otomatis + notifikasi toast — **tanpa reload halaman**
- Tetap ada fallback normal (redirect) jika JavaScript/AJAX tidak tersedia

### 💬 Pengingat WhatsApp (Guru Piket → Guru)
- Tombol **WhatsApp** (ikon hijau) di halaman **Kelas**, **Absensi Guru Mengajar**, dan **Monitoring Guru**
- Satu klik membuka chat WhatsApp ke guru pengajar dengan pesan pengingat otomatis: nama guru, hari/tanggal, jam, kelas, mata pelajaran, dan ruangan
- Nomor HP otomatis dikonversi ke format internasional (`08xx` → `62xx`) — simpan nomor guru dengan format `08xx` di halaman **Guru**
- Jika nomor kosong/tidak valid, tombol tidak ditampilkan

> ⚠️ **Agar tombol muncul**: pastikan kolom **No. HP** guru terisi (contoh: `081234567890`). WhatsApp harus terinstall di perangkat untuk membuka tautan `wa.me`.

## 🛠️ Tech Stack

| Component | Technology |
|-----------|------------|
| **Frontend** | Bootstrap 5.3.3, Bootstrap Icons 1.11 |
| **Animation** | GSAP 3.12.7 |
| **Backend** | PHP 7.4+ / PHP 8.0+ |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Font** | Google Fonts (Inter) |

## 📦 Instalasi

### Prasyarat
- PHP 7.4 atau lebih tinggi
- MySQL 5.7+ atau MariaDB 10.3+
- Web Server (Apache/Nginx/XAMPP)

### Langkah Setup

1. **Clone/Copy** project ke folder web server
   ```
   cp -r e-piket /var/www/html/  # atau htdocs untuk XAMPP
   ```

   > 💡 **BASE_URL otomatis**: Aplikasi mendeteksi lokasi folder secara otomatis, jadi bisa dijalankan dari root maupun subfolder apa pun (contoh: `http://localhost/` atau `http://localhost/e-piket/`). Tidak perlu mengubah kode.

2. **Buka halaman setup** di browser:
   ```
   http://localhost/e-piket/setup.php
   ```

3. **Isi konfigurasi database** dan klik "Setup Database"

4. **Login** dengan akun default:
   - Username: `admin`
   - Password: `password`

### Setup Manual
1. Buat database `e_piket` di MySQL
2. Import file `db/e_piket.sql` (sudah berisi 25 guru & 22 mapel asli dari PDF jadwal 2026/2027)
3. Edit `config/database.php` sesuai konfigurasi server

> 💡 **Data guru & mapel** bersumber dari PDF "JADWAL PELAJARAN 2026-2027 - SMK Tunas Grafika Informatika Jakarta".
> - `id` guru = kode guru di PDF (2–26), NIP = placeholder (`20260002` dst.) — perbaiki di halaman **Guru**
> - No. HP guru **kosong** (tidak ada di PDF) — isi agar tombol pengingat WhatsApp muncul
> - Mapel untuk **Intan Irawati** tidak terbaca di PDF (kosong) — lengkapi di halaman Guru
> - Untuk re-import: `mysql -u root e_piket < db/import_guru_mapel_2026.sql`

## 📁 Struktur Folder

```
e-piket/
├── admin/                  # Halaman admin
│   ├── dashboard.php      # Dashboard utama
│   ├── guru.php           # Manajemen guru
│   ├── mapel.php          # Manajemen mata pelajaran
│   ├── kelas.php          # Manajemen kelas
│   ├── jadwal.php         # Jadwal mengajar
│   ├── jadwal-piket.php   # Jadwal piket
│   ├── absensi.php        # Input absensi
│   ├── laporan-mengajar.php # Laporan mengajar
│   ├── laporan-absensi.php  # Laporan absensi
│   ├── monitoring.php     # Monitoring kehadiran
│   └── users.php          # Manajemen pengguna
├── assets/
│   ├── css/style.css      # Custom stylesheet
│   └── js/app.js          # Custom JavaScript
├── config/
│   └── database.php       # Konfigurasi DB
├── db/
│   └── e_piket.sql        # Database schema
├── includes/
│   ├── header.php         # Header & sidebar
│   └── footer.php         # Footer & scripts
├── index.php              # Login page
├── logout.php             # Logout handler
├── setup.php              # Database setup wizard
└── README.md              # Dokumentasi
```

## 🎨 Tema

Menggunakan **Dark Theme** dengan:
- Glass morphism effect
- Gradient accents (Blue → Purple)
- Smooth GSAP animations
- Responsive design (mobile-friendly)
- Print-friendly report styles

## 🔐 Autentikasi

| Role | Akses |
|------|-------|
| Admin | Full access |
| Guru Piket | Input monitoring, absensi |
| Guru | View jadwal, laporan |

## 📄 License

MIT License - Gratis untuk penggunaan pendidikan

---

**E-Piket** © 2026 - Dibuat untuk memudahkan manajemen guru piket di sekolah 🏫
