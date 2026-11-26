# Fitur Pelatihan, Paket SPJ & Modernisasi Pengaturan Pejabat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memodernisasi pengaturan pejabat penandatangan (Kepala, PPK, Bendahara) berbasis database pegawai dengan backward-compatibility penuh, lalu menghadirkan menu terpisah **Pelatihan** (Opsi 1: dua menu Filament `Kegiatan` dan `Pelatihan` di atas tabel `surveys` yang sama) lengkap dengan generator Undangan `.docx` dan Paket SPJ Multi-Sheet `.xlsx` (plus Quick Print Daftar Hadir `.pdf`) yang menarik data rekening & NIK otomatis dari database SIPETRA.

**Architecture:**
- **Fase 1 (Foundation):** Tambah `kepala_user_id`, `ppk_user_id`, `bendahara_user_id`, `bendahara_name`, `bendahara_nip`, `bendahara_title` di `SystemSettings`. Dropdown di `SystemSettingsPage` otomatis menyinkronkan properti teks lama (`cert_signer_*`, `ppk_*`) agar modul `SuratTugas`, `SppdService`, `LemburExportService`, dan `CertificateTemplateResource` tidak mengalami regresi.
- **Fase 2 & 3 (Opsi 1 Hybrid Menu + DB):** Tabel `surveys` ditambah kolom `is_pelatihan` beserta metadata pelatihan & DIPA (migrasi non-destructive, dilarang `migrate:fresh`). `SurveyResource` menjadi menu **Kegiatan** (`is_pelatihan = false`), sedangkan `PelatihanResource` menjadi menu **Pelatihan** (`is_pelatihan = true`) sehingga relasi `surat_tugas.survey_id` dan `certificates.survey_id` langsung terhubung tanpa duplikasi tabel.
- **Fase 4 (Document Generators):** `SipetraUserDataService` melakukan batch lookup NIK/Bank/Rekening dari koneksi `sipetra`. `PelatihanUndanganService` menghasilkan `.docx` Undangan + Lampiran peserta per kelas (jadwal tetap fleksibel di Word). `PelatihanSpjExport` (`WithMultipleSheets` via `Maatwebsite\Excel`) menghasilkan workbook `.xlsx` berisi 8–9 sheet SPJ dengan rumus `SUM` aktif, plus opsi cetak cepat PDF Daftar Hadir via DomPDF.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 5.0, Livewire 4.0, Spatie Laravel Settings 3.4, Filament Shield 4.0, Maatwebsite/Excel (`PhpOffice\PhpSpreadsheet`), `PhpOffice\PhpWord\TemplateProcessor`, `Barryvdh\DomPDF` 3.1, Pest PHP 4.0.

---

## File Structure Map

| File | Responsibility | Phase |
| :--- | :--- | :--- |
| `database/settings/2026_11_25_090000_add_official_user_ids_to_system_settings.php` | Migrasi setting ID pejabat & bendahara | Fase 1 |
| `app/Settings/SystemSettings.php` | Properti settings pejabat + default values | Fase 1 |
| `app/Services/OfficialSettingsSyncService.php` | Resolusi data User/Profile ke properti teks pejabat (Kepala, PPK, Bendahara) | Fase 1 |
| `app/Filament/Pages/SystemSettingsPage.php` | Form dropdown Kepala, PPK, Bendahara & auto-sync saat simpan | Fase 1 |
| `tests/Feature/OfficialSettingsSyncTest.php` | Uji kompatibilitas settings baru ke SuratTugas & SPPD | Fase 1 |
| `database/migrations/2026_11_25_100000_add_pelatihan_fields_to_surveys_and_survey_users.php` | Kolom pelatihan/DIPA di `surveys` & kelas/role/asal/tujuan di `survey_users` | Fase 2 |
| `app/Models/Survey.php` | Fillable, casts, scopes (`pelatihan`/`nonPelatihan`), relasi pejabat | Fase 2 |
| `app/Models/SurveyUser.php` | Fillable kolom pelatihan (`role_pelatihan`, `kelas`, `asal`, `tujuan`, overrides) | Fase 2 |
| `app/Services/SipetraUserDataService.php` | Batch lookup NIK, nama bank, no rekening, alamat dari `db_sipetra.users` | Fase 2 |
| `tests/Unit/SipetraUserDataServiceTest.php` | Uji batch lookup & fallback saat koneksi sipetra tidak tersedia | Fase 2 |
| `app/Filament/Resources/SurveyResource.php` | Rename label ke "Kegiatan", scope `is_pelatihan = false` | Fase 3 |
| `app/Filament/Resources/SurveyUserResource.php` | Rename group ke "Manajemen Kegiatan" | Fase 3 |
| `app/Filament/Resources/PelatihanResource.php` + `Pages/*` | Menu terpisah "Pelatihan" (`is_pelatihan = true`), form pelatihan & DIPA | Fase 3 |
| `app/Filament/Resources/SurveyResource/RelationManagers/ParticipantsRelationManager.php` | Auto-default role PPL/PML, inline edit kelas/role, bulk set kelas/role, Excel import/export kelas | Fase 3 |
| `app/Exports/PelatihanKelasTemplateExport.php` & `app/Imports/PelatihanKelasImport.php` | Helper Excel pembagian kelas & role peserta | Fase 3 |
| `app/Services/PelatihanSpjDataBuilder.php` | Agregasi data pelatihan, peserta per kelas, INDA, nomor ST, nominal & terbilang | Fase 4 |
| `app/Services/PelatihanUndanganService.php` & `resources/templates/template_undangan_pelatihan.docx` | Generator Word `.docx` Undangan + Lampiran per Kelas & PDF Daftar Hadir | Fase 4 |
| `app/Exports/PelatihanSpjExport.php` & `app/Exports/PelatihanSheets/*.php` | Workbook Excel multi-sheet SPJ (8–9 sheet dengan rumus aktif) | Fase 4 |
| `resources/views/pdf/daftar_hadir_pelatihan.blade.php` | Template PDF Quick Print Daftar Hadir Hari-H | Fase 4 |
| `tests/Feature/PelatihanDocumentsTest.php` | Uji integrasi `PelatihanSpjDataBuilder`, export `.xlsx`, `.docx`, & `.pdf` | Fase 4 |

---

## FASE 1: Modernisasi Pengaturan Pejabat & Uji Kompatibilitas Sistem

> **Aturan Mutlak:** Dilarang menjalankan `migrate:fresh`. Gunakan `php artisan migrate` saja.

### Task 1: Settings Migration, `SystemSettings` & `OfficialSettingsSyncService`

**Files:**
- Create: `database/settings/2026_11_25_090000_add_official_user_ids_to_system_settings.php`
- Modify: `app/Settings/SystemSettings.php:16-72`
- Create: `app/Services/OfficialSettingsSyncService.php`
- Test: `tests/Feature/OfficialSettingsSyncTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/OfficialSettingsSyncTest.php`:

