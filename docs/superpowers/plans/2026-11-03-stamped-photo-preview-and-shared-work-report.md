# Stamped Photo Preview Overlay & Multi-Day Shared Work Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menampilkan stempel watermark visual langsung di atas preview foto kegiatan, menyediakan pembakar stempel permanen ke file gambar (PHP GD), serta membangun modul berkas bukti dukung multi-hari dengan tautan publik resmi (*public shareable link*) untuk aplikasi pelaporan pusat.

**Architecture:** Mengganti placeholder preview form dengan custom blade overlay stempel langsung di atas preview gambar foto; membuat service `PhotoWatermarkService` berbasis PHP GD untuk membakar stempel ke file fisik foto; membuat entitas `SharedWorkReport` dengan UUID publik untuk membundel kegiatan multi-hari menjadi satu halaman publik resmi yang bebas login, responsif, dan dapat disalin tautannya ke aplikasi pusat (E-Kinerja BPS).

**Tech Stack:** Laravel 11, FilamentPHP v3, PHP GD Extension (native), TailwindCSS, Alpine.js, PHPWord (`WorkReportGeneratorService`).

**Spec:** Kebutuhan pengguna pada sesi 3 November 2026: koreksi preview stempel yang harus tampil sebagai watermark visual langsung di atas gambar foto, pembakaran stempel ke gambar, serta kebutuhan melampirkan satu link publik ke aplikasi pusat untuk pekerjaan multi-hari.

## Global Constraints
- Database PostgreSQL/MySQL: DILARANG menjalankan `migrate:fresh`, `db:wipe`, `truncate`, atau operasi drop data.
- Ekstensi PHP GD: Menggunakan modul `gd` native yang sudah terkonfirmasi aktif di FrankenPHP server produksi.
- Seluruh rute publik (`/bukti-dukung/share/{uuid}`) tidak boleh mewajibkan autentikasi login agar dapat diperiksa oleh pemeriksa pusat.
- Setiap task harus terverifikasi syntax PHP-nya sebelum commit.
- Alur rilis: Commit dengan pesan jelas, push ke `main`, update changelog `README.md`, dan deploy ke server via SSH.

## Review Focus
1. **Foto portrait vs landscape:** EXIF orientation harus dikoreksi sebelum penulisan stempel agar tulisan tidak miring/terbalik.
2. **Foto tanpa GPS:** Koordinat otomatis menggunakan default kantor BPS Kabupaten Demak (`-6.894561, 110.637492`) bila EXIF kosong.
3. **Kegiatan tanggal lompat-lompat:** Koleksi multi-hari harus dapat menggabungkan tanggal yang tidak berurutan secara urut kronologis.
4. **Keamanan URL Publik:** Menggunakan UUID v4 acak agar URL publik tidak dapat ditebak (*anti-enumeration*).
5. **Kinerja Halaman Publik:** Foto ditampilkan dengan thumbnail responsif dan fitur klik zoom lightbox tanpa membebani browser pemeriksa.

---

