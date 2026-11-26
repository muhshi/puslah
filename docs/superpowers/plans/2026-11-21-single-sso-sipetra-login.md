# Transisi Login Tunggal SIPETRA SSO Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyederhanakan proses autentikasi aplikasi DINAMIT menjadi metode tunggal (Single Sign-On SIPETRA) dengan meniadakan form login app (email & password bawaan) dan registrasi manual dari tampilan utama, guna menghilangkan kebingungan pengguna.

**Architecture:** 
1. Buat custom Filament Login Page (`App\Filament\Pages\Auth\Login`) yang mengutamakan antarmuka bersih satu pintu dengan tombol heroik *Masuk dengan SIPETRA SSO*, dilengkapi visual branding BPS Kab. Demak, state loading dinamis, dan penanganan session flash error.
2. Sediakan opsi *fallback login darurat* (collapsible/tersembunyi secara default, hanya terbuka via parameter/toggle khusus) untuk super admin/developer saat server SIPETRA offline atau dalam tahap maintenance.
3. Hapus registrasi manual publik (`->registration()`) di `AdminPanelProvider` dan bersihkan route auth legacy yang tidak relevan (seperti Google Socialite).
4. Berikan edukasi konteks pada menu profil pengguna (`ProfileMe.php`) bahwa pengelolaan sandi dan identitas berpusat di SIPETRA SSO.

**Tech Stack:** Laravel 11, Filament v3 (Panel Provider, Custom Auth Page), Laravel Socialite (SIPETRA OAuth2 Provider), Livewire v3, Blade & Tailwind CSS.

**Spec:** Permintaan pengguna: *"selama ini aplikasi2 ku kan ada 2 login, login app dan sipetra. Kayaknya aku pengen loginnya pakai sipetra aja deh biar gak bikin bingung. Di mulai dari aplikasi Dinamit ini."*

## Global Constraints
- Jangan menghapus, drop, atau truncate tabel/database apapun (`RULE[user_global]`).
- Jangan menjalankan `migrate:fresh` (`RULE[dinamit.md]`).
- Jangan gunakan browser subagent untuk testing (`RULE[dinamit.md]`).
- Wajib verifikasi fungsi route, controller, view, dan komponen Livewire via command PHP / route checks sebelum menyatakan selesai.
- Setelah implementasi selesai dan OK: commit & push git, update changelog di `README.md`.

## Review Focus
1. **Peniadaan Dual Login yang Membingungkan:** Halaman login tidak boleh lagi langsung menampilkan form input email dan password biasa. Pengguna hanya disajikan tombol masuk SIPETRA yang jelas dan intuitif.
2. **Kesiapsiagaan Darurat (Emergency Fallback):** Admin/developer tidak boleh terkunci dari sistem jika koneksi SIPETRA SSO mengalami gangguan teknis atau saat pengujian lokal; form darurat harus tersedia secara tersembunyi (collapsible / parameter `?fallback=1`).
3. **Penanganan Error / Pembatalan SSO:** Jika pengguna membatalkan otorisasi di SIPETRA atau token gagal divalidasi, halaman login harus menampilkan notifikasi peringatan error yang ramah tanpa menyebabkan loop redirect.
4. **Peniadaan Registrasi Mandiri:** Registrasi lokal mandiri di Filament harus dinonaktifkan agar tidak ada akun ganda tanpa relasi SIPETRA.
5. **Kejelasan Profil Pengguna:** Pada halaman profil (`ProfileMe`), pengguna diberitahu secara informatif bahwa manajemen kredensial dan kata sandi dikelola langsung di SIPETRA.

---