```php
<?php

use App\Models\User;
use App\Services\OfficialSettingsSyncService;
use App\Settings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    SystemSettings::fake(SystemSettings::defaults());
});

it('resolves user profile details for kepala, ppk, and bendahara while keeping legacy strings in sync', function () {
    $kepala = User::factory()->create(['name' => 'Khomarudin']);
    $kepala->profile()->updateOrCreate(
        ['user_id' => $kepala->id],
        [
            'full_name' => 'Khomarudin, S.ST',
            'nip' => '197512091999011001',
            'jabatan' => 'Kepala Badan Pusat Statistik Kabupaten Demak',
        ]
    );

    $ppk = User::factory()->create(['name' => 'Sulaeman']);
    $ppk->profile()->updateOrCreate(
        ['user_id' => $ppk->id],
        [
            'full_name' => 'Sulaeman, S.Stat',
            'nip' => '198204012006041015',
            'jabatan' => 'Statistisi Ahli Muda',
        ]
    );

    $bendahara = User::factory()->create(['name' => 'Khusnul Khotimah']);
    $bendahara->profile()->updateOrCreate(
        ['user_id' => $bendahara->id],
        [
            'full_name' => 'Khusnul Khotimah, S.Stat',
            'nip' => '198610252009022009',
            'jabatan' => 'Pranata Keuangan APBN',
        ]
    );

    $service = app(OfficialSettingsSyncService::class);
    $payload = $service->buildSyncedAttributes([
        'kepala_user_id' => $kepala->id,
        'ppk_user_id' => $ppk->id,
        'bendahara_user_id' => $bendahara->id,
    ]);

    expect($payload['cert_signer_name'])->toBe('Khomarudin, S.ST')
        ->and($payload['cert_signer_nip'])->toBe('197512091999011001')
        ->and($payload['cert_signer_title'])->toBe('Kepala Badan Pusat Statistik Kabupaten Demak')
        ->and($payload['ppk_name'])->toBe('Sulaeman, S.Stat')
        ->and($payload['ppk_nip'])->toBe('198204012006041015')
        ->and($payload['ppk_title'])->toBe('Pejabat Pembuat Komitmen')
        ->and($payload['bendahara_name'])->toBe('Khusnul Khotimah, S.Stat')
        ->and($payload['bendahara_nip'])->toBe('198610252009022009')
        ->and($payload['bendahara_title'])->toBe('Bendahara Pengeluaran');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/OfficialSettingsSyncTest.php`
Expected: FAIL with `Target class [App\Services\OfficialSettingsSyncService] does not exist`.

- [ ] **Step 3: Create Settings Migration and update `SystemSettings.php`**

Create `database/settings/2026_11_25_090000_add_official_user_ids_to_system_settings.php`:

```php
<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('system.kepala_user_id', null);
        $this->migrator->add('system.ppk_user_id', null);
        $this->migrator->add('system.bendahara_user_id', null);
        $this->migrator->add('system.bendahara_name', '-');
        $this->migrator->add('system.bendahara_nip', '-');
        $this->migrator->add('system.bendahara_title', 'Bendahara Pengeluaran');
        $this->migrator->add('system.undangan_pelatihan_template_path', null);
    }
};
```

Update `app/Settings/SystemSettings.php` (add properties and defaults):

```php
    // Official User IDs (linked to users table)
    public ?int $kepala_user_id;
    public ?int $ppk_user_id;
    public ?int $bendahara_user_id;

    // Bendahara Settings (synced from bendahara_user_id or manual fallback)
    public string $bendahara_name;
    public string $bendahara_nip;
    public string $bendahara_title;

    // Pelatihan Template Override (optional)
    public ?string $undangan_pelatihan_template_path;
```

And inside `SystemSettings::defaults()`:

```php
            'kepala_user_id' => null,
            'ppk_user_id' => null,
            'bendahara_user_id' => null,
            'bendahara_name' => '-',
            'bendahara_nip' => '-',
            'bendahara_title' => 'Bendahara Pengeluaran',
            'undangan_pelatihan_template_path' => null,
```

- [ ] **Step 4: Implement `OfficialSettingsSyncService.php`**

Create `app/Services/OfficialSettingsSyncService.php`:

```php
<?php

namespace App\Services;

use App\Models\User;

class OfficialSettingsSyncService
{
    /**
     * Resolve official data (name, nip, title) from user IDs while keeping
     * manual overrides intact when no user_id is selected.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function buildSyncedAttributes(array $state): array
    {
        $userIds = array_values(array_filter([
            $state['kepala_user_id'] ?? null,
            $state['ppk_user_id'] ?? null,
            $state['bendahara_user_id'] ?? null,
        ]));

        $users = empty($userIds)
            ? collect()
            : User::with('profile')->whereIn('id', $userIds)->get()->keyBy('id');

        if (! empty($state['kepala_user_id']) && ($kepala = $users->get((int) $state['kepala_user_id']))) {
            $state['cert_signer_name'] = $kepala->profile?->full_name ?: $kepala->name;
            $state['cert_signer_nip'] = $kepala->profile?->nip ?: '-';
            if (empty($state['cert_signer_title'])) {
                $state['cert_signer_title'] = $kepala->profile?->jabatan ?: 'Kepala Badan Pusat Statistik Kabupaten Demak';
            }
        }

        if (! empty($state['ppk_user_id']) && ($ppk = $users->get((int) $state['ppk_user_id']))) {
            $state['ppk_name'] = $ppk->profile?->full_name ?: $ppk->name;
            $state['ppk_nip'] = $ppk->profile?->nip ?: '-';
            if (empty($state['ppk_title'])) {
                $state['ppk_title'] = 'Pejabat Pembuat Komitmen';
            }
        }

        if (! empty($state['bendahara_user_id']) && ($bendahara = $users->get((int) $state['bendahara_user_id']))) {
            $state['bendahara_name'] = $bendahara->profile?->full_name ?: $bendahara->name;
            $state['bendahara_nip'] = $bendahara->profile?->nip ?: '-';
            if (empty($state['bendahara_title'])) {
                $state['bendahara_title'] = 'Bendahara Pengeluaran';
            }
        }

        return $state;
    }

    /**
     * Options array of organic/internal employees for Filament Select dropdowns.
     *
     * @return array<int, string>
     */
    public static function getEmployeeOptions(): array
    {
        return User::with('profile')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Mitra'))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (User $user): array {
                $displayName = $user->profile?->full_name ?: $user->name;
                $nip = $user->profile?->nip ?: '-';
                return [$user->id => "{$displayName} (NIP. {$nip})"];
            })
            ->toArray();
    }
}
```

- [ ] **Step 5: Run settings migration on local DB & run test**

Run: `php artisan migrate --path=database/settings && php artisan test tests/Feature/OfficialSettingsSyncTest.php`
Expected: Migration succeeds, test PASSES.

- [ ] **Step 6: Commit Task 1**

```bash
git add database/settings/2026_11_25_090000_add_official_user_ids_to_system_settings.php app/Settings/SystemSettings.php app/Services/OfficialSettingsSyncService.php tests/Feature/OfficialSettingsSyncTest.php
git commit -m "feat(settings): add official user ID relations and sync service"
```

---

### Task 2: Redesign `SystemSettingsPage` UI (Filament 5 Schema: 4 Tab Full-Width + Fieldset + Dropdown Pegawai)

**Files:**
- Modify: `app/Filament/Pages/SystemSettingsPage.php:1-318`

> **Keputusan UI/UX (Filament 5 Schema API):** Menggunakan `Filament\Schemas\Components\Tabs` full-width (`persistTabInQueryString()`) dengan `Fieldset` + `Group` (`Filament\Schemas\Components\*`) + `prefixIcon` pada setiap input. Signature method di Filament 5 wajib menggunakan `public function form(Schema $schema): Schema` dan `$schema->components([...])`. Field lama yang tidak dipakai di form (`cert_signer_signature_path`, `surat_tugas_template_path`, `laporan_dinas_template_path`) **dihapus dari tampilan UI** agar bersih, namun properti di `SystemSettings.php` & nilai DB eksisting **tetap dipertahankan** sehingga `LpdExportService` dan fitur lama tetap aman.

