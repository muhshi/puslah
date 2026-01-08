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
        $this->form->fill([
            'tanggal' => now(),
            'waktu_mulai' => now()->setTime(8, 0),
            'waktu_selesai' => now()->setTime(16, 0),
            'kode_klasifikasi' => 'KP.650',
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
                            ->label('Pegawai BPS')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (Forms\Get $get) {
                                $surveyId = $get('survey_id');
                                if (!$surveyId)
                                    return [];

                                return \App\Models\SurveyUser::where('survey_id', $surveyId)
                                    ->whereHas('user.roles', function ($q) {
                                        $q->where('name', 'Pegawai BPS');
                                    })
                                    ->with('user.profile')
                                    ->get()
                                    ->mapWithKeys(function ($su) {
                                        $jabatan = $su->user->profile->jabatan ?? '-';
                                        return [$su->user_id => "{$su->user->name} ({$jabatan})"];
                                    });
                            })
                            ->disabled(fn(Forms\Get $get) => !$get('survey_id'))
                            ->helperText('Hanya peserta survey dengan role Pegawai BPS'),
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
                                ->default(now()),
                            Forms\Components\DateTimePicker::make('waktu_mulai')
                                ->label('Mulai')
                                ->seconds(false)
                                ->default(now()->setTime(8, 0)),
                            Forms\Components\DateTimePicker::make('waktu_selesai')
                                ->label('Selesai')
                                ->seconds(false)
                                ->default(now()->setTime(16, 0)),
                        ])->columns(3),
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

        // Get max nomor_urut for the year
        $maxUrut = SuratTugas::whereYear('tanggal', $year)->max('nomor_urut') ?? 0;

        DB::transaction(function () use ($userIds, $data, $settings, $prefix, $office, $klasifikasi, $year, $maxUrut) {
            foreach ($userIds as $userId) {
                $maxUrut++;
                $urut = str_pad($maxUrut, 4, '0', STR_PAD_LEFT);
                $nomorSurat = "{$prefix}-{$urut}/{$office}/{$klasifikasi}/{$year}";

                SuratTugas::create([
                    'user_id' => $userId,
                    'survey_id' => $data['survey_id'],
                    'nomor_surat' => $nomorSurat,
                    'nomor_urut' => $maxUrut,
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
