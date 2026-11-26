<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\Survey;
use Carbon\Carbon;
use App\Models\SurveyUser;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Illuminate\Support\Str;
use App\Models\LaporanPerjalananDinas;
use Filament\Actions\EditAction;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use pxlrbt\FilamentExcel\Actions\ExportBulkAction;
use App\Jobs\GenerateBulkSuratTugasZip;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\SuratTugasResource\Pages\ListSuratTugas;
use App\Filament\Resources\SuratTugasResource\Pages\CreateSuratTugas;
use App\Filament\Resources\SuratTugasResource\Pages\CreateBulkSuratTugas;
use App\Filament\Resources\SuratTugasResource\Pages\ManageBlockedNumbers;
use App\Filament\Resources\SuratTugasResource\Pages\EditSuratTugas;
use App\Filament\Resources\SuratTugasResource\Pages\ListSuratTugasActivities;
use App\Filament\Resources\SuratTugasResource\Pages;
use App\Filament\Resources\SuratTugasResource\RelationManagers;
use App\Filament\Resources\SuratTugasResource\Support\SuratTugasFormFactory;
use App\Models\SuratTugas;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Settings\SystemSettings;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\User;
use App\Models\UserProfile;
use App\Filament\Resources\LaporanPerjalananDinasResource;
use App\Services\SppdService;
use App\Services\SuratTugasPdfService;
use App\Services\SuratTugasNumberingService;

class SuratTugasResource extends Resource
{
    protected static ?string $model = SuratTugas::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Dokumen';
    protected static ?string $navigationLabel = 'Surat Tugas';


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Tugas')->schema([
                    Group::make()->schema([
                        Select::make('survey_id')
                            ->label('Kegiatan / Pelatihan (Opsional)')
                            ->relationship(
                                'survey',
                                'name',
                                fn (Builder $query) => $query->orderByDesc('is_active')->orderByDesc('created_at')
                            )
                            ->getOptionLabelFromRecordUsing(fn (Survey $record) => $record->selector_label)
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Reset user_id when survey changes
                                $set('user_id', null);
                                // Auto-fill keperluan, dates, and location if survey/pelatihan selected
                                if ($state) {
                                    $survey = SuratTugasFormFactory::applySurveyDefaultsToForm($set, $state);
                                    if (! $survey) {
                                        return;
                                    }

                                    // Checker for empty users
                                    $query = SurveyUser::where('survey_id', $state);
                                    if (! $survey->is_multiple) {
                                        $query->where(function ($q) use ($state) {
                                            $q->whereHas('user', function ($uq) {
                                                $uq->where('name', 'like', '%Terlampir%');
                                            })->orWhereDoesntHave('user.suratTugas', function ($sq) use ($state) {
                                                $sq->where('survey_id', $state);
                                            });
                                        });
                                    }
                                    $hasParticipants = $query->exists();

                                    if (! $hasParticipants) {
                                        Notification::make()
                                            ->title('Semua petugas pada kegiatan/pelatihan ini sudah memiliki Surat Tugas.')
                                            ->warning()
                                            ->send();
                                    }
                                }
                            }),

