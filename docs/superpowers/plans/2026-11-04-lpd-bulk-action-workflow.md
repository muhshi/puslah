# Alur Pembuatan LPD via Bulk Action (Tindakan) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengubah alur pembuatan Laporan Perjalanan Dinas (LPD) dari Bukti Dukung Harian menjadi berbasis seleksi baris (multi-select / Bulk Action pada menu Tindakan), lengkap dengan pemilihan Survey & Surat Tugas di dalam modal, sintesis narasi kronologis per jam AI, otomatisasi lampiran foto ke LPD, dan tombol direct link ke halaman edit LPD.

**Architecture:**
1. Tambahkan `BulkAction` baru di `DailyActivityResource`: `create_lpd_from_selected`.
2. Form modal interaktif menyediakan seleksi `survey_id` dan `surat_tugas_id` (dengan reaktif dropdown), `tanggal_kunjungan`, `tujuan`, `desa_pejabat`, `nama_pejabat`, dan RichEditor `uraian_kegiatan` yang otomatis diisi hasil kronologis AI (`LpdAiService`) dari seluruh baris yang dipilih.
3. Eksekusi action: membuat `LaporanPerjalananDinas`, menyalin seluruh foto dari kegiatan terpilih ke `LaporanFoto`, mengaitkan `laporan_perjalanan_dinas_id` dan `surat_tugas_id` pada seluruh baris kegiatan terpilih, dan menampilkan notifikasi sukses dengan tombol langsung *"Buka & Edit LPD 🚀"*.
4. Harmonisasi tombol row action `convert_to_lpd` agar selaras dan tidak tumpang tindih.

**Tech Stack:** Laravel 11, Filament v3 Table Bulk Actions, Livewire v3, Google Gemini via `LpdAiService`.

**Spec:** Permintaan pengguna: "user memilih baris2 yang mau dibikin dalam 1 LPD (bisa lebih dari 1 baris) -> klik menu Tindakan -> klik Buat LPD -> muncul modal yang salah satunya memilih survey dan surat tugas terkait -> foto2 otomatis ter-attach di LPD -> bisa langsung diedit di menu LPD".

## Global Constraints
- Jangan menghapus, drop, truncate database (`RULE[user_global]`).
- Jangan jalankan migrate fresh (`RULE[dinamit.md]`).
- Jangan gunakan browser subagent untuk testing (`RULE[dinamit.md]`).
- Setelah selesai: git commit & push, update changelog di `README.md`, dan deploy ke server `bpsdemak@10.133.21.24` via `deploy.sh`.

## Review Focus
1. **Pilihan Survey & Surat Tugas Dinamis:** Saat user memilih survey, dropdown Surat Tugas harus otomatis memfilter ST yang relevan untuk user tersebut. Jika baris yang dipilih sudah punya ST, ST tersebut harus otomatis terpilih (*prefilled*).
2. **Koleksi Foto Bersih:** Seluruh attachment dari seluruh baris yang dicentang harus disalin ke `LaporanFoto` tanpa duplikasi file dan terurut kronologis jam foto.
3. **Pembaruan Status Relasi:** Seluruh `DailyActivity` yang dipilih harus ter-update `laporan_perjalanan_dinas_id` dan `surat_tugas_id`-nya.
4. **Validasi Kuota LPD & Bentrok Tanggal:** Jika Surat Tugas sudah habis kuotanya atau bentrok dengan LPD lain di tanggal tersebut, sistem menampilkan pesan peringatan ramah dan membatalkan proses tanpa crash.
5. **Navigasi Langsung:** Notifikasi sukses menyediakan tombol navigasi satu klik ke halaman edit LPD (`filament.admin.resources.laporan-perjalanan-dinas.edit`).

---

