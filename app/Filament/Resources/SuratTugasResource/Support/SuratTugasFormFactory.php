<?php

namespace App\Filament\Resources\SuratTugasResource\Support;

use App\Models\BlockedSuratTugasNumber;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\SuratTugasNumberingService;
use App\Settings\SystemSettings;
use Carbon\Carbon;
use Exception;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\DB;

class SuratTugasFormFactory
{
    /**
     * Skema form pembuatan Surat Tugas dari nomor terblokir/reserved.
     * Digunakan bersama oleh ManageBlockedNumbers dan SkippedNumbersInfoWidget.
     */
    public static function getBlockedNumberFormSchema(?BlockedSuratTugasNumber $record = null, ?array $blockedOptions = null, ?int $defaultBlockedId = null): array
    {
        $schema = [];

        // Jika dipanggil dari Widget dengan multiple opsi nomor terblokir
        if ($blockedOptions !== null) {
            $schema[] = Forms\Components\Select::make('blocked_id')
                ->label('Pilih Nomor Urut Terblokir')
                ->options($blockedOptions)
                ->default($defaultBlockedId)
                ->required()
                ->live();
        } elseif ($record !== null) {
            $preview = SuratTugasNumberingService::formatNomorSurat($record->nomor_urut, 'KP.650', $record->year);
            $schema[] = Forms\Components\Placeholder::make('nomor_surat_info')
                ->label('Nomor Surat')
                ->content($preview);
        }

        $schema = array_merge($schema, [
            Forms\Components\Select::make('survey_id')
                ->label('Survey (Opsional)')
                ->options(Survey::where('is_active', true)->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (Set $set, $state) {
                    $set('user_id', null);
                    if ($state) {
                        $survey = Survey::find($state);
                        if ($survey) {
                            $set('keperluan', $survey->name);
                            if ($survey->start_date) {
                                $set('waktu_mulai', Carbon::parse($survey->start_date)->format('Y-m-d'));
                            }
                            if ($survey->end_date) {
                                $set('waktu_selesai', Carbon::parse($survey->end_date)->format('Y-m-d'));
                            }
                        }
                    }
                }),

            Forms\Components\Select::make('user_id')
                ->label('Pegawai yang Ditugaskan')
                ->options(function (Get $get) {
                    $surveyId = $get('survey_id');
                    if ($surveyId) {
                        return SurveyUser::where('survey_id', $surveyId)
                            ->with('user.profile')
                            ->get()
                            ->mapWithKeys(function ($su) {
                                $jabatan = $su->user->profile->jabatan ?? '-';
                                return [$su->user_id => "{$su->user->name} ({$jabatan})"];
                            });
                    }
                    return User::with('profile')->get()->mapWithKeys(function ($user) {
                        $jabatan = $user->profile->jabatan ?? '-';
                        return [$user->id => "{$user->name} ({$jabatan})"];
                    });
                })
                ->searchable()
                ->preload()
                ->live()
                ->required()
                ->afterStateUpdated(function (Set $set, $state) {
                    if ($state) {
                        $profile = UserProfile::where('user_id', $state)->first();
                        if ($profile && $profile->jabatan) {
                            $set('jabatan', $profile->jabatan);
                        }
                    }
                }),

            Forms\Components\TextInput::make('jabatan')
                ->label('Jabatan (Saat Tugas)')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('kode_klasifikasi')
                ->label('Klasifikasi')
                ->default('KP.650')
                ->required(),

            Forms\Components\Textarea::make('keperluan')
                ->label('Keperluan')
                ->required(),

            Forms\Components\TextInput::make('tempat_tugas')
                ->label('Tempat Tugas')
                ->maxLength(255),

            Forms\Components\DatePicker::make('tanggal')
                ->label('Tanggal Surat')
                ->required()
                ->default(now()),

            Forms\Components\Group::make([
                Forms\Components\Toggle::make('abaikan_validasi')
                    ->label('Abaikan Validasi Bentrok')
                    ->helperText('Hanya untuk Admin.')
                    ->default(false)
                    ->visible(fn() => auth()->user()?->hasAnyRole(['super_admin', 'Kepala', 'Kasubag'])),
                Forms\Components\DatePicker::make('waktu_mulai')
                    ->label('Mulai')
                    ->default(now()),
                Forms\Components\DatePicker::make('waktu_selesai')
                    ->label('Selesai')
                    ->default(now()),
            ])->columns(2),
        ]);

        return $schema;
    }

    /**
     * Eksekusi pembuatan Surat Tugas dari nomor yang di-block dan hapus (release) status block-nya.
     *
     * @throws Exception
     */
    public static function createSuratTugasFromBlocked(BlockedSuratTugasNumber $record, array $data): SuratTugas
    {
        $settings = app(SystemSettings::class);
        $klasifikasi = $data['kode_klasifikasi'] ?? 'KP.650';
        $abaikanValidasi = $data['abaikan_validasi'] ?? false;

        return DB::transaction(function () use ($record, $data, $settings, $klasifikasi, $abaikanValidasi) {
            if (!$abaikanValidasi && ($overlap = SuratTugas::getOverlap($data['user_id'], $data['survey_id'] ?? null, $data['waktu_mulai'] ?? null, $data['waktu_selesai'] ?? null))) {
                $user = User::find($data['user_id']);
                $userName = $user ? $user->name : 'Pegawai ini';
                $overlapMsg = SuratTugas::formatOverlapMessage($userName, $overlap);
                throw new Exception("Overlap: {$overlapMsg}");
            }

            $nomorSurat = SuratTugasNumberingService::formatNomorSurat($record->nomor_urut, $klasifikasi, $record->year);

            // Cek apakah nomor_surat sudah ada
            if (SuratTugas::where('nomor_surat', $nomorSurat)->exists()) {
                throw new Exception("Nomor surat {$nomorSurat} sudah ada di database.");
            }

            $suratTugas = SuratTugas::create([
                'user_id' => $data['user_id'],
                'survey_id' => $data['survey_id'] ?? null,
                'nomor_surat' => $nomorSurat,
                'nomor_urut' => $record->nomor_urut,
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

            // Release nomor yang di-block
            $record->delete();

            return $suratTugas;
        });
    }
}
