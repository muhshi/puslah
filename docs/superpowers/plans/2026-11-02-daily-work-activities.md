# Daily Work Activities & Evidence Compiler Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun modul pencatatan laporan kinerja harian / bukti dukung terintegrasi di Puslah (Dinamit), mencakup input santai via Telegram Bot (teks & foto album), polesan narasi otomatis via Gemini AI, manajemen visual via Filament Resource, konversi otomatis ke Laporan Perjalanan Dinas (LPD) berstempel, dan compiler dokumen Word/PDF.

**Architecture:**
- **Core Persistence & Relationships:** Tabel `daily_activities`, `daily_activity_attachments`, dan `telegram_updates` (idempotency), terhubung opsional ke `users`, `surat_tugas`, dan `laporan_perjalanan_dinas`.
- **Telegram Bot Ingestion:** Menggunakan `nutgram/nutgram` untuk handle webhook, token pairing akun user, penerimaan pesan teks/foto, debounce media group (album foto), dan tombol inline approval/AI rewrite.
- **Gemini AI Service (`DailyActivityAiService`):** Menggunakan API Key & model dari `SystemSettings` yang sudah ada untuk mengubah bahasa santai menjadi narasi formal bukti dukung kinerja instansi tanpa halusinasi fakta.
- **Filament Management (`DailyActivityResource`):** CRUD log kegiatan harian, image gallery preview, tombol poles AI, shortcut stempel foto, dan aksi satu-klik konversi ke LPD.
- **Document Generator (`WorkReportGeneratorService`):** Template-driven compiler menggunakan PHPWord (`TemplateProcessor`) dengan layout tabel adaptif dan halaman lampiran untuk foto berlebih.

**Tech Stack:** Laravel 11, Filament v3, Nutgram v4, PHPWord 1.4, Gemini AI (Google Cloud), MySQL / Docker FrankenPHP.

---

## Global Constraints

- **Database Safety Rule:** DILARANG menjalankan `migrate:fresh`, `drop`, `truncate`, atau mengubah kolom destruktif pada tabel database yang sudah ada.
- **Preserve Existing Business Logic:** Tidak mengganggu modul Puslah yang sudah berjalan (`SuratTugas`, `Attendance`, `LaporanPerjalananDinas`, `PhotoStamp`).
- **Workflow Completion Rule:** Setiap tahapan selesai dan terverifikasi wajib di-commit, push ke repository git, dan dicatat di `README.md` Changelog.

---

## Review Focus

1. **Media Group Handling:** Album foto di Telegram terkirim sebagai event terpisah dengan `media_group_id` yang sama dalam selang milidetik — wajib diagregasi agar tidak menghasilkan kegiatan ganda.
2. **Nullable Surat Tugas:** Sebagian besar kegiatan harian adalah tugas rutin non-ST — kolom `surat_tugas_id` wajib nullable.
3. **Pemisahan Teks AI:** Teks mentah (`raw_content`) dan teks formal (`final_content`) harus dipisah agar user tetap memegang kendali atas kebenaran fakta laporan.
4. **Stempel Foto (Anti-Double Stamp):** Foto berstempel (Open Camera) tidak boleh ditimpa watermark lagi saat dialihkan ke LPD.
5. **Integritas File Foto:** Foto harus disimpan di storage privat/publik terstruktur dan dikompresi agar ukuran dokumen akhir tidak membengkak.

---

## Tasks

### Task 1: Migrasi Database & Schema Foundation

**Files:**
- Create: `database/migrations/2026_11_02_180000_add_telegram_fields_to_users_table.php`
- Create: `database/migrations/2026_11_02_180100_create_daily_activities_table.php`
- Create: `database/migrations/2026_11_02_180200_create_daily_activity_attachments_table.php`
- Create: `database/migrations/2026_11_02_180300_create_telegram_updates_table.php`

- [ ] Buat migrasi penambahan kolom Telegram pada tabel `users`:
  - `telegram_chat_id` (string/bigint, nullable, unique, indexed)
  - `telegram_auth_token` (string, nullable, indexed)
