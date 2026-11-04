# LPD Multi-Activity & Chronological AI Generator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengintegrasikan Bukti Dukung Harian ke Laporan Perjalanan Dinas (LPD) dengan agregasi multi-kegiatan satu hari, penyusunan narasi kronologis berbasis jam secara otomatis via AI Gemini, dan modal editor interaktif sebelum pembuatan LPD resmi.

**Architecture:** 
1. Service layer `LpdAiService` mengekstrak seluruh catatan dan waktu foto dari kumpulan `DailyActivity` pada hari penugasan yang sama, lalu menyintesisnya menjadi 4 segmen narasi kronologis formal BPS (`Pukul HH.MM–HH.MM WIB`) beserta pejabat dan lokasi.
2. Filament Action `convert_to_lpd` di `DailyActivityResource` diubah menjadi modal interaktif yang mengagregasikan seluruh kegiatan dan foto hari itu, menyediakan preview dan edit narasi AI.
3. Form manual `LaporanPerjalananDinasResource` dilengkapi tombol *"📥 Impor dari Bukti Dukung Harian"* untuk sinkronisasi dua arah.

**Tech Stack:** Laravel 11, Filament v3, Livewire v3, Google Gemini API via `LpdAiService`, Carbon, PHP GD.

**Spec:** Pembicaraan dan kesepakatan kebutuhan LPD kronologis BPS (pagi-siang-sore, multi-kegiatan, tanpa peluru/numbering, berjam tebal).

## Global Constraints
- Tidak boleh menjalankan perintah yang menghapus, drop, atau truncate database (`RULE[user_global]`).
- Tidak boleh migrate fresh (`RULE[dinamit.md]`).
- Jangan gunakan browser subagent untuk testing (`RULE[dinamit.md]`).
- Selesai implementasi: wajib commit, push, update changelog `README.md`, dan jalankan `deploy.sh` ke server produksi (`bpsdemak@10.133.21.24`).

## Review Focus
1. **Multi-kegiatan pada hari yang sama:** Seluruh kegiatan pada tanggal dan ST yang sama harus di-link ke `LaporanPerjalananDinas` yang baru dibuat, bukan hanya 1 baris yang diklik.
2. **Koleksi Foto Lengkap:** Semua attachment foto dari semua kegiatan hari itu harus disalin ke `laporan_foto` secara kronologis tanpa duplikasi.
3. **Format Jam Kronologis:** Output narasi AI harus konsisten `<p><strong>Pukul HH.MM–HH.MM WIB:</strong> ...</p>` mencakup perjalanan berangkat, koordinasi teknis, ISHOMA, dan kepulangan.
4. **Fallback bila AI gagal:** Jika koneksi/API Gemini timeout, narasi gabungan catatan kasar tetap tersusun runtut per jam sebagai draf awal yang dapat diedit manual.
5. **Validasi Kuota LPD:** Validasi 1 hari = 1 LPD per pegawai tetap terjaga dengan pesan informasi yang ramah.

---

### Task 1: Engine Agregasi & Generator Narasi Kronologis di `LpdAiService`

**Files:**
- Modify: `app/Services/LpdAiService.php`
- Test: `scratch/test_lpd_chronological_ai.php`

**Interfaces:**
- Produces: `LpdAiService::generateChronologicalFromActivities(Collection $activities, SuratTugas $suratTugas, User $user, Carbon $activityDate): array{narasi: string, nama_pejabat: ?string, desa_pejabat: ?string, summary_items: array}`

- [x] **Step 1: Tambahkan method `generateChronologicalFromActivities` di `LpdAiService.php`**
  Mengumpulkan seluruh `raw_content`, `final_content`, dan metadata waktu attachment (`captured_at` atau `created_at`) dari setiap kegiatan. Merangkai prompt khusus format kronologis 4 segmen (Keberangkatan, Substansi & Koordinasi, ISHOMA, Kepulangan) dengan instruksi format tag `<p><strong>Pukul HH.MM–HH.MM WIB:</strong> ...</p>`.

- [x] **Step 2: Buat fallback algoritma kronologis lokal jika API Gemini tidak tersedia**
  Jika API key belum disetel atau ada kendala koneksi, susun draf kronologis dasar per jam berdasarkan waktu bukti dukung yang ada agar proses tidak macet.

- [x] **Step 3: Verifikasi sintaks dan pengujian unit logic**
  Jalankan `php -l app/Services/LpdAiService.php` dan buat scratch script untuk memverifikasi parsing output JSON narasi, pejabat, dan lokasi.

