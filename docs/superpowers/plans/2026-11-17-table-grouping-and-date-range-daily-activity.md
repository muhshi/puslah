# Pengelompokan Tabel Harian (Table Grouping) & Pengisian Rentang Tanggal Bukti Dukung

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengelompokkan tampilan tabel Bukti Dukung Harian secara otomatis per tanggal (Opsi A) sehingga tampilan rapi tanpa tanggal berulang, serta menyediakan fitur pengisian laporan rentang tanggal (multi-hari) di form web dan bot Telegram yang secara otomatis men-generate entri harian per hari kerja tanpa merusak relasi Wadah KIPAPP dan foto bukti.

**Architecture:** 
- **Tampilan Tabel (View):** Menggunakan fitur native Filament v3 `defaultGroup('activity_date')` dengan header tanggal terformat bahasa Indonesia, collapsible, dan ringkasan jumlah kegiatan & foto per tanggal.
- **Form Web Rentang Tanggal (Create):** Menambahkan toggle `is_range` pada `DailyActivityResource` untuk beralih antara satu tanggal (`activity_date`) dan rentang tanggal (`range_start` s/d `range_end` + opsi `skip_weekends`). Di `CreateDailyActivity::handleRecordCreation()`, sistem melakukan batch generation entri `DailyActivity` untuk masing-masing hari kerja dalam rentang tersebut dan menyinkronkan wadah KIPAPP serta foto attachment ke setiap hari.
- **Bot Telegram Rentang Tanggal:** Menambahkan method `resolveActivityDateRange()` pada `DailyActivityTelegramHandler` untuk mengenali perintah rentang tanggal (misal: "13 s.d. 15 oktober"), dan membuat entri terpisah per hari kerja secara otomatis dengan balasan daftar tanggal yang berhasil diisi.

**Tech Stack:** Laravel 11, Filament v3 (Table Grouping & Forms), Carbon/CarbonPeriod, Livewire 3, Pest/PHPUnit.

---

## File Structure

```
app/
├── Filament/
│   └── Resources/
│       ├── DailyActivityResource.php                       # [MODIFY] Konfigurasi defaultGroup tabel dan schema toggle rentang tanggal
│       └── DailyActivityResource/
│           └── Pages/
│               └── CreateDailyActivity.php                 # [MODIFY] Override handleRecordCreation untuk batch multi-hari
├── Services/
│   └── DailyActivityAiService.php                          # [EXISTING] AI Formalizer & date extraction
└── Telegram/
    └── Handlers/
        └── DailyActivityTelegramHandler.php                # [MODIFY] Dukungan batch multi-hari untuk rentang tanggal
tests/
└── Feature/
    └── DailyActivityDateRangeTest.php                      # [CREATE] Test batch creation rentang tanggal web & telegram
```

---

### Task 1: Buat Feature Test untuk Batch Generation Rentang Tanggal (TDD)

**Files:**
- Create: `tests/Feature/DailyActivityDateRangeTest.php`

