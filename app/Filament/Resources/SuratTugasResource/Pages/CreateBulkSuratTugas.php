<?php

namespace App\Filament\Resources\SuratTugasResource\Pages;

use App\Filament\Resources\SuratTugasResource;
use App\Models\SuratTugas;
use App\Models\User;
use App\Settings\SystemSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\DB;

class CreateBulkSuratTugas extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SuratTugasResource::class;
    protected static string $view = 'filament.resources.surat-tugas-resource.pages.create-bulk-surat-tugas';
    protected static ?string $title = 'Buat Surat Tugas Kolektif';

    public ?array $data = [];

    public function mount(): void
    {
        $year = now()->year;
        $nextNumber = SuratTugas::getNextNomorUrut($year);

        $this->form->fill([
            'tanggal' => now(),
            'waktu_mulai' => now()->setTime(8, 0),
            'waktu_selesai' => now()->setTime(16, 0),
            'kode_klasifikasi' => 'KP.650',
            'nomor_urut_mulai' => $nextNumber,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pilih Survey & Pegawai')
                    ->description('Pilih survey terlebih dahulu, lalu pilih pegawai dari peserta survey tersebut.')
                    ->schema([
                        Forms\Components\Select::make('survey_id')
                            ->label('Survey')
                            ->options(\App\Models\Survey::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Reset user selections when survey changes
                                $set('mitra_user_ids', []);
                                $set('pegawai_bps_user_ids', []);

                                // Auto-fill keperluan
                                if ($state) {
                                    $survey = \App\Models\Survey::find($state);
                                    if ($survey) {
                                        $set('keperluan', "Pendataan {$survey->name}");
                                    }

                                    // Checker for empty users (those who don't have surat tugas yet)
                                    $hasMitra = \App\Models\SurveyUser::where('survey_id', $state)
                                        ->whereHas('user.roles', fn($q) => $q->where('name', 'Mitra'))
                                        ->whereDoesntHave('user.suratTugas', fn($q) => $q->where('survey_id', $state))
                                        ->exists();

                                    $hasOrganik = \App\Models\SurveyUser::where('survey_id', $state)
                                        ->whereHas('user.roles', fn($q) => $q->where('name', 'Organik'))
                                        ->whereDoesntHave('user.suratTugas', fn($q) => $q->where('survey_id', $state))
                                        ->exists();

                                    if (!$hasMitra && !$hasOrganik) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Semua petugas pada survey ini sudah memiliki Surat Tugas.')
                                            ->warning()
                                            ->send();
                                    }
                                }
                            })
                            ->columnSpan('full')
                            ->required(),

                        Forms\Components\Select::make('mitra_user_ids')
                            ->label('Pegawai Mitra')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (Forms\Get $get) {
                                $surveyId = $get('survey_id');
                                if (!$surveyId)
                                    return [];

                                return \App\Models\SurveyUser::where('survey_id', $surveyId)
                                    ->whereHas('user.roles', function ($q) {
                                        $q->where('name', 'Mitra');
                                    })
                                    ->whereDoesntHave('user.suratTugas', function ($q) use ($surveyId) {
                                        $q->where('survey_id', $surveyId);
                                    })
                                    ->with('user.profile')
                                    ->get()
                                    ->mapWithKeys(function ($su) {
                                        $jabatan = $su->user->profile->jabatan ?? '-';
                                        return [$su->user_id => "{$su->user->name} ({$jabatan})"];
                                    });
                            })
                            ->disabled(fn(Forms\Get $get) => !$get('survey_id'))
                            ->helperText('Hanya peserta survey dengan role Mitra'),

                        Forms\Components\Select::make('pegawai_bps_user_ids')
                            ->label('Pegawai Organik')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (Forms\Get $get) {
                                $surveyId = $get('survey_id');
                                if (!$surveyId)
                                    return [];

                                return \App\Models\SurveyUser::where('survey_id', $surveyId)
                                    ->whereHas('user.roles', function ($q) {
                                        $q->where('name', 'Organik');
                                    })
                                    ->whereDoesntHave('user.suratTugas', function ($q) use ($surveyId) {
                                        $q->where('survey_id', $surveyId);
                                    })
                                    ->with('user.profile')
                                    ->get()
                                    ->mapWithKeys(function ($su) {
                                        $jabatan = $su->user->profile->jabatan ?? '-';
                                        return [$su->user_id => "{$su->user->name} ({$jabatan})"];
                                    });
                            })
                            ->disabled(fn(Forms\Get $get) => !$get('survey_id'))
                            ->helperText('Hanya peserta survey dengan role Organik'),
                    ])->columns(2),

                Forms\Components\Section::make('Data Surat (Berlaku untuk Semua)')
                    ->schema([
                        Forms\Components\Group::make()->schema([
                            Forms\Components\TextInput::make('jabatan')
                                ->label('Jabatan (Saat Tugas)')
                                ->helperText('Jabatan yang sama untuk semua pegawai terpilih.')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\TextInput::make('kode_klasifikasi')
                                ->label('Klasifikasi')
                                ->default('KP.650')
                                ->required(),
                        ])->columns(2),

                        Forms\Components\Textarea::make('keperluan')
                            ->label('Keperluan')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('tempat_tugas')
                            ->label('Tempat Tugas')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Group::make()->schema([
                            Forms\Components\DatePicker::make('tanggal')
                                ->label('Tanggal Surat')
                                ->required()
                                ->default(now())
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if ($state) {
                                        $year = \Carbon\Carbon::parse($state)->year;
                                        $nextNumber = SuratTugas::getNextNomorUrut($year);
                                        $set('nomor_urut_mulai', $nextNumber);
                                    }
                                }),
                            Forms\Components\DateTimePicker::make('waktu_mulai')
                                ->label('Mulai')
                                ->seconds(false)
                                ->default(now()->setTime(8, 0)),
                            Forms\Components\DateTimePicker::make('waktu_selesai')
                                ->label('Selesai')
                                ->seconds(false)
                                ->default(now()->setTime(16, 0)),
                        ])->columns(3),

                        Forms\Components\Section::make('Penomoran Surat')
                            ->description('Tentukan nomor urut awal untuk surat tugas yang akan dibuat.')
                            ->schema([
                                Forms\Components\TextInput::make('nomor_urut_mulai')
                                    ->label('Nomor Urut Mulai')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->live(debounce: 500)
                                    ->helperText(function (Forms\Get $get) {
                                        $mitraCount = count($get('mitra_user_ids') ?? []);
                                        $pegawaiCount = count($get('pegawai_bps_user_ids') ?? []);
                                        $totalPegawai = $mitraCount + $pegawaiCount;
                                        $start = (int) ($get('nomor_urut_mulai') ?? 1);

                                        if ($totalPegawai > 0) {
                                            $end = $start + $totalPegawai - 1;
                                            return "{$totalPegawai} pegawai terpilih → Nomor #{$start} s/d #{$end}";
                                        }
                                        return 'Pilih pegawai untuk melihat range nomor';
                                    }),

                                Forms\Components\Placeholder::make('skipped_warning')
                                    ->label('')
                                    ->content(function (Forms\Get $get) {
                                        $tanggal = $get('tanggal');
                                        if (!$tanggal)
                                            return '';

                                        $year = \Carbon\Carbon::parse($tanggal)->year;
                                        $skipped = SuratTugas::getSkippedNumbers($year);

                                        if (empty($skipped))
                                            return '';

                                        $formatted = SuratTugas::formatSkippedNumbers($skipped);
                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="text-warning-600 dark:text-warning-400 text-sm">'
                                            . '<strong>⚠️ Nomor terlewat di tahun ' . $year . ':</strong> ' . $formatted
                                            . '</div>'
                                        );
                                    })
                                    ->visible(function (Forms\Get $get) {
                                        $tanggal = $get('tanggal');
                                        if (!$tanggal)
                                            return false;

                                        $year = \Carbon\Carbon::parse($tanggal)->year;
                                        return !empty(SuratTugas::getSkippedNumbers($year));
                                    }),
                            ])->columns(1),
                    ]),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        $data = $this->form->getState();

        // Merge user IDs from both Mitra and Pegawai BPS
        $mitraIds = $data['mitra_user_ids'] ?? [];
        $pegawaiIds = $data['pegawai_bps_user_ids'] ?? [];
        $userIds = array_merge($mitraIds, $pegawaiIds);

        if (empty($userIds)) {
            Notification::make()
                ->title('Pilih minimal 1 pegawai dari Mitra atau Pegawai BPS')
                ->danger()
                ->send();
            return;
        }

        $settings = app(SystemSettings::class);
        $prefix = $settings->surat_prefix ?? 'B';
        $office = $settings->office_code ?? '33210';
        $klasifikasi = $data['kode_klasifikasi'];
        $year = \Carbon\Carbon::parse($data['tanggal'])->year;

        // Use custom starting number from user input
        $currentUrut = (int) $data['nomor_urut_mulai'] - 1;

        DB::transaction(function () use ($userIds, $data, $settings, $prefix, $office, $klasifikasi, $year, &$currentUrut) {
            foreach ($userIds as $userId) {
                $currentUrut++;
                $urut = str_pad($currentUrut, 4, '0', STR_PAD_LEFT);
                $nomorSurat = "{$prefix}-{$urut}/{$office}/{$klasifikasi}/{$year}";

                SuratTugas::create([
                    'user_id' => $userId,
                    'survey_id' => $data['survey_id'],
                    'nomor_surat' => $nomorSurat,
                    'nomor_urut' => $currentUrut,
                    'kode_klasifikasi' => $klasifikasi,
                    'jabatan' => $data['jabatan'],
                    'keperluan' => $data['keperluan'],
                    'tempat_tugas' => $data['tempat_tugas'] ?? null,
                    'tanggal' => $data['tanggal'],
                    'waktu_mulai' => $data['waktu_mulai'],
                    'waktu_selesai' => $data['waktu_selesai'],
                    'signer_city' => $settings->cert_city,
                    'signer_name' => $settings->cert_signer_name,
                    'signer_nip' => $settings->cert_signer_nip,
                    'signer_title' => $settings->cert_signer_title,
                    'signer_signature_path' => $settings->cert_signer_signature_path,
                    'created_by' => auth()->id(),
                ]);
            }
        });

        Notification::make()
            ->title('Berhasil membuat ' . count($userIds) . ' surat tugas')
            ->success()
            ->send();

        $this->redirect(SuratTugasResource::getUrl('index'));
    }
}
