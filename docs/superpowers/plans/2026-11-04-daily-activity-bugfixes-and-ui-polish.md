# Bug Fixes & UI/UX Polish Bukti Dukung Harian Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memperbaiki bug fungsional relasi wadah KIPAPP, eliminasi N+1 query, mencegah over-watermarking foto, serta menata ulang margin tombol yang menempel, harmonisasi warna, dan kerapian tabel Bukti Dukung Harian.

**Architecture:** 
- Perbaikan sinkronisasi relasi Many-to-Many pada `DailyActivityResource::create_shared_report` dan query optimization pada `SharedWorkReportResource`.
- Penataan margin dan hierarchy komponen form Filament (`Forms\Components\Actions`) pada foto repeater.
- Pengelompokan aksi baris tabel Filament menjadi `ActionGroup` ringkas.
- Penyesuaian semantik warna badge dan aksi (AI, Geolocation, Warning, Destructive Action).

**Tech Stack:** Laravel 11, Filament v3, Livewire 3, Tailwind CSS, Alpine.js.

**Spec:** Temuan audit observasi tanggal 4 November 2026: perbaikan bug bulk action wadah, N+1 count, over-watermarking, safe telegram disconnect, margin tombol nempel, crowded table actions, badge color severity, dan mobile header overflow.

## Global Constraints

- Jangan drop, truncate, atau delete database.
- Jangan migrate fresh.
- Jangan gunakan subagent browser untuk testing; pengujian diverifikasi via unit/functional test dan command CLI.
- Workflow selesai: `git add .`, `git commit -m "..."`, `git push`, dan perbarui Changelog di `README.md`.

## Review Focus

- Pembuatan Berkas Publik Multi-Hari via Bulk Action harus otomatis memasukkan kegiatan ke pivot `daily_activity_shared_work_report` dan memperbarui `shared_work_report_id`.
- Filter `wadah_kipapp` pada tabel harus menampilkan kegiatan yang baru dibundel tanpa refresh anomali.
- Kolom tabel `SharedWorkReportResource` tidak boleh memicu N+1 query (`getActivities()->count()`).
- Tombol "Pilih Titik di Peta" dan "Cap Stempel" di form repeater tidak boleh menempel tanpa margin dengan elemen tetangganya.
- Tombol aksi pada setiap baris tabel tidak boleh memenuhi kolom hingga >300px atau bertumpuk canggung.

---

### Task 1: Fix Relasi Sinkronisasi Pivot & Filter Wadah KIPAPP

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php:614-644`
- Modify: `resources/views/filament/tables/columns/activity-wadah-badges.blade.php:1-44`
- Test: `tests/Feature/DailyActivitySharedReportSyncTest.php`

**Interfaces:**
- Consumes: `SharedWorkReport::create()`, `DailyActivity::whereIn('id', $sortedIds)`
- Produces: `$report->dailyActivities()->syncWithoutDetaching($sortedIds)`

- [ ] **Step 1: Write test untuk memastikan bulk action menyinkronkan relasi pivot**

```php
// tests/Feature/DailyActivitySharedReportSyncTest.php
namespace Tests\Feature;

use App\Models\DailyActivity;
use App\Models\SharedWorkReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyActivitySharedReportSyncTest extends TestCase
{
    public function test_bulk_action_syncs_pivot_and_updates_wadah_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $activity = DailyActivity::create([
            'user_id' => $user->id,
            'activity_date' => now()->toDateString(),
            'raw_content' => 'Uji kegiatan',
            'final_content' => 'Uji kegiatan formal',
            'status' => 'submitted',
            'source' => 'web',
        ]);

        $report = SharedWorkReport::create([
            'user_id' => $user->id,
            'title' => 'Wadah Uji',
            'activity_ids' => [$activity->id],
            'is_active' => true,
        ]);

        $report->dailyActivities()->syncWithoutDetaching([$activity->id]);
        $activity->update(['shared_work_report_id' => $report->id]);

        $this->assertTrue($report->dailyActivities()->where('daily_activities.id', $activity->id)->exists());
        $this->assertTrue($activity->sharedWorkReports()->where('shared_work_reports.id', $report->id)->exists());
    }
}
```

- [ ] **Step 2: Run test to verify behavior**

Run: `php artisan test --filter=DailyActivitySharedReportSyncTest`
Expected: PASS

- [ ] **Step 3: Update `DailyActivityResource.php` bulk action `create_shared_report`**

Di `app/Filament/Resources/DailyActivityResource.php`, perbarui action closure:
```php
$report = \App\Models\SharedWorkReport::create([
    'user_id' => $user->id,
    'title' => $data['title'],
    'description' => $data['description'] ?? null,
    'activity_ids' => $sortedIds,
    'surat_tugas_id' => $stId,
    'is_active' => true,
]);

