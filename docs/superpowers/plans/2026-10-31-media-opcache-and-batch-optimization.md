# Media Compression, OPcache & Batch Query Optimization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengoptimalkan ukuran media foto dokumentasi dengan auto-resize, memaksimalkan efisiensi PHP FrankenPHP via OPcache tuning, menjadwalkan pembersihan log database berkala, mengeliminasi query berulang pada pembuatan Surat Tugas kolektif, dan merapikan kode render PDF.

**Architecture:**
- **Media Optimization:** Penerapan client/server image auto-resizing pada komponen Filament `FileUpload` di `LaporanPerjalananDinasResource` dan `LaporanLemburResource` (target 1280px, ratio preserved, mode cover, upscale false).
- **Engine Performance:** Konfigurasi parameter OPcache di `docker/php.ini` (`memory_consumption=256`, `max_accelerated_files=20000`, `validate_timestamps=0`).
- **Database Maintenance:** Penjadwalan cron harian untuk `activitylog:clean` di `routes/console.php`.
- **Batch Processing:** Preloading data user & profil sebelum loop pembuatan Surat Tugas di `CreateBulkSuratTugas.php`.
- **Code Quality:** Refaktor endpoint verifikasi PDF di `routes/web.php` untuk memanggil `SuratTugasPdfService`.

**Tech Stack:** Laravel 11, FilamentPHP v3, FrankenPHP / Caddy, Spatie ActivityLog, Docker.

---

## Global Constraints

- **Database Safety Rule:** TIDAK BOLEH menjalankan `migrate:fresh`, `drop`, atau `truncate` data apapun.
- **Browser Testing Rule:** Jangan gunakan browser subagent untuk testing, biarkan user mengecek langsung di browser.
- **Workflow Completion Rule:** Setiap perbaikan yang selesai dan terverifikasi wajib di-commit, push, dan diperbarui di `README.md` Changelog.

---

## Tasks

### Task 1: Auto-Resize & Kompresi Foto Dokumentasi (LPD & Lembur)

**Files:**
- Modify: `app/Filament/Resources/LaporanPerjalananDinasResource.php`
- Modify: `app/Filament/Resources/LaporanLemburResource.php`

- [ ] Di `LaporanPerjalananDinasResource.php` (komponen `file_path`):
  - Tambahkan `->imageResizeMode('cover')`
  - Tambahkan `->imageResizeTargetWidth('1280')`
  - Tambahkan `->imageResizeTargetHeight('1280')`
  - Tambahkan `->imageResizeUpscale(false)`
- [ ] Di `LaporanLemburResource.php` (komponen `foto_1`, `foto_2`, `foto_3`, `foto_4`):
  - Tambahkan konfigurasi auto-resize 1280px yang sama pada masing-masing field foto.

---

### Task 2: Tuning OPcache Produksi di Docker

**Files:**
- Modify: `docker/php.ini`

- [ ] Tambahkan konfigurasi OPcache di `docker/php.ini`:
  ```ini
  opcache.enable = 1
  opcache.enable_cli = 1
  opcache.memory_consumption = 256
  opcache.interned_strings_buffer = 16
  opcache.max_accelerated_files = 20000
  opcache.validate_timestamps = 0
  opcache.save_comments = 1
  ```

---

### Task 3: Jadwalkan Pembersihan Rutin Activity Log

**Files:**
- Modify: `routes/console.php`

- [ ] Di `routes/console.php`, tambahkan jadwal harian:
  ```php
  \Illuminate\Support\Facades\Schedule::command('activitylog:clean')->daily();
  ```

---

### Task 4: Optimasi Pre-fetching Query pada Pembuatan Surat Tugas Kolektif

**Files:**
- Modify: `app/Filament/Resources/SuratTugasResource/Pages/CreateBulkSuratTugas.php`

- [ ] Di `CreateBulkSuratTugas.php` method `create()`:
  - Sebelum loop `foreach ($userIds as $userId)`:
    - Pre-load seluruh profil: `$profiles = \App\Models\UserProfile::whereIn('user_id', $userIds)->get()->keyBy('user_id');`
    - Pre-load seluruh user: `$users = \App\Models\User::whereIn('id', $userIds)->get()->keyBy('id');`
  - Di dalam loop:
    - Gunakan `$profiles[$userId] ?? null` untuk mendapatkan jabatan pegawai tanpa query DB berulang.
    - Gunakan `$users[$userId] ?? null` jika membutuhkan data user.

---

### Task 5: Refaktor Duplikasi Kode Render PDF di Web Routes

**Files:**
- Modify: `routes/web.php`

- [ ] Sederhanakan closure route `/surat-tugas/verify/{hash}/pdf` agar memanggil `app(SuratTugasPdfService::class)->renderPdf($surat)`.

---

### Task 6: Verifikasi, Changelog, Git Commit, Push & Remote Deploy SSH

**Files:**
- Modify: `README.md`

- [ ] Update Changelog di `README.md` pada tanggal 2026-10-31.
- [ ] Lakukan `git add .`, `git commit -m "..."`, dan `git push origin main`.
- [ ] Jalankan deploy via SSH: `ssh bpsdemak@10.133.21.24 "cd apps/puslah && bash deploy.sh"`.
- [ ] Verifikasi status container dan HTTP response.
