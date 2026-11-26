# Hierarchical Sorting & Flexible Sorting Kolom Bukti Dukung Harian Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengimplementasikan strategi sorting bertingkat (hierarchical sorting) pada tabel Bukti Dukung Harian dengan default urutan tanggal kegiatan (`activity_date DESC`) dan urutan sub-kegiatan kronologis pagi-ke-sore (`created_at ASC`), serta menambahkan kolom waktu input (`created_at`) yang dapat disortir sewaktu-waktu oleh pengguna.

**Architecture:** 
- **Tingkat 1 (Grup Tanggal):** Diurutkan menurun (`activity_date DESC`) sehingga hari ini/terbaru selalu di atas secara kronologis kalender.
- **Tingkat 2 (Item per Tanggal):** Menggunakan `orderQueryUsing` pada `Tables\Grouping\Group` untuk menyisipkan secondary ordering `created_at ASC` sehingga beberapa kegiatan dalam 1 hari tersusun runtut dari awal hari (pagi) ke akhir hari (sore).
- **Fleksibilitas Pengguna:** Kolom `created_at` ("Waktu Input") ditambahkan ke tabel dengan opsi `sortable()` dan `toggleable(isToggledHiddenByDefault: true)` sehingga pengguna yang baru saja menginput kegiatan backdate dapat langsung menyortir berdasarkan waktu input jika diinginkan tanpa merusak tampilan bersih default tabel.

**Tech Stack:** Laravel 11, Filament v3 (Table Grouping, Sorting, Columns), PHPUnit/Livewire testing.

---

## File Structure

```
app/
└── Filament/
    └── Resources/
        └── DailyActivityResource.php               # [MODIFY] Tambahkan orderQueryUsing pada Group dan kolom sortable created_at
tests/
└── Feature/
    └── DailyActivityDateRangeTest.php              # [MODIFY] Tambahkan automated tests untuk hierarki sorting dan kolom created_at
README.md                                           # [MODIFY] Dokumentasikan pembaruan di Changelog 2026-11-17
```

---

### Task 1: Tulis Test untuk Hierarchical Sorting dan Kolom `created_at` (TDD)

**Files:**
- Modify: `tests/Feature/DailyActivityDateRangeTest.php`

- [ ] **Step 1: Tulis test pengujian urutan kegiatan dalam hari yang sama dan keberadaan kolom `created_at`**

Tambahkan method test baru di `tests/Feature/DailyActivityDateRangeTest.php`:
1. `test_activities_within_same_date_are_ordered_chronologically_by_created_at()`: membuat 3 kegiatan pada tanggal yang sama dengan waktu `created_at` berbeda, lalu memastikan urutannya di tabel sesuai `created_at ASC`.
2. `test_table_has_sortable_created_at_column()`: memastikan kolom `created_at` terdaftar, sortable, dan toggleable.

```php
    public function test_activities_within_same_date_are_ordered_chronologically_by_created_at(): void
    {
        $date = '2026-10-13';

        // Buat kegiatan 1 (Pagi)
        $act1 = DailyActivity::create([
            'user_id' => $this->user->id,
            'activity_date' => $date,
            'raw_content' => 'Apel pagi dan briefing',
            'final_content' => 'Apel pagi dan briefing',
            'status' => 'submitted',
            'source' => 'web',
            'created_at' => Carbon::parse('2026-10-13 07:30:00'),
        ]);

        // Buat kegiatan 2 (Siang)
        $act2 = DailyActivity::create([
            'user_id' => $this->user->id,
            'activity_date' => $date,
            'raw_content' => 'Pengawasan lapangan mitra',
            'final_content' => 'Pengawasan lapangan mitra',
            'status' => 'submitted',
            'source' => 'web',
            'created_at' => Carbon::parse('2026-10-13 13:00:00'),
        ]);

        // Buat kegiatan 3 (Sore)
        $act3 = DailyActivity::create([
            'user_id' => $this->user->id,
            'activity_date' => $date,
            'raw_content' => 'Evaluasi hasil pengawasan sore',
            'final_content' => 'Evaluasi hasil pengawasan sore',
            'status' => 'submitted',
            'source' => 'web',
            'created_at' => Carbon::parse('2026-10-13 16:30:00'),
        ]);

        $component = Livewire::test(\App\Filament\Resources\DailyActivityResource\Pages\ListDailyActivities::class);

        // Ambil record yang dirender di tabel
        $records = $component->instance()->getTable()->getRecords();
        $this->assertCount(3, $records);

        // Pastikan urutan item adalah Pagi (act1) -> Siang (act2) -> Sore (act3)
        $this->assertEquals($act1->id, $records->values()[0]->id);
        $this->assertEquals($act2->id, $records->values()[1]->id);
        $this->assertEquals($act3->id, $records->values()[2]->id);
    }

    public function test_table_has_sortable_created_at_column(): void
    {
        $table = \App\Filament\Resources\DailyActivityResource::table(
            new \Filament\Tables\Table(new \App\Filament\Resources\DailyActivityResource\Pages\ListDailyActivities())
        );

        $column = $table->getColumn('created_at');
        $this->assertNotNull($column, 'Kolom created_at harus ada di tabel');
        $this->assertTrue($column->isSortable(), 'Kolom created_at harus sortable');
        $this->assertTrue($column->isToggledHiddenByDefault(), 'Kolom created_at harus hidden default agar tabel tetap ringkas');
    }
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan (Red)**

Run: `php artisan test tests/Feature/DailyActivityDateRangeTest.php --filter=test_table_has_sortable_created_at_column`
Expected: FAIL dengan pesan "Kolom created_at harus ada di tabel".

---

### Task 2: Implementasi `orderQueryUsing` dan Kolom `created_at` di `DailyActivityResource.php`

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php`