- [ ] **Step 1: Update imports & `mount()` in `SystemSettingsPage.php`**

Pastikan import Filament 5 di `app/Filament/Pages/SystemSettingsPage.php`:

```php
use App\Services\OfficialSettingsSyncService;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
```

Tambahkan field baru pada `$this->form->fill([...])` di `mount(SystemSettings $s)`:

```php
            'kepala_user_id' => $s->kepala_user_id,
            'ppk_user_id' => $s->ppk_user_id,
            'bendahara_user_id' => $s->bendahara_user_id,
            'bendahara_name' => $s->bendahara_name ?? '-',
            'bendahara_nip' => $s->bendahara_nip ?? '-',
            'bendahara_title' => $s->bendahara_title ?? 'Bendahara Pengeluaran',
            'undangan_pelatihan_template_path' => $s->undangan_pelatihan_template_path,
```

- [ ] **Step 2: Replace `form(Schema $schema): Schema` with 4-Tab Full-Width Layout**

Susunan di `SystemSettingsPage::form(Schema $schema): Schema`:

```php
        return $schema->components([
            Tabs::make('Pengaturan Sistem')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Pejabat & Persuratan')
                        ->icon('heroicon-o-identification')
                        ->schema([
                            Fieldset::make('Identitas & Format Nomor Surat')
                                ->schema([
                                    TextInput::make('office_code')->label('Kode Kantor')->prefixIcon('heroicon-m-building-office')->required()->maxLength(20),
                                    TextInput::make('surat_prefix')->label('Prefix Surat')->prefixIcon('heroicon-m-hashtag')->required()->maxLength(10),
                                    TextInput::make('cert_city')->label('Kota Penetapan')->prefixIcon('heroicon-m-map-pin')->required(),
                                    FileUpload::make('logo_bps_path')
                                        ->label('Logo BPS')
                                        ->image()
                                        ->directory('logos')
                                        ->visibility('public')
                                        ->maxSize(2048)
                                        ->downloadable()
                                        ->openable()
                                        ->columnSpanFull(),
                                ])->columns(3)->columnSpanFull(),

                            Fieldset::make('Kepala BPS (Penandatangan Utama)')
                                ->schema([
                                    Select::make('kepala_user_id')
                                        ->label('Pilih Pegawai (Kepala BPS)')
                                        ->prefixIcon('heroicon-m-user-circle')
                                        ->options(fn () => OfficialSettingsSyncService::getEmployeeOptions())
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if ($state && ($u = \App\Models\User::with('profile')->find($state))) {
                                                $set('cert_signer_name', $u->profile?->full_name ?: $u->name);
                                                $set('cert_signer_nip', $u->profile?->nip ?: '-');
                                            }
                                        })
                                        ->helperText('Otomatis mengisi Nama & NIP Kepala untuk Surat Tugas, Undangan, & Sertifikat.')
                                        ->columnSpanFull(),
                                    Group::make([
                                        TextInput::make('cert_signer_name')->label('Nama Pejabat')->prefixIcon('heroicon-m-user')->required(),
                                        TextInput::make('cert_signer_nip')->label('NIP')->prefixIcon('heroicon-m-identification')->required(),
                                        TextInput::make('cert_signer_title')->label('Jabatan')->prefixIcon('heroicon-m-briefcase')->required(),
                                    ])->columns(3)->columnSpanFull(),
                                ])->columnSpanFull(),

                            Fieldset::make('Pejabat Pembuat Komitmen (PPK)')
                                ->schema([
                                    Select::make('ppk_user_id')
                                        ->label('Pilih Pegawai (PPK)')
                                        ->prefixIcon('heroicon-m-user-circle')
                                        ->options(fn () => OfficialSettingsSyncService::getEmployeeOptions())
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if ($state && ($u = \App\Models\User::with('profile')->find($state))) {
                                                $set('ppk_name', $u->profile?->full_name ?: $u->name);
                                                $set('ppk_nip', $u->profile?->nip ?: '-');
                                            }
                                        })
                                        ->helperText('Digunakan untuk SPPD dan Dokumen SPJ Pelatihan.')
                                        ->columnSpanFull(),
                                    Group::make([
                                        TextInput::make('ppk_name')->label('Nama PPK')->prefixIcon('heroicon-m-user')->required(),
                                        TextInput::make('ppk_nip')->label('NIP PPK')->prefixIcon('heroicon-m-identification')->required(),
                                        TextInput::make('ppk_title')->label('Jabatan PPK')->prefixIcon('heroicon-m-briefcase')->required(),
                                    ])->columns(3)->columnSpanFull(),
                                ])->columnSpanFull(),

                            Fieldset::make('Bendahara Pengeluaran')
                                ->schema([
                                    Select::make('bendahara_user_id')
                                        ->label('Pilih Pegawai (Bendahara)')
                                        ->prefixIcon('heroicon-m-user-circle')
                                        ->options(fn () => OfficialSettingsSyncService::getEmployeeOptions())
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if ($state && ($u = \App\Models\User::with('profile')->find($state))) {
                                                $set('bendahara_name', $u->profile?->full_name ?: $u->name);
                                                $set('bendahara_nip', $u->profile?->nip ?: '-');
                                            }
                                        })
                                        ->helperText('Digunakan untuk Kuitansi / Bukti Penerimaan Uang.')
                                        ->columnSpanFull(),
                                    Group::make([
                                        TextInput::make('bendahara_name')->label('Nama Bendahara')->prefixIcon('heroicon-m-user')->required(),
                                        TextInput::make('bendahara_nip')->label('NIP Bendahara')->prefixIcon('heroicon-m-identification')->required(),
                                        TextInput::make('bendahara_title')->label('Jabatan Bendahara')->prefixIcon('heroicon-m-briefcase')->required(),
                                    ])->columns(3)->columnSpanFull(),
                                ])->columnSpanFull(),
                        ]),

                    Tab::make('Template Dokumen')
                        ->icon('heroicon-o-document-duplicate')
                        ->schema([
                            Fieldset::make('Template Word Aktif (.docx)')
                                ->schema([
                                    FileUpload::make('sppd_template_path')
                                        ->label('Template SPPD (.docx)')
                                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                        ->directory('templates')->visibility('public')->maxSize(5120)
                                        ->downloadable()->openable()
                                        ->helperText('Variabel: ${nomor_sppd}, ${nama_ppk}, ${nip_ppk}, ${nama_pegawai}, ${nip_pegawai}, ${pangkat_golongan}, ${jabatan_pegawai}, ${tingkat_perjalanan}, ${maksud_perjalanan}, ${alat_angkutan}, ${tempat_berangkat}, ${tempat_tujuan}, ${lama_perjalanan}, ${tanggal_berangkat}, ${tanggal_kembali}, ${mak}, ${nomor_surat_tugas}, ${tanggal_surat}')
                                        ->columnSpanFull(),

                                    FileUpload::make('laporan_lembur_template_path')
                                        ->label('Template Laporan Lembur (.docx)')
                                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                        ->directory('templates')->visibility('public')->maxSize(5120)
                                        ->downloadable()->openable()
                                        ->helperText('Variabel: ${waktu}, ${nama_pegawai}, ${nip_pegawai}, ${pangkat_golongan}, ${jabatan}, ${unit_kerja}, ${mulai}, ${selesai}, ${pekerjaan}, ${jabatan_kepala}, ${nama_kepala}, ${nip_kepala}, ${foto_1} s/d ${foto_4}')
                                        ->columnSpanFull(),

                                    FileUpload::make('undangan_pelatihan_template_path')
                                        ->label('Template Undangan Pelatihan (.docx) — Opsional')
                                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                        ->directory('templates')->visibility('public')->maxSize(5120)
                                        ->downloadable()->openable()
                                        ->helperText('Kosongkan untuk memakai template default sistem. Isi jika ingin mengganti kop/redaksi surat undangan pelatihan.')
                                        ->columnSpanFull(),
                                ])->columns(1)->columnSpanFull(),
                        ]),

                    Tab::make('Kantor & Presensi')
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Fieldset::make('Lokasi Kantor Default')
                                ->schema([
                                    TextInput::make('default_office_name')->label('Nama Kantor')->prefixIcon('heroicon-m-building-office-2')->required()->maxLength(100)->columnSpanFull(),
                                    // MapPicker default_location + lat + lng + default_geofence_radius_m (pertahankan konfigurasi existing)
                                ])->columns(2)->columnSpanFull(),

                            Fieldset::make('Jam & Hari Kerja Default')
                                ->schema([
                                    TimePicker::make('default_work_start')->label('Mulai')->prefixIcon('heroicon-m-clock')->seconds(false)->required(),
                                    TimePicker::make('default_work_end')->label('Selesai')->prefixIcon('heroicon-m-clock')->seconds(false)->required(),
                                    CheckboxList::make('default_workdays')->label('Hari Kerja')->columns(4)->required()->columnSpanFull()
                                        ->options([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu']),
                                ])->columns(2)->columnSpanFull(),
                        ]),

                    Tab::make('Keamanan & AI')
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            Fieldset::make('Proteksi Dokumen PDF')
                                ->schema([
                                    TextInput::make('pdf_master_password')
                                        ->label('Password Master (Anti-Edit)')
                                        ->prefixIcon('heroicon-m-lock-closed')
                                        ->password()->revealable()
                                        ->helperText('Jika diisi, PDF Surat Tugas bersifat Read-Only.')
                                        ->columnSpanFull(),
                                ])->columnSpanFull(),

                            Fieldset::make('Integrasi AI (Google Gemini)')
                                ->schema([
                                    TextInput::make('gemini_api_key')
                                        ->label('Gemini API Key')->prefixIcon('heroicon-m-key')->password()->revealable()
                                        ->placeholder('AIzaSy...')
                                        ->helperText('Jika dikosongkan, sistem menggunakan nilai dari .env (GEMINI_API_KEY).'),
                                    Select::make('gemini_model')
                                        ->label('Model AI Gemini')->prefixIcon('heroicon-m-cpu-chip')
                                        ->options([
                                            'gemini-3.5-flash' => 'Gemini 3.5 Flash (Sangat Cepat & Cerdas - Rekomendasi)',
                                            'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash Lite (Ringan & Cepat)',
                                            'gemini-3.8-flash' => 'Gemini 3.8 Flash (Model Terbaru)',
                                        ])
                                        ->default('gemini-3.5-flash')->required(),
                                ])->columns(2)->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ])->statePath('data');
```

