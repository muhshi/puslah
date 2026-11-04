# Daily Activity Enhancements (Telegram Backdate, QR-Code, Watermark Position, Templates, & Monthly Recap) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghadirkan fitur Natural Date Backdate di Telegram, QR-Code verifikasi digital pada buku PDF bukti dukung, Smart Position Watermark foto (Kiri Bawah/Kanan Bawah/Kiri Atas), Katalog Template Kegiatan Rutin vs Berkala BPS, serta Rekapan Hari Kosong Akhir Bulan (Command Telegram & Visual Kalender Web).

**Architecture:** 
- `DailyActivityTelegramHandler`: Menambahkan parser tanggal bahasa alami (relatif: `kemarin`, `kemarin lusa`, `H-x`; eksplisit: `tgl 28/10`, `tanggal 28 Oktober 2026`; dan auto-detect dari metadata EXIF foto lampau).
- `SharedWorkReportController` & `pdf/shared-work-report-book`: Injeksi base64 QR-Code verifikasi publik pada lembar pengesahan buku PDF.
- `PhotoWatermarkService` & `DailyActivityResource`: Penambahan dukungan opsi posisi stempel watermark (`bottom-left`, `bottom-right`, `top-left`) dengan migrasi kolom `stamp_position` pada tabel `daily_activity_attachments`.
- `DailyActivityResource`: Aksi modal Katalog Template Kegiatan BPS dengan pemisahan tab/kategori *Rutin Tahunan* (Sakernas, Susenas, SHPB, KSA, PDRB) dan *Berkala/Sensus* (Sensus Ekonomi, SBR, Wilkerstat, SKLNP, Inovasi IT).
- `SendMonthlyActivitySummaryCommand` & Telegram `/rekap`: Rekapan otomatis hari kerja kosong akhir bulan via Telegram dan visualisasi peringatan hari kosong di kalender web.

**Tech Stack:** Laravel 11, Nutgram (Telegram Bot), FilamentPHP v3, DomPDF, SimpleSoftwareIO/QrCode, PHP GD Image Processing, FullCalendar.

**Spec:** Sesi 4 November 2026: Persetujuan pengguna terhadap roadmap 5 fitur pengembangan bukti dukung harian.

## Global Constraints

- Jangan drop, truncate, atau delete database.
- Jangan migrate fresh. Setiap perubahan skema dilakukan via migrasi penambahan kolom baru secara aman.
- Jangan gunakan subagent browser untuk testing; verifikasi via script dan CLI.
- Workflow selesai: `git add .`, `git commit -m "..."`, `git push`, dan perbarui Changelog di `README.md`.

## Review Focus

- Deteksi tanggal di Telegram harus akurat membedakan hari ini vs hari lampau (misal: "kemarin", "tgl 28/10", "28 Oktober") tanpa memotong isi narasi penting.
- Foto lampau yang dikirim via Telegram tanpa tanggal di teks harus mengambil tanggal dari metadata EXIF kamera asli jika tersedia.
- QR-Code pada berkas PDF harus dapat dibaca oleh pembaca QR standar dan mengarah ke link publik bukti dukung.
- Posisi stempel Kanan Bawah dan Kiri Atas tidak boleh overflow dari batas gambar fisik.
- Template narasi BPS harus langsung mengisi field form dan mendukung polesan AI satu-klik.
- Pengecekan hari kosong hanya memperhitungkan hari kerja (Senin–Jumat) di luar akhir pekan.

---

### Task 1: Natural Date Parser di Telegram Bot (Teks, Caption & EXIF Foto)

**Files:**
- Modify: `app/Telegram/Handlers/DailyActivityTelegramHandler.php`
- Modify: `routes/telegram.php`
- Test: `scratch/test_telegram_date_parser.php`

**Interfaces:**
- Consumes: Pesan Telegram (`$msg->text`, `$msg->caption`), `$exifData['captured_at']`
- Produces: `$activityDate = $this->resolveActivityDate($rawText, $exifData['captured_at'] ?? null)`

- [ ] **Step 1: Implementasi method `resolveActivityDate(string $text, ?Carbon $exifCapturedAt = null): Carbon` di `DailyActivityTelegramHandler.php`**
Mendukung:
- "kemarin lusa" -> `Carbon::yesterday()->subDay()`
- "kemarin" -> `Carbon::yesterday()`
- "X hari lalu" / "X hari yang lalu" -> `Carbon::today()->subDays($x)`
- "tgl DD/MM", "tanggal DD-MM-YYYY", "tgl DD/MM/YYYY"
- "tgl DD [Bulan Indo] [YYYY]" (misal: "28 Oktober", "28 okt 2026")
- Fallback ke `$exifCapturedAt` jika foto diambil di hari lampau
- Default ke `Carbon::today()`

- [ ] **Step 2: Sambungkan `resolveActivityDate` ke handler pesan teks, foto tunggal, dan album foto**
- Perbarui `DailyActivity::whereDate('activity_date', $activityDate)`
- Isi field `'activity_date' => $activityDate`
- Sertakan tanggal dalam pesan balasan Telegram (tampilkan badge `[Backdate]` jika bukan hari ini).

- [ ] **Step 3: Uji parser tanggal via script scratch**
Run: `php scratch/test_telegram_date_parser.php`
Expected: Seluruh skenario tanggal lulus dengan benar.

