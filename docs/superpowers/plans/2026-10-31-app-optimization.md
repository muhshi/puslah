# Application Performance Optimization & Pulse Removal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghilangkan overhead komputasi dan N+1 query, menghapus Laravel Pulse yang membebani database, menambahkan index database penting, mengaktifkan lazy loading widget dashboard, mengoptimalkan pipeline deploy Caddy/FrankenPHP, serta melakukan commit, push, dan remote deployment via SSH.

**Architecture:**
- **ORM & Query Layer:** Eager loading relasi utama (`sppd`, `laporanPerjalananDinas`, `user.profile`, `survey`) pada Filament Resource (`SuratTugasResource`, `LaporanPerjalananDinasResource`, `UserResource`, `AttendanceResource`) dan widgets untuk mengeliminasi N+1 query.
- **Database Indexing:** Migrasi terstruktur untuk menambahkan B-Tree index pada kolom pencarian dan filter berulang (`status`, `nomor_urut`, `tanggal`, `tanggal_kunjungan`).
- **Dashboard & UI:** Eliminasi Laravel Pulse dan widget terkait (`DailyVisitsWidget`, `DailyVisitsChartWidget`), aktivasi `isLazy` pada Chart Widgets Filament v3, dan standardisasi pengecekan role Spatie.
- **Infrastructure & Caching:** Caching static assets di Caddyfile, serta integrasi `icons:cache` dan `filament:optimize` di `deploy.sh`.

**Tech Stack:** Laravel 11, PHP 8.2, FilamentPHP v3, PostgreSQL / MySQL, FrankenPHP / Caddy, Docker.

---

## Global Constraints

- **Database Safety Rule:** TIDAK BOLEH menjalankan `migrate:fresh`, `drop`, atau `truncate` tabel data apapun. Migrasi hanya menambahkan index (`$table->index(...)`).
- **Browser Testing Rule:** Jangan gunakan browser subagent untuk testing, biarkan user mengecek langsung di browser.
- **Workflow Completion Rule:** Setiap perbaikan yang selesai dan terverifikasi wajib di-commit, push, dan diperbarui di `README.md` Changelog.

---

## Tasks

### Task 1: Hapus Laravel Pulse & Bersihkan Dependencies & Dashboard

**Files:**
- Modify: `composer.json`
- Delete: `app/Filament/Pages/PulseAnalytics.php`
- Delete: `app/Filament/Widgets/DailyVisitsWidget.php`
- Delete: `app/Filament/Widgets/DailyVisitsChartWidget.php`
- Delete: `config/pulse.php`
- Modify: `app/Filament/Pages/Dashboard.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`

- [ ] Hapus `laravel/pulse` dari `composer.json`.
- [ ] Hapus file `app/Filament/Pages/PulseAnalytics.php`, `DailyVisitsWidget.php`, `DailyVisitsChartWidget.php`, dan `config/pulse.php`.
- [ ] Update `app/Filament/Pages/Dashboard.php` untuk menghapus referensi kedua widget tersebut dan perbaiki `isPegawai()` menjadi `Auth::user()->hasRole('Pegawai BPS')`.
- [ ] Hapus gate `viewPulse` di `app/Providers/AppServiceProvider.php`.
- [ ] Bersihkan sisa komentar Pulse di `AdminPanelProvider.php`.
- [ ] Jalankan `composer update laravel/pulse --no-scripts` atau `composer dump-autoload` untuk memverifikasi autoload bersih.

---

### Task 2: Eliminasi N+1 Query pada Surat Tugas, LPD, User & Attendance

**Files:**
- Modify: `app/Filament/Resources/SuratTugasResource.php`
- Modify: `app/Filament/Resources/LaporanPerjalananDinasResource.php`
- Modify: `app/Filament/Resources/UserResource.php`
- Modify: `app/Filament/Resources/AttendanceResource.php`
- Modify: `app/Filament/Widgets/LatestSuratTugasWidget.php`