### Task 1: Implementasi Bulk Action "Buat Laporan Perjalanan Dinas (LPD)" di `DailyActivityResource`

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php:700-750`

**Interfaces:**
- Input: `Collection<DailyActivity> $records`
- Form Modal:
  - `survey_id`: Select survey BPS (live, reactive)
  - `surat_tugas_id`: Select surat tugas (difilter berdasarkan `survey_id` & user yang login)
  - `tanggal_kunjungan`: DatePicker (default tanggal kegiatan terpilih)
  - `tujuan`: TextInput keperluan tugas
  - `desa_pejabat`: TextInput instansi/desa tujuan
  - `nama_pejabat`: TextInput pejabat yang ditemui
  - `uraian_kegiatan`: RichEditor narasi kronologis (prefilled oleh `LpdAiService`)
- Output: Membuat `LaporanPerjalananDinas`, mengisi `LaporanFoto`, mengaitkan relasi pivot/foreign key, membuka notifikasi direct link edit.

- [x] **Step 1: Definisikan `create_lpd_from_selected` di dalam `bulkActions` `DailyActivityResource.php`**
  Letakkan di grup tindakan massal dengan icon `heroicon-m-document-text`, label *"📑 Buat Laporan Perjalanan Dinas (LPD)"*, dan warna `success`.

- [x] **Step 2: Rancang form modal reaktif**
  - Pasang reactive hook `afterStateUpdated` antara `survey_id` dan `surat_tugas_id`.
  - Pasang `afterStateUpdated` pada `surat_tugas_id` untuk mengisi otomatis `tujuan` dan `desa_pejabat`.
  - Tambahkan callout ringkasan jumlah baris terpilih dan total foto yang akan dilampirkan.
  - Sediakan hint action *"✨ Susun Ulang Kronologis via AI"* pada RichEditor `uraian_kegiatan`.

- [x] **Step 3: Implementasi callback aksi penyimpanan**
  - Validasi kuota dan tanggal Surat Tugas.
  - Simpan record `LaporanPerjalananDinas`.
  - Loop seluruh attachment dari seluruh `$records` terpilih, simpan ke `LaporanFoto`.
  - Update `laporan_perjalanan_dinas_id` dan `surat_tugas_id` pada seluruh record yang dipilih.
  - Berikan notifikasi sukses dengan action button menuju URL edit LPD.

- [x] **Step 4: Verifikasi sintaks PHP**
  Jalankan `php -l app/Filament/Resources/DailyActivityResource.php`.

- [x] **Step 5: Commit Task 1**
  ```bash
  git add app/Filament/Resources/DailyActivityResource.php
  git commit -m "feat(lpd): tambahkan bulk action buat LPD dari baris kegiatan terpilih lengkap dengan pemilih ST & survei"
  ```

---

### Task 2: Harmonisasi Row Action `convert_to_lpd` di Tabel Kegiatan

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php:440-540`

- [x] **Step 1: Periksa & sesuaikan row action `convert_to_lpd`**
  Pastikan row action `convert_to_lpd` tetap bekerja harmonis untuk single/multi-activity jika user memilih klik langsung dari baris kegiatan, dengan menyertakan pemilihan Survey dan Surat Tugas jika baris belum memiliki ST.

- [x] **Step 2: Verifikasi sintaks PHP**
  Jalankan `php -l app/Filament/Resources/DailyActivityResource.php`.

- [x] **Step 3: Commit Task 2**
  ```bash
  git add app/Filament/Resources/DailyActivityResource.php
  git commit -m "refactor(lpd): selaraskan modal row action convert_to_lpd dengan alur bulk action"
  ```

---

### Task 3: Verifikasi Sistem, Update Changelog, Push & Deploy

**Files:**
- Modify: `README.md`

- [x] **Step 1: Uji coba alur bulk action menggunakan scratch script simulasi**
- [x] **Step 2: Update Changelog di `README.md` dengan tanggal hari ini**
- [ ] **Step 3: Git add, commit, dan push ke `origin/main`**
- [ ] **Step 4: Jalankan `deploy.sh` di server remote `bpsdemak@10.133.21.24` via SSH**
- [ ] **Step 5: Verifikasi status container dan kesehatan aplikasi di server**