- [ ] **Step 3: Hook `OfficialSettingsSyncService` & add `undangan_pelatihan_template_path` to `$fileFields` in `save()`**

Di `SystemSettingsPage::save()`:

```php
        $state = collect($this->form->getState())
            ->except('default_location')
            ->toArray();

        $state = app(OfficialSettingsSyncService::class)->buildSyncedAttributes($state);
```

Dan tambahkan `'undangan_pelatihan_template_path'` ke `$fileFields` di `save()` (tetap pertahankan field file lama di `$fileFields` agar aman):

```php
        $fileFields = [
            'surat_tugas_template_path',
            'laporan_dinas_template_path',
            'laporan_lembur_template_path',
            'cert_signer_signature_path',
            'logo_bps_path',
            'sppd_template_path',
            'undangan_pelatihan_template_path',
        ];
```

- [ ] **Step 4: Syntax, Filament 5 route, & Livewire 4 smoke check**

Run: `php -l app/Filament/Pages/SystemSettingsPage.php && php artisan route:list --path=admin/system-settings && php artisan test tests/Feature/FilamentUpgradeSmokeTest.php`
Expected: No syntax errors, route listed cleanly, Livewire 4 `SystemSettingsPage` smoke test PASS.

- [ ] **Step 5: Commit Task 2**

```bash
git add app/Filament/Pages/SystemSettingsPage.php
git commit -m "feat(settings): redesign settings page into 4 Filament 5 tabs with official dropdowns"
```

---

### Task 3: Verifikasi Regresi Modul Eksisting (Surat Tugas, SPPD, Lembur)

**Files:**
- Modify: `tests/Feature/OfficialSettingsSyncTest.php`

- [ ] **Step 1: Add regression test asserting `SppdService` and `SuratTugas` snapshots read synced `SystemSettings`**

Append to `tests/Feature/OfficialSettingsSyncTest.php`:

```php
it('feeds synced settings seamlessly into SppdService without breaking legacy string consumers', function () {
    $ppk = User::factory()->create(['name' => 'Sulaeman']);
    $ppk->profile()->updateOrCreate(
        ['user_id' => $ppk->id],
        ['full_name' => 'Sulaeman, S.Stat', 'nip' => '198204012006041015']
    );

    $settings = app(SystemSettings::class);
    $synced = app(OfficialSettingsSyncService::class)->buildSyncedAttributes([
        'ppk_user_id' => $ppk->id,
        'ppk_title' => 'Pejabat Pembuat Komitmen',
    ]);
    $settings->fill($synced)->save();

    $pegawai = User::factory()->create(['name' => 'Budi']);
    $st = \App\Models\SuratTugas::create([
        'user_id' => $pegawai->id,
        'nomor_surat' => 'B-0001/33210/KP.650/2026',
        'nomor_urut' => 1,
        'kode_klasifikasi' => 'KP.650',
        'jabatan' => 'Staff',
        'keperluan' => 'Uji SPPD',
        'tempat_tugas' => 'Demak',
        'tanggal' => now(),
        'waktu_mulai' => now(),
        'waktu_selesai' => now(),
    ]);

    $sppd = app(\App\Services\SppdService::class)->createSppd($st, [
        'nomor_sppd' => 'B-0001/33210/SE2026/KP.650/2026',
        'nomor_urut_sppd' => 1,
    ]);

    expect($sppd->ppk_name)->toBe('Sulaeman, S.Stat')
        ->and($sppd->ppk_nip)->toBe('198204012006041015');
});
```

- [ ] **Step 2: Run entire relevant test suite**

Run: `php artisan test tests/Feature/OfficialSettingsSyncTest.php tests/Feature/FilamentUpgradeSmokeTest.php tests/Feature/SuratTugasNumberingTest.php tests/Unit/ServiceLayerRefactoringTest.php`
Expected: All PASS.

- [ ] **Step 3: Commit Task 3**

```bash
git add tests/Feature/OfficialSettingsSyncTest.php
git commit -m "test(settings): verify backward compatibility with SPPD and SuratTugas"
```

> **Checkpoint Fase 1:** Minta user mencoba membuka halaman **Pengaturan Sistem** di browser lokal, memilih Kepala/PPK/Bendahara dari dropdown, dan memverifikasi Surat Tugas & SPPD berjalan normal sebelum lanjut ke Fase 2.

---

## FASE 2: Skema Database Non-Destructive & Integrasi SIPETRA

### Task 4: Non-Destructive Migration pada `surveys` dan `survey_users`

