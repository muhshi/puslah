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
            'waktu_mulai' => now()->format('Y-m-d'),
            'waktu_selesai' => now()->format('Y-m-d'),
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
                            ->options(function () {
                                return \App\Models\Survey::where('is_active', true)
                                    ->whereHas('participants', function ($q) {
                                        // Hanya survey yang punya user tanpa surat tugas untuk survey tersebut
                                        $q->whereDoesntHave('suratTugas', function ($q_st) {
                                            $q_st->whereColumn('surat_tugas.survey_id', 'surveys.id');
                                        });
                                    })
                                    ->orderByDesc('created_at')
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Reset user selections when survey changes
                                $set('mitra_user_ids', []);
                                $set('pegawai_bps_user_ids', []);

                                // Auto-fill keperluan and dates
                                if ($state) {
                                    $survey = \App\Models\Survey::find($state);
                                    if ($survey) {
                                        $set('keperluan', "{$survey->name}");
                                        // Auto-fill waktu_mulai/selesai from survey dates
                                        if ($survey->start_date) {
                                            $set('waktu_mulai', \Carbon\Carbon::parse($survey->start_date)->format('Y-m-d'));
                                        }
                                        if ($survey->end_date) {
                                            $set('waktu_selesai', \Carbon\Carbon::parse($survey->end_date)->format('Y-m-d'));
                                        }
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
                            Forms\Components\ToggleButtons::make('sumber_jabatan')
                                ->label('Sumber Jabatan Pegawai')
                                ->options([
                                    'database' => 'Database',
                                    'manual' => 'Manual',
                                ])
                                ->default('database')
                                ->inline()
                                ->live()
                                ->required(),

                            Forms\Components\TextInput::make('jabatan')
                                ->label('Jabatan (Manual)')
                                ->helperText('Berlaku sama untuk semua pegawai terpilih.')
                                ->required(fn(Forms\Get $get) => $get('sumber_jabatan') === 'manual')
                                ->visible(fn(Forms\Get $get) => $get('sumber_jabatan') === 'manual')
                                ->maxLength(255),

                            Forms\Components\Hidden::make('kode_klasifikasi')
                                ->default('KP.650'),
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
                            Forms\Components\DatePicker::make('waktu_mulai')
                                ->label('Mulai')
                                ->default(now()),
                            Forms\Components\DatePicker::make('waktu_selesai')
                                ->label('Selesai')
                                ->default(now()),
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

                                        // Check if starting number already exists
                                        $tanggal = $get('tanggal');
                                        $year = $tanggal ? \Carbon\Carbon::parse($tanggal)->year : now()->year;
                                        $exists = SuratTugas::whereYear('tanggal', $year)
                                            ->where('nomor_urut', $start)
                                            ->exists();

                                        $warning = '';
                                        if ($exists) {
                                            $warning = '⚠️ Nomor ' . $start . ' sudah dipakai, akan otomatis dilewati. ';
                                        }

                                        if ($totalPegawai > 0) {
                                            // Calculate actual numbers that will be used (skipping existing and blocked ones)
                                            $usedNumbers = SuratTugas::getOccupiedNumbers($year);
                                            $assignedNumbers = [];
                                            $currentUrut = $start - 1;
                                            for ($i = 0; $i < $totalPegawai; $i++) {
                                                $currentUrut++;
                                                while (isset($usedNumbers[$currentUrut])) {
                                                    $currentUrut++;
                                                }
                                                $assignedNumbers[] = $currentUrut;
                                            }
                                            $first = $assignedNumbers[0];
                                            $last = end($assignedNumbers);
                                            return new \Illuminate\Support\HtmlString(
                                                ($warning ? '<span class="text-warning-600 dark:text-warning-400">' . $warning . '</span><br>' : '')
                                                . "{$totalPegawai} pegawai terpilih → Nomor #{$first} s/d #{$last} (nomor terpakai otomatis dilewati)"
                                            );
                                        }
                                        if ($warning) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<span class="text-warning-600 dark:text-warning-400">' . $warning . '</span>'
                                            );
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

        // Get all existing and blocked nomor_urut for this year to skip over them
        $usedNumbers = SuratTugas::getOccupiedNumbers($year);

        $sumberJabatan = $data['sumber_jabatan'] ?? 'database';
        $jabatanManual = $data['jabatan'] ?? '-';

        try {
            DB::transaction(function () use ($userIds, $data, $settings, $prefix, $office, $klasifikasi, $year, &$currentUrut, $usedNumbers, $sumberJabatan, $jabatanManual) {
                foreach ($userIds as $userId) {
                    $currentUrut++;
                    // Skip over already-used nomor_urut
                    while (isset($usedNumbers[$currentUrut])) {
                        $currentUrut++;
                    }

                    $urut = str_pad($currentUrut, 4, '0', STR_PAD_LEFT);
                    $nomorSurat = "{$prefix}-{$urut}/{$office}/{$klasifikasi}/{$year}";

                    $jabatanPegawai = $jabatanManual;
                    if ($sumberJabatan === 'database') {
                        $profile = \App\Models\UserProfile::where('user_id', $userId)->first();
                        $jabatanPegawai = $profile && !empty($profile->jabatan) ? $profile->jabatan : '-';
                    }

                    SuratTugas::create([
                        'user_id' => $userId,
                        'survey_id' => $data['survey_id'],
                        'nomor_surat' => $nomorSurat,
                        'nomor_urut' => $currentUrut,
                        'kode_klasifikasi' => $klasifikasi,
                        'jabatan' => $jabatanPegawai,
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
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal membuat surat tugas')
                ->body('Terjadi kesalahan: Nomor surat duplikat atau data tidak valid. Silakan cek nomor urut dan coba lagi.')
                ->danger()
                ->send();
        }
    }
}