### Task 1: Pembersihan Tombol & Placeholder Form Lama

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php`

**Interfaces:**
- Menghapus: `Tables\Actions\Action::make('open_photo_stamp')` pada tabel.
- Menghapus: `Forms\Components\Placeholder::make('stamp_preview')` yang berada di kolom kanan samping foto.

- [ ] **Step 1: Hapus action `open_photo_stamp` di tabel `DailyActivityResource.php`**
  Hapus definisi action tabel `open_photo_stamp` yang sebelumnya membuka halaman stempel kosong.

- [ ] **Step 2: Hapus komponen placeholder `stamp_preview` di repeater attachments**
  Hapus blok `Forms\Components\Placeholder::make('stamp_preview')` di kolom samping agar form menjadi rapi dan bersih.

- [ ] **Step 3: Verifikasi sintaks PHP**
  Jalankan: `php -l app/Filament/Resources/DailyActivityResource.php`
  Harus: No syntax errors detected.

- [ ] **Step 4: Commit perubahan Task 1**
  ```bash
  git add app/Filament/Resources/DailyActivityResource.php
  git commit -m "refactor(daily-activity): remove obsolete photo stamp action and placeholder"
  ```

---

### Task 2: Custom Component Preview Stempel Visual Langsung di Atas Gambar

**Files:**
- Create: `resources/views/filament/forms/components/stamped-image-preview.blade.php`
- Modify: `app/Filament/Resources/DailyActivityResource.php`

**Interfaces:**
- Produces: Komponen Blade `stamped-image-preview` yang menampilkan gambar foto dengan lapisan overlay stempel semi-transparan tepat di atas gambar (pojok kiri bawah).
- Mengonsumsi: `$get('file_path')`, `$get('caption')`, `$get('latitude')`, `$get('longitude')`, `$get('captured_at')`, `$get('is_stamped')`.

- [ ] **Step 1: Buat view `resources/views/filament/forms/components/stamped-image-preview.blade.php`**
  Komponen menampilkan:
  - Container gambar dengan aspect ratio proporsional dan rounded corner.
  - Lapisan overlay watermark di pojok kiri bawah foto dengan background `rgba(0, 0, 0, 0.72)` dan teks kontras (kuning & putih).
  - Teks:
    - `📍 Lat: {latitude}, Long: {longitude}`
    - `🕒 {captured_at} WIB`
    - `📝 {caption || 'Dokumentasi Kegiatan'}`
    - `🏛️ BPS KABUPATEN DEMAK`
  - Indikator badge jika `is_stamped == true`: overlay digantikan badge hijau *"✅ Berstempel Kamera Bawaan"*.

- [ ] **Step 2: Pasang komponen ke repeater attachment di `DailyActivityResource.php`**
  Gantikan kolom upload dengan layout yang memuat `FileUpload` sekaligus `ViewField` / preview overlay stempel di atas foto.

- [ ] **Step 3: Uji coba sintaks dan view rendering**
  Jalankan `php -l app/Filament/Resources/DailyActivityResource.php` dan pastikan file view terdaftar.

- [ ] **Step 4: Commit Task 2**
  ```bash
  git add resources/views/filament/forms/components/stamped-image-preview.blade.php app/Filament/Resources/DailyActivityResource.php
  git commit -m "feat(daily-activity): add visible live watermark overlay on photo preview"
  ```

---

### Task 3: Service Pembakar Stempel Permanen ke File Gambar (`PhotoWatermarkService`)

**Files:**
- Create: `app/Services/PhotoWatermarkService.php`
- Modify: `app/Telegram/Handlers/DailyActivityTelegramHandler.php`
- Modify: `app/Filament/Resources/DailyActivityResource.php`

**Interfaces:**
- Produces: `PhotoWatermarkService::applyWatermark(string $fullPath, array $params): bool`
  - `$params`: `['latitude' => float, 'longitude' => float, 'captured_at' => Carbon|string, 'caption' => string, 'office_name' => string]`
- Returns: `true` jika berhasil membakar watermark ke file fisik gambar.

- [ ] **Step 1: Buat `app/Services/PhotoWatermarkService.php`**
  - Menggunakan PHP GD: `imagecreatefromjpeg`, `imagecreatefrompng`, `imagecreatefromwebp`.
  - Auto-rotasi EXIF orientation (`imagerotate`) agar foto HP tidak terbalik.
  - Hitung proporsi banner stempel (tinggi ~18-22% dari tinggi gambar).
  - Gambar kotak background hitam transparan (`imagecolorallocatealpha(..., 0, 0, 0, 45)`).
  - Tulis teks stempel dengan warna kontras (putih `#FFFFFF`, kuning `#FDE047`, hijau `#86EFAC`).
  - Simpan kembali ke file asli dengan kompresi kualitas tinggi (JPEG quality 90).

- [ ] **Step 2: Integrasikan ke `DailyActivityTelegramHandler.php`**
  Saat foto diterima via Telegram:
  Setelah file foto disimpan dan koordinat ditentukan (EXIF / default kantor), panggil `PhotoWatermarkService::applyWatermark()` secara otomatis. Tandai `is_stamped = true`.

- [ ] **Step 3: Tambahkan Aksi 1-Klik di Form Repeater Web `DailyActivityResource.php`**
  Tombol *"⚡ Terapkan Watermark ke File"* untuk foto yang diunggah manual di web agar file fisiknya langsung berstempel permanen.

- [ ] **Step 4: Verifikasi sintaks PHP**
  Jalankan:
  `php -l app/Services/PhotoWatermarkService.php`
  `php -l app/Telegram/Handlers/DailyActivityTelegramHandler.php`

- [ ] **Step 5: Commit Task 3**
  ```bash
  git add app/Services/PhotoWatermarkService.php app/Telegram/Handlers/DailyActivityTelegramHandler.php app/Filament/Resources/DailyActivityResource.php
  git commit -m "feat(watermark): add PhotoWatermarkService with GD engine and auto-stamp on telegram upload"
  ```

---

### Task 4: Skema Database & Model Berkas Publik Multi-Hari (`SharedWorkReport`)

**Files:**
- Create: `database/migrations/2026_11_03_144000_create_shared_work_reports_table.php`
- Create: `app/Models/SharedWorkReport.php`

**Interfaces:**
- Model `SharedWorkReport`:
  - Fields: `id`, `uuid`, `user_id`, `title`, `description`, `activity_ids` (cast: array), `surat_tugas_id`, `is_active`, `view_count`, `timestamps`.
  - Relations: `belongsTo(User::class)`, `belongsTo(SuratTugas::class)`.
  - Methods: `getActivities(): Collection` (mengambil model `DailyActivity` terkait secara terurut berdasarkan `activity_date` asc).
  - Accessors: `share_url` (menghasilkan full public URL).

