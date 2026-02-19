# Puslah — Sistem Manajemen Kepegawaian & Survei

Aplikasi internal **BPS Kabupaten Demak** berbasis Laravel + FilamentPHP untuk mengelola kepegawaian, surat tugas, sertifikat, survei, dan absensi.

## Tech Stack

- **Backend:** Laravel 11, PHP 8.2+
- **Admin Panel:** FilamentPHP v3
- **Database:** PostgreSQL
- **PDF:** barryvdh/laravel-dompdf, SimpleSoftwareIO/QrCode
- **Settings:** spatie/laravel-settings
- **Auth & RBAC:** filament-shield (Spatie Permission)
- **Calendar:** saade/filament-fullcalendar
- **Maps:** dotswan/filament-map-picker
- **Import/Export:** Maatwebsite/Excel

## Fitur Utama

| Modul | Deskripsi |
|---|---|
| **Surat Tugas** | Pembuatan single & bulk, approval workflow, PDF dengan footer & QR verifikasi, proteksi PDF (read-only), download ZIP |
| **Survei & Penugasan** | Pembuatan survei, assignment petugas, auto-deactivate expired, copy survey |
| **Sertifikat** | Generate sertifikat dengan template, approval, download |
| **Dashboard** | Statistik overview, chart survei bulanan, kalender kegiatan (FullCalendar) |
| **Import Pegawai/Mitra** | Import massal via Excel dengan validasi |
| **Pengaturan Sistem** | Lokasi kantor, jam kerja, template surat (.docx), penandatangan, proteksi PDF |
| **Landing Page** | Halaman publik untuk informasi dan formulir pengunjung |

## Instalasi

```bash
# Clone repository
git clone https://github.com/muhshi/puslah.git
cd puslah

# Install dependencies
composer install
npm install && npm run build

# Setup environment
cp .env.example .env
php artisan key:generate

# Konfigurasi database di .env lalu:
php artisan migrate
php artisan db:seed

# Jalankan
php artisan serve
```

## Changelog

### 2026-02-19
- **Proteksi PDF Surat Tugas** — Fitur password master untuk membuat PDF read-only (anti-edit). Konfigurasi di Pengaturan Sistem.
- **Footer PDF Surat Tugas** — Footer berisi QR code dan disclaimer "*Dokumen ini telah ditandatangani secara elektronik...*"
- **Verifikasi PDF** — Halaman verifikasi QR sekarang bisa menampilkan PDF langsung di browser.
- **Optimasi Pengaturan Sistem** — Hanya menyimpan field yang diubah; file path tidak tertimpa null dari environment lokal.
- **Tabel Surat Tugas** — Lebar kolom Nama Survei dan Pegawai dibatasi agar tabel lebih proporsional.
- **Favicon Sistem** — Menggunakan logo BPS pada tab browser.
- **Refinement Footer** — Update teks pada footer PDF Surat Tugas.

### 2026-02-10
- Auto-deactivate survei yang sudah expired.
- Sorting survei: aktif di atas, kemudian berdasarkan tanggal dibuat.
- Surat tugas diurutkan berdasarkan nomor urut.

### 2026-02-09
- Perbaikan teks keperluan di surat tugas (hapus kata "pendataan").

### 2026-01-30
- Ketua Tim dapat melihat semua surat tugas.
- Nomor surat yang dilewatkan (skip) dikategorikan per bulan dan ditampilkan merah.

### 2026-01-29
- Perbaikan template PDF: tambah kata "Melaksanakan" di keperluan.

### 2026-01-23 — 2026-01-24
- **Dashboard Calendar** — Kalender interaktif (FullCalendar) dengan toggle ST/LPD, detail modal, dan scoping per user/role.
- **Chart Survei Bulanan** — Grafik jumlah survei per bulan di dashboard.
- **Bulk PDF Download** — Download banyak surat tugas dalam satu file ZIP.
- **Bulk Approve** — Approve banyak surat tugas sekaligus.
- **Akses Ketua Tim** — Ketua Tim bisa melihat surat tugas + fitur Copy Survey.

### 2026-01-19
- Pembuatan bulk surat tugas bisa custom mulai dari nomor berapa.
- Notifikasi ketika semua petugas sudah dibuatkan surat tugas.

### 2026-01-13
- Fitur import user pegawai via Excel.
- Rebranding: ubah nama aplikasi dan landing page.

### 2026-01-12
- Import user (Mitra) dengan Excel + validasi.
- Fix bug approve/unapprove sertifikat dan download.
- Perbaikan akses role Admin Pegawai di survey user.

### 2026-01-08 — 2026-01-09
- PDF Surat Tugas: layout table-based, setting tampilan, download.
- Approval workflow surat tugas.
- Dashboard widget baru (Stats Overview).
- Verifikasi sertifikat dipercantik.

## License

Proprietary — BPS Kabupaten Demak. All rights reserved.