- [x] **Step 4: Commit Task 1**
  ```bash
  git add app/Services/LpdAiService.php
  git commit -m "feat(lpd): tambahkan generator narasi kronologis LPD multi-kegiatan di LpdAiService"
  ```

---

### Task 2: Transformasi Aksi "Salin ke LPD" di `DailyActivityResource` Menjadi Modal Agregator Cerdas

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php`

**Interfaces:**
- Consumes: `LpdAiService::generateChronologicalFromActivities`
- Updates: Seluruh `DailyActivity` pada tanggal & ST yang sama (`laporan_perjalanan_dinas_id`), membuat `LaporanPerjalananDinas`, dan membuat entri `LaporanFoto` untuk tiap foto.

- [x] **Step 1: Ubah `convert_to_lpd` Action di `DailyActivityResource.php`**
  Ganti aksi langsung dengan form modal interaktif:
  - Header: *"Konversi Bukti Dukung ke Laporan Perjalanan Dinas (LPD)"*
  - Deteksi seluruh kegiatan pada tanggal yang sama untuk user & ST yang sama:
    `$sameDayActivities = DailyActivity::where('user_id', $record->user_id)->whereDate('activity_date', $record->activity_date)->where('surat_tugas_id', $record->surat_tugas_id)->with('attachments')->get();`
  - Tampilkan ringkasan kegiatan yang ditemukan (misal: "Ditemukan 3 catatan bukti dukung & 4 foto pada tanggal ini yang akan digabung ke LPD").
  - Sediakan tombol interaktif di modal *"✨ Susun Narasi Kronologis (AI)"* yang mengisi RichEditor `uraian_kegiatan`.
  - Tampilkan input `tujuan`, `nama_pejabat`, dan `desa_pejabat`.

- [x] **Step 2: Implementasi penyimpanan multi-kegiatan dan foto**
  Di callback action modal:
  - Simpan `LaporanPerjalananDinas`.
  - Salin seluruh lampiran dari SEMUA kegiatan hari itu ke `LaporanFoto`.
  - Perbarui status `laporan_perjalanan_dinas_id` untuk semua kegiatan terkait.
  - Tampilkan notifikasi sukses dengan aksi tombol *"Buka LPD"*.

- [x] **Step 3: Verifikasi sintaks PHP**
  Jalankan `php -l app/Filament/Resources/DailyActivityResource.php`.

- [x] **Step 4: Commit Task 2**
  ```bash
  git add app/Filament/Resources/DailyActivityResource.php
  git commit -m "feat(lpd): jadikan aksi convert_to_lpd modal agregator multi-kegiatan interaktif dengan AI timeline"
  ```

---

### Task 3: Fitur "Impor dari Bukti Dukung Harian" di Form `LaporanPerjalananDinasResource`

**Files:**
- Modify: `app/Filament/Resources/LaporanPerjalananDinasResource.php`

**Interfaces:**
- Menambahkan Action di samping RichEditor `uraian_kegiatan` atau di header section Data Laporan: *"📥 Tarik dari Bukti Dukung"* yang otomatis mengambil bukti dukung sesuai Surat Tugas dan Tanggal Kunjungan yang dipilih pada form.

- [x] **Step 1: Tambahkan tombol aksi penarik bukti dukung di form LPD**
  Saat user membuat LPD manual dan memilih ST serta Tanggal Kunjungan, user dapat mengklik *"📥 Tarik dari Bukti Dukung Hari Ini"* untuk otomatis mengimpor narasi kronologis AI dan melampirkan foto-fotonya ke repeater foto.

- [x] **Step 2: Verifikasi sintaks PHP**
  Jalankan `php -l app/Filament/Resources/LaporanPerjalananDinasResource.php`.

- [x] **Step 3: Commit Task 3**
  ```bash
  git add app/Filament/Resources/LaporanPerjalananDinasResource.php
  git commit -m "feat(lpd): sediakan opsi impor bukti dukung harian langsung di form input LPD"
  ```

---

### Task 4: Verifikasi Menyeluruh, Update Changelog, Push & Deploy

**Files:**
- Modify: `README.md`

- [x] **Step 1: Verifikasi seluruh file dengan `php -l` dan artisan route/view compile**
- [x] **Step 2: Update Changelog di `README.md` dengan tanggal hari ini**
- [ ] **Step 3: Git add, commit, dan push ke `origin/main`**
- [ ] **Step 4: Jalankan `deploy.sh` di server remote `bpsdemak@10.133.21.24` via SSH**
- [ ] **Step 5: Verifikasi status container dan kesehatan aplikasi di server**