- [ ] Di `SuratTugasResource.php`:
  - Tambahkan eager loading di `getEloquentQuery()`: `->with(['user.profile', 'survey', 'sppd'])` dan `->withCount('laporanPerjalananDinas')`.
  - Ganti `$record->sppd()->exists()` pada action `generate_sppd` dan `word_sppd` dengan pengecekan relasi yang sudah di-load di memori (`$record->sppd !== null`).
  - Optimalkan `view_lpd` dan `generate_lpd` agar memanfaatkan `laporan_perjalanan_dinas_count` dan relasi `laporanPerjalananDinas` tanpa query berulang per baris.
- [ ] Di `LaporanPerjalananDinasResource.php`:
  - Tambahkan eager loading di `modifyQueryUsing()` / `getEloquentQuery()`: `->with(['suratTugas.survey', 'suratTugas.user'])`.
- [ ] Di `UserResource.php`:
  - Tambahkan method `getEloquentQuery()` dengan `->with(['profile', 'roles'])`.
- [ ] Di `LatestSuratTugasWidget.php`:
  - Eager load `user`: `SuratTugas::query()->with('user')->latest()->limit(5)`.
- [ ] Di `AttendanceResource.php`:
  - Eager load `user`: `modifyQueryUsing(fn($q) => $q->with('user'))` dan perbaiki pengecekan role `Auth::user()->hasRole('super_admin')`.

---

### Task 3: Penambahan Database Index pada Kolom Kunci

**Files:**
- Create: `database/migrations/2026_10_31_000001_add_performance_indexes_to_tables.php`

- [ ] Buat migration baru dengan proteksi pengecekan nama index (`hasIndex` / `indexExists`).
- [ ] Tambahkan index pada tabel `surat_tugas`:
  - `status`
  - `nomor_urut`
  - `tanggal`
  - composite `['tanggal', 'nomor_urut']`
  - composite `['user_id', 'status']`
  - `created_by`
  - composite `['waktu_mulai', 'waktu_selesai']`
- [ ] Tambahkan index pada tabel `laporan_perjalanan_dinas`:
  - `tanggal_kunjungan`
  - composite `['surat_tugas_id', 'tanggal_kunjungan']`
- [ ] Jalankan migrasi: `php artisan migrate --force`.

---

### Task 4: Optimasi Dashboard & Widget Responsiveness (Lazy Loading)

**Files:**
- Modify: `app/Filament/Widgets/SuratTugasChartWidget.php`
- Modify: `app/Filament/Widgets/SurveyChartWidget.php`

- [ ] Tambahkan `protected static bool $isLazy = true;` pada `SuratTugasChartWidget.php`.
- [ ] Tambahkan `protected static bool $isLazy = true;` pada `SurveyChartWidget.php`.
- [ ] Verifikasi widget dashboard terbuka mulus tanpa blocking render.

---

### Task 5: Optimasi Caching Asset di FrankenPHP Caddyfile & Deployment Script

**Files:**
- Modify: `Caddyfile`
- Modify: `deploy.sh`

- [ ] Update `Caddyfile`: Tambahkan static asset caching header untuk file CSS, JS, font, dan image:
  ```caddy
  @static {
      path /build/* *.png *.jpg *.jpeg *.svg *.woff2 *.css *.js
  }
  header @static Cache-Control "public, max-age=31536000, immutable"
  ```
- [ ] Update `deploy.sh`: Tambahkan optimasi Filament pada langkah [4/6]:
  ```bash
  docker exec "$CONTAINER_NAME" php artisan icons:cache
  docker exec "$CONTAINER_NAME" php artisan filament:optimize
  ```

---

### Task 6: Verifikasi, Commit, Push, dan Remote SSH Deployment

**Files:**
- Modify: `README.md`

- [ ] Jalankan verifikasi lokal: `php artisan route:list`, `php artisan config:cache`, `php artisan test`.
- [ ] Perbarui `README.md` pada bagian Changelog dengan tanggal hari ini (2026-10-31) mencakup semua optimasi.
- [ ] Lakukan `git add .`, `git commit -m "perf: optimize queries, indexes, dashboard, and remove pulse"`, dan `git push origin main`.
- [ ] Hubungkan ke server via SSH `bpsdemak@10.133.21.24`, masuk ke `apps/puslah`, dan jalankan `bash deploy.sh`.
- [ ] Verifikasi output deployment di server.