- [ ] Buat migrasi tabel `daily_activities`:
  - `id` (bigint unsigned)
  - `user_id` (foreignId -> users, cascade on delete)
  - `surat_tugas_id` (foreignId -> surat_tugas, nullable, set null on delete)
  - `activity_date` (date, indexed)
  - `started_at` (time, nullable)
  - `ended_at` (time, nullable)
  - `raw_content` (text)
  - `ai_formal_content` (text, nullable)
  - `final_content` (text)
  - `status` (enum: `draft`, `submitted`, `verified`, default `submitted`)
  - `source` (enum: `telegram`, `web`, default `web`)
  - `laporan_perjalanan_dinas_id` (foreignId -> laporan_perjalanan_dinas, nullable, set null on delete)
  - `timestamps` & index kombinasi (`user_id`, `activity_date`)
- [ ] Buat migrasi tabel `daily_activity_attachments`:
  - `id` (bigint unsigned)
  - `daily_activity_id` (foreignId -> daily_activities, cascade on delete)
  - `file_path` (string)
  - `file_name` (string, nullable)
  - `file_size` (unsignedBigInteger, nullable)
  - `mime_type` (string, nullable)
  - `is_stamped` (boolean, default false)
  - `latitude` (decimal 10,8, nullable)
  - `longitude` (decimal 11,8, nullable)
  - `captured_at` (datetime, nullable)
  - `caption` (string, nullable)
  - `sort_order` (integer default 0)
  - `timestamps`
- [ ] Buat migrasi tabel `telegram_updates`:
  - `id` (bigint unsigned)
  - `update_id` (unsignedBigInteger, unique, indexed)
  - `chat_id` (bigint, nullable, indexed)
  - `message_id` (bigint, nullable)
  - `media_group_id` (string, nullable, indexed)
  - `payload` (json, nullable)
  - `processed_at` (timestamp, nullable)
  - `timestamps`
- [ ] Eksekusi `php artisan migrate` dan verifikasi seluruh tabel terbuat dengan benar.

---

### Task 2: Eloquent Models & Relasi

**Files:**
- Create: `app/Models/DailyActivity.php`
- Create: `app/Models/DailyActivityAttachment.php`
- Create: `app/Models/TelegramUpdate.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/SuratTugas.php`

- [ ] Buat model `DailyActivity` lengkap dengan:
  - Fillable, casts (`activity_date` => 'date'), Spatie ActivityLog.
  - Relasi `user()`, `suratTugas()`, `attachments()`, `laporanPerjalananDinas()`.
  - Scopes: `forUser()`, `dateRange()`, `forMonth()`.
- [ ] Buat model `DailyActivityAttachment`:
  - Fillable, casts (`is_stamped` => 'boolean'), URL accessor (`storage_url`).
  - Relasi `dailyActivity()`.
- [ ] Buat model `TelegramUpdate`:
  - Fillable, casts (`payload` => 'array', `processed_at` => 'datetime').
- [ ] Modifikasi `User.php`:
  - Tambahkan `telegram_chat_id` & `telegram_auth_token` ke `$fillable`.
  - Tambahkan relasi `dailyActivities(): HasMany`.
- [ ] Modifikasi `SuratTugas.php`:
  - Tambahkan relasi `dailyActivities(): HasMany`.

---

### Task 3: Gemini AI Service (`DailyActivityAiService`)

**Files:**
- Create: `app/Services/DailyActivityAiService.php`

- [ ] Manfaatkan konfigurasi `SystemSettings` yang sudah ada (`gemini_api_key`, `gemini_model`).
- [ ] Rancang prompt sistem formalisasi kegiatan harian:
  - Input: bahasa percakapan santai, poin-poin catatan, atau keterangan aktivitas lapangan.
  - Context: kegiatan instansi pemerintah (BPS / dinas), tata bahasa baku, aktif profesional.
  - Aturan ketat: dilarang menambah nama pejabat/lokasi/angka yang tidak ada dalam teks input (anti-halusinasi).
- [ ] Method `formalize(string $rawText, ?string $context = null): string`.
- [ ] Unit/Integration test service dengan mock HTTP request.

---

### Task 4: Telegram Bot Integration (`nutgram/nutgram`)

