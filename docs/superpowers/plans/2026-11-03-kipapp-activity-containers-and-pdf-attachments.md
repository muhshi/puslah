# KIPAPP Activity Containers & PDF Bukti Dukung Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun sistem Wadah Kegiatan KIPAPP / E-Kinerja dengan tautan publik permanen untuk setiap butir kegiatan (misal: Wilkerstat, Pengembangan Inovasi), auto-tagging hashtag Telegram, relasi langsung kegiatan harian, serta mendukung lampiran dokumen PDF selain foto.

**Architecture:** Memperluas entitas `SharedWorkReport` dengan `tag_slug`, `period_start`, dan `period_end`; menambahkan `shared_work_report_id` pada `DailyActivity` agar terhubung langsung ke wadah butir KIPAPP; memperbarui `DailyActivityTelegramHandler` untuk mengenali hashtag (#wilkerstat, dsb.); memperluas `FileUpload` di web & bot agar menerima berkas `PDF` dengan kartu dokumen resmi di view publik dan dokumen Word; serta membuat `SharedWorkReportResource` di panel admin Filament.

**Tech Stack:** Laravel 11, FilamentPHP v3, PHP GD Extension, TailwindCSS, Alpine.js, PHPWord, Nutgram Telegram Bot.

**Spec:** Sesi 3 November 2026: KIPAPP memerlukan 1 link publik bukti dukung per butir kegiatan SKP yang link-nya permanen selama periode 1-3 bulan, dan lampiran bukti dukung dapat berupa dokumen PDF (SK, Berita Acara, Undangan) di samping foto lapangan berstempel.

## Global Constraints
- DILARANG drop, truncate, wipe, atau fresh database yang menghapus data.
- Setiap migrasi hanya menggunakan `Schema::table` atau `Schema::create`.
- Halaman publik `/bukti-dukung/share/{uuid}` wajib bebas login (akses penilai KIPAPP pusat).
- Setiap task harus lolos `php -l` tanpa syntax error sebelum commit.
- Alur rilis: Commit, Push `main`, update Changelog di `README.md`, dan deploy SSH ke server produksi `10.133.21.24`.

## Review Focus
1. **Handling Berkas PDF vs Foto:** Foto menampilkan watermark stempel koordinat & jam, sedangkan PDF menampilkan kartu dokumen resmi dengan tombol pratinjau/buka PDF.
2. **Hashtag Telegram (#wilkerstat, #inovasi):** Bot mengekstrak hashtag dari teks caption dan mencocokkannya ke `tag_slug` wadah KIPAPP milik user.
3. **Link Publik Permanen:** Satu URL `/bukti-dukung/share/{uuid}` tetap sama dan otomatis menampilkan seluruh kegiatan yang dikaitkan ke wadah tersebut secara *live sync*.
4. **Export Dokumen Word (.docx):** Foto masuk ke galeri visual, dokumen PDF masuk ke tabel rincian lampiran dokumen berkas.
5. **Dukungan Multi-User:** Setiap user hanya melihat wadah KIPAPP dan link miliknya sendiri.

---

### Task 1: Migrasi Database untuk Wadah KIPAPP & Relasi DailyActivity

**Files:**
- Create: `database/migrations/2026_11_03_153000_add_fields_to_shared_work_reports_and_daily_activities.php`
- Modify: `app/Models/SharedWorkReport.php`
- Modify: `app/Models/DailyActivity.php`

**Interfaces:**
- `shared_work_reports`: tambah `tag_slug` (string, nullable, index), `period_start` (date, nullable), `period_end` (date, nullable).
- `daily_activities`: tambah `shared_work_report_id` (foreignId, nullable, constrained nullOnDelete).

- [ ] **Step 1: Buat migration schema**
- [ ] **Step 2: Update model `SharedWorkReport` & `DailyActivity` dengan relasi `hasMany` / `belongsTo`**
- [ ] **Step 3: Verifikasi sintaks PHP**
- [ ] **Step 4: Commit Task 1**

---

### Task 2: Dukungan Lampiran Berkas PDF di Web & Halaman Publik

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php`
- Modify: `resources/views/filament/forms/components/stamped-image-preview.blade.php`
- Modify: `resources/views/public/shared-work-report.blade.php`
- Modify: `app/Services/WorkReportGeneratorService.php`

**Interfaces:**
- `FileUpload` menerima `image/*` dan `application/pdf` dengan batas ukuran 10MB.
- `stamped-image-preview.blade.php`: jika file PDF, render kartu PDF merah dengan tombol preview; jika foto, render preview stempel koordinat.
- `shared-work-report.blade.php`: memisahkan galeri foto berstempel dan galeri dokumen berkas PDF.
- `WorkReportGeneratorService`: mencantumkan lampiran PDF dalam tabel kelengkapan dokumen berkas.

- [ ] **Step 1: Update form `DailyActivityResource` agar menerima `application/pdf`**
- [ ] **Step 2: Update preview blade component untuk membedakan PDF dan Gambar**
- [ ] **Step 3: Update halaman publik untuk menampilkan dokumen PDF secara elegan**
- [ ] **Step 4: Update generator Word (.docx) agar menangani lampiran PDF**
- [ ] **Step 5: Verifikasi sintaks PHP**
- [ ] **Step 6: Commit Task 2**

---

### Task 3: Integrasi Telegram Bot (Auto-Tagging Hashtag KIPAPP)

**Files:**
- Modify: `app/Telegram/Handlers/DailyActivityTelegramHandler.php`

**Interfaces:**
- Deteksi hashtag pada caption foto/pesan Telegram (contoh: `#wilkerstat`, `#inovasi`).
- Cari `SharedWorkReport` aktif milik user dengan `tag_slug` yang cocok.
- Jika ditemukan, otomatis pasang `shared_work_report_id` pada `DailyActivity` yang dibuat.
- Kirim pesan balasan konfirmasi: `"📁 Dikaitkan ke Berkas KIPAPP: [Nama Berkas]"`.

- [ ] **Step 1: Tambahkan method parser hashtag di `DailyActivityTelegramHandler`**
- [ ] **Step 2: Tautkan kegiatan ke `shared_work_report_id` jika hashtag cocok**
- [ ] **Step 3: Beri informasi wadah KIPAPP pada respons konfirmasi Telegram**
- [ ] **Step 4: Verifikasi sintaks PHP**
- [ ] **Step 5: Commit Task 3**

---

### Task 4: Filament Resource "Wadah Berkas KIPAPP" (`SharedWorkReportResource`)

**Files:**
- Create: `app/Filament/Resources/SharedWorkReportResource.php`
- Create: `app/Filament/Resources/SharedWorkReportResource/Pages/ListSharedWorkReports.php`
- Create: `app/Filament/Resources/SharedWorkReportResource/Pages/CreateSharedWorkReport.php`
- Create: `app/Filament/Resources/SharedWorkReportResource/Pages/EditSharedWorkReport.php`
- Modify: `app/Filament/Resources/DailyActivityResource.php`

**Interfaces:**
- Resource baru di menu `Manajemen Dokumen > Wadah Berkas KIPAPP`:
  - Form: Judul Butir Kegiatan, Hashtag Telegram (slug), Periode (Mulai s.d. Selesai), Deskripsi, Surat Tugas Terkait (opsional).
  - Tabel: Judul, Hashtag, Periode, Jumlah Kegiatan Terkumpul, Tautan Publik (Copyable), Dilihat (X kali).
  - Aksi Baris: Salin Tautan KIPAPP, Buka Halaman Publik, Unduh Word (.docx), Edit, Hapus.
- Di `DailyActivityResource`:
  - Tambah kolom dropdown `shared_work_report_id` di form tambah/edit.
  - Tambah Bulk Action: `"Tautkan ke Wadah KIPAPP"`.

- [ ] **Step 1: Buat `SharedWorkReportResource` beserta Form & Table schema**
- [ ] **Step 2: Pasang filter kepemilikan user (user hanya melihat wadah KIPAPP miliknya)**
- [ ] **Step 3: Hubungkan dropdown di form `DailyActivityResource` dan Bulk Action**
- [ ] **Step 4: Verifikasi sintaks PHP**
- [ ] **Step 5: Commit Task 4**

---

### Task 5: Changelog, Push & Deploy Server Produksi

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update changelog di `README.md`**
- [ ] **Step 2: Git push `main` ke remote repository**
- [ ] **Step 3: Deploy via SSH ke server `10.133.21.24` (git pull, php artisan migrate, optimize:clear, restart docker)**
- [ ] **Step 4: Verifikasi log container server & konfirmasi ke pengguna**