---

### Task 2: Smart Position Watermark Foto (Kiri Bawah, Kanan Bawah, Kiri Atas)

**Files:**
- Create: `database/migrations/2026_11_04_080000_add_stamp_position_to_daily_activity_attachments_table.php`
- Modify: `app/Services/PhotoWatermarkService.php`
- Modify: `app/Models/DailyActivityAttachment.php`
- Modify: `app/Filament/Resources/DailyActivityResource.php`

**Interfaces:**
- Consumes: `$params['position']` (`bottom-left`, `bottom-right`, `top-left`)
- Produces: Watermark tercetak presisi di pojok yang dipilih

- [ ] **Step 1: Buat dan jalankan migrasi penambahan kolom `stamp_position`**
Run: `php artisan migrate` (pastikan tanpa drop tabel)

- [ ] **Step 2: Perbarui `PhotoWatermarkService.php` untuk menghitung `$boxX` dan `$boxY` berdasarkan parameter `position`**
- `'bottom-left'`: `$boxX = 16`, `$boxY = $height - $boxHeight - 16`
- `'bottom-right'`: `$boxX = $width - $boxWidth - 16`, `$boxY = $height - $boxHeight - 16`
- `'top-left'`: `$boxX = 16`, `$boxY = 16`

- [ ] **Step 3: Tambahkan input posisi stempel di Repeater Foto `DailyActivityResource.php`**
Field `Select::make('stamp_position')` dengan opsi:
`['bottom-left' => 'Pojok Kiri Bawah (Default)', 'bottom-right' => 'Pojok Kanan Bawah', 'top-left' => 'Pojok Kiri Atas']`.

---

### Task 3: QR-Code Verifikasi Digital pada Berkas PDF Resmi Bukti Dukung

**Files:**
- Modify: `app/Http/Controllers/SharedWorkReportController.php:57-77`
- Modify: `resources/views/pdf/shared-work-report-book.blade.php:250-265`

**Interfaces:**
- Consumes: `$report->uuid`, `route('shared-work-report.show', ['uuid' => $report->uuid])`
- Produces: `$qrBase64` disematkan di footer lembar pengesahan PDF

- [ ] **Step 1: Generate QR-Code SVG Base64 di `SharedWorkReportController::downloadPdf`**
```php
$publicUrl = route('shared-work-report.show', ['uuid' => $report->uuid]);
$qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(90)->margin(0)->generate($publicUrl);
$qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
```

- [ ] **Step 2: Tampilkan blok QR-Code verifikasi di tabel tanda tangan `pdf/shared-work-report-book.blade.php`**
Menampilkan QR-Code mini di sebelah kiri nama pegawai dengan teks panduan verifikasi.

---

### Task 4: Katalog Template Narasi Kegiatan BPS (Rutin Tahunan vs Berkala/Sensus)

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php`

**Interfaces:**
- Consumes: Modal Action form / Action dropdown
- Produces: Mengisi `raw_content` dan `final_content` dengan draft baku BPS

- [ ] **Step 1: Siapkan dataset katalog template narasi BPS**
- **Rutin Tahunan:** Sakernas, Susenas, Survei Harga (SHPB/HKD), Kompilasi PDRB, KSA Padi/Jagung.
- **Berkala / Sensus:** Sensus Ekonomi, Updating SBR, Wilkerstat, SKLNP, Pengembangan Sistem IT Puslah.

- [ ] **Step 2: Pasang aksi modal `use_template` pada field `raw_content` di `DailyActivityResource.php`**
Tombol *"📋 Template BPS"* yang menampilkan pilihan kategori dan pilihan survei, lalu otomatis mengisi draft narasi saat dipilih.

---

### Task 5: Rekapan Hari Kosong Akhir Bulan (Telegram Command `/rekap` & Visual Kalender)

**Files:**
- Create: `app/Console/Commands/SendMonthlyActivitySummaryCommand.php`
- Modify: `routes/telegram.php`
- Modify: `app/Telegram/Handlers/DailyActivityTelegramHandler.php`
- Modify: `app/Filament/Widgets/DailyActivityCalendarWidget.php`

**Interfaces:**
- Consumes: Log `DailyActivity` sebulan berjalan, hari kerja (Senin–Jumat)
- Produces: Pesan rekap Telegram & highlight visual hari kosong di kalender web

- [ ] **Step 1: Buat method `sendMonthlySummary` dan command `/rekap` di Telegram**
Menghitung persentase hari kerja terisi dan mendaftar tanggal hari kerja mana saja yang belum ada laporannya.

- [ ] **Step 2: Tambahkan artisan command `daily-activity:send-monthly-recap`**
Dapat dijalankan terjadwal di akhir bulan.

- [ ] **Step 3: Tambahkan penanda hari kerja kosong di `DailyActivityCalendarWidget.php`**
Event berwarna amber (`#F59E0B`) bertuliskan `[Kosong] Belum Ada Laporan` pada hari kerja lampau yang belum diisi.

---

### Task 6: Verifikasi Menyeluruh, Git Commit, Push & Update Changelog

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Lint dan jalankan pengujian backend**
- [ ] **Step 2: Update `README.md` Changelog**
- [ ] **Step 3: Commit dan Push ke Git**
