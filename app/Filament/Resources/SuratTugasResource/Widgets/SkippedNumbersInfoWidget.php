<?php

namespace App\Filament\Resources\SuratTugasResource\Widgets;

use App\Models\BlockedSuratTugasNumber;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use App\Models\UserProfile;
use App\Settings\SystemSettings;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class SkippedNumbersInfoWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $view = 'filament.resources.surat-tugas-resource.widgets.skipped-numbers-info';

    protected int|string|array $columnSpan = 'full';

    public int $selectedYear;

    public function mount(): void
    {
        $this->selectedYear = now()->year;
    }

    public function getSkippedNumbersByMonth(): array
    {
        return SuratTugas::getSkippedNumbersByMonth($this->selectedYear);
    }

    public function getBlockedNumbersGrouped(): array
    {
        return BlockedSuratTugasNumber::getBlockedNumbersGroupedByKeterangan($this->selectedYear);
    }

    public function getBlockedGroupsDetails(): array
    {
        $records = BlockedSuratTugasNumber::where('year', $this->selectedYear)
            ->orderBy('nomor_urut')
            ->get();

        $grouped = [];
        foreach ($records as $record) {
            $ket = $record->keterangan ?? 'Tanpa Keterangan';
            $grouped[$ket][] = $record;
        }

        $result = [];
        foreach ($grouped as $ket => $items) {
            $numbers = array_column($items, 'nomor_urut');
            $result[] = [
                'keterangan' => $ket,
                'ranges' => SuratTugas::formatSkippedNumbers($numbers),
                'count' => count($numbers),
                'numbers' => $numbers,
            ];
        }

        return $result;
    }

    public function getAvailableYears(): array
    {
        $years = SuratTugas::query()
            ->selectRaw('YEAR(tanggal) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        if (empty($years)) {
            return [now()->year => now()->year];
        }

        if (!in_array(now()->year, $years)) {
            array_unshift($years, now()->year);
        }

        return array_combine($years, $years);
    }

    public function updatedSelectedYear(): void
    {
        // Widget re-renders automatically when selectedYear changes
    }

    public function buatSuratTugasAction(): Action
    {
        return Action::make('buatSuratTugas')
            ->label('Buat ST')
            ->icon('heroicon-m-document-plus')
            ->color('success')
            ->modalHeading(function (array $arguments) {
                $ket = $arguments['keterangan'] ?? '';
                return 'Buat Surat Tugas — ' . ($ket ? "({$ket})" : 'Nomor Terblokir');
            })
            ->modalDescription('Pilih nomor urut terblokir yang akan digunakan. Setelah Surat Tugas berhasil dibuat, nomor tersebut otomatis di-release.')
            ->modalSubmitActionLabel('Buat Surat Tugas')
            ->form(function (array $arguments) {
                $ket = $arguments['keterangan'] ?? '';
                $query = BlockedSuratTugasNumber::where('year', $this->selectedYear);

                if ($ket === 'Tanpa Keterangan') {
                    $query->where(function ($sq) {
                        $sq->whereNull('keterangan')->orWhere('keterangan', '');
                    });
                } else {
                    $query->where('keterangan', $ket);
                }

                $blockedRecords = $query->orderBy('nomor_urut')->get();

                $options = [];
                $settings = app(SystemSettings::class);
                $prefix = $settings->surat_prefix ?? 'B';
                $office = $settings->office_code ?? '33210';

                foreach ($blockedRecords as $rec) {
                    $urut = str_pad($rec->nomor_urut, 4, '0', STR_PAD_LEFT);
                    $preview = "{$prefix}-{$urut}/{$office}/KP.650/{$rec->year}";
                    $options[$rec->id] = "Nomor #{$rec->nomor_urut} ({$preview})";
                }

                $firstRecordId = $blockedRecords->first()?->id;

                return [
                    Forms\Components\Select::make('blocked_id')
                        ->label('Pilih Nomor Urut Terblokir')
                        ->options($options)
                        ->default($firstRecordId)
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('survey_id')
                        ->label('Survey (Opsional)')
                        ->options(Survey::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            $set('user_id', null);
                            if ($state) {
                                $survey = Survey::find($state);
                                if ($survey) {
                                    $set('keperluan', $survey->name);
                                    if ($survey->start_date) {
                                        $set('waktu_mulai', \Carbon\Carbon::parse($survey->start_date)->format('Y-m-d'));
                                    }
                                    if ($survey->end_date) {
                                        $set('waktu_selesai', \Carbon\Carbon::parse($survey->end_date)->format('Y-m-d'));
                                    }
                                }
                            }
                        }),

                    Forms\Components\Select::make('user_id')
                        ->label('Pegawai yang Ditugaskan')
                        ->options(function (Forms\Get $get) {
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
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
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
                            ->dehydrated(false)
                            ->visible(fn() => auth()->user()?->hasAnyRole(['super_admin', 'Kepala', 'Kasubag'])),
                        Forms\Components\DatePicker::make('waktu_mulai')
                            ->label('Mulai')
                            ->default(now()),
                        Forms\Components\DatePicker::make('waktu_selesai')
                            ->label('Selesai')
                            ->default(now()),
                    ])->columns(2),
                ];
            })
            ->action(function (array $data) {
                $record = BlockedSuratTugasNumber::find($data['blocked_id'] ?? null);
                if (!$record) {
                    Notification::make()
                        ->title('Nomor terblokir tidak ditemukan')
                        ->danger()
                        ->send();
                    return;
                }

                $settings = app(SystemSettings::class);
                $prefix = $settings->surat_prefix ?? 'B';
                $office = $settings->office_code ?? '33210';
                $klasifikasi = $data['kode_klasifikasi'] ?? 'KP.650';

                try {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data, $settings, $prefix, $office, $klasifikasi) {
                        $abaikanValidasi = $data['abaikan_validasi'] ?? false;
                        if (!$abaikanValidasi && SuratTugas::hasOverlap($data['user_id'], $data['survey_id'] ?? null, $data['waktu_mulai'] ?? null, $data['waktu_selesai'] ?? null)) {
                            throw new \Exception("Overlap: Pegawai ini sudah memiliki Surat Tugas di rentang tanggal tersebut untuk survey yang sama.");
                        }

                        $urut = str_pad($record->nomor_urut, 4, '0', STR_PAD_LEFT);
                        $nomorSurat = "{$prefix}-{$urut}/{$office}/{$klasifikasi}/{$record->year}";

                        if (SuratTugas::where('nomor_surat', $nomorSurat)->exists()) {
                            throw new \Exception("Nomor surat {$nomorSurat} sudah ada di database.");
                        }

                        SuratTugas::create([
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

                        $record->delete();
                    });

                    Notification::make()
                        ->title('Surat Tugas berhasil dibuat!')
                        ->body("Nomor #{$record->nomor_urut} telah digunakan dan nomor terblokir otomatis di-release.")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $body = str_starts_with($msg, 'Overlap:') ? substr($msg, 9) : $msg;
                    Notification::make()
                        ->title('Gagal membuat surat tugas')
                        ->body($body)
                        ->danger()
                        ->send();
                }
            });
    }

    public function releaseBlockedGroupAction(): Action
    {
        return Action::make('releaseBlockedGroup')
            ->label('Release Nomor')
            ->icon('heroicon-m-lock-open')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(function (array $arguments) {
                $ket = $arguments['keterangan'] ?? '';
                return 'Release Nomor Terblokir — ' . ($ket ? "({$ket})" : '');
            })
            ->modalDescription('Apakah Anda yakin ingin membatalkan/merelease semua nomor yang terblokir pada grup ini agar bisa dipakai untuk surat tugas baru?')
            ->action(function (array $arguments) {
                $ket = $arguments['keterangan'] ?? '';
                $query = BlockedSuratTugasNumber::where('year', $this->selectedYear);

                if ($ket === 'Tanpa Keterangan') {
                    $query->where(function ($sq) {
                        $sq->whereNull('keterangan')->orWhere('keterangan', '');
                    });
                } else {
                    $query->where('keterangan', $ket);
                }

                $count = $query->count();
                $query->delete();

                Notification::make()
                    ->title("{$count} nomor terblokir berhasil di-release")
                    ->success()
                    ->send();
            });
    }
}