**Files:**
- Create: `database/migrations/2026_11_25_100000_add_pelatihan_fields_to_surveys_and_survey_users.php`
- Modify: `app/Models/Survey.php:22-77`
- Modify: `app/Models/SurveyUser.php:16-43`

- [ ] **Step 1: Create migration file**

Create `database/migrations/2026_11_25_100000_add_pelatihan_fields_to_surveys_and_survey_users.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->boolean('is_pelatihan')->default(false)->index()->after('is_multiple');
            $table->string('lokasi')->nullable()->after('is_pelatihan');
            $table->string('kota_kegiatan')->nullable()->default('Semarang')->after('lokasi');
            $table->string('nomor_surat_undangan')->nullable()->after('kota_kegiatan');
            $table->date('tanggal_surat_undangan')->nullable()->after('nomor_surat_undangan');
            $table->text('rujukan_surat')->nullable()->after('tanggal_surat_undangan');
            $table->string('link_materi')->nullable()->after('rujukan_surat');

            // DIPA & Biaya Pelatihan
            $table->string('program')->nullable()->after('link_materi');
            $table->string('kegiatan_dipa')->nullable()->after('program');
            $table->string('klasifikasi_ro')->nullable()->after('kegiatan_dipa');
            $table->string('rincian_output')->nullable()->after('klasifikasi_ro');
            $table->string('komponen')->nullable()->after('rincian_output');
            $table->string('mak')->nullable()->default('524113')->after('komponen');
            $table->unsignedBigInteger('biaya_transport')->nullable()->default(100000)->after('mak');
            $table->unsignedBigInteger('biaya_uang_harian')->nullable()->default(130000)->after('biaya_transport');

            // Penanggung Jawab & ATK
            $table->foreignId('penyelenggara_user_id')->nullable()->after('biaya_uang_harian')->constrained('users')->nullOnDelete();
            $table->foreignId('ppk_user_id')->nullable()->after('penyelenggara_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('bendahara_user_id')->nullable()->after('ppk_user_id')->constrained('users')->nullOnDelete();
            $table->boolean('include_atk')->default(false)->after('bendahara_user_id');
            $table->json('atk_items')->nullable()->after('include_atk');
        });

        Schema::table('survey_users', function (Blueprint $table) {
            $table->string('role_pelatihan', 50)->default('PPL')->after('notes');
            $table->string('kelas', 50)->default('Kelas A')->after('role_pelatihan');
            $table->string('asal')->nullable()->after('kelas');
            $table->string('tujuan')->nullable()->after('asal');
            $table->unsignedBigInteger('transport_override')->nullable()->after('tujuan');
            $table->unsignedBigInteger('uang_harian_override')->nullable()->after('transport_override');
        });
    }

    public function down(): void
    {
        Schema::table('survey_users', function (Blueprint $table) {
            $table->dropColumn([
                'role_pelatihan', 'kelas', 'asal', 'tujuan',
                'transport_override', 'uang_harian_override',
            ]);
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropForeign(['penyelenggara_user_id']);
            $table->dropForeign(['ppk_user_id']);
            $table->dropForeign(['bendahara_user_id']);
            $table->dropColumn([
                'is_pelatihan', 'lokasi', 'kota_kegiatan', 'nomor_surat_undangan',
                'tanggal_surat_undangan', 'rujukan_surat', 'link_materi',
                'program', 'kegiatan_dipa', 'klasifikasi_ro', 'rincian_output',
                'komponen', 'mak', 'biaya_transport', 'biaya_uang_harian',
                'penyelenggara_user_id', 'ppk_user_id', 'bendahara_user_id',
                'include_atk', 'atk_items',
            ]);
        });
    }
};
```

- [ ] **Step 2: Update `app/Models/Survey.php` and `app/Models/SurveyUser.php`**

Tambahkan kolom ke `$fillable`, `$casts`, serta relasi `penyelenggara()`, `ppk()`, `bendahara()`, dan scope `scopePelatihan` / `scopeNonPelatihan` pada `Survey.php`:

```php
    // Tambahkan ke $fillable Survey.php:
    'is_pelatihan', 'lokasi', 'kota_kegiatan', 'nomor_surat_undangan',
    'tanggal_surat_undangan', 'rujukan_surat', 'link_materi',
    'program', 'kegiatan_dipa', 'klasifikasi_ro', 'rincian_output',
    'komponen', 'mak', 'biaya_transport', 'biaya_uang_harian',
    'penyelenggara_user_id', 'ppk_user_id', 'bendahara_user_id',
    'include_atk', 'atk_items',

    // Tambahkan ke $casts Survey.php:
    'is_pelatihan' => 'boolean',
    'tanggal_surat_undangan' => 'date',
    'biaya_transport' => 'integer',
    'biaya_uang_harian' => 'integer',
    'include_atk' => 'boolean',
    'atk_items' => 'array',
```

Update `participants()` pivot columns di `Survey.php` agar `withPivot(['status', 'registered_at', 'score', 'notes', 'role_pelatihan', 'kelas', 'asal', 'tujuan', 'transport_override', 'uang_harian_override'])`, serta tambahkan relasi:

```php
    public function penyelenggara(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'penyelenggara_user_id');
    }

    public function ppk(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'ppk_user_id');
    }

    public function bendahara(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'bendahara_user_id');
    }
```

Update `app/Models/SurveyUser.php`:

```php
    protected $fillable = [
        'survey_id',
        'user_id',
        'registered_at',
        'status',
        'score',
        'notes',
        'role_pelatihan',
        'kelas',
        'asal',
        'tujuan',
        'transport_override',
        'uang_harian_override',
    ];
```

- [ ] **Step 3: Run migration & commit**

Run: `php artisan migrate`
Expected: `2026_11_25_100000_add_pelatihan_fields_to_surveys_and_survey_users` DONE.

```bash
git add database/migrations/2026_11_25_100000_add_pelatihan_fields_to_surveys_and_survey_users.php app/Models/Survey.php app/Models/SurveyUser.php
git commit -m "feat(db): add pelatihan, DIPA, and class columns to surveys and survey_users"
```

---

### Task 5: Integrasi SIPETRA Batch Service (`SipetraUserDataService`)

**Files:**
- Create: `app/Services/SipetraUserDataService.php`
- Test: `tests/Unit/SipetraUserDataServiceTest.php`

- [ ] **Step 1: Write failing unit test**

Create `tests/Unit/SipetraUserDataServiceTest.php`:

```php
<?php

use App\Models\User;
use App\Services\SipetraUserDataService;
use Illuminate\Support\Collection;

it('maps sipetra records by sipetra_id first and falls back to lowercase email', function () {
    $u1 = new User(['name' => 'Dewi', 'email' => 'dewi@example.com', 'sipetra_id' => 101]);
    $u1->id = 1;
    $u2 = new User(['name' => 'Musaadah', 'email' => 'Musa@Example.com', 'sipetra_id' => null]);
    $u2->id = 2;

    $sipetraRows = collect([
        (object) [
            'id' => 101,
            'email' => 'other@example.com',
            'nik' => '3309024303909002',
            'nama_bank' => 'BRI',
            'nomor_rekening' => '588501046131533',
            'nama_pemilik_rekening' => 'Dewi Sartika',
            'alamat' => 'Demak',
        ],
        (object) [
            'id' => 202,
            'email' => 'musa@example.com',
            'nik' => '3321126706890003',
            'nama_bank' => 'BRI',
            'nomor_rekening' => '001601003908595',
            'nama_pemilik_rekening' => 'Musaadah',
            'alamat' => 'Wonosalam',
        ],
    ]);

    $service = new SipetraUserDataService();
    $mapped = $service->mapRowsToUsers(new Collection([$u1, $u2]), $sipetraRows);

    expect($mapped[1]->nama_bank)->toBe('BRI')
        ->and($mapped[1]->nomor_rekening)->toBe('588501046131533')
        ->and($mapped[2]->nik)->toBe('3321126706890003');
});
```

