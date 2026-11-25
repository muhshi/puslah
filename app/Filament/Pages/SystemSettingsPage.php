<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\MapWithSearch;
use App\Models\User;
use App\Services\OfficialSettingsSyncService;
use App\Settings\SystemSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class SystemSettingsPage extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $slug = 'system-settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $title = 'Pengaturan Sistem';

    protected string $view = 'filament.pages.system-settings-page';

    protected static ?int $navigationSort = 70;

    public ?array $data = [];

    public function mount(SystemSettings $s): void
    {
        $lat = (float) ($s->default_office_lat ?: -6.894561);
        $lng = (float) ($s->default_office_lng ?: 110.637492);

        $this->form->fill([
            'default_office_name' => $s->default_office_name ?? 'BPS Kabupaten Demak',
            'default_location' => ['lat' => $lat, 'lng' => $lng],
            'default_office_lat' => $s->default_office_lat ?? 0,
            'default_office_lng' => $s->default_office_lng ?? 0,
            'default_geofence_radius_m' => $s->default_geofence_radius_m ?? 100,
            'default_work_start' => $s->default_work_start ?? '08:00',
            'default_work_end' => $s->default_work_end ?? '16:00',
            'default_workdays' => $s->default_workdays ?? [1, 2, 3, 4, 5],
            'cert_city' => $s->cert_city ?? 'Demak',
            'kepala_user_id' => $s->kepala_user_id,
            'cert_signer_name' => $s->cert_signer_name ?? '-',
            'cert_signer_nip' => $s->cert_signer_nip ?? '-',
            'cert_signer_title' => $s->cert_signer_title ?? 'Kepala Badan Pusat Statistik Kabupaten Demak',
            'ppk_user_id' => $s->ppk_user_id,
            'ppk_name' => $s->ppk_name ?? '-',
            'ppk_nip' => $s->ppk_nip ?? '-',
            'ppk_title' => $s->ppk_title ?? 'Pejabat Pembuat Komitmen',
            'bendahara_user_id' => $s->bendahara_user_id,
            'bendahara_name' => $s->bendahara_name ?? '-',
            'bendahara_nip' => $s->bendahara_nip ?? '-',
            'bendahara_title' => $s->bendahara_title ?? 'Bendahara Pengeluaran',
            'kasubag_umum_user_id' => $s->kasubag_umum_user_id,
            'kasubag_umum_name' => $s->kasubag_umum_name ?? '-',
            'kasubag_umum_nip' => $s->kasubag_umum_nip ?? '-',
            'kasubag_umum_title' => $s->kasubag_umum_title ?? 'Kepala Subbagian Umum',
            'office_code' => $s->office_code ?? '33210',
            'surat_prefix' => $s->surat_prefix ?? 'B',
            'laporan_lembur_template_path' => $s->laporan_lembur_template_path,
            'logo_bps_path' => $s->logo_bps_path,
            'pdf_master_password' => $s->pdf_master_password,
            'sppd_template_path' => $s->sppd_template_path,
            'undangan_pelatihan_template_path' => $s->undangan_pelatihan_template_path,
            'gemini_api_key' => $s->gemini_api_key,
            'gemini_model' => $s->gemini_model ?? 'gemini-3.5-flash',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Pengaturan Sistem')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Pejabat & Persuratan')
                        ->icon('heroicon-o-identification')
                        ->schema([
                            Fieldset::make('Identitas & Format Nomor Surat')
                                ->schema([
                                    TextInput::make('office_code')
                                        ->label('Kode Kantor')
                                        ->prefixIcon('heroicon-m-building-office')
                                        ->required()
                                        ->maxLength(20),
                                    TextInput::make('surat_prefix')
                                        ->label('Prefix Surat')
                                        ->prefixIcon('heroicon-m-hashtag')
                                        ->required()
                                        ->maxLength(10),
                                    TextInput::make('cert_city')
                                        ->label('Kota Penetapan')
                                        ->prefixIcon('heroicon-m-map-pin')
                                        ->required(),
                                    FileUpload::make('logo_bps_path')
                                        ->label('Logo BPS')
                                        ->image()
                                        ->disk('public')
                                        ->directory('logos')
                                        ->visibility('public')
                                        ->maxSize(2048)
                                        ->downloadable()
                                        ->openable()
                                        ->hint(fn () => $this->formatStoredFileHint(app(SystemSettings::class)->logo_bps_path))
                                        ->columnSpanFull(),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),

                            $this->makeOfficialFieldset(
                                title: 'Kepala BPS (Penandatangan Utama)',
                                userField: 'kepala_user_id',
                                userLabel: 'Pilih Pegawai (Kepala BPS)',
                                nameField: 'cert_signer_name',
                                nameLabel: 'Nama Pejabat',
                                nipField: 'cert_signer_nip',
                                nipLabel: 'NIP',
                                titleField: 'cert_signer_title',
                                titleLabel: 'Jabatan',
                                helperText: 'Otomatis mengisi Nama & NIP Kepala untuk Surat Tugas, Undangan, & Sertifikat.'
                            ),

                            $this->makeOfficialFieldset(
                                title: 'Pejabat Pembuat Komitmen (PPK)',
                                userField: 'ppk_user_id',
                                userLabel: 'Pilih Pegawai (PPK)',
                                nameField: 'ppk_name',
                                nameLabel: 'Nama PPK',
                                nipField: 'ppk_nip',
                                nipLabel: 'NIP PPK',
                                titleField: 'ppk_title',
                                titleLabel: 'Jabatan PPK',
                                helperText: 'Digunakan untuk SPPD dan Dokumen SPJ Pelatihan.'
                            ),

                            $this->makeOfficialFieldset(
                                title: 'Bendahara Pengeluaran',
                                userField: 'bendahara_user_id',
                                userLabel: 'Pilih Pegawai (Bendahara)',
                                nameField: 'bendahara_name',
                                nameLabel: 'Nama Bendahara',
                                nipField: 'bendahara_nip',
                                nipLabel: 'NIP Bendahara',
                                titleField: 'bendahara_title',
                                titleLabel: 'Jabatan Bendahara',
                                helperText: 'Digunakan untuk Kuitansi / Bukti Penerimaan Uang.'
                            ),

                            $this->makeOfficialFieldset(
                                title: 'Kasubag Umum / Penanggung Jawab',
                                userField: 'kasubag_umum_user_id',
                                userLabel: 'Pilih Pegawai (Kasubag Umum)',
                                nameField: 'kasubag_umum_name',
                                nameLabel: 'Nama Kasubag Umum',
                                nipField: 'kasubag_umum_nip',
                                nipLabel: 'NIP Kasubag Umum',
                                titleField: 'kasubag_umum_title',
                                titleLabel: 'Jabatan Penanggung Jawab',
                                helperText: 'Digunakan sebagai Penanggung Jawab / Penyelenggara pada Dokumen SPJ Pelatihan.'
                            ),
                        ]),

                    Tab::make('Template Dokumen')
                        ->icon('heroicon-o-document-duplicate')
                        ->schema([
                            Fieldset::make('Template Word Aktif (.docx)')
                                ->schema([
                                    FileUpload::make('sppd_template_path')
                                        ->label('Template SPPD (.docx)')
                                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                        ->disk('public')
                                        ->directory('templates')
                                        ->visibility('public')
                                        ->maxSize(5120)
                                        ->downloadable()
                                        ->openable()
                                        ->hint(fn () => $this->formatStoredFileHint(app(SystemSettings::class)->sppd_template_path))
                                        ->helperText('Variabel: ${nomor_sppd}, ${nama_ppk}, ${nip_ppk}, ${nama_pegawai}, ${nip_pegawai}, ${pangkat_golongan}, ${jabatan_pegawai}, ${tingkat_perjalanan}, ${maksud_perjalanan}, ${alat_angkutan}, ${tempat_berangkat}, ${tempat_tujuan}, ${lama_perjalanan}, ${tanggal_berangkat}, ${tanggal_kembali}, ${mak}, ${nomor_surat_tugas}, ${tanggal_surat}')
                                        ->columnSpanFull(),

                                    FileUpload::make('laporan_lembur_template_path')
                                        ->label('Template Laporan Lembur (.docx)')
                                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                        ->disk('public')
                                        ->directory('templates')
                                        ->visibility('public')
                                        ->maxSize(5120)
                                        ->downloadable()
                                        ->openable()
                                        ->hint(fn () => $this->formatStoredFileHint(app(SystemSettings::class)->laporan_lembur_template_path))
                                        ->helperText('Variabel: ${waktu}, ${nama_pegawai}, ${nip_pegawai}, ${pangkat_golongan}, ${jabatan}, ${unit_kerja}, ${mulai}, ${selesai}, ${pekerjaan}, ${jabatan_kepala}, ${nama_kepala}, ${nip_kepala}, ${foto_1} s/d ${foto_4}')
                                        ->columnSpanFull(),

                                    FileUpload::make('undangan_pelatihan_template_path')
                                        ->label('Template Undangan Pelatihan (.docx) — Opsional')
                                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                        ->disk('public')
                                        ->directory('templates')
                                        ->visibility('public')
                                        ->maxSize(5120)
                                        ->downloadable()
                                        ->openable()
                                        ->hint(fn () => $this->formatStoredFileHint(app(SystemSettings::class)->undangan_pelatihan_template_path))
                                        ->helperText('Kosongkan untuk memakai template default sistem. Isi jika ingin mengganti kop/redaksi surat undangan pelatihan.')
                                        ->columnSpanFull(),
                                ])
                                ->columns(1)
                                ->columnSpanFull(),
                        ]),

                    Tab::make('Kantor & Presensi')
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Fieldset::make('Lokasi Kantor Default')
                                ->schema([
                                    TextInput::make('default_office_name')
                                        ->label('Nama Kantor')
                                        ->prefixIcon('heroicon-m-building-office-2')
                                        ->required()
                                        ->maxLength(100)
                                        ->columnSpanFull(),

                                    MapWithSearch::make('default_location')
                                        ->label('Peta Lokasi Kantor')
                                        ->columnSpanFull(),

                                    TextInput::make('default_office_lat')
                                        ->label('Latitude')
                                        ->prefixIcon('heroicon-m-globe-alt')
                                        ->required()
                                        ->numeric(),
                                    TextInput::make('default_office_lng')
                                        ->label('Longitude')
                                        ->prefixIcon('heroicon-m-globe-alt')
                                        ->required()
                                        ->numeric(),
                                    TextInput::make('default_geofence_radius_m')
                                        ->label('Radius (m)')
                                        ->prefixIcon('heroicon-m-signal')
                                        ->numeric()
                                        ->minValue(10)
                                        ->required()
                                        ->suffix('m')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->columnSpanFull(),

                            Fieldset::make('Jam & Hari Kerja Default')
                                ->schema([
                                    TimePicker::make('default_work_start')
                                        ->label('Mulai')
                                        ->prefixIcon('heroicon-m-clock')
                                        ->seconds(false)
                                        ->required(),
                                    TimePicker::make('default_work_end')
                                        ->label('Selesai')
                                        ->prefixIcon('heroicon-m-clock')
                                        ->seconds(false)
                                        ->required(),
                                    CheckboxList::make('default_workdays')
                                        ->label('Hari Kerja')
                                        ->columns(4)
                                        ->required()
                                        ->columnSpanFull()
                                        ->options([
                                            1 => 'Senin',
                                            2 => 'Selasa',
                                            3 => 'Rabu',
                                            4 => 'Kamis',
                                            5 => 'Jumat',
                                            6 => 'Sabtu',
                                            7 => 'Minggu',
                                        ]),
                                ])
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),

                    Tab::make('Keamanan & AI')
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            Fieldset::make('Proteksi Dokumen PDF')
                                ->schema([
                                    TextInput::make('pdf_master_password')
                                        ->label('Password Master (Anti-Edit)')
                                        ->prefixIcon('heroicon-m-lock-closed')
                                        ->password()
                                        ->revealable()
                                        ->helperText('Jika diisi, PDF Surat Tugas akan bersifat Read-Only (tidak bisa diedit/copy). Admin memerlukan password ini untuk membuka akses edit.')
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),

                            Fieldset::make('Integrasi AI (Google Gemini)')
                                ->schema([
                                    TextInput::make('gemini_api_key')
                                        ->label('Gemini API Key')
                                        ->prefixIcon('heroicon-m-key')
                                        ->password()
                                        ->revealable()
                                        ->placeholder('AIzaSy...')
                                        ->helperText('Dapatkan gratis di https://aistudio.google.com/app/apikey. Jika dikosongkan di sini, sistem akan menggunakan nilai dari .env (GEMINI_API_KEY).'),
                                    Select::make('gemini_model')
                                        ->label('Model AI Gemini')
                                        ->prefixIcon('heroicon-m-cpu-chip')
                                        ->options([
                                            'gemini-3.5-flash' => 'Gemini 3.5 Flash (Sangat Cepat & Cerdas - Rekomendasi)',
                                            'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash Lite (Ringan & Cepat)',
                                            'gemini-3.8-flash' => 'Gemini 3.8 Flash (Model Terbaru)',
                                        ])
                                        ->default('gemini-3.5-flash')
                                        ->required(),
                                ])
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ])->statePath('data');
    }

    /**
     * Build a consistent Fieldset for selecting an internal official and auto-syncing their profile fields.
     */
    private function makeOfficialFieldset(
        string $title,
        string $userField,
        string $userLabel,
        string $nameField,
        string $nameLabel,
        string $nipField,
        string $nipLabel,
        string $titleField,
        string $titleLabel,
        string $helperText
    ): Fieldset {
        return Fieldset::make($title)
            ->schema([
                Select::make($userField)
                    ->label($userLabel)
                    ->prefixIcon('heroicon-m-user-circle')
                    ->options(fn () => OfficialSettingsSyncService::getEmployeeOptions())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) use ($nameField, $nipField): void {
                        if ($state && ($user = User::with('profile')->find($state))) {
                            $set($nameField, $user->profile?->full_name ?: $user->name);
                            $set($nipField, $user->profile?->nip ?: '-');
                        }
                    })
                    ->helperText($helperText)
                    ->columnSpanFull(),
                Group::make([
                    TextInput::make($nameField)
                        ->label($nameLabel)
                        ->prefixIcon('heroicon-m-user')
                        ->required(),
                    TextInput::make($nipField)
                        ->label($nipLabel)
                        ->prefixIcon('heroicon-m-identification')
                        ->required(),
                    TextInput::make($titleField)
                        ->label($titleLabel)
                        ->prefixIcon('heroicon-m-briefcase')
                        ->required(),
                ])->columns(3)->columnSpanFull(),
            ])
            ->columnSpanFull();
    }

    /**
     * Show stored path status so admins can see existing database bindings even if local storage lacks prod files.
     */
    private function formatStoredFileHint(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (Storage::disk('public')->exists($path)) {
            return 'Tersimpan: '.basename($path);
        }

        if (Storage::disk('local')->exists($path)) {
            return 'Tersimpan (disk lokal): '.basename($path);
        }

        return 'Path DB: '.basename($path).' (file belum ada di storage lokal)';
    }

    public function save(): void
    {
        $rawState = $this->form->getState();
        $settings = app(SystemSettings::class);

        $state = collect($rawState)
            ->except('default_location')
            ->toArray();

        // Sync coordinates from MapWithSearch if user moved the map marker without manually editing lat/lng inputs
        $mapLocation = $rawState['default_location'] ?? null;
        if (is_string($mapLocation)) {
            $mapLocation = json_decode($mapLocation, true);
        }

        $latUnchanged = (float) ($state['default_office_lat'] ?? 0) === (float) $settings->default_office_lat;
        $lngUnchanged = (float) ($state['default_office_lng'] ?? 0) === (float) $settings->default_office_lng;
        if ($latUnchanged && $lngUnchanged && is_array($mapLocation) && isset($mapLocation['lat'], $mapLocation['lng'])) {
            $state['default_office_lat'] = round((float) $mapLocation['lat'], 6);
            $state['default_office_lng'] = round((float) $mapLocation['lng'], 6);
        }

        $state = app(OfficialSettingsSyncService::class)->buildSyncedAttributes($state);

        if (strcmp($state['default_work_start'], $state['default_work_end']) >= 0) {
            Notification::make()->title('Jam mulai harus < jam selesai')->danger()->send();

            return;
        }

        $changed = [];

        // File/upload fields that should not be overwritten with null
        $fileFields = [
            'surat_tugas_template_path',
            'laporan_dinas_template_path',
            'laporan_lembur_template_path',
            'cert_signer_signature_path',
            'logo_bps_path',
            'sppd_template_path',
            'undangan_pelatihan_template_path',
        ];

        foreach ($state as $key => $value) {
            if (! property_exists($settings, $key)) {
                continue;
            }

            $original = $settings->{$key};

            // Skip file fields if new value is empty but original has a value
            if (in_array($key, $fileFields) && empty($value) && ! empty($original)) {
                continue;
            }

            if ($original != $value) {
                $changed[$key] = $value;
            }
        }

        if (empty($changed)) {
            Notification::make()->title('Tidak ada perubahan')->info()->send();

            return;
        }

        $settings->fill($changed)->save();

        // Keep form state & map coordinates aligned with saved values
        $state['default_location'] = [
            'lat' => (float) ($settings->default_office_lat ?: -6.894561),
            'lng' => (float) ($settings->default_office_lng ?: 110.637492),
        ];
        $this->form->fill(array_merge($state, $changed));

        Notification::make()->title('Pengaturan tersimpan')->success()->send();
    }
}