### Task 1: Nonaktifkan Registrasi Lokal dan Bersihkan Route Auth Legacy

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php:35-45`
- Modify: `routes/web.php:12-25`

**Interfaces:**
- Consumes: Panel configuration di `AdminPanelProvider` & web routes di `routes/web.php`.
- Produces: Penghapusan rute registrasi mandiri `/admin/register` dan pembersihan rute Google OAuth yang sudah tidak digunakan.

- [ ] **Step 1: Hapus `->registration()` dari `AdminPanelProvider.php`**
  Hapus baris `->registration()` agar endpoint registrasi manual dinonaktifkan.

- [ ] **Step 2: Bersihkan route auth yang tidak digunakan di `routes/web.php`**
  Hapus rute legacy `auth.google` dan `auth.google.callback` jika tidak digunakan, serta pastikan rute `login` mengarah ke Filament login (`route('filament.admin.auth.login')` atau redirect ke `admin/login`).

- [ ] **Step 3: Verifikasi daftar rute**
  Jalankan: `php artisan route:list --path=admin/register`
  Expected: Tidak ada rute registrasi yang terdaftar.

- [ ] **Step 4: Commit**
  ```bash
  git add app/Providers/Filament/AdminPanelProvider.php routes/web.php
  git commit -m "fix(auth): disable public local registration and clean legacy auth routes"
  ```

---

### Task 2: Buat Custom Filament Login Page & View Terpusat SIPETRA

**Files:**
- Create: `app/Filament/Pages/Auth/Login.php`
- Create: `resources/views/filament/pages/auth/login.blade.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php:30-40,115-120`

**Interfaces:**
- Consumes: `App\Filament\Pages\Auth\Login` menggantikan default `Filament\Pages\Auth\Login`.
- Produces: Antarmuka login satu pintu SIPETRA SSO dengan single hero button, banner pesan error sesi, dan collapsible emergency form.

- [ ] **Step 1: Buat kelas `App\Filament\Pages\Auth\Login`**
  Memperluas `Filament\Pages\Auth\Login`, mengesampingkan `$view` ke `filament.pages.auth.login`, menambahkan properti `$showFallback = false`, dan method toggle `toggleFallback()`.

- [ ] **Step 2: Buat tampilan `resources/views/filament/pages/auth/login.blade.php`**
  - Tampilkan header selamat datang: Logo BPS/DINAMIT, judul aplikasi, dan deskripsi sistem.
  - Tampilkan alert flash error jika sesi membawa pesan error dari `SsoController` (misal pembatalan SSO atau kegagalan koneksi).
  - Tampilkan tombol utama: **"Masuk dengan SIPETRA SSO"** dengan styling konsisten BPS (#005596), ikon logo BPS, dan efek animasi loading saat diklik.
  - Sediakan accordion halus di bagian bawah: *"Opsi Darurat / Pengembang"* yang secara tersembunyi menampilkan form login email & password Filament standar jika dibutuhkan dalam situasi darurat offline.

- [ ] **Step 3: Hubungkan Custom Login Page di `AdminPanelProvider.php`**
  Ubah `->login()` menjadi `->login(\App\Filament\Pages\Auth\Login::class)`.
  Lepaskan renderHook `panels::auth.login.form.after` yang lama karena tombol SSO kini menjadi elemen utama tampilan.

- [ ] **Step 4: Verifikasi kompilasi view dan route Filament login**
  Jalankan: `php artisan view:cache` & `php artisan route:list --path=admin/login`
  Expected: View berhasil dikompilasi tanpa syntax error dan rute login terdaftar dengan controller custom.

- [ ] **Step 5: Commit**
  ```bash
  git add app/Filament/Pages/Auth/Login.php resources/views/filament/pages/auth/login.blade.php app/Providers/Filament/AdminPanelProvider.php
  git commit -m "feat(auth): implement dedicated SIPETRA SSO login page with emergency fallback"
  ```

---

### Task 3: Sinkronisasi Penanganan Error SSO & Redirection di `SsoController`

**Files:**
- Modify: `app/Http/Controllers/Auth/SsoController.php:20-35`

**Interfaces:**
- Consumes: Request callback dari SIPETRA SSO (`/auth/sipetra/callback`).
- Produces: Redirection aman ke `admin/login` dengan flash message saat terjadi error atau pembatalan.

- [ ] **Step 1: Periksa redirect error di `SsoController.php`**
  Pastikan saat `$request->has('error')` atau saat exception terjadi, redirect diarahkan ke `route('filament.admin.auth.login')` dengan flash pesan peringatan bahasa Indonesia yang jelas (misal: *"Login SSO dibatalkan oleh pengguna."* atau *"Gagal terhubung dengan server SIPETRA. Silakan coba lagi."*).

- [ ] **Step 2: Verifikasi alur callback dengan simulasi parameter error**
  Uji dengan perintah PHP artisan tinker atau unit check bahwa flash session terpasang dengan baik.

- [ ] **Step 3: Commit**
  ```bash
  git add app/Http/Controllers/Auth/SsoController.php
  git commit -m "fix(sso): refine error redirection and messages for SIPETRA callback"
  ```

---

### Task 4: Harmonisasi Halaman Profil Pengguna (`ProfileMe`) untuk Akun SIPETRA

**Files:**
- Modify: `app/Filament/Pages/ProfileMe.php:150-180`

**Interfaces:**
- Consumes: Data user yang login (`Auth::user()`).
- Produces: Petunjuk informatif pada bagian ganti sandi bahwa akun dikelola terpusat via SIPETRA.

- [ ] **Step 1: Tambahkan callout/helper text pada form ganti sandi di `ProfileMe.php`**
  Pada `Section::make('Update Password')`, tambahkan deskripsi atau helper text:
  *"Akun Anda terhubung dengan SIPETRA SSO. Mengubah kata sandi di sini hanya untuk keperluan lokal darurat dan tidak mengubah kata sandi utama akun SIPETRA Anda. Untuk mengelola kata sandi utama, silakan gunakan menu Profil & Password (SIPETRA)."*

- [ ] **Step 2: Verifikasi halaman profil ter-render dengan baik**
  Jalankan: `php artisan view:cache`
  Expected: Tidak ada error sintaksis.

- [ ] **Step 3: Commit**
  ```bash
  git add app/Filament/Pages/ProfileMe.php
  git commit -m "docs(profile): clarify centralized SIPETRA password management in profile page"
  ```

---

### Task 5: Dokumentasi, Changelog, dan Verifikasi Akhir

**Files:**
- Modify: `README.md`

**Interfaces:**
- Consumes: Ringkasan seluruh perubahan arsitektur login.
- Produces: Changelog terupdate dengan tanggal hari ini.

- [ ] **Step 1: Update `README.md` pada bagian Changelog**
  Tambahkan entri tertanggal hari ini mengenai pembaruan login tunggal SIPETRA SSO.

- [ ] **Step 2: Jalankan pemeriksaan integritas menyeluruh**
  Jalankan: `php artisan optimize:clear` dan periksa status git.

- [ ] **Step 3: Commit dan Push**
  ```bash
  git add README.md
  git commit -m "docs: update changelog for single SIPETRA SSO login transition"
  git push
  ```
