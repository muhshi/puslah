# Daily Activity Enhancements: Auto-Detect ST, EXIF Extraction, Multi-Tier Reminder & Interactive Calendar

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyempurnakan modul bukti dukung harian Puslah dengan fitur auto-detect Surat Tugas aktif hari ini di Telegram, ekstraksi otomatis metadata EXIF GPS & timestamp foto, multi-tier reminder Telegram (15:00, 16:00, 18:30 WIB dengan pengecekan hari libur/cuti), serta Kalender Interaktif untuk deteksi hari kosong & input backdate satu-klik.

**Architecture:**
- **Auto-Detect Surat Tugas (Telegram):** Handler Telegram memeriksa penugasan aktif user pada hari ini (`waktu_mulai <= today <= waktu_selesai` atau `tanggal = today`). Jika ada 1 ST, otomatis ditautkan; jika >1 ST, sediakan opsi pilihan; jika 0, tandai sebagai Kegiatan Rutin.
- **EXIF GPS & Timestamp Extractor:** Service pembaca EXIF gambar (`exif_read_data`) untuk mendeteksi koordinat latitude/longitude desimal dan jam foto asli. Disimpan ke tabel `daily_activity_attachments` dan ditampilkan di form Filament dalam input field yang 100% bisa diedit manual.
- **Multi-Tier Reminder Command:** Artisan command `daily-activity:send-reminders` yang dipanggil pada jam 15:00, 16:00, dan 18:30 WIB. Memeriksa: (1) bukan akhir pekan (Sabtu/Minggu), (2) bukan cuti approved di tabel `leaves`, (3) belum ada kegiatan hari ini, (4) akun telegram terhubung.
- **Interactive Calendar Widget (`DailyActivityCalendarWidget`):** Widget FullCalendar pada halaman `DailyActivityResource` yang memetakan aktivitas user dengan kode warna (Hijau: Rutin, Biru: ST, Ungu: LPD) serta mendukung klik pada tanggal kosong (`dateClick`) untuk membuka form input backdate.

**Tech Stack:** Laravel 11, Filament v3, Filament FullCalendar, Nutgram v4, PHP EXIF, Carbon.

---

## Global Constraints

- **Database Safety Rule:** DILARANG menjalankan `migrate:fresh`, `drop`, atau `truncate`.
- **User Control Rule:** Data hasil ekstraksi EXIF (koordinat, waktu) bersifat saran default, user wajib bisa mengeditnya secara fleksibel di form.
- **Holiday Protection Rule:** Reminder Telegram TIDAK BOLEH dikirim pada hari libur (Sabtu, Minggu, hari libur nasional, dan pegawai yang sedang cuti approved).
- **Workflow Completion Rule:** Commit, push, dan update `README.md` Changelog setelah terverifikasi.

---

## Tasks

### Task 1: Auto-Detect Surat Tugas Hari Ini di Handler Telegram

**Files:**
- Modify: `app/Telegram/Handlers/DailyActivityTelegramHandler.php`

- [ ] Tambahkan method `resolveActiveSuratTugas(User $user, Carbon $date): ?SuratTugas`.
  - Cek penugasan user pada `$date`:
    - Rentang `waktu_mulai` s.d. `waktu_selesai` mencakup hari ini, ATAU `tanggal = today`.
    - Status bukan `rejected` / dibatalkan.
- [ ] Di `handleTextMessage` & `handlePhotoMessage`:
  - Jika user memiliki tepat 1 ST aktif hari ini, otomatis isi `surat_tugas_id`.
  - Tampilkan keterangan di notifikasi Telegram: `"🔖 Terkait: ST No. [Nomor ST] - [Keperluan]"`.
  - Jika tidak ada ST, tetap simpan sebagai Kegiatan Rutin harian.

---

### Task 2: Ekstraksi EXIF GPS & Timestamp Foto + Form Editor