- [ ] **Step 1: Tulis feature test kasus pembuatan rentang tanggal dan grouping tabel**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\DailyActivityResource;
use App\Filament\Resources\DailyActivityResource\Pages\CreateDailyActivity;
use App\Models\DailyActivity;
use App\Models\SharedWorkReport;
use App\Models\User;
use App\Telegram\Handlers\DailyActivityTelegramHandler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DailyActivityDateRangeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email' => 'pegawai@bpsdemak.com',
            'name' => 'Pegawai Uji',
        ]);
        $this->actingAs($this->user);
    }

    public function test_it_creates_multiple_daily_activities_from_date_range_skipping_weekends(): void
    {
        $wadah = SharedWorkReport::create([
            'user_id' => $this->user->id,
            'title' => 'Wadah Uji Susenas',
            'is_active' => true,
        ]);

        // Rentang Senin (12 Okt 2026) s.d. Senin (19 Okt 2026) -> 6 hari kerja (lewati Sab-Ming 17-18 Okt)
        Livewire::test(CreateDailyActivity::class)
            ->fillForm([
                'is_range' => true,
                'range_start' => '2026-10-12',
                'range_end' => '2026-10-19',
                'skip_weekends' => true,
                'raw_content' => 'Melakukan pengawasan pencacahan lapangan Susenas',
                'final_content' => 'Melakukan pengawasan pencacahan lapangan Susenas',
                'sharedWorkReports' => [$wadah->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Harus terbentuk 6 catatan kegiatan (Senin-Jumat + Senin berikutnya)
        $activities = DailyActivity::where('user_id', $this->user->id)->orderBy('activity_date')->get();
        $this->assertCount(6, $activities);

        $dates = $activities->pluck('activity_date')->map(fn ($d) => $d->toDateString())->toArray();
        $this->assertEquals([
            '2026-10-12', // Senin
            '2026-10-13', // Selasa
            '2026-10-14', // Rabu
            '2026-10-15', // Kamis
            '2026-10-16', // Jumat
            '2026-10-19', // Senin berikutnya (Sabtu 17 & Minggu 18 dilewati)
        ], $dates);

        // Pastikan relasi wadah tersambung ke setiap hari
        foreach ($activities as $act) {
            $this->assertTrue($act->sharedWorkReports->contains($wadah->id));
        }
    }

    public function test_telegram_handler_detects_date_range(): void
    {
        $handler = app(DailyActivityTelegramHandler::class);

        $range = $handler->resolveActivityDateRange('laporan pengawasan lapangan tgl 13 s.d. 16 oktober 2026');
        $this->assertNotNull($range);
        $this->assertEquals('2026-10-13', $range['start']->toDateString());
        $this->assertEquals('2026-10-16', $range['end']->toDateString());
    }
}
```

- [ ] **Step 2: Jalankan test dan pastikan gagal (merah) karena fungsi belum diimplementasi**

Run: `php artisan test tests/Feature/DailyActivityDateRangeTest.php`
Expected: FAIL dengan pesan error field `is_range` atau method `resolveActivityDateRange` tidak ditemukan.

---

### Task 2: Implementasi Table Grouping by Date pada DailyActivityResource (Opsi A)

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php`

- [ ] **Step 1: Tambahkan konfigurasi defaultGroup pada table di DailyActivityResource.php**

Buka `app/Filament/Resources/DailyActivityResource.php`. Pada method `public static function table(Table $table): Table`:

Tambahkan konfigurasi grouping:
```php
    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup(
                Tables\Grouping\Group::make('activity_date')
                    ->label('Tanggal Kegiatan')
                    ->date()
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (DailyActivity $record): string =>
                        \Carbon\Carbon::parse($record->activity_date)->translatedFormat('l, d F Y')
                    )
            )
            ->defaultSort('activity_date', 'desc')
```

- [ ] **Step 2: Rapikan kolom tabel agar tidak redundan menampilkan tanggal berulang di setiap baris**

Di dalam `$table->columns([...])`:
Ubah kolom `activity_date` agar bersifat ringkas (misal menampilkan jam kegiatan atau waktu entri jika ada, atau tetap tanggal singkat saat grouping dimatikan oleh user):
```php
                Tables\Columns\TextColumn::make('activity_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan default karena sudah ada di header group
```

- [ ] **Step 3: Jalankan verifikasi sintaks PHP**

Run: `php -l app/Filament/Resources/DailyActivityResource.php`
Expected: `No syntax errors detected in app/Filament/Resources/DailyActivityResource.php`

---

### Task 3: Implementasi Form Input Rentang Tanggal di Form Create

**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php:60-100`
- Modify: `app/Filament/Resources/DailyActivityResource/Pages/CreateDailyActivity.php`

- [ ] **Step 1: Tambahkan skema input rentang tanggal di form `DailyActivityResource.php`**

Pada `Section::make('Informasi Kegiatan')` di `DailyActivityResource.php`:
Tambahkan toggle `is_range`, `range_start`, `range_end`, dan `skip_weekends`:

```php
                        Forms\Components\Toggle::make('is_range')
                            ->label('Isi Rentang Tanggal (Multi-Hari)')
                            ->helperText('Aktifkan jika kegiatan berlangsung beberapa hari dan ingin dicatat untuk masing-masing hari.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('activity_date')
                            ->label('Tanggal Kegiatan')
                            ->required(fn (Forms\Get $get) => !$get('is_range'))
                            ->default(Carbon::today())
                            ->displayFormat('d/m/Y')
                            ->visible(fn (Forms\Get $get) => !$get('is_range'))
                            ->columnSpan(1),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('range_start')
                                    ->label('Tanggal Mulai')
                                    ->required(fn (Forms\Get $get) => (bool) $get('is_range'))
                                    ->default(Carbon::today())
                                    ->displayFormat('d/m/Y'),

                                Forms\Components\DatePicker::make('range_end')
                                    ->label('Tanggal Selesai')
                                    ->required(fn (Forms\Get $get) => (bool) $get('is_range'))
                                    ->default(Carbon::today())
                                    ->afterOrEqual('range_start')
                                    ->displayFormat('d/m/Y'),

                                Forms\Components\Toggle::make('skip_weekends')
                                    ->label('Lewati Sabtu & Minggu')
                                    ->helperText('Hanya catat pada hari kerja aktif')
                                    ->default(true)
                                    ->inline(false),
                            ])
                            ->visible(fn (Forms\Get $get) => (bool) $get('is_range'))
                            ->columnSpanFull(),
```

- [ ] **Step 2: Implementasi Batch Generation di `CreateDailyActivity.php`**

Buka `app/Filament/Resources/DailyActivityResource/Pages/CreateDailyActivity.php`.
Tambahkan method `handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model`:

```php
    protected int $createdBatchCount = 1;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $isRange = (bool) ($data['is_range'] ?? false);

        if (!$isRange) {
            unset($data['is_range'], $data['range_start'], $data['range_end'], $data['skip_weekends']);
            return parent::handleRecordCreation($data);
        }

        $startDate = \Carbon\Carbon::parse($data['range_start'] ?? now())->startOfDay();
        $endDate = \Carbon\Carbon::parse($data['range_end'] ?? now())->startOfDay();
        $skipWeekends = (bool) ($data['skip_weekends'] ?? true);

        // Ambil relasi wadah & attachments jika ada
        $sharedReports = $data['sharedWorkReports'] ?? [];
        $attachmentsData = $data['attachments'] ?? [];

        unset(
            $data['is_range'],
            $data['range_start'],
            $data['range_end'],
            $data['skip_weekends'],
            $data['sharedWorkReports'],
            $data['attachments']
        );

        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
        $records = [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($period, $skipWeekends, $data, $sharedReports, $attachmentsData, &$records) {
            foreach ($period as $date) {
                if ($skipWeekends && ($date->isSaturday() || $date->isSunday())) {
                    continue;
                }

                $activityData = array_merge($data, [
                    'activity_date' => $date->toDateString(),
                ]);

                /** @var \App\Models\DailyActivity $activity */
                $activity = static::getModel()::create($activityData);

                // Sinkronkan Wadah KIPAPP
                if (!empty($sharedReports)) {
                    $activity->sharedWorkReports()->sync($sharedReports);
                    if (empty($activity->shared_work_report_id)) {
                        $activity->update(['shared_work_report_id' => $sharedReports[0]]);
                    }
                }

                // Duplikasi Lampiran Foto ke masing-masing hari jika ada
                if (!empty($attachmentsData)) {
                    foreach ($attachmentsData as $att) {
                        $activity->attachments()->create([
                            'file_path' => $att['file_path'],
                            'file_name' => $att['file_name'] ?? basename($att['file_path']),
                            'file_type' => $att['file_type'] ?? 'image/jpeg',
                            'file_size' => $att['file_size'] ?? null,
                            'caption' => $att['caption'] ?? $activity->raw_content,
                            'latitude' => $att['latitude'] ?? null,
                            'longitude' => $att['longitude'] ?? null,
                            'captured_at' => $date->setTimeFromTimeString(now()->toTimeString()),
                            'is_stamped' => $att['is_stamped'] ?? false,
                            'stamp_position' => $att['stamp_position'] ?? 'bottom-left',
                        ]);
                    }
                }

                $records[] = $activity;
            }
        });

        $this->createdBatchCount = count($records);

        return $records[0] ?? static::getModel()::create($data);
    }

    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        if ($this->createdBatchCount > 1) {
            return \Filament\Notifications\Notification::make()
                ->success()
                ->title("🎉 Berhasil Membuat {$this->createdBatchCount} Laporan Kegiatan!")
                ->body('Kegiatan telah otomatis dicatat untuk masing-masing hari kerja dalam rentang tanggal yang dipilih.');
        }

        return parent::getCreatedNotification();
    }