- [ ] **Step 1: Buat migration `2026_11_03_144000_create_shared_work_reports_table.php`**
- [ ] **Step 2: Buat model `app/Models/SharedWorkReport.php` dengan relasi dan helper**
- [ ] **Step 3: Jalankan migration lokal**
  `php artisan migrate`
- [ ] **Step 4: Commit Task 4**
  ```bash
  git add database/migrations/2026_11_03_144000_create_shared_work_reports_table.php app/Models/SharedWorkReport.php
  git commit -m "feat(shared-report): create shared_work_reports table and model"
  ```

---

### Task 5: Bulk Action di Tabel Filament & Modal "Buat Link Berkas Publik"

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php`

**Interfaces:**
- Produces: `Tables\Actions\BulkAction::make('create_shared_report')`
  - Input: checklist multi-hari dari tabel kegiatan.
  - Form Modal: `title` (required), `description` (optional).
  - Action: Generate UUID, simpan `SharedWorkReport`, tampilkan modal/notifikasi berisi Tautan Publik yang siap di-copy.

- [ ] **Step 1: Tambahkan Bulk Action di `DailyActivityResource::table()`**
  Tersedia tombol *"🔗 Buat Berkas Publik Multi-Hari"* saat 1 atau beberapa baris kegiatan dicentang.
- [ ] **Step 2: Tampilkan Dialog Notifikasi Berisi Link Siap Salin**
  Menggunakan `Notification::make()->actions([...])` dengan tombol *"Salin Tautan 📋"* dan *"Buka Tautan 🌐"*.
- [ ] **Step 3: Tambahkan Tab / Menu Cepat Daftar Berkas Publik**
  Agar user bisa melihat kembali link-link berkas publik yang sudah pernah dibuat.
- [ ] **Step 4: Commit Task 5**
  ```bash
  git add app/Filament/Resources/DailyActivityResource.php
  git commit -m "feat(daily-activity): add bulk action to create multi-day shared public report"
  ```

---

### Task 6: Controller & Tampilan Web Halaman Publik Bebas Login

**Files:**
- Create: `app/Http/Controllers/SharedWorkReportController.php`
- Create: `resources/views/public/shared-work-report.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Route: `GET /bukti-dukung/share/{uuid}` -> `SharedWorkReportController@show`
- Route: `GET /bukti-dukung/share/{uuid}/download` -> `SharedWorkReportController@downloadDocx`
- View: `resources/views/public/shared-work-report.blade.php`

- [ ] **Step 1: Buat Controller `SharedWorkReportController.php`**
  - Mengambil record berdasarkan `uuid`.
  - Naikkan `view_count`.
  - Siapkan data profil pegawai, ringkasan durasi hari kerja, dan koleksi kegiatan + foto.
  - Method `downloadDocx` memanggil `WorkReportGeneratorService` untuk mengunduh rekap Word multi-hari.

- [ ] **Step 2: Buat Halaman Publik Blade `resources/views/public/shared-work-report.blade.php`**
  - Desain modern bernuansa kedinasan Badan Pusat Statistik (warna navy, putih, emas).
  - Kop resmi BPS Kabupaten Demak + Segel Keaslian Dokumen Digital.
  - Card Profil Pegawai (Nama Lengkap, NIP, Jabatan).
  - Ringkasan Statistik: Total Hari Kerja, Rentang Tanggal Kegiatan, Total Foto Bukti.
  - Linimasa Harian (Timeline Log):
    - Hari 1, Hari 2, dst. dengan tanggal kegiatan.
    - Narasi formal hasil Gemini AI.
    - Foto kegiatan berstempel dengan modal klik zoom lightbox.
  - Tombol Floating / Action Bar:
    - 📋 **Salin Link untuk Aplikasi Pusat (E-Kinerja)**.
    - 📄 **Unduh Dokumen Word (.docx)**.
    - 🖨️ **Cetak Halaman (Print PDF)**.

- [ ] **Step 3: Daftarkan rute di `routes/web.php`**
  Rute publik tanpa middleware auth.

- [ ] **Step 4: Commit Task 6**
  ```bash
  git add app/Http/Controllers/SharedWorkReportController.php resources/views/public/shared-work-report.blade.php routes/web.php
  git commit -m "feat(public-report): create public shareable report view and controller"
  ```

---

### Task 7: Verifikasi Menyeluruh, Update Changelog & Deployment Produksi

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Verifikasi Sintaks Seluruh File**
  Jalankan `php -l` pada semua file yang dibuat/dimodifikasi.
- [ ] **Step 2: Update Changelog di `README.md`**
  Catat fitur stempel visual overlay, auto-watermark GD engine, dan berkas publik multi-hari tanggal 2026-11-03.
- [ ] **Step 3: Git Commit & Push ke `main`**
- [ ] **Step 4: Deploy ke Server Produksi via SSH (`10.133.21.24`)**
  Jalankan migrasi di database server (tanpa drop), pull git main, dan restart container franken & telegram bot.
- [ ] **Step 5: Verifikasi di Server**
  Pastikan migration sukses dan container berjalan sehat (`Listening...`).
