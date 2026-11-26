<?php

namespace App\Filament\Resources\SuratTugasResource\Support;

use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Toggle;
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
            $schema[] = Select::make('blocked_id')
                ->label('Pilih Nomor Urut Terblokir')
                ->options($blockedOptions)
                ->default($defaultBlockedId)
                ->required()
                ->live();
        } elseif ($record !== null) {
            $preview = SuratTugasNumberingService::formatNomorSurat($record->nomor_urut, 'KP.650', $record->year);
            $schema[] = TextEntry::make('nomor_surat_info')
                ->label('Nomor Surat')
                ->state($preview);
        }

        $schema = array_merge($schema, [
            Select::make('survey_id')
                ->label('Kegiatan / Pelatihan (Opsional)')
                ->options(fn () => self::getActiveSurveyOptions())
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (Set $set, $state) {
                    $set('user_id', null);
                    self::applySurveyDefaultsToForm($set, $state);
                }),

            Select::make('user_id')
                ->label('Pegawai yang Ditugaskan')
                ->options(function (Get $get) {
                    $surveyId = $get('survey_id');
                    if ($surveyId) {
                        return self::getSurveyParticipantOptions((int) $surveyId, enforceSingleConstraint: false);
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

            TextInput::make('jabatan')
                ->label('Jabatan (Saat Tugas)')
                ->required()
                ->maxLength(255),

            TextInput::make('kode_klasifikasi')
                ->label('Klasifikasi')
                ->default('KP.650')
                ->required(),

            Textarea::make('keperluan')
                ->label('Keperluan')
                ->required(),

            TextInput::make('tempat_tugas')
                ->label('Tempat Tugas')
                ->maxLength(255),

            DatePicker::make('tanggal')
                ->label('Tanggal Surat')
                ->required()
                ->default(now()),

            Group::make([
                Toggle::make('abaikan_validasi')
                    ->label('Abaikan Validasi Bentrok')
                    ->helperText('Hanya untuk Admin.')
                    ->default(false)
                    ->visible(fn() => auth()->user()?->hasAnyRole(['super_admin', 'Kepala', 'Kasubag'])),
                DatePicker::make('waktu_mulai')
                    ->label('Mulai')
                    ->default(now()),
                DatePicker::make('waktu_selesai')
                    ->label('Selesai')
                    ->default(now()),
            ])->columns(2),
        ]);

        return $schema;
    }

    /**
     * Daftar opsi kegiatan dan pelatihan aktif untuk dropdown Surat Tugas.
     *
     * @return array<int, string>
     */
    public static function getActiveSurveyOptions(?int $includeSurveyId = null, bool $onlyWithRemainingParticipants = false): array
    {
        $query = Survey::query()
            ->where(function ($outer) use ($includeSurveyId, $onlyWithRemainingParticipants) {
                $outer->where('is_active', true);

                if ($onlyWithRemainingParticipants) {
                    $outer->where(function ($eligible) {
                        $eligible->where('is_multiple', true)
                            ->orWhereHas('participants', function ($participants) {
                                $participants->whereDoesntHave('suratTugas', function ($suratTugas) {
                                    $suratTugas->whereColumn('surat_tugas.survey_id', 'surveys.id');
                                });
                            });
                    });
                }

                if ($includeSurveyId) {
                    $outer->orWhere('id', $includeSurveyId);
                }
            })
            ->orderByDesc('is_active')
            ->orderByDesc('created_at');

        return $query->get()
            ->mapWithKeys(fn (Survey $survey) => [$survey->id => $survey->selector_label])
            ->all();
    }

    /**
     * Daftar opsi peserta kegiatan/pelatihan (opsional filter role Organik/Mitra).
     *
     * @return array<int, string>
     */
    public static function getSurveyParticipantOptions(
        int $surveyId,
        ?string $userRole = null,
        bool $includeTerlampir = true,
        bool $enforceSingleConstraint = true,
    ): array {
        $survey = Survey::find($surveyId);
        $query = SurveyUser::where('survey_id', $surveyId);

        if ($userRole !== null) {
            $query->whereHas('user.roles', fn ($q) => $q->where('name', $userRole));
        }

        if ($enforceSingleConstraint && $survey && ! $survey->is_multiple) {
            $query->where(function ($q) use ($surveyId, $includeTerlampir) {
                if ($includeTerlampir) {
                    $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', '%Terlampir%'))
                        ->orWhereDoesntHave('user.suratTugas', fn ($sq) => $sq->where('survey_id', $surveyId));
                } else {
                    $q->whereDoesntHave('user.suratTugas', fn ($sq) => $sq->where('survey_id', $surveyId));
                }
            });
        }

        return $query->with('user.profile')
            ->get()
            ->filter(fn (SurveyUser $su) => $su->user !== null)
            ->mapWithKeys(fn (SurveyUser $su) => [$su->user_id => self::formatParticipantLabel($su, $survey)])
            ->all();
    }

    /**
     * Format label peserta beserta informasi peran & kelas bila kegiatan berupa Pelatihan.
     */
    public static function formatParticipantLabel(SurveyUser $surveyUser, ?Survey $survey = null): string
    {
        $jabatan = $surveyUser->user?->profile?->jabatan ?? '-';
        $label = "{$surveyUser->user?->name} ({$jabatan})";

        if ($survey?->is_pelatihan) {
            $details = array_filter([
                $surveyUser->role_pelatihan,
                $surveyUser->kelas,
            ]);

            if ($details !== []) {
                $label .= ' — '.implode(' · ', $details);
            }
        }

        return $label;
    }

    /**
     * Isi otomatis field keperluan, tanggal pelaksanaan, dan tempat tugas dari kegiatan/pelatihan terpilih.
     */
    public static function applySurveyDefaultsToForm(Set $set, mixed $surveyId, bool $includeTempatTugas = true): ?Survey
    {
        if (! $surveyId) {
            return null;
        }

        $survey = Survey::find($surveyId);
        if (! $survey) {
            return null;
        }

        $set('keperluan', "{$survey->name}");

        if ($survey->start_date) {
            $set('waktu_mulai', Carbon::parse($survey->start_date)->format('Y-m-d'));
        }

        if ($survey->end_date) {
            $set('waktu_selesai', Carbon::parse($survey->end_date)->format('Y-m-d'));
        }

        if ($includeTempatTugas && filled($survey->lokasi)) {
            $set('tempat_tugas', $survey->lokasi);
        }

        return $survey;
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
