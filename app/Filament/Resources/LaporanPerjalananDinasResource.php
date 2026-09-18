<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaporanPerjalananDinasResource\Pages;
use App\Models\LaporanPerjalananDinas;
use App\Models\SuratTugas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Services\LpdExportService;

class LaporanPerjalananDinasResource extends Resource
{
    protected static ?string $model = LaporanPerjalananDinas::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Manajemen Dokumen';
    protected static ?string $navigationLabel = 'Laporan Dinas';

    public static function form(Form $form): Form
    {
        $isSuperAdmin = Auth::user()?->hasRole('super_admin') ?? false;

        return $form
            ->schema([
                Forms\Components\Section::make('Pilih Pegawai, Survey & Surat Tugas')->schema([
                    Forms\Components\Select::make('user_id_temp')
                        ->label('Pegawai / Petugas')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->visible(fn () => Auth::user()?->hasRole('super_admin') ?? false)
                        ->default(function () {
                            $stId = request()->query('surat_tugas_id');
                            if ($stId) {
                                return SuratTugas::find($stId)?->user_id;
                            }
                            return Auth::id();
                        })
                        ->formatStateUsing(fn (?LaporanPerjalananDinas $record) => $record?->suratTugas?->user_id ?? Auth::id())
                        ->options(function (?LaporanPerjalananDinas $record) {
                            return \App\Models\User::where('name', 'not like', '%Terlampir%')
                                ->whereHas('suratTugas', function ($q) use ($record) {
                                    $q->where(function ($sub) use ($record) {
                                        $sub->whereDoesntHave('laporanPerjalananDinas');
                                        if ($record?->surat_tugas_id) {
                                            $sub->orWhere('id', $record->surat_tugas_id);
                                        }
                                    });
                                })
                                ->pluck('name', 'id');
                        })
                        ->afterStateUpdated(function (Forms\Set $set) {
                            $set('survey_id_temp', null);
                            $set('surat_tugas_id', null);
                            $set('nomor_surat_tugas', '');
                            $set('tujuan', '');
                            $set('tanggal_kunjungan', null);
                        })
                        ->helperText('Super Admin: Default terisi akun Anda. Pilih pegawai lain jika ingin membuat LPD pegawai tersebut.'),

                    Forms\Components\Select::make('survey_id_temp')
                        ->label('Survey')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->default(function () {
                            $stId = request()->query('surat_tugas_id');
                            if ($stId) {
                                return SuratTugas::find($stId)?->survey_id;
                            }
                            return null;
                        })
                        ->formatStateUsing(fn (?LaporanPerjalananDinas $record) => $record?->suratTugas?->survey_id)
                        ->options(function (Forms\Get $get, ?LaporanPerjalananDinas $record) {
                            $isSuperAdmin = Auth::user()?->hasRole('super_admin') ?? false;
                            $userId = $isSuperAdmin ? ($get('user_id_temp') ?? Auth::id()) : Auth::id();

                            $query = \App\Models\Survey::whereHas('suratTugas', function ($q) use ($userId, $record) {
                                if ($userId) {
                                    $q->where('user_id', $userId);
                                } else {
                                    $q->whereHas('user', fn($u) => $u->where('name', 'not like', '%Terlampir%'));
                                }
                                $q->where(function ($sub) use ($record) {
                                    $sub->whereDoesntHave('laporanPerjalananDinas');
                                    if ($record?->surat_tugas_id) {
                                        $sub->orWhere('id', $record->surat_tugas_id);
                                    }
                                });
                            });

                            return $query->pluck('name', 'id');
                        })
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                            if ($state) {
                                $isSuperAdmin = Auth::user()?->hasRole('super_admin') ?? false;
                                $userId = $isSuperAdmin ? ($get('user_id_temp') ?? Auth::id()) : Auth::id();

                                $stQuery = SuratTugas::where('survey_id', $state)
                                    ->whereDoesntHave('laporanPerjalananDinas');
                                if ($userId) {
                                    $stQuery->where('user_id', $userId);
                                }

                                $st = $stQuery->first();

                                if ($st) {
                                    $set('surat_tugas_id', $st->id);
                                    $set('nomor_surat_tugas', $st->nomor_surat);
                                    $set('tujuan', $st->keperluan);
                                    $initialDate = LaporanPerjalananDinas::determineAvailableDate($st);
                                    $set('tanggal_kunjungan', $initialDate);

                                    if (!$initialDate) {
                                        \Filament\Notifications\Notification::make()
                                            ->warning()
                                            ->title('Tanggal Tugas Sudah Terisi LPD')
                                            ->body('Semua tanggal pada Surat Tugas ini sudah digunakan untuk LPD lain oleh pegawai ini. Silakan pilih tanggal kunjungan yang belum terisi.')
                                            ->send();
                                    }
                                } else {
                                    $set('surat_tugas_id', null);
                                    $set('nomor_surat_tugas', '');
                                    $set('tujuan', '');
                                    $set('tanggal_kunjungan', null);
                                }
                            } else {
                                $set('surat_tugas_id', null);
                                $set('nomor_surat_tugas', '');
                                $set('tujuan', '');
                                $set('tanggal_kunjungan', null);
                            }
                        })
                        ->helperText('Pilih survey untuk menyaring Surat Tugas'),

                    Forms\Components\Select::make('surat_tugas_id')
                        ->label('Surat Tugas')
                        ->searchable()
                        ->live()
                        ->default(fn () => request()->query('surat_tugas_id'))
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state) {
                                $st = SuratTugas::find($state);
                                if ($st) {
                                    $set('nomor_surat_tugas', $st->nomor_surat);
                                    $set('tujuan', $st->keperluan);
                                    $initialDate = LaporanPerjalananDinas::determineAvailableDate($st);
                                    $set('tanggal_kunjungan', $initialDate);

                                    if (!$initialDate) {
                                        \Filament\Notifications\Notification::make()
                                            ->warning()
                                            ->title('Tanggal Tugas Sudah Terisi LPD')
                                            ->body('Semua tanggal pada Surat Tugas ini sudah digunakan untuk LPD lain oleh pegawai ini. Silakan pilih tanggal kunjungan yang belum terisi.')
                                            ->send();
                                    }
                                }
                            } else {
                                $set('nomor_surat_tugas', '');
                                $set('tujuan', '');
                                $set('tanggal_kunjungan', null);
                            }
                        })
                        ->options(function (Forms\Get $get, ?LaporanPerjalananDinas $record) {
                            $isSuperAdmin = Auth::user()?->hasRole('super_admin') ?? false;
                            $userId = $isSuperAdmin ? ($get('user_id_temp') ?? Auth::id()) : Auth::id();
                            $surveyId = $get('survey_id_temp');

                            $query = SuratTugas::with('user', 'survey');

                            if ($userId) {
                                $query->where('user_id', $userId);
                            } else {
                                $query->whereHas('user', fn($u) => $u->where('name', 'not like', '%Terlampir%'));
                            }

                            // Filter: ONLY Surat Tugas that DO NOT have an LPD created yet (unless editing current record)
                            $query->where(function ($q) use ($record) {
                                $q->whereDoesntHave('laporanPerjalananDinas');
                                if ($record?->surat_tugas_id) {
                                    $q->orWhere('id', $record->surat_tugas_id);
                                }
                            });

                            if ($surveyId) {
                                $query->where('survey_id', $surveyId);
                            }

                            return $query->get()->mapWithKeys(function ($st) use ($userId, $record) {
                                // Format tanggal/periode keberangkatan
                                $tglStr = '';
                                if ($st->waktu_mulai && $st->waktu_selesai) {
                                    $start = \Carbon\Carbon::parse($st->waktu_mulai);
                                    $end = \Carbon\Carbon::parse($st->waktu_selesai);
                                    if ($start->isSameDay($end)) {
                                        $tglStr = ' (' . $start->translatedFormat('d M Y') . ')';
                                    } else {
                                        $tglStr = ' (' . $start->translatedFormat('d M') . ' - ' . $end->translatedFormat('d M Y') . ')';
                                    }
                                } elseif ($st->tanggal) {
                                    $tglStr = ' (' . \Carbon\Carbon::parse($st->tanggal)->translatedFormat('d M Y') . ')';
                                }

                                $survey = $st->survey ? " [{$st->survey->name}]" : '';
                                $user = $st->user ? " - {$st->user->name}" : '';

                                $statusNote = '';
                                if ($userId && !LaporanPerjalananDinas::determineAvailableDate($st, $record?->id)) {
                                    $statusNote = ' ⚠️ (Semua tgl terisi LPD)';
                                }

                                return [$st->id => "{$st->nomor_surat}{$tglStr}{$survey}{$user}{$statusNote}"];
                            });
                        })
                        ->required(),
                ])->columns($isSuperAdmin ? 3 : 2),

                Forms\Components\Section::make('Data Laporan')->schema([
                    Forms\Components\TextInput::make('nomor_surat_tugas')
                        ->label('Nomor Surat Tugas')
                        ->disabled()
                        ->dehydrated()
                        ->default(function () {
                            $stId = request()->query('surat_tugas_id');
                            return $stId ? SuratTugas::find($stId)?->nomor_surat : null;
                        })
                        ->required(),

                    Forms\Components\TextInput::make('tujuan')
                        ->label('Tujuan/Keperluan')
                        ->default(function () {
                            $stId = request()->query('surat_tugas_id');
                            return $stId ? SuratTugas::find($stId)?->keperluan : null;
                        })
                        ->required()
                        ->maxLength(255),

                    Forms\Components\DatePicker::make('tanggal_kunjungan')
                        ->label('Tanggal Kunjungan')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->disabledDates(function (Forms\Get $get, ?LaporanPerjalananDinas $record) {
                            $stId = $get('surat_tugas_id') ?? request()->query('surat_tugas_id');
                            $userId = null;
                            if ($stId) {
                                $userId = SuratTugas::find($stId)?->user_id;
                            }
                            if (!$userId) {
                                $isSuperAdmin = Auth::user()?->hasRole('super_admin') ?? false;
                                $userId = $isSuperAdmin ? ($get('user_id_temp') ?? Auth::id()) : Auth::id();
                            }

                            if (!$userId) {
                                return [];
                            }

                            return LaporanPerjalananDinas::getExistingDatesForUser($userId, $record?->id);
                        })
                        ->rules([
                            fn (Forms\Get $get, ?LaporanPerjalananDinas $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                if (!$value) {
                                    return;
                                }

                                $stId = $get('surat_tugas_id') ?? request()->query('surat_tugas_id');
                                $userId = null;
                                if ($stId) {
                                    $userId = SuratTugas::find($stId)?->user_id;
                                }
                                if (!$userId) {
                                    $isSuperAdmin = Auth::user()?->hasRole('super_admin') ?? false;
                                    $userId = $isSuperAdmin ? ($get('user_id_temp') ?? Auth::id()) : Auth::id();
                                }

                                if (!$userId) {
                                    return;
                                }

                                $conflict = LaporanPerjalananDinas::getDuplicateForUser($userId, $value, $record?->id);
                                if ($conflict) {
                                    $userName = \App\Models\User::find($userId)?->name ?? 'Pegawai';
                                    $tglFormatted = \Carbon\Carbon::parse($value)->translatedFormat('d F Y');
                                    $fail("Pegawai {$userName} sudah memiliki kegiatan LPD pada tanggal {$tglFormatted} (Surat Tugas: {$conflict->nomor_surat_tugas}). Satu tanggal hanya diperbolehkan untuk 1 kegiatan LPD.");
                                }
                            },
                        ])
                        ->default(function (Forms\Get $get) {
                            $stId = request()->query('surat_tugas_id') ?? $get('surat_tugas_id');
                            if ($stId) {
                                $st = SuratTugas::find($stId);
                                if ($st) {
                                    return LaporanPerjalananDinas::determineAvailableDate($st);
                                }
                            }
                            return null;
                        })
                        ->helperText('Satu tanggal hanya boleh untuk 1 kegiatan LPD. Tanggal yang sudah memiliki LPD otomatis dinonaktifkan di kalender.')
                        ->required(),

                    Forms\Components\RichEditor::make('uraian_kegiatan')
                        ->label('Uraian Kegiatan')
                        ->helperText('Gunakan bullets, numbering, atau format teks')
                        ->required()
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'strike',
                            'bulletList',
                            'orderedList',
                            'h2',
                            'h3',
                            'redo',
                            'undo',
                        ])
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('nama_pejabat')
                        ->label('Nama Pejabat yang Dikunjungi')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('desa_pejabat')
                        ->label('Desa/Lokasi Pejabat')
                        ->maxLength(255),
                ])->columns(2),

                Forms\Components\Section::make('Dokumentasi Foto')->schema([
                    Forms\Components\Repeater::make('fotos')
                        ->relationship('fotos')
                        ->schema([
                            Forms\Components\FileUpload::make('file_path')
                                ->label('Foto')
                                ->image()
                                ->directory('laporan-foto')
                                ->disk('public')
                                ->visibility('public')
                                ->maxSize(5120)
                                ->required(),
                            Forms\Components\Textarea::make('keterangan')
                                ->label('Keterangan Foto')
                                ->rows(2),
                        ])
                        ->reorderable('urutan')
                        ->collapsible()
                        ->itemLabel(fn(array $state): ?string => $state['keterangan'] ?? 'Foto')
                        ->defaultItems(0)
                        ->addActionLabel('Tambah Foto')
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $isSuperAdmin = Auth::user()?->hasRole('super_admin') ?? false;

                if (!$isSuperAdmin) {
                    $query->whereHas('suratTugas', function ($q) {
                        $q->where('user_id', Auth::id());
                    });
                }
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('nomor_surat_tugas')
                    ->label('Nomor Surat')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('suratTugas.survey.name')
                    ->label('Survey')
                    ->wrap()
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('suratTugas.user.name')
                    ->label('Nama Petugas')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tanggal')
                    ->label('Tanggal Kunjungan')
                    ->getStateUsing(function (LaporanPerjalananDinas $record) {
                        $start = $record->suratTugas?->waktu_mulai;
                        $end = $record->suratTugas?->waktu_selesai;

                        if (!$start || !$end) {
                            return $record->tanggal_kunjungan?->format('d M Y');
                        }

                        $startDate = $start->format('d M Y');
                        $endDate = $end->format('d M Y');

                        if ($startDate === $endDate) {
                            return $startDate;
                        }

                        return $startDate . ' - ' . $endDate;
                    })
                    ->sortable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->join('surat_tugas', 'laporan_perjalanan_dinas.surat_tugas_id', '=', 'surat_tugas.id')
                            ->orderBy('surat_tugas.waktu_mulai', $direction)
                            ->select('laporan_perjalanan_dinas.*');
                    }),
                Tables\Columns\TextColumn::make('fotos_count')
                    ->counts('fotos')
                    ->label('Foto')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('survey')
                    ->relationship('suratTugas.survey', 'name')
                    ->label('Kegiatan')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('suratTugas.user', 'name')
                    ->label('Nama Petugas')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => Auth::user()?->hasRole('super_admin') ?? false),
            ])
            ->actions([
                Tables\Actions\Action::make('downloadWord')
                    ->label('Word')
                    ->icon('heroicon-o-document-text')
                    ->action(fn(LaporanPerjalananDinas $record) => app(LpdExportService::class)->downloadWord($record)),

                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('activities')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->url(fn ($record) => LaporanPerjalananDinasResource::getUrl('activities', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('downloadBulk')
                        ->label('Download Semua (ZIP)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(fn(\Illuminate\Database\Eloquent\Collection $records) => app(LpdExportService::class)->downloadBulkZip($records)),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function processWordDocument(LaporanPerjalananDinas $record, $isBulk = false): ?array
    {
        return app(LpdExportService::class)->processWordDocument($record, $isBulk);
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
            'index' => Pages\ListLaporanPerjalananDinas::route('/'),
            'create' => Pages\CreateLaporanPerjalananDinas::route('/create'),
            'edit' => Pages\EditLaporanPerjalananDinas::route('/{record}/edit'),
            'activities' => Pages\ListLaporanPerjalananDinasActivities::route('/{record}/activities'),
        ];
    }
}