**Files:**
- Create: `app/Services/ImageExifExtractorService.php`
- Modify: `app/Telegram/Handlers/DailyActivityTelegramHandler.php`
- Modify: `app/Filament/Resources/DailyActivityResource.php`

- [ ] Buat `ImageExifExtractorService`:
  - Method `extract(string $fullPath): array{latitude: ?float, longitude: ?float, captured_at: ?Carbon}`.
  - Parsing koordinat GPS format derajat-menit-detik (DMS) ke desimal (DD).
  - Parsing `DateTimeOriginal` EXIF ke objek Carbon.
- [ ] Integrasikan ke `DailyActivityTelegramHandler::handlePhotoMessage`:
  - Ekstrak EXIF dari foto yang baru diunduh dan simpan ke kolom `latitude`, `longitude`, `captured_at`.
- [ ] Modifikasi form `DailyActivityResource`:
  - Tambahkan kolom editable di Repeater `attachments`:
    - `captured_at`: DateTimePicker (Waktu Foto).
    - `latitude`: TextInput (Latitude GPS).
    - `longitude`: TextInput (Longitude GPS).
  - Kolom-kolom ini terisi otomatis saat upload dan dapat diedit bebas oleh user kapan saja.

---

### Task 3: Multi-Tier Daily Reminder Command (15:00, 16:00, 18:30 WIB)

**Files:**
- Create: `app/Console/Commands/SendDailyActivityReminderCommand.php`
- Modify: `routes/console.php`

- [ ] Buat Artisan Command: `daily-activity:send-reminders {--tier=15:00}`.
- [ ] Cek filter hari libur:
  - Skip jika hari ini Sabtu atau Minggu (`Carbon::today()->isWeekend()`).
  - Skip user jika user memiliki cuti `Leave` dengan status `approved` pada tanggal hari ini.
- [ ] Query user yang:
  - Memiliki `telegram_chat_id` valid.
  - Memiliki 0 `daily_activities` pada hari ini.
- [ ] Pesan reminder bergradasi sesuai jam (tier):
  - **15:00 WIB:** Pengingat santai jelang sore.
  - **16:00 WIB:** Pengingat menjelang jam pulang kantor.
  - **18:30 WIB:** Pengingat malam hari agar rekap harian tidak terlewat.
- [ ] Daftarkan jadwal di `routes/console.php` (cron schedule).

---

### Task 4: Interactive FullCalendar Widget di Bukti Dukung Harian

**Files:**
- Create: `app/Filament/Widgets/DailyActivityCalendarWidget.php`
- Modify: `app/Filament/Resources/DailyActivityResource/Pages/ListDailyActivities.php`

- [ ] Buat widget `DailyActivityCalendarWidget` turunan `FullCalendarWidget`:
  - Event query: mengambil kegiatan harian milik user yang login.
  - Skema warna event:
    - 🟢 `#10B981` (Hijau) jika Kegiatan Rutin.
    - 🔵 `#3B82F6` (Biru) jika Terkait Surat Tugas.
    - 🟣 `#8B5CF6` (Ungu) jika sudah disalin ke LPD.
  - Event title: `final_content` (dipersingkat) + ikon kamera jika ada foto.
  - Klik event (`onEventClick`): Membuka halaman edit kegiatan tersebut.
  - Klik tanggal kosong (`onDateSelect` / `dateClick`): Membuka form tambah kegiatan dengan `activity_date` otomatis terisi tanggal yang diklik!
- [ ] Pasang widget `DailyActivityCalendarWidget` di `ListDailyActivities::getHeaderWidgets()`.

---

### Task 5: Verifikasi, Changelog, Commit & Push

- [ ] Uji coba ekstraksi EXIF dengan sampel file gambar.
- [ ] Uji coba artisan reminder command secara lokal (`php artisan daily-activity:send-reminders --dry-run`).
- [ ] Uji coba auto-detect ST.
- [ ] Update `README.md` Changelog.
- [ ] Git commit & push.