```

- [ ] **Step 3: Jalankan test feature untuk memastikan implementasi Create lulus**

Run: `php artisan test tests/Feature/DailyActivityDateRangeTest.php --filter=test_it_creates_multiple_daily_activities_from_date_range_skipping_weekends`
Expected: PASS.

---

### Task 4: Dukungan Rentang Tanggal di Telegram Bot Handler

**Files:**
- Modify: `app/Telegram/Handlers/DailyActivityTelegramHandler.php`

- [ ] **Step 1: Tambahkan method `resolveActivityDateRange` di `DailyActivityTelegramHandler.php`**

```php
    /**
     * Deteksi apakah pesan menyebutkan rentang tanggal (misal: "13 s.d. 17 oktober", "13-16 okt")
     * @return array{start: \Carbon\Carbon, end: \Carbon\Carbon}|null
     */
    public function resolveActivityDateRange(string $text): ?array
    {
        $lower = strtolower(trim($text));

        $monthMap = [
            'januari' => 1, 'jan' => 1, 'january' => 1,
            'februari' => 2, 'feb' => 2, 'february' => 2,
            'maret' => 3, 'mar' => 3, 'march' => 3,
            'april' => 4, 'apr' => 4,
            'mei' => 5, 'may' => 5,
            'juni' => 6, 'jun' => 6, 'june' => 6,
            'juli' => 7, 'jul' => 7, 'july' => 7,
            'agustus' => 8, 'agu' => 8, 'ags' => 8, 'agust' => 8, 'august' => 8, 'aug' => 8,
            'september' => 9, 'sep' => 9, 'sept' => 9,
            'oktober' => 10, 'okt' => 10, 'october' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11, 'nopember' => 11, 'nop' => 11,
            'desember' => 12, 'des' => 12, 'december' => 12, 'dec' => 12,
        ];
        $monthPattern = implode('|', array_keys($monthMap));

        // Pola: "13 s/d 17 oktober", "13 - 17 oktober", "13 sampai 17 okt", "13 dan 14 oktober"
        if (preg_match('/(?:tgl\.?|tanggal)?\s*(\d{1,2})\s*(?:-|s\/d|s\.d\.|sampai|sd|dan|&)\s*(\d{1,2})[\s\-\.\/]+(' . $monthPattern . ')(?:[\s\-\.\/]+(\d{4}))?/i', $lower, $m)) {
            $startDay = (int) $m[1];
            $endDay = (int) $m[2];
            $month = $monthMap[strtolower($m[3])] ?? null;
            $year = !empty($m[4]) ? (int) $m[4] : (int) date('Y');

            if ($month && checkdate($month, $startDay, $year) && checkdate($month, $endDay, $year)) {
                $start = \Carbon\Carbon::createFromDate($year, $month, min($startDay, $endDay))->startOfDay();
                $end = \Carbon\Carbon::createFromDate($year, $month, max($startDay, $endDay))->startOfDay();

                if ($start->diffInDays($end) >= 1 && $start->diffInDays($end) <= 31) {
                    return ['start' => $start, 'end' => $end];
                }
            }
        }

        return null;
    }
