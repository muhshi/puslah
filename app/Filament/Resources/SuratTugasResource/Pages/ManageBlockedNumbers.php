<?php

namespace App\Filament\Resources\SuratTugasResource\Pages;

use App\Filament\Resources\SuratTugasResource;
use App\Models\BlockedSuratTugasNumber;
use App\Models\SuratTugas;
use App\Models\User;
use App\Models\UserProfile;
use App\Settings\SystemSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ManageBlockedNumbers extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = SuratTugasResource::class;
    protected static string $view = 'filament.resources.surat-tugas-resource.pages.manage-blocked-numbers';
    protected static ?string $title = 'Kelola Nomor Terblokir';

    public ?int $year = null;
    public ?int $nomor_dari = null;
    public ?int $nomor_sampai = null;
    public ?string $keterangan = null;

    public function mount(): void
    {
        $this->year = now()->year;
        $nextNumber = SuratTugas::getNextNomorUrut($this->year);
        $this->nomor_dari = $nextNumber;
        $this->nomor_sampai = $nextNumber;
    }

    protected function getForms(): array
    {
        return [
            'blockingForm',
        ];
    }

    public function blockingForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Block Nomor Baru')
                    ->description('Blokir range nomor urut agar tidak dipakai saat pembuatan surat tugas.')
                    ->schema([
                        Forms\Components\TextInput::make('year')
                            ->label('Tahun')
                            ->numeric()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $nextNumber = SuratTugas::getNextNomorUrut((int) $state);
                                    $set('nomor_dari', $nextNumber);
                                    $set('nomor_sampai', $nextNumber);
                                }
                            }),

                        Forms\Components\TextInput::make('nomor_dari')
                            ->label('Nomor Dari')
                            ->numeric()
                            ->required()
                            ->minValue(1),

                        Forms\Components\TextInput::make('nomor_sampai')
                            ->label('Nomor Sampai')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText(function (Forms\Get $get) {
                                $dari = (int) ($get('nomor_dari') ?? 0);
                                $sampai = (int) ($get('nomor_sampai') ?? 0);
                                if ($dari > 0 && $sampai > 0 && $sampai >= $dari) {
                                    $count = $sampai - $dari + 1;
                                    return "{$count} nomor akan di-block (#{$dari} s/d #{$sampai})";
                                }
                                return '';
                            }),

                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan (Opsional)')
                            ->placeholder('Contoh: Reserved untuk ST Sakernas batch 2')
                            ->maxLength(500),
                    ])->columns(4),
            ]);
    }

    public function blockNumbers(): void
    {
        $dari = (int) $this->nomor_dari;
        $sampai = (int) $this->nomor_sampai;
        $year = (int) $this->year;
        $keterangan = $this->keterangan;

        if (!$dari || !$sampai || !$year) {
            Notification::make()
                ->title('Lengkapi semua field yang diperlukan')
                ->danger()
                ->send();
            return;
        }

        if ($sampai < $dari) {
            Notification::make()
                ->title('Nomor "Sampai" harus >= Nomor "Dari"')
                ->danger()
                ->send();
            return;
        }

        if (($sampai - $dari + 1) > 100) {
            Notification::make()
                ->title('Maksimal 100 nomor dalam sekali block')
                ->danger()
                ->send();
            return;
        }

        $count = BlockedSuratTugasNumber::blockRange($dari, $sampai, $year, $keterangan);

        if ($count > 0) {
            Notification::make()
                ->title("Berhasil memblokir {$count} nomor")
                ->body("Nomor #{$dari} s/d #{$sampai} untuk tahun {$year}")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Tidak ada nomor baru yang di-block')
                ->body('Semua nomor dalam range sudah dipakai atau sudah di-block sebelumnya.')
                ->warning()
                ->send();
        }

        // Reset the form with next available number
        $nextNumber = SuratTugas::getNextNomorUrut($year);
        $this->nomor_dari = $nextNumber;
        $this->nomor_sampai = $nextNumber;
        $this->keterangan = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BlockedSuratTugasNumber::query()->orderBy('year', 'desc')->orderBy('nomor_urut', 'asc'))
            ->columns([
                Tables\Columns\TextColumn::make('nomor_urut')
                    ->label('Nomor Urut')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('year')
                    ->label('Tahun')
                    ->sortable(),
                Tables\Columns\TextColumn::make('nomor_surat_preview')
                    ->label('Preview Nomor Surat')
                    ->getStateUsing(function (BlockedSuratTugasNumber $record) {
                        $settings = app(SystemSettings::class);
                        $prefix = $settings->surat_prefix ?? 'B';
                        $office = $settings->office_code ?? '33210';
                        $urut = str_pad($record->nomor_urut, 4, '0', STR_PAD_LEFT);
                        return "{$prefix}-{$urut}/{$office}/KP.650/{$record->year}";
                    }),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(50)
                    ->tooltip(fn(BlockedSuratTugasNumber $record) => $record->keterangan),
                Tables\Columns\TextColumn::make('blocker.name')
                    ->label('Di-block oleh'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Block')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(function () {
                        $years = BlockedSuratTugasNumber::distinct()
                            ->pluck('year')
                            ->sort()
                            ->reverse()
                            ->mapWithKeys(fn($y) => [$y => (string) $y])
                            ->toArray();
                        if (empty($years)) {
                            $years = [now()->year => (string) now()->year];
                        }
                        return $years;
                    })
                    ->default(now()->year),
            ])
            ->actions([
                Tables\Actions\Action::make('buatSuratTugas')
                    ->label('Buat ST')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->modalHeading(fn(BlockedSuratTugasNumber $record) => 'Buat Surat Tugas — Nomor #' . $record->nomor_urut)
                    ->modalDescription(fn(BlockedSuratTugasNumber $record) => 'Nomor surat akan menggunakan nomor urut #' . $record->nomor_urut . ' yang sudah di-block. Setelah dibuat, nomor ini otomatis di-release.')
                    ->modalSubmitActionLabel('Buat Surat Tugas')
                    ->form(function (BlockedSuratTugasNumber $record) {
                        $settings = app(SystemSettings::class);
                        $prefix = $settings->surat_prefix ?? 'B';
                        $office = $settings->office_code ?? '33210';
                        $urut = str_pad($record->nomor_urut, 4, '0', STR_PAD_LEFT);
                        $nomorSuratPreview = "{$prefix}-{$urut}/{$office}/KP.650/{$record->year}";

                        return [
                            Forms\Components\Placeholder::make('nomor_surat_info')
                                ->label('Nomor Surat')
                                ->content($nomorSuratPreview),

                            Forms\Components\Select::make('survey_id')
                                ->label('Survey (Opsional)')
                                ->options(\App\Models\Survey::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    $set('user_id', null);
                                    if ($state) {
                                        $survey = \App\Models\Survey::find($state);
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
                                        return \App\Models\SurveyUser::where('survey_id', $surveyId)
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
                                Forms\Components\DatePicker::make('waktu_mulai')
                                    ->label('Mulai')
                                    ->default(now()),
                                Forms\Components\DatePicker::make('waktu_selesai')
                                    ->label('Selesai')
                                    ->default(now()),
                            ])->columns(2),
                        ];
                    })
                    ->action(function (BlockedSuratTugasNumber $record, array $data) {
                        $settings = app(SystemSettings::class);
                        $prefix = $settings->surat_prefix ?? 'B';
                        $office = $settings->office_code ?? '33210';
                        $klasifikasi = $data['kode_klasifikasi'] ?? 'KP.650';
                        $urut = str_pad($record->nomor_urut, 4, '0', STR_PAD_LEFT);
                        $nomorSurat = "{$prefix}-{$urut}/{$office}/{$klasifikasi}/{$record->year}";

                        // Check if nomor_surat already exists
                        if (SuratTugas::where('nomor_surat', $nomorSurat)->exists()) {
                            Notification::make()
                                ->title('Nomor surat sudah dipakai!')
                                ->body("Nomor {$nomorSurat} sudah ada di database.")
                                ->danger()
                                ->send();
                            return;
                        }

                        try {
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

                            // Release the blocked number
                            $record->delete();

                            Notification::make()
                                ->title('Surat Tugas berhasil dibuat!')
                                ->body("Nomor {$nomorSurat} sudah dibuat dan nomor blokir otomatis di-release.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Gagal membuat surat tugas')
                                ->body('Terjadi kesalahan: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('release')
                    ->label('Release')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Release Nomor?')
                    ->modalDescription(fn(BlockedSuratTugasNumber $record) => "Nomor #{$record->nomor_urut} tahun {$record->year} akan di-release dan bisa dipakai untuk surat tugas baru.")
                    ->action(function (BlockedSuratTugasNumber $record) {
                        $record->delete();
                        Notification::make()
                            ->title("Nomor #{$record->nomor_urut} berhasil di-release")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('buatSuratTugasBulk')
                        ->label('Buat ST Kolektif')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->modalHeading(fn (\Illuminate\Database\Eloquent\Collection $records) => 'Buat Surat Tugas Kolektif — ' . $records->count() . ' Nomor Terpilih')
                        ->modalDescription('Pastikan jumlah pegawai yang dipilih tidak melebihi jumlah nomor yang Anda block. Sisa nomor block yang tidak terpakai akan tetap diblock.')
                        ->modalSubmitActionLabel('Buat Surat Tugas')
                        ->form([
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
                                            $set('mitra_user_ids', []);
                                            $set('pegawai_bps_user_ids', []);

                                            if ($state) {
                                                $survey = \App\Models\Survey::find($state);
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
                                            ->default(now()),
                                        Forms\Components\DatePicker::make('waktu_mulai')
                                            ->label('Mulai')
                                            ->default(now()),
                                        Forms\Components\DatePicker::make('waktu_selesai')
                                            ->label('Selesai')
                                            ->default(now()),
                                    ])->columns(3),
                                ]),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $mitraIds = $data['mitra_user_ids'] ?? [];
                            $pegawaiIds = $data['pegawai_bps_user_ids'] ?? [];
                            $userIds = array_merge($mitraIds, $pegawaiIds);

                            if (empty($userIds)) {
                                Notification::make()
                                    ->title('Pilih minimal 1 pegawai')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            if (count($userIds) > $records->count()) {
                                Notification::make()
                                    ->title('Jumlah pegawai melebihi nomor yang dipilih!')
                                    ->body('Anda memilih ' . count($userIds) . ' pegawai, tapi hanya ' . $records->count() . ' nomor blokir yang dipilih.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $settings = app(SystemSettings::class);
                            $prefix = $settings->surat_prefix ?? 'B';
                            $office = $settings->office_code ?? '33210';
                            $klasifikasi = $data['kode_klasifikasi'];

                            // Sort records cleanly
                            $records = $records->sortBy(function ($record) {
                                return $record->year * 100000 + $record->nomor_urut;
                            })->values();

                            $successCount = 0;
                            try {
                                \Illuminate\Support\Facades\DB::transaction(function () use ($userIds, $data, $settings, $prefix, $office, $klasifikasi, $records, &$successCount) {
                                    foreach ($userIds as $index => $userId) {
                                        $record = $records[$index];
                                        
                                        $urut = str_pad($record->nomor_urut, 4, '0', STR_PAD_LEFT);
                                        $nomorSurat = "{$prefix}-{$urut}/{$office}/{$klasifikasi}/{$record->year}";

                                        SuratTugas::create([
                                            'user_id' => $userId,
                                            'survey_id' => $data['survey_id'],
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
                                        $successCount++;
                                    }
                                });

                                Notification::make()
                                    ->title('Berhasil membuat ' . $successCount . ' surat tugas')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Gagal membuat surat tugas')
                                    ->body('Terjadi kesalahan: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Tables\Actions\BulkAction::make('releaseBulk')
                        ->label('Release Selected')
                        ->icon('heroicon-o-lock-open')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $count = $records->count();
                            $records->each->delete();

                            Notification::make()
                                ->title("{$count} nomor berhasil di-release")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('nomor_urut', 'asc');
    }
}