                        \Filament\Forms\Components\Radio::make('tipe_penugasan')
                            ->label('Metode Penugasan')
                            ->options([
                                'individu' => 'Individu (1 Pegawai)',
                                'kolektif' => 'Kolektif / Terlampir (Banyak Petugas)',
                            ])
                            ->default('individu')
                            ->inline()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state === 'kolektif') {
                                    $terlampir = \App\Models\SuratTugas::getOrCreateTerlampirUser();
                                    $set('user_id', $terlampir->id);
                                    $set('jabatan', 'Terlampir');
                                } else {
                                    $set('user_id', null);
                                    $set('jabatan', '');
                                }
                            }),

                        Select::make('participant_ids')
                            ->label('Daftar Petugas yang Ditugaskan (Halaman Lampiran)')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(function (Get $get) {
                                $surveyId = $get('survey_id');
                                if ($surveyId) {
                                    $survey = \App\Models\Survey::with(['classes', 'surveyUsers.user.profile', 'surveyUsers.class'])->find($surveyId);
                                    if ($survey) {
                                        $participants = $survey->surveyUsers;
                                        if ($survey->is_multiclass || $survey->is_pelatihan) {
                                            return $participants->mapWithKeys(function ($su) {
                                                $userName = $su->user?->profile?->full_name ?: ($su->user?->name ?: 'Petugas');
                                                $className = $su->class?->name ?: ($su->kelas ?: 'Tanpa Kelas');
                                                $role = $su->role ?: ($su->role_pelatihan ?: 'Peserta');
                                                return [$su->user_id => "[{$className}] {$userName} ({$role})"];
                                            });
                                        } else {
                                            return $participants->mapWithKeys(function ($su) {
                                                $userName = $su->user?->profile?->full_name ?: ($su->user?->name ?: 'Petugas');
                                                $role = $su->role ?: ($su->role_pelatihan ?: 'Peserta');
                                                return [$su->user_id => "{$userName} ({$role})"];
                                            });
                                        }
                                    }
                                }

                                return User::with('profile')->get()->mapWithKeys(function ($u) {
                                    $name = $u->profile?->full_name ?: $u->name;
                                    return [$u->id => "{$name}"];
                                });
                            })
                            ->helperText('Petugas yang dipilih otomatis dicantumkan pada Halaman Lampiran Surat Tugas (dikelompokkan per kelas jika kegiatan multi-kelas).')
                            ->visible(fn (Get $get) => $get('tipe_penugasan') === 'kolektif')
                            ->required(fn (Get $get) => $get('tipe_penugasan') === 'kolektif'),

                        Select::make('user_id')
                            ->label('Pegawai yang Ditugaskan')
                            ->relationship('user', 'name')
                            ->visible(fn (Get $get) => $get('tipe_penugasan') !== 'kolektif')
                            ->options(function (Get $get) {
                                $surveyId = $get('survey_id');

                                // If survey/pelatihan selected, filter by participants only
                                if ($surveyId) {
                                    return SuratTugasFormFactory::getSurveyParticipantOptions((int) $surveyId);
                                }

                                // If no survey, show all users
                                return User::with('profile')
                                    ->get()
                                    ->mapWithKeys(function ($user) {
                                        $jabatan = $user->profile->jabatan ?? '-';

                                        return [$user->id => "{$user->name} ({$jabatan})"];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $profile = UserProfile::where('user_id', $state)->first();
                                    if ($profile && $profile->jabatan) {
                                        $set('jabatan', $profile->jabatan);
                                    }
                                }
                            })
                            ->required(),

                        TextInput::make('jabatan')
                            ->label('Jabatan (Saat Tugas)')
                            ->helperText('Otomatis diambil dari profil, bisa diedit jika perlu.')
                            ->withProperCase()
                            ->disabled(fn (Get $get) => $get('tipe_penugasan') === 'kolektif')
                            ->dehydrated()
                            ->required()
                            ->maxLength(255),

                        TextInput::make('nomor_surat')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(function () {
                                // Calculate default number on load
                                $settings = app(SystemSettings::class);
                                $prefix = $settings->surat_prefix ?? 'B';
                                $office = $settings->office_code ?? '33210';

                                // Default uses current year since tanggal default is now()
                                $year = now()->year;
                                $max = SuratTugas::getNextNomorUrut($year) - 1;
                                $nextUrut = $max + 1;
                                $urut = str_pad($nextUrut, 4, '0', STR_PAD_LEFT);

                                $klasifikasi = 'KP.650';

                                return "{$prefix}-{$urut}/{$office}/{$klasifikasi}/{$year}";
                            })
                            ->readOnly()
                            ->helperText('Otomatis dibuat oleh sistem.'),

                        Hidden::make('kode_klasifikasi')
                            ->default('KP.650'),
                        Hidden::make('nomor_urut')
                            ->default(function () {
                                return SuratTugas::getNextNomorUrut(now()->year);
                            }),
                    ])->columns(1),

                    Group::make()->schema([
                        DatePicker::make('tanggal')
                            ->label('Tanggal Surat')
                            ->required()
                            ->default(now())
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state, ?SuratTugas $record) {
                                // Only auto-assign next sequence on create, or if the year actually changed
                                if ($state) {
                                    $newYear = Carbon::parse($state)->year;
                                    $oldYear = $record?->tanggal ? Carbon::parse($record->tanggal)->year : null;

                                    if (!$record || $newYear !== $oldYear) {
                                        $next = SuratTugas::getNextNomorUrut($newYear);
                                        $set('nomor_urut', $next);
                                        self::updateNomorSurat($get, $set);

                                        if (!$record || !$record->sppd) {
                                            $nextSppd = SuratTugas::getNextNomorUrutSppd($newYear);
                                            $set('nomor_urut_sppd', $nextSppd);
                                            self::updateNomorSppd($get, $set);
                                        }
                                    }
                                }
                            }),
                        Toggle::make('abaikan_validasi')
                            ->label('Abaikan Validasi Bentrok Tanggal')
                            ->helperText('Hanya untuk Admin. Jika diaktifkan, pegawai bisa ditugaskan di tanggal yang sama dengan survei lain.')
                            ->default(false)
                            ->visible(fn() => auth()->user()?->hasAnyRole(['super_admin', 'Kepala', 'Kasubag'])),
                        DatePicker::make('waktu_mulai')
                            ->label('Mulai')
                            ->default(now()),
                        DatePicker::make('waktu_selesai')
                            ->label('Selesai')
                            ->default(now()),
                    ])->columns(1),

                    Textarea::make('keperluan')
                        // ->withProperCase()
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('tempat_tugas')
                        ->label('Tempat Tugas')
                        ->placeholder('Contoh: Kecamatan Demak')
                        ->withProperCase()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])->columns(2),

                Section::make('Informasi SPPD')->schema([
                    Toggle::make('is_sppd')
                        ->label('Memerlukan SPPD?')
                        ->default(false)
                        ->live(),

                    Group::make()->schema([
                        TextInput::make('nomor_sppd')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'sppds', column: 'nomor_sppd', ignoreRecord: false, ignorable: fn (?SuratTugas $record) => $record?->sppd)
                            ->default(function () {
                                $settings = app(SystemSettings::class);
                                $prefix = $settings->surat_prefix ?? 'B';
                                $office = $settings->office_code ?? '33210';
                                $year = now()->year;
                                $nextUrut = SuratTugas::getNextNomorUrutSppd($year);
                                $urut = str_pad($nextUrut, 4, '0', STR_PAD_LEFT);
                                $klasifikasi = 'KP.650';

                                return "{$prefix}-{$urut}/{$office}/SE2026/{$klasifikasi}/{$year}";
                            })
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if (preg_match('/^(?:[A-Za-z]+-)?0*(\d+)\//', (string) $state, $matches)) {
                                    $set('nomor_urut_sppd', (int) $matches[1]);
                                }
                            })
                            ->helperText('Otomatis di-generate saat disimpan.'),

                        Hidden::make('kode_klasifikasi_sppd')
                            ->default('KP.650'),

                        TextInput::make('nomor_urut_sppd')
                            ->label('No. Urut SPPD')
                            ->numeric()
                            ->default(function () {
                                return SuratTugas::getNextNomorUrutSppd(now()->year);
                            })
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateNomorSppd($get, $set);
                            }),

                        Select::make('tingkat_perjalanan_dinas')
                            ->label('Tingkat Perjalanan Dinas')
                            ->options([
                                'A' => 'Tingkat A',
                                'B' => 'Tingkat B',
                                'C' => 'Tingkat C',
                            ])
                            ->default('C'),

                        TextInput::make('alat_angkutan')
                            ->label('Alat Angkutan')
                            ->default('Kendaraan Pribadi')
                            ->withProperCase()
                            ->maxLength(255),

                        TextInput::make('mak')
                            ->label('Pembebanan Anggaran (MAK)')
                            ->default('054.01.GG.2902.006.005.A.524113')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('maksud_perjalanan')
                            ->label('Maksud Perjalanan Dinas')
                            ->default(fn(Get $get) => $get('keperluan'))
                            // ->withProperCase()
                            ->columnSpanFull(),

                        TextInput::make('tempat_berangkat')
                            ->label('Tempat Berangkat')
                            ->default('Demak')
                            ->withProperCase()
                            ->maxLength(255),

                        TextInput::make('tempat_tujuan')
                            ->label('Tempat Tujuan')
                            ->placeholder('Kosongkan untuk mengikuti Tempat Tugas')
                            ->withProperCase()
                            ->maxLength(255),
                    ])->columns(2)->visible(fn(Get $get) => $get('is_sppd')),
                ]),

                Section::make('Pejabat Penandatangan (Snapshot)')
                    ->description('Data ini tersimpan di surat dan tidak akan berubah meski pengaturan sistem diganti.')
                    ->schema([
                        TextInput::make('signer_city')
                            ->label('Kota Penetapan')
                            ->default(fn() => app(SystemSettings::class)->cert_city)
                            ->readOnly(),
                        TextInput::make('signer_name')
                            ->label('Nama Pejabat')
                            ->default(fn() => app(SystemSettings::class)->cert_signer_name)
                            ->readOnly(),
                        TextInput::make('signer_nip')
                            ->label('NIP')
                            ->default(fn() => app(SystemSettings::class)->cert_signer_nip)
                            ->readOnly(),
                        Textarea::make('signer_title')
                            ->label('Jabatan Pejabat')
                            ->rows(3)
                            ->default(fn() => app(SystemSettings::class)->cert_signer_title)
                            ->readOnly()
                            ->columnSpanFull(),

                        // Hidden signature path
                        Hidden::make('signer_signature_path')
                            ->default(fn() => app(SystemSettings::class)->cert_signer_signature_path),
                    ])
                    ->collapsed() // Collapsed by default so it doesn't clutter
                    ->columns(3),

            ]);

    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_surat')
                    ->searchable()
                    ->sortable()
                    ->wrap() // Allow wrapping for long numbers
                    ->width('18%'), // Explicit width suggestion
                TextColumn::make('survey.name')
                    ->label('Nama Survei')
                    ->searchable()
                    ->sortable()
                    ->limit(40) // Limit characters
                    ->tooltip(function (TextColumn $column): ?string {
                        return $column->getState();
                    })
                    ->wrap(), // Wrap if still long within limit
                TextColumn::make('user.name')
                    ->label('Pegawai')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->tooltip(function (TextColumn $column): ?string {
                        return $column->getState();
                    }),
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable()
                    ->description(function (SuratTugas $record): ?string {
                        if (!$record->waktu_mulai && !$record->waktu_selesai) {
                            return null;
                        }
                        $periode = SuratTugasResource::formatPeriodeTugas($record->waktu_mulai, $record->waktu_selesai);
                        return "Tugas: {$periode}";
                    })
                    ->tooltip(function (SuratTugas $record): ?string {
                        if (!$record->waktu_mulai && !$record->waktu_selesai) {
                            return null;
                        }
                        return 'Pelaksanaan Tugas: ' . SuratTugasResource::formatPeriodeTugas($record->waktu_mulai, $record->waktu_selesai);
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('survey_id')
                    ->label('Filter Kegiatan / Pelatihan')
                    ->relationship('survey', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Survey $record) => $record->selector_label)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->visible(false)
                    ->color('info')
                    ->url(fn(SuratTugas $record) => route('surat-tugas.preview', $record->id))
                    ->openUrlInNewTab(),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (SuratTugas $record) {
                        return $record->status === 'pending' && auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag']);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Persetujuan & Penerbitan TTE Surat Tugas')
                    ->modalDescription('Apakah Anda yakin menyetujui Surat Tugas ini? Tindakan ini akan mengesahkan dokumen secara elektronik.')
                    ->action(function (SuratTugas $record) {
                        $updateData = ['status' => 'approved'];
                        if (empty($record->hash)) {
                            $updateData['hash'] = Str::random(32);
                        }
                        $record->update($updateData);

                        Notification::make()
                            ->title('Surat Tugas Berhasil Disetujui')
                            ->body('Dokumen telah disahkan secara elektronik.')
                            ->success()
                            ->send();
                    }),
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color(fn(SuratTugas $record) => $record->status === 'approved' ? 'danger' : 'gray')
                    ->action(fn(SuratTugas $record) => app(SuratTugasPdfService::class)->downloadPdf($record)),

                Action::make('generate_sppd')
                    ->label('Buat SPPD')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(fn(SuratTugas $record) => $record->sppd === null && auth()->user()->hasAnyRole(['super_admin', 'Kasubag', 'Kepala', 'Operator', 'Ketua Tim', 'Pegawai']))
                    ->schema([
                        TextInput::make('nomor_sppd')
                            ->label('Nomor SPPD')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'sppds', column: 'nomor_sppd', ignoreRecord: false)
                            ->default(function (SuratTugas $record) {
                                $settings = app(SystemSettings::class);
                                $prefix = $settings->surat_prefix ?? 'B';
                                $office = $settings->office_code ?? '33210';
                                $year = Carbon::parse($record->tanggal)->year;
                                $nextUrut = SuratTugas::getNextNomorUrutSppd($year);
                                $urut = str_pad($nextUrut, 4, '0', STR_PAD_LEFT);
                                $klasifikasi = 'KP.650';
                                return "{$prefix}-{$urut}/{$office}/SE2026/{$klasifikasi}/{$year}";
                            })
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if (preg_match('/(?:B-)?0*(\d+)\//', $state, $matches)) {
                                    $set('nomor_urut_sppd', (int) $matches[1]);
                                }
                            }),
                        TextInput::make('nomor_urut_sppd')
                            ->label('No. Urut SPPD')
                            ->numeric()
                            ->required()
                            ->default(function (SuratTugas $record) {
                                return SuratTugas::getNextNomorUrutSppd(Carbon::parse($record->tanggal)->year);
                            })
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if (blank($state))
                                    return;
                                $settings = app(SystemSettings::class);
                                $prefix = $settings->surat_prefix ?? 'B';
                                $office = $settings->office_code ?? '33210';
                                $urut = str_pad($state, 4, '0', STR_PAD_LEFT);
                                $klasifikasi = $get('kode_klasifikasi_sppd') ?? 'KP.650';
                                $year = $get('tahun_sppd') ?? now()->year;
                                $set('nomor_sppd', "{$prefix}-{$urut}/{$office}/SE2026/{$klasifikasi}/{$year}");
                            }),
                        Hidden::make('kode_klasifikasi_sppd')->default('KP.650'),
                        Hidden::make('tahun_sppd')->default(fn(SuratTugas $record) => Carbon::parse($record->tanggal)->year),
                        Select::make('tingkat_perjalanan_dinas')
                            ->label('Tingkat Perjalanan Dinas')
                            ->options([
                                'A' => 'Tingkat A',
                                'B' => 'Tingkat B',
                                'C' => 'Tingkat C',
                            ])
                            ->default('C')
                            ->required(),
                        TextInput::make('alat_angkutan')
                            ->label('Alat Angkutan')
                            ->default('Kendaraan Pribadi')
                            ->maxLength(255)
                            ->required(),
                        TextInput::make('mak')
                            ->label('Pembebanan Anggaran (MAK)')
                            ->default('054.01.GG.2902.006.005.A.524113')
                            ->maxLength(255)
                            ->required(),
                        TextInput::make('biaya_transport')
                            ->label('Biaya Transport (Rp)')
                            ->numeric()
                            ->default(170000)
                            ->required(),
                        Textarea::make('maksud_perjalanan')
                            ->label('Maksud Perjalanan Dinas')
                            ->default(fn(SuratTugas $record) => "Transport lokal dalam rangka {$record->keperluan}")
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('tempat_berangkat')
                            ->label('Tempat Berangkat')
                            ->default('Demak')
                            ->maxLength(255)
                            ->required(),
                        TextInput::make('tempat_tujuan')
                            ->label('Tempat Tujuan')
                            ->default(fn(SuratTugas $record) => $record->tempat_tugas)
                            ->maxLength(255)
                            ->required(),
                    ])
                    ->action(function (SuratTugas $record, array $data) {
                        app(SppdService::class)->createSppd($record, $data);
                        Notification::make()->title('SPPD Berhasil Dibuat')->success()->send();
                    }),
                Action::make('word_sppd')
                    ->label('Cetak SPPD')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(fn(SuratTugas $record) => $record->sppd !== null)
                    ->action(fn(SuratTugas $record) => app(SppdService::class)->downloadWord($record)),
                Action::make('generate_lpd')
                    ->label('Buat LPD')
                    ->icon('heroicon-o-document-plus')
                    ->color('info')
                    ->visible(fn(SuratTugas $record) => LaporanPerjalananDinas::canCreateLpd($record))
                    ->url(fn(SuratTugas $record) => LaporanPerjalananDinasResource::getUrl('create', ['surat_tugas_id' => $record->id])),
                Action::make('view_lpd')
                    ->label(function (SuratTugas $record) {
                        $count = $record->laporan_perjalanan_dinas_count ?? $record->laporanPerjalananDinas->count();
                        $max = LaporanPerjalananDinas::getMaxLpdCount($record);
                        if ($max > 1) {
                            return "Lihat LPD ({$count}/{$max})";
                        }
                        return 'Lihat LPD';
                    })
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->visible(fn(SuratTugas $record) => ($record->laporan_perjalanan_dinas_count ?? $record->laporanPerjalananDinas->count()) > 0)
                    ->url(function (SuratTugas $record) {
                        $lpds = $record->laporanPerjalananDinas;
                        if ($lpds->count() === 1) {
                            return LaporanPerjalananDinasResource::getUrl('edit', ['record' => $lpds->first()->id]);
                        }
                        return LaporanPerjalananDinasResource::getUrl('index', [
                            'tableFilters' => [
                                'survey' => ['value' => $record->survey_id],
                            ],
                        ]);
                    }),
                EditAction::make(),
                Action::make('activities')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->url(fn ($record) => SuratTugasResource::getUrl('activities', ['record' => $record])),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename('Surat_Tugas_' . date('Y-m-d'))
                            ->withColumns([
                                Column::make('nomor_surat'),
                                Column::make('user.name')->heading('Pegawai'),
                                Column::make('jabatan')->heading('Jabatan'),
                                Column::make('survey.name')->heading('Survei'),
                                Column::make('tanggal'),
                                Column::make('keperluan'),
                                Column::make('tempat_tugas'),
                                Column::make('waktu_mulai'),
                                Column::make('waktu_selesai'),
                            ]),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveBulk')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn() => auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag']))
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'approved') {
                                    $updateData = ['status' => 'approved'];
                                    if (empty($record->hash)) {
                                        $updateData['hash'] = Str::random(32);
                                    }
                                    $record->update($updateData);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title('Berhasil Disetujui')
                                ->body("{$count} surat tugas telah di-approve & disahkan secara elektronik.")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('generateSppdBulk')
                        ->label('Buat SPPD')
                        ->icon('heroicon-o-document-plus')
                        ->color('primary')
                        ->visible(fn() => auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag', 'Operator', 'Ketua Tim', 'Pegawai']))
                        ->schema([
                            TextInput::make('nomor_urut_sppd_mulai')
                                ->label('Nomor Urut Mulai')
                                ->numeric()
                                ->required()
                                ->default(function () {
                                    return SuratTugas::getNextNomorUrutSppd(now()->year);
                                })
                                ->helperText('Nomor urut akan di-increment otomatis untuk setiap SPPD dari angka ini.'),
                            TextInput::make('format_nomor_sppd')
                                ->label('Format Nomor SPPD')
                                ->required()
                                ->default(function () {
                                    $settings = app(SystemSettings::class);
                                    $prefix = $settings->surat_prefix ?? 'B';
                                    $office = $settings->office_code ?? '33210';
                                    $klasifikasi = 'KP.650';
                                    $year = now()->year;
                                    return "{$prefix}-{urut}/{$office}/SE2026/{$klasifikasi}/{$year}";
                                })
                                ->helperText('Gunakan {urut} di mana nomor urut akan disisipkan (contoh: B-{urut}/33210/SE2026/KP.650/2026)'),
                            Select::make('tingkat_perjalanan_dinas')
                                ->label('Tingkat Perjalanan Dinas')
                                ->options([
                                    'A' => 'Tingkat A',
                                    'B' => 'Tingkat B',
                                    'C' => 'Tingkat C',
                                ])
                                ->default('C')
                                ->required(),
                            TextInput::make('alat_angkutan')
                                ->label('Alat Angkutan')
                                ->default('Kendaraan Pribadi')
                                ->maxLength(255)
                                ->required(),
                            TextInput::make('mak')
                                ->label('Pembebanan Anggaran (MAK)')
                                ->default('054.01.GG.2902.006.005.A.524113')
                                ->maxLength(255)
                                ->required(),
                            TextInput::make('biaya_transport')
                                ->label('Biaya Transport (Rp)')
                                ->numeric()
                                ->default(170000)
                                ->required(),
                            Textarea::make('maksud_perjalanan')
                                ->label('Maksud Perjalanan Dinas (Opsional, kosongkan untuk default otomatis)')
                                ->columnSpanFull(),
                            TextInput::make('tempat_berangkat')
                                ->label('Tempat Berangkat')
                                ->default('Demak')
                                ->maxLength(255)
                                ->required(),
                            TextInput::make('tempat_tujuan')
                                ->label('Tempat Tujuan (Kosongkan jika mengikuti Surat Tugas)')
                                ->maxLength(255),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $count = app(SppdService::class)->generateBulkSppd($records, $data);

                            Notification::make()
                                ->title('Berhasil!')
                                ->body("{$count} SPPD baru telah di-generate.")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('syncPejabatBulk')
                        ->label('Update Pejabat Terpilih')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn() => auth()->user()->hasAnyRole(['super_admin', 'kepala', 'kasubag']))
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $settings = app(SystemSettings::class);
                            $records->each(function ($record) use ($settings) {
                                $record->update([
                                    'signer_name' => $settings->cert_signer_name,
                                    'signer_nip' => $settings->cert_signer_nip,
                                    'signer_title' => $settings->cert_signer_title,
                                    'signer_city' => $settings->cert_city,
                                    'signer_signature_path' => $settings->cert_signer_signature_path,
                                ]);
                            });

                            Notification::make()
                                ->title('Berhasil!')
                                ->body("Data pejabat pada {$records->count()} surat tugas telah diperbarui ke pengaturan terbaru.")
                                ->success()
                                ->send();
                        }),
                    ExportBulkAction::make(),
                    BulkAction::make('downloadBulk')
                        ->label('Download Semua (ZIP)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $settings = app(SystemSettings::class);
                            $templatePath = $settings->surat_tugas_template_path;

                            if (!$templatePath || !file_exists(storage_path('app/public/' . $templatePath))) {
                                Notification::make()
                                    ->title('Template belum diupload di Pengaturan Sistem')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Dispatch Job
                            GenerateBulkSuratTugasZip::dispatch($records->pluck('id')->toArray(), 'word', auth()->id());

                            Notification::make()
                                ->title('Proses Download Dimulai')
                                ->body('File sedang dikompresi di latar belakang. Anda akan menerima notifikasi di Ikon Lonceng jika file ZIP sudah siap didownload.')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('downloadPdfBulk')
                        ->label('Download PDF (Gabungan)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->modalHeading(function (Collection $records) {
                            $pendingCount = $records->where('status', '!=', 'approved')->count();
                            if ($pendingCount > 0) {
                                return "⚠️ {$pendingCount} surat tugas belum di-approve";
                            }
                            return 'Download PDF';
                        })
                        ->modalDescription(function (Collection $records) {
                            $pendingCount = $records->where('status', '!=', 'approved')->count();
                            if ($pendingCount > 0) {
                                return "Ada {$pendingCount} surat tugas yang belum di-approve. PDF yang belum approved tidak akan memiliki QR Code. Tetap download?";
                            }
                            return "Download {$records->count()} surat tugas sebagai satu file PDF gabungan?";
                        })
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            GenerateBulkSuratTugasZip::dispatch($records->pluck('id')->toArray(), 'pdf', auth()->id());

                            Notification::make()
                                ->title('Proses Download Dimulai')
                                ->body('File PDF sedang di-generate dan digabungkan di latar belakang. Anda akan menerima notifikasi di Ikon Lonceng jika file PDF sudah siap didownload.')
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nomor_urut', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['user.profile', 'survey', 'sppd', 'laporanPerjalananDinas'])
            ->withCount('laporanPerjalananDinas');

        /** @var User $user */
        $user = auth()->user();

        if ($user && !$user->hasRole(['super_admin', 'Kasubag', 'Kepala', 'Operator', 'Ketua Tim'])) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuratTugas::route('/'),
            'create' => CreateSuratTugas::route('/create'),
            'create-bulk' => CreateBulkSuratTugas::route('/create-bulk'),
            'manage-blocked-numbers' => ManageBlockedNumbers::route('/manage-blocked-numbers'),
            'edit' => EditSuratTugas::route('/{record}/edit'),
            'activities' => ListSuratTugasActivities::route('/{record}/activities'),
        ];
    }

    protected static function updateNomorSurat(Get $get, Set $set): void
    {
        $nomor = SuratTugasNumberingService::formatNomorSurat(
            $get('nomor_urut') ?? 0,
            $get('kode_klasifikasi') ?? 'KP.650',
            $get('tanggal') ? Carbon::parse($get('tanggal'))->year : now()->year
        );
        $set('nomor_surat', $nomor);
    }

    public static function formatPeriodeTugas($mulai, $selesai): string
    {
        return SuratTugasPdfService::formatPeriodeTugas($mulai, $selesai);
    }

    protected static function updateNomorSppd(Get $get, Set $set): void
    {
        $urut = $get('nomor_urut_sppd');
        if (blank($urut)) {
            return;
        }

        $nomor = SppdService::formatNomorSppd(
            $urut,
            $get('kode_klasifikasi_sppd') ?? 'KP.650',
            $get('tanggal') ? Carbon::parse($get('tanggal'))->year : now()->year
        );
        $set('nomor_sppd', $nomor);
    }

    public static function terbilang($angka): string
    {
        return SppdService::terbilang($angka);
    }
}