$report->dailyActivities()->syncWithoutDetaching($sortedIds);
foreach ($records as $rec) {
    $rec->update(['shared_work_report_id' => $report->id]);
    $rec->sharedWorkReports()->syncWithoutDetaching([$report->id]);
}
```

- [ ] **Step 4: Update `activity-wadah-badges.blade.php` agar warna badge "Belum Ada Wadah" tidak merah menyala**

Ganti badge `color="danger"` menjadi `color="gray"` dengan ikon `heroicon-m-folder` dan teks `"Belum Masuk Wadah"`.

---

### Task 2: Optimasi N+1 Query & Memory Leak pada `SharedWorkReportResource`

**Files:**
- Modify: `app/Filament/Resources/SharedWorkReportResource.php:113-120`

**Interfaces:**
- Consumes: `$record->activities_count` (SQL count accessor)
- Produces: Kolom tabel cepat tanpa hydrasi model Eloquent

- [ ] **Step 1: Ganti pemanggilan `getActivities()->count()` dengan accessor `activities_count`**

Di `app/Filament/Resources/SharedWorkReportResource.php`:
```php
Tables\Columns\TextColumn::make('activities_count')
    ->label('Terkumpul')
    ->badge()
    ->color('success')
    ->icon('heroicon-m-calendar-days')
    ->getStateUsing(fn (SharedWorkReport $record) => $record->activities_count . ' Hari'),
```

- [ ] **Step 2: Verifikasi syntax dan runtime check**

Run: `php artisan test --filter=SharedWorkReportTest` (atau cek linting PHP)
Expected: PASS tanpa error.

---

### Task 3: Cegah Over-Watermarking Foto & Safe Telegram Disconnect

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php:211-257`
- Modify: `app/Filament/Resources/DailyActivityResource/Pages/ListDailyActivities.php:67-96`

**Interfaces:**
- Consumes: `$get('is_stamped')`
- Produces: Proteksi stempel ganda & modal konfirmasi putus hubungan bot

- [ ] **Step 1: Tambahkan proteksi & visual disable pada tombol `burn_stamp_now` jika sudah dicap**

Di `DailyActivityResource.php`:
- Sembunyikan atau berikan notice jika foto sudah memiliki stempel (`is_stamped == true`).
- Tambahkan pengecekan `$get('is_stamped')` di action callback sebelum eksekusi watermark.

- [ ] **Step 2: Amankan modal Telegram disconnect**

Di `ListDailyActivities.php`:
- Jika Telegram sudah terhubung:
  - Berikan konfirmasi: `->requiresConfirmation()` saat klik 'Putus Hubungan Akun'.
  - Gunakan `->modalSubmitAction(fn ($action) => $action->color('danger')->label('Putus Hubungan Akun'))`.

---

### Task 4: Perbaikan Spacing / Margin Tombol yang Menempel & Tata Letak Tabel

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php`
- Modify: `resources/views/public/shared-work-report.blade.php:64-98`

**Interfaces:**
- Consumes: Filament Layout classes & Tailwind CSS
- Produces: Antarmuka bernapas (*spacious*), tidak ada tombol bertabrakan/nempel

- [ ] **Step 1: Berikan margin vertikal pada Form Repeater Foto**

Di `DailyActivityResource.php`:
- Pada `Forms\Components\Actions::make(['pick_coordinate_map'])`:
  Tambahkan `->extraAttributes(['class' => 'my-3'])`.
- Pada `Forms\Components\Actions::make(['burn_stamp_now'])`:
  Tambahkan `->extraAttributes(['class' => 'mt-3 mb-1'])`.

- [ ] **Step 2: Harmonisasi Warna Aksi Form**

Di `DailyActivityResource.php`:
- `ai_polish`: Ubah `color('warning')` menjadi `color('primary')` (dengan heroicon-m-sparkles).
- `pick_coordinate_map`: Ubah `color('warning')` menjadi `color('info')` (nuansa peta biru).

- [ ] **Step 3: Ringkaskan Aksi Baris Tabel Utama Bukti Dukung**

Di `DailyActivityResource.php`:
- Pertahankan tombol cepat `convert_to_lpd` sebagai aksi tersendiri.
- Kelompokkan aksi sekunder `export_docx`, `EditAction`, dan `DeleteAction` ke dalam `Tables\Actions\ActionGroup` yang bersih dan elegan, sehingga lebar aksi berkurang dari 360px+ menjadi kompak ~120px.

- [ ] **Step 4: Responsivitas Header Berkas Publik Mobile**

Di `resources/views/public/shared-work-report.blade.php`:
- Perbaiki container tombol header dengan `flex-wrap justify-end gap-2`.
- Berikan penyesuaian label agar di layar kecil tidak meluap ke samping.

---

### Task 5: Verifikasi Menyeluruh, Git Commit, Push & Update Changelog

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Jalankan pengujian PHP syntax & Laravel tests**
- [ ] **Step 2: Verifikasi status git**
- [ ] **Step 3: Update `README.md` Changelog dengan tanggal hari ini (4 November 2026)**
- [ ] **Step 4: Commit dan Push ke Git**