- [ ] **Step 1: Tambahkan `orderQueryUsing` pada `Tables\Grouping\Group` di `DailyActivityResource::table()`**

Buka `app/Filament/Resources/DailyActivityResource.php`. Pada konfigurasi `defaultGroup()`:
Tambahkan `->orderQueryUsing(...)`:

```php
            ->defaultGroup(
                Tables\Grouping\Group::make('activity_date')
                    ->label('Tanggal Kegiatan')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getKeyFromRecordUsing(fn (DailyActivity $record): string =>
                        $record->activity_date ? \Carbon\Carbon::parse($record->activity_date)->toDateString() : ''
                    )
                    ->getTitleFromRecordUsing(fn (DailyActivity $record): string =>
                        $record->activity_date ? \Carbon\Carbon::parse($record->activity_date)->locale('id')->translatedFormat('l, d F Y') : '-'
                    )
                    ->orderQueryUsing(fn (Builder $query, string $direction) =>
                        $query->orderBy('activity_date', $direction)->orderBy('created_at', 'asc')
                    )
            )
```

- [ ] **Step 2: Tambahkan kolom `created_at` ("Waktu Input") yang sortable dan toggleable di tabel**

Di dalam `->columns([...])`:
Tambahkan setelah kolom `laporan_perjalanan_dinas_id`:

```php
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Input')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn (DailyActivity $record) => 'Dibuat pada ' . $record->created_at?->translatedFormat('d F Y H:i:s')),
```

- [ ] **Step 3: Pastikan `defaultSort` tetap `activity_date` descending**

```php
            ->defaultSort('activity_date', 'desc')
```

- [ ] **Step 4: Lint sintaks file PHP**

Run: `php -l app/Filament/Resources/DailyActivityResource.php`
Expected: `No syntax errors detected`

---

### Task 3: Verifikasi Automated Tests (Green) & Regression Testing

**Files:**
- Test: `tests/Feature/DailyActivityDateRangeTest.php`

- [ ] **Step 1: Jalankan seluruh test di `DailyActivityDateRangeTest.php`**

Run: `php artisan test tests/Feature/DailyActivityDateRangeTest.php`
Expected: ALL PASS (6/6 tests passed).

- [ ] **Step 2: Jalankan test regresi modul LPD AI**

Run: `php artisan test tests/Feature/LpdAiServiceTest.php`
Expected: ALL PASS.

---

### Task 4: Dokumentasi Changelog & Commit / Push

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Perbarui Changelog pada README.md**
Catat penambahan strategi sorting bertingkat (`activity_date DESC` + `created_at ASC`) dan kolom toggleable `created_at`.

- [ ] **Step 2: Git Add, Commit & Push ke branch main**

```bash
git add README.md app/Filament/Resources/DailyActivityResource.php tests/Feature/DailyActivityDateRangeTest.php docs/superpowers/plans/2026-11-17-hierarchical-sorting-daily-activity.md
git commit -m "feat(daily-activity): implementasi hierarchical sorting tanggal & waktu input serta kolom sortable created_at"
git push origin main
```