- [ ] **Step 2: Run test to verify failure**

Run: `php artisan test tests/Unit/SipetraUserDataServiceTest.php`
Expected: FAIL (`Class App\Services\SipetraUserDataService not found`).

- [ ] **Step 3: Implement `app/Services/SipetraUserDataService.php`**

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SipetraUserDataService
{
    /**
     * Batch fetch bank, account number, NIK, and address from db_sipetra.users
     * keyed by Puslah user_id. Never throws on connection failure (graceful fallback).
     *
     * @param  Collection<int, User>  $users
     * @return array<int, object>
     */
    public function fetchForUsers(Collection $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $sipetraIds = $users->pluck('sipetra_id')->filter()->unique()->values()->all();
        $emails = $users->pluck('email')->filter()->map(fn ($e) => strtolower((string) $e))->unique()->values()->all();

        if (empty($sipetraIds) && empty($emails)) {
            return [];
        }

        try {
            $rows = DB::connection('sipetra')
                ->table('users')
                ->select(['id', 'email', 'nik', 'nama_bank', 'nomor_rekening', 'nama_pemilik_rekening', 'alamat', 'nip', 'nip_baru'])
                ->where(function ($query) use ($sipetraIds, $emails) {
                    if (! empty($sipetraIds)) {
                        $query->whereIn('id', $sipetraIds);
                    }
                    if (! empty($emails)) {
                        $query->orWhereIn(DB::raw('LOWER(email)'), $emails);
                    }
                })
                ->get();

            return $this->mapRowsToUsers($users, $rows);
        } catch (\Throwable $e) {
            Log::warning('SipetraUserDataService fallback due to DB error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, object>  $sipetraRows
     * @return array<int, object>
     */
    public function mapRowsToUsers(Collection $users, Collection $sipetraRows): array
    {
        $byId = $sipetraRows->keyBy('id');
        $byEmail = $sipetraRows->keyBy(fn ($r) => strtolower((string) ($r->email ?? '')));

        $result = [];
        foreach ($users as $user) {
            $match = null;
            if ($user->sipetra_id && $byId->has($user->sipetra_id)) {
                $match = $byId->get($user->sipetra_id);
            } elseif ($user->email) {
                $match = $byEmail->get(strtolower((string) $user->email));
            }

            if ($match) {
                $result[$user->id] = $match;
            }
        }

        return $result;
    }
}
```

- [ ] **Step 4: Run test & commit**

Run: `php artisan test tests/Unit/SipetraUserDataServiceTest.php`
Expected: PASS.

```bash
git add app/Services/SipetraUserDataService.php tests/Unit/SipetraUserDataServiceTest.php
git commit -m "feat(sipetra): add batch service for bank, account, and NIK lookup"
```

---

## FASE 3: UI Dual Menu (`Kegiatan` & `Pelatihan`) & Pembagian Kelas

### Task 6: Rename Menu `Survei` $\rightarrow$ `Kegiatan` (Scoped Non-Pelatihan)

**Files:**
- Modify: `app/Filament/Resources/SurveyResource.php:21-25,179-185`
- Modify: `app/Filament/Resources/SurveyUserResource.php:33-36`

- [ ] **Step 1: Update labels & query scope in `SurveyResource.php` and `SurveyUserResource.php`**

Di `SurveyResource.php`:

```php
    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Kegiatan';
    protected static ?string $navigationLabel = 'Kegiatan';
    protected static ?string $modelLabel = 'Kegiatan';
    protected static ?string $pluralModelLabel = 'Kegiatan';
```

Dan di `SurveyResource::getEloquentQuery()`:

```php
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_pelatihan', false)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
```

Di `SurveyUserResource.php`:

```php
    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Kegiatan';
    protected static ?string $navigationLabel = 'Peserta Kegiatan';
```

- [ ] **Step 2: Verify routes and syntax**

Run: `php -l app/Filament/Resources/SurveyResource.php && php -l app/Filament/Resources/SurveyUserResource.php`
Expected: No syntax errors.

- [ ] **Step 3: Commit Task 6**

```bash
git add app/Filament/Resources/SurveyResource.php app/Filament/Resources/SurveyUserResource.php
git commit -m "refactor(ui): rename Survei menu to Kegiatan and scope non-pelatihan"
```

---

### Task 7: Create `PelatihanResource` (Menu Terpisah Khusus Pelatihan)

**Files:**
- Create: `app/Filament/Resources/PelatihanResource.php`
- Create: `app/Filament/Resources/PelatihanResource/Pages/ListPelatihans.php`
- Create: `app/Filament/Resources/PelatihanResource/Pages/CreatePelatihan.php`
- Create: `app/Filament/Resources/PelatihanResource/Pages/EditPelatihan.php`

- [ ] **Step 1: Create `PelatihanResource.php` and Pages**

Key config in `app/Filament/Resources/PelatihanResource.php` (Filament 5 Schema & Actions API):
- `protected static ?string $model = Survey::class;`
- `protected static ?string $slug = 'pelatihans';`
- `protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';`
- `protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Kegiatan';`
- `protected static ?string $navigationLabel = 'Pelatihan';`
- `protected static ?string $modelLabel = 'Pelatihan';`
- `protected static ?string $pluralModelLabel = 'Pelatihan';`
- `getEloquentQuery()` filters `->where('is_pelatihan', true)->withoutGlobalScopes([SoftDeletingScope::class])`.
- `public static function form(Schema $schema): Schema` menggunakan `$schema->components([...])` berisi 4 `Filament\Schemas\Components\Section` (atau `Fieldset` di dalam 1 `Section` full-span):
  1. `Informasi Pelatihan` (`name`, `description`, `dasar_surat`, `start_date`, `end_date`, `lokasi`, `kota_kegiatan`, `is_active`, `is_multiple`).
  2. `Surat Undangan & Materi` (`nomor_surat_undangan`, `tanggal_surat_undangan`, `rujukan_surat`, `link_materi`).
  3. `Anggaran DIPA & Translok` (`program`, `kegiatan_dipa`, `klasifikasi_ro`, `rincian_output`, `komponen`, `mak`, `biaya_transport`, `biaya_uang_harian`).
  4. `Pejabat & Perlengkapan ATK` (`penyelenggara_user_id`, `ppk_user_id` default `app(SystemSettings::class)->ppk_user_id`, `bendahara_user_id` default `app(SystemSettings::class)->bendahara_user_id`, toggle `include_atk`, `TagsInput` / `Repeater` `atk_items` default `['Totebag', 'Pulpen', 'Pensil 2B', 'Penghapus 2 buah', 'Rautan 1 buah', 'Blocknote']`).
- `table(Table $table)` menggunakan `->recordActions([...])` dan `->toolbarActions([...])` dengan `Filament\Actions\*`.
- `getRelations()` returns `[ParticipantsRelationManager::class]`.
- `CreatePelatihan::mutateFormDataBeforeCreate()` sets `$data['is_pelatihan'] = true;`.
- Tambahkan route `/admin/pelatihans` & `/admin/pelatihans/create` serta `ListPelatihans::class` / `CreatePelatihan::class` ke `tests/Feature/FilamentUpgradeSmokeTest.php`.

- [ ] **Step 2: Syntax & Filament resource check**

Run: `php -l app/Filament/Resources/PelatihanResource.php && php artisan route:list --path=admin/pelatihans`
Expected: Routes `/admin/pelatihans`, `/admin/pelatihans/create`, `/admin/pelatihans/{record}/edit` registered cleanly.

- [ ] **Step 3: Commit Task 7**

```bash
git add app/Filament/Resources/PelatihanResource.php app/Filament/Resources/PelatihanResource/
git commit -m "feat(pelatihan): create dedicated PelatihanResource sharing Survey model"
```

---

### Task 8: Upgrade `ParticipantsRelationManager` (Role, Kelas, Asal, Tujuan, Bulk Action)

**Files:**
- Modify: `app/Filament/Resources/SurveyResource/RelationManagers/ParticipantsRelationManager.php`

- [ ] **Step 1: Add conditional Pelatihan columns, auto-defaults, and bulk actions**

1. Helper visibility check: `$isPelatihan = fn ($livewire) => (bool) ($livewire->getOwnerRecord()?->is_pelatihan ?? false);`
2. Di `massAdd` action:
   - Mitra otomatis diisi `role_pelatihan = 'PPL'`.
   - Pegawai Organik otomatis diisi `role_pelatihan = 'PML'`.
   - `kelas` default `'Kelas A'`.
   - `tujuan` default `$survey->kota_kegiatan ?? 'Semarang'`.
   - `asal` diambil dari kecamatan/alamat profil (fallback `'Demak'`).
3. Di `table()->columns()`:
   - Tambah `SelectColumn::make('role_pelatihan')->options(['PPL' => 'PPL', 'PML' => 'PML', 'Inda' => 'Inda', 'Panitia' => 'Panitia'])->visible($isPelatihan)`
   - Tambah `SelectColumn::make('kelas')->options(['Kelas A' => 'Kelas A', 'Kelas B' => 'Kelas B', 'Kelas C' => 'Kelas C', 'Kelas D' => 'Kelas D'])->visible($isPelatihan)`
   - Tambah `TextInputColumn::make('asal')->visible($isPelatihan)` dan `TextInputColumn::make('tujuan')->visible($isPelatihan)`.
4. Tambah `SelectFilter::make('kelas')` dan `SelectFilter::make('role_pelatihan')`.
5. Tambah Bulk Actions di `->toolbarActions([...])` menggunakan `Filament\Actions\BulkAction` + `->schema([...])` (visible saat `$isPelatihan`):
   - `setKelas`: modal pilih `Kelas A/B/C/D` (atau ketik custom) $\rightarrow$ update massal `kelas`.
   - `setRolePelatihan`: modal pilih `PPL/PML/Inda/Panitia` $\rightarrow$ update massal `role_pelatihan`.
   - `setAsalTujuan`: modal isi `asal` / `tujuan` $\rightarrow$ update massal.

- [ ] **Step 2: Syntax check**

Run: `php -l app/Filament/Resources/SurveyResource/RelationManagers/ParticipantsRelationManager.php`
Expected: No syntax errors.

- [ ] **Step 3: Commit Task 8**

```bash
git add app/Filament/Resources/SurveyResource/RelationManagers/ParticipantsRelationManager.php
git commit -m "feat(pelatihan): add inline kelas/role editing, smart defaults, and bulk actions"
```

---

### Task 9: Export/Import Excel Pembagian Kelas Peserta

**Files:**
- Create: `app/Exports/PelatihanKelasTemplateExport.php`
- Create: `app/Imports/PelatihanKelasImport.php`
- Modify: `app/Filament/Resources/SurveyResource/RelationManagers/ParticipantsRelationManager.php`

- [ ] **Step 1: Create `PelatihanKelasTemplateExport` & `PelatihanKelasImport`**

- `PelatihanKelasTemplateExport`: Mengekspor baris `survey_users` milik `Survey $survey` dengan kolom `['ID', 'Nama', 'Email', 'Role (PPL/PML/Inda/Panitia)', 'Kelas', 'Asal', 'Tujuan']`.
- `PelatihanKelasImport`: Mengimpor file yang sama, mencocokkan `ID` (`survey_user_id` milik `$survey->id` agar terisolasi ke pelatihan itu saja) lalu memperbarui `role_pelatihan`, `kelas`, `asal`, `tujuan`.
- Tambahkan Header Action `exportKelasTemplate` dan `importKelas` di `ParticipantsRelationManager` (visible ketika `$isPelatihan`).

- [ ] **Step 2: Write unit test for round-trip update & run tests**

Run: `php -l app/Exports/PelatihanKelasTemplateExport.php app/Imports/PelatihanKelasImport.php`
Expected: No syntax errors.

- [ ] **Step 3: Commit Task 9**

```bash
git add app/Exports/PelatihanKelasTemplateExport.php app/Imports/PelatihanKelasImport.php app/Filament/Resources/SurveyResource/RelationManagers/ParticipantsRelationManager.php
git commit -m "feat(pelatihan): add Excel export/import for bulk class and role assignment"
```

---

## FASE 4: Generator Dokumen (Undangan Word, Paket SPJ Excel Multi-Sheet, Quick Print PDF)

### Task 10: `PelatihanSpjDataBuilder` (Data Aggregator & Kalkulasi Terbilang)

**Files:**
- Create: `app/Services/PelatihanSpjDataBuilder.php`
- Test: `tests/Feature/PelatihanDocumentsTest.php`

- [ ] **Step 1: Write failing feature test for data aggregation**

Create `tests/Feature/PelatihanDocumentsTest.php` menguji:
- Durasi hari dihitung inklusif (`2026-11-24` s.d. `2026-11-25` = `2` hari; `2026-11-24` s.d. `2026-11-24` = `1` hari).
- Peserta dipisahkan antara `peserta` (non-INDA, dikelompokkan per `kelas` lalu diurutkan) dan `indas` (`role_pelatihan === 'Inda'`).
- Nomor Surat Tugas terkait diambil otomatis dari `$survey->suratTugas()->latest()->first()`.
- Nominal total (`transport + (uang_harian * jumlah_hari)`) dan `terbilang` dihitung tepat.

- [ ] **Step 2: Run test to confirm failure**

Run: `php artisan test tests/Feature/PelatihanDocumentsTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement `app/Services/PelatihanSpjDataBuilder.php`**

Menyatukan:
- `$survey->load(['surveyUsers.user.profile', 'penyelenggara.profile', 'ppk.profile', 'bendahara.profile', 'suratTugas'])`.
- Panggil `SipetraUserDataService::fetchForUsers($users)` dalam 1 batch query.
- Resolusi Pejabat (Kepala dari `SystemSettings`, PPK dari `$survey->ppk ?? SystemSettings`, Bendahara dari `$survey->bendahara ?? SystemSettings`, Penyelenggara dari `$survey->penyelenggara`).
- Kembalikan struktur array terstandar yang dipakai oleh `PelatihanUndanganService` maupun seluruh sheet `PelatihanSpjExport`.

- [ ] **Step 4: Run test & commit**

Run: `php artisan test tests/Feature/PelatihanDocumentsTest.php`
Expected: PASS.

```bash
git add app/Services/PelatihanSpjDataBuilder.php tests/Feature/PelatihanDocumentsTest.php
git commit -m "feat(pelatihan): add SPJ data aggregator and calculation builder"
```

---

### Task 11: Undangan Pelatihan Word (`.docx`) & Template Generator

**Files:**
- Create: `app/Services/PelatihanUndanganService.php`
- Create: `resources/templates/template_undangan_pelatihan.docx`
- Create: `resources/views/pdf/daftar_hadir_pelatihan.blade.php`

- [ ] **Step 1: Generate default template `resources/templates/template_undangan_pelatihan.docx`**

Gunakan `PhpOffice\PhpWord\PhpWord` untuk membuat file template `.docx` resmi ber-Kop BPS Kabupaten Demak berisi placeholder:
- Halaman 1: `${nomor_surat}`, `${tanggal_surat}`, `${hal}`, `${rujukan_surat}`, `${nama_kegiatan}`, `${periode_pelatihan}`, `${jumlah_hari}`, `${lokasi}`, `${link_materi}`, `${nama_kepala}`.
- Halaman 2+: Lampiran 1 Daftar Peserta per Kelas menggunakan `${kelas_block}` / `cloneBlock` dan `cloneRow('no', ...)` untuk kolom `no`, `nama`, `asal`, `bertugas_sebagai`.
- Halaman Terakhir: Kerangka Lampiran 2 Jadwal Pelatihan siap tempel/edit di Word.

- [ ] **Step 2: Implement `PelatihanUndanganService.php`**

- `downloadUndanganWord(Survey $survey): BinaryFileResponse` menggunakan `TemplateProcessor` (mengisi variabel header dan `cloneBlock` per kelas + `cloneRow` peserta).
- `renderDaftarHadirPdf(Survey $survey): \Barryvdh\DomPDF\PDF` menggunakan view `resources/views/pdf/daftar_hadir_pelatihan.blade.php` (Quick Print PDF adaptif 1 hari atau multi-hari, dikelompokkan per kelas).

- [ ] **Step 3: Test Word & PDF generation in `tests/Feature/PelatihanDocumentsTest.php`**

Assert file `.docx` terbuat tanpa exception dan `renderDaftarHadirPdf()` mengembalikan stream PDF valid (`%PDF-`).

- [ ] **Step 4: Commit Task 11**

```bash
git add app/Services/PelatihanUndanganService.php resources/templates/template_undangan_pelatihan.docx resources/views/pdf/daftar_hadir_pelatihan.blade.php tests/Feature/PelatihanDocumentsTest.php
git commit -m "feat(pelatihan): add Word invitation generator and quick-print attendance PDF"
```

---

### Task 12: Paket SPJ Excel Multi-Sheet (`PelatihanSpjExport`)

**Files:**
- Create: `app/Exports/PelatihanSpjExport.php`
- Create: `app/Exports/PelatihanSheets/DaftarHadirSheet.php`
- Create: `app/Exports/PelatihanSheets/DaftarHadirIndaSheet.php`
- Create: `app/Exports/PelatihanSheets/BuktiPenerimaanUangSheet.php`
- Create: `app/Exports/PelatihanSheets/PernyataanRiilIndukSheet.php`
- Create: `app/Exports/PelatihanSheets/LampiranRiilSheet.php`
- Create: `app/Exports/PelatihanSheets/PernyataanTidakKendaraanSheet.php`
- Create: `app/Exports/PelatihanSheets/LampiranTidakKendaraanSheet.php`
- Create: `app/Exports/PelatihanSheets/LampiranLpdSheet.php`
- Create: `app/Exports/PelatihanSheets/TandaTerimaAtkSheet.php`

- [ ] **Step 1: Implement `PelatihanSpjExport.php` (`WithMultipleSheets`)**

Susunan sheet sesuai standar SPJ:
1. `Daftar Hadir` (per kelas atau gabungan; otomatis 1 kolom TTD jika `jumlah_hari === 1`, atau N kolom TTD per tanggal jika `jumlah_hari > 1`).
2. `Daftar Hadir INDA` (khusus peserta dengan `role_pelatihan === 'Inda'`, TTD Kepala & Penyelenggara).
3. `Bukti Penerimaan Uang` (Header DIPA lengkap, Bank/No Rekening/NIK dari Sipetra, rumus Excel `=(H{r}*J{r})+I{r}` atau `=I{r}+J{r}` & `=SUM(...)`, terbilang otomatis, TTD 3 pihak: Bendahara, PPK, Pembuat Daftar).
4. `Pernyataan Riil` (Lembar induk merujuk Nomor ST, tabel ringkas asal $\rightarrow$ tujuan $\rightarrow$ total, TTD PPK, Pelaksana, Kepala BPS).
5. `Lampiran Riil` (Tabel peserta + NIK/NIP + Kecamatan + Jumlah Transport + TTD zig-zag + TTD PPK).
6. `Pernyataan Tdk Kendaraan` (Lembar induk merujuk Nomor ST, Pangkat/Gol, Pelaksana).
7. `Lampiran Tdk Kendaraan` (Tabel peserta + NIK/NIP + Kecamatan + TTD zig-zag + TTD PPK).
8. `Lampiran LPD` (Tabel peserta + NIK/NIP + Kecamatan + TTD zig-zag + TTD PPK).
9. `Tanda Terima ATK` (Hanya ditambahkan ke array `sheets()` jika `$data['survey']->include_atk === true`).

- [ ] **Step 2: Add integration test inspecting generated `.xlsx` via PhpSpreadsheet**

Assert file `.xlsx` berhasil ditulis ke temp disk, memuat exact sheet titles (8 sheet saat `include_atk = false`, 9 sheet saat `include_atk = true`), dan formula `SUM` serta data rekening bank ada pada sheet `Bukti Penerimaan Uang`.

- [ ] **Step 3: Run full test suite & commit**

Run: `php artisan test tests/Feature/PelatihanDocumentsTest.php`
Expected: PASS.

```bash
git add app/Exports/PelatihanSpjExport.php app/Exports/PelatihanSheets/ tests/Feature/PelatihanDocumentsTest.php
git commit -m "feat(pelatihan): add multi-sheet SPJ Excel export with active formulas"
```

---

### Task 13: Integrasi Tombol Download di `PelatihanResource` & Final Verification

**Files:**
- Modify: `app/Filament/Resources/PelatihanResource.php`
- Modify: `app/Filament/Resources/PelatihanResource/Pages/EditPelatihan.php`
- Modify: `README.md` (Changelog saat fitur selesai diverifikasi)

- [ ] **Step 1: Add Action buttons in `PelatihanResource` `table()->recordActions([...])` & `EditPelatihan::getHeaderActions()`**

Tambahkan `Filament\Actions\ActionGroup` **"Dokumen Pelatihan"** berisi 3 `Filament\Actions\Action`:
1. `Download Undangan (.docx)` $\rightarrow$ memanggil `PelatihanUndanganService::downloadUndanganWord($record)`
2. `Download Paket SPJ (.xlsx)` $\rightarrow$ memanggil `Excel::download(new PelatihanSpjExport($record), "Paket_SPJ_{$slug}.xlsx")`
3. `Cetak Daftar Hadir (PDF)` $\rightarrow$ stream PDF `PelatihanUndanganService::renderDaftarHadirPdf($record)` (open in new tab).

- [ ] **Step 2: Run full test suite & code style check**

Run: `php artisan test && ./vendor/bin/pint --test`
Expected: All tests PASS, code formatting clean.

- [ ] **Step 3: Update `README.md` Changelog, Commit & Push**

Update `README.md` Changelog dengan tanggal dan ringkasan perubahan, lalu jalankan `git add .`, `git commit`, dan `git push` setelah user memverifikasi di browser lokal.
