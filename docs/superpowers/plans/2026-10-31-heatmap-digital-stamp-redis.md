# Workload Heatmap, Digital Approval Stamp & Redis Cache Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengimplementasikan visual Heatmap Beban Kerja Petugas untuk pemerataan penugasan, menyempurnakan stempel dinas / TTE digital pada Surat Tugas approved, dan mengintegrasikan Redis container untuk caching serta session ultra-cepat di lingkungan produksi FrankenPHP.

**Architecture:**
- **Workload Heatmap Module:** Halaman Filament Page baru `WorkloadHeatmap` dengan filter bulan/tahun, menghitung densitas penugasan harian per petugas (Pegawai BPS & Mitra), visualisasi grid kalender berwarna (0 tugas = hijau/kosong, 1 = biru, 2+ = oranye/merah peringatan), dan ringkasan total hari tugas.
- **Digital Approval Stamp & Signature:** Penyempurnaan template PDF `pdf_table_layout.blade.php` dan `SuratTugasPdfService` untuk menampilkan kotak pengesahan TTE resmi ber-QR Code dengan stempel dinas digital saat status surat `approved`, serta watermark/placeholder bersih saat status `pending`.
- **Redis Cache & Session Infrastructure:** Penambahan service container `puslah-redis` (`redis:7-alpine`) di `docker-compose.yml`, kompilasi ekstensi `phpredis` di `Dockerfile`, serta penyesuaian konfigurasi cache/session/queue dengan fallback otomatis.

**Tech Stack:** Laravel 11, FilamentPHP v3, Redis 7 Alpine, DomPDF, FrankenPHP / Docker.

---

## Global Constraints

- **Database Safety Rule:** TIDAK BOLEH menjalankan `migrate:fresh`, `drop`, atau `truncate` tabel data apapun.
- **Browser Testing Rule:** Jangan gunakan browser subagent untuk testing, biarkan user mengecek langsung di browser.
- **Workflow Completion Rule:** Setiap perbaikan yang selesai dan terverifikasi wajib di-commit, push, dan diperbarui di `README.md` Changelog.

---

## Tasks

### Task 1: Stempel Dinas Digital & Approval Signature pada Surat Tugas PDF

**Files:**
- Modify: `resources/views/surat-tugas/pdf_table_layout.blade.php`
- Modify: `app/Services/SuratTugasPdfService.php`
- Modify: `app/Filament/Resources/SuratTugasResource.php`

- [ ] Di `SuratTugasPdfService.php`:
  - Pastikan hash digenerate otomatis saat render/approval.
  - Sediakan logo stempel/tanda tangan jika ada di storage atau default SVG stempel BPS.
- [ ] Di `resources/views/surat-tugas/pdf_table_layout.blade.php`:
  - Desain ulang blok tanda tangan pejabat:
    - Jika `approved`: tampilkan QR verifikasi tajam dengan badge resmi TTE BPS Demak ("Telah diverifikasi & disahkan secara digital").
    - Jika `pending`: tampilkan watermark/badge "[DRAFT - BELUM DISETUJUI]" tanpa gambar QR rusak.
- [ ] Di `SuratTugasResource.php`:
  - Pastikan action `approve` membuat verifikasi hash jika belum ada dan memicu notifikasi sukses.

---

### Task 2: Halaman Visual Heatmap Beban Kerja Petugas (`WorkloadHeatmap`)

**Files:**
- Create: `app/Filament/Pages/WorkloadHeatmap.php`
- Create: `resources/views/filament/pages/workload-heatmap.blade.php`

- [ ] Buat page Filament `WorkloadHeatmap` di bawah navigation group `Manajemen Dokumen` atau `Manajemen Survei`.
- [ ] Implementasikan query agregasi tanggal penugasan dalam satu bulan terpilih:
  - Ambil semua Surat Tugas aktif dalam rentang bulan tersebut (`waktu_mulai` s/d `waktu_selesai` atau `tanggal`).
  - Petakan penugasan ke setiap hari (1 sampai jumlah hari dalam bulan tersebut) per pegawai/mitra.
- [ ] Buat Blade view `workload-heatmap.blade.php`:
  - Kontrol pemilihan Bulan dan Tahun (default bulan dan tahun berjalan).
  - Filter tipe pegawai (Semua, Pegawai Organik BPS, Mitra Statistik).
  - Tabel grid interaktif: Kolom Nama Pegawai, Total Hari Tugas, dan sel hari 1–31 dengan pewarnaan densitas (0 = abu/hijau muda, 1 = biru, >=2 = oranye/merah bertanda bentrok).
  - Tooltip/popover pada sel untuk melihat survei apa yang sedang dijalankan pada tanggal tersebut.

---

### Task 3: Integrasi Redis Container di Docker & Dockerfile

**Files:**
- Modify: `Dockerfile`
- Modify: `docker-compose.yml`

- [ ] Di `Dockerfile`:
  - Tambahkan instalasi ekstensi PHP `redis`:
    ```dockerfile
    RUN pecl install redis && docker-php-ext-enable redis
    ```
- [ ] Di `docker-compose.yml`:
  - Tambahkan container `puslah-redis` menggunakan image `redis:7-alpine`.
  - Pasang resource limit (`mem_limit: 128m`) dan volume persistensi `redis-data`.
  - Hubungkan ke network `default`.
  - Tambahkan dependensi `depends_on: [puslah-redis]` pada `puslah-franken`, `puslah-worker`, dan `puslah-scheduler`.
  - Set environment variable `REDIS_HOST=puslah-redis` dan `CACHE_STORE=redis`, `SESSION_DRIVER=redis`.

---

### Task 4: Verifikasi, Changelog, Git Commit, Push & Remote Deploy SSH

**Files:**
- Modify: `README.md`

- [ ] Verifikasi lokal: `php artisan optimize:clear`.
- [ ] Update `README.md` Changelog pada tanggal 2026-10-31.
- [ ] Lakukan `git add .`, `git commit -m "feat: add workload heatmap, digital approval stamp on PDF, and integrate redis cache"`, dan `git push origin main`.
- [ ] Jalankan remote deploy via SSH: `ssh bpsdemak@10.133.21.24 "cd apps/puslah && bash deploy.sh --build"`.
- [ ] Verifikasi status Redis container, PHP redis extension, dan akses HTTP.