**Files:**
- Modify: `composer.json` (tambahkan `nutgram/nutgram`)
- Create: `app/Telegram/Commands/StartCommand.php`
- Create: `app/Telegram/Handlers/DailyActivityHandler.php`
- Create: `routes/telegram.php` (atau registrasi handler di Service Provider)
- Modify: `routes/api.php`

- [ ] Install package `nutgram/nutgram` via Composer.
- [ ] Publish / konfigurasi config Nutgram:
  - Tambahkan environment variable `TELEGRAM_BOT_TOKEN` di `.env.example` dan config.
- [ ] Implementasi Handler Pairing Akun (`/start <token>`):
  - Memverifikasi `telegram_auth_token`, mengaitkan `chat_id` dengan akun `User` yang bersangkutan.
  - Mengirim pesan konfirmasi: "Selamat datang [Nama Pegawai], akun Puslah Anda telah berhasil terhubung!".
- [ ] Implementasi Handler Teks & Foto:
  - Menyimpan foto ke `storage/app/public/daily-activities/{user_id}/{Y-m-d}/`.
  - Mengidentifikasi `media_group_id` untuk album foto.
  - Membalas dengan konfirmasi dan inline button: `[✨ Rapikan Narasi]` & `[Lihat di Web]`.
- [ ] Implementasi Idempotency via `TelegramUpdate` untuk menghindari webhook retry.

---

### Task 5: Filament Admin Resource (`DailyActivityResource`)

**Files:**
- Create: `app/Filament/Resources/DailyActivityResource.php`
- Create: `app/Filament/Resources/DailyActivityResource/Pages/ListDailyActivities.php`
- Create: `app/Filament/Resources/DailyActivityResource/Pages/CreateDailyActivity.php`
- Create: `app/Filament/Resources/DailyActivityResource/Pages/EditDailyActivity.php`
- Create: `resources/views/filament/resources/daily-activity/gallery-preview.blade.php`

- [ ] Buat `DailyActivityResource` di navigasi grup "Kegiatan & Kinerja".
- [ ] Form Schema:
  - Section 1: Tanggal kegiatan, pilihan `surat_tugas_id` (opsional / searchable).
  - Section 2: Catatan Mentah (`raw_content`) + Tombol Action *"✨ Poles dengan Gemini AI"*.
  - Section 3: Narasi Final (`final_content`).
  - Section 4: FileUpload Repeater / Multiple untuk foto bukti dukung dengan toggle `is_stamped`.
- [ ] Table Schema:
  - Kolom: Tanggal, User (khusus admin/atasan), Surat Tugas (badge), Preview Thumbnail Foto (stack), Narasi Final.
  - Filter: Rentang Tanggal, Punya Surat Tugas / Tidak, Status.
- [ ] Table Actions:
  - Action **"🚀 Salin ke LPD"**: Jika kegiatan memiliki `surat_tugas_id`, langsung membuat draf `LaporanPerjalananDinas` dan memindahkan foto ke `LaporanFoto`.
  - Action **"📸 Stempel Foto"**: Melempar foto belum berstempel ke halaman `PhotoStamp`.
  - Action **"📄 Cetak Bukti Dukung (DOCX)"**.

---

### Task 6: PHPWord Template Compiler Service

**Files:**
- Create: `app/Services/WorkReportGeneratorService.php`
- Create: `storage/app/templates/template_bukti_dukung_harian.docx`

- [ ] Siapkan template master DOCX resmi dengan kop surat, tabel log kegiatan, dan kolom tanda tangan.
- [ ] Buat service `WorkReportGeneratorService`:
  - Menggunakan `PhpOffice\PhpWord\TemplateProcessor`.
  - Loop rows tabel kegiatan harian (`cloneRow`).
  - Injeksi foto secara proporsional (max-width / max-height fixed agar layout Word tidak rusak).
  - Pindahkan foto ke halaman lampiran dokumentasi jika lebih dari 2 foto per kegiatan.
- [ ] Sediakan endpoint unduh di Filament Resource.

---

### Task 7: Verifikasi, Changelog, Commit & Push

- [ ] Jalankan pengujian sintaks (`php artisan test` atau manual verification).
- [ ] Update `README.md` Changelog sesuai User Rule.
- [ ] Eksekusi `git add .`, `git commit -m "feat: implementasi pondasi laporan kinerja harian & integrasi AI"`, dan `git push`.