```

- [ ] **Step 2: Hubungkan `resolveActivityDateRange` di `handleTextMessage` Telegram**

Pada `handleTextMessage()` di `DailyActivityTelegramHandler.php`:
Jika `resolveActivityDateRange()` mengembalikan rentang tanggal multi-hari:
Lakukan loop pembuatan `DailyActivity` untuk setiap hari kerja dalam rentang tersebut, dan kirimkan balasan ringkasan tanggal-tanggal yang berhasil diisi.

```php
        // Cek apakah pesan menyebutkan rentang tanggal multi-hari
        $dateRange = $this->resolveActivityDateRange($rawText);
        if ($dateRange) {
            $period = \Carbon\CarbonPeriod::create($dateRange['start'], $dateRange['end']);
            $createdDates = [];

            DB::transaction(function () use ($period, $user, $rawText, $formalContent, $activeSt, $sharedReports, &$createdDates) {
                foreach ($period as $dt) {
                    if ($dt->isSaturday() || $dt->isSunday()) {
                        continue;
                    }

                    $act = DailyActivity::create([
                        'user_id' => $user->id,
                        'surat_tugas_id' => $activeSt?->id,
                        'shared_work_report_id' => $sharedReports->first()?->id,
                        'activity_date' => $dt->toDateString(),
                        'raw_content' => $rawText,
                        'ai_formal_content' => $formalContent,
                        'final_content' => $formalContent,
                        'status' => 'submitted',
                        'source' => 'telegram',
                    ]);

                    if ($sharedReports->isNotEmpty()) {
                        $act->sharedWorkReports()->sync($sharedReports->pluck('id'));
                    }

                    $createdDates[] = $dt->translatedFormat('D, d M Y');
                }
            });

            $listStr = implode("\n• ", $createdDates);
            $bot->sendMessage(
                text: "🗓️ *Laporan Rentang Multi-Hari Berhasil Dibuat!* 🚀\n\n" .
                "📝 *Catatan:* \"{$rawText}\"\n" .
                "✨ *Narasi AI:* \"{$formalContent}\"\n\n" .
                "📅 *Tercatat untuk " . count($createdDates) . " hari kerja:*\n• {$listStr}\n\n" .
                "✅ _Semua kegiatan berhasil dicatat di Puslah._",
                chat_id: $bot->chatId(),
                parse_mode: 'Markdown'
            );
            return;
        }
```

- [ ] **Step 3: Jalankan seluruh test suite**

Run: `php artisan test tests/Feature/DailyActivityDateRangeTest.php`
Expected: ALL PASS.

---

### Task 5: Dokumentasi Changelog & Verifikasi Akhir

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Perbarui Changelog pada README.md**
- [ ] **Step 2: Commit perubahan fitur & push ke repository**

```bash
git add README.md app/Filament/ app/Telegram/ tests/
git commit -m "feat(daily-activity): implementasi table grouping per tanggal dan pengisian rentang multi-hari web & telegram"
git push origin main
```
