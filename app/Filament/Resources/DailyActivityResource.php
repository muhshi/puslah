<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyActivityResource\Pages;
use App\Models\DailyActivity;
use App\Models\LaporanFoto;
use App\Models\LaporanPerjalananDinas;
use App\Models\SuratTugas;
use App\Models\User;
use App\Services\DailyActivityAiService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DailyActivityResource extends Resource
{
    protected static ?string $model = DailyActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Manajemen Dokumen';
    protected static ?string $navigationLabel = 'Bukti Dukung Harian';
    protected static ?string $pluralModelLabel = 'Bukti Dukung Harian';
    protected static ?string $modelLabel = 'Kegiatan Harian';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user', 'suratTugas', 'attachments']);
        $user = Auth::user();

        // Super admin / admin melihat semua, pegawai hanya melihat miliknya
        if ($user && !$user->hasAnyRole(['super_admin', 'admin'])) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $isSuperAdmin = Auth::user()?->hasAnyRole(['super_admin', 'admin']) ?? false;

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Kegiatan')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Pegawai / Petugas')
                            ->options(fn () => User::where('name', 'not like', '%Terlampir%')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(Auth::id())
                            ->visible($isSuperAdmin)
                            ->live()
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('activity_date')
                            ->label('Tanggal Kegiatan')
                            ->required()
                            ->default(Carbon::today())
                            ->displayFormat('d/m/Y')
                            ->columnSpan($isSuperAdmin ? 1 : 1),

                        Forms\Components\Select::make('surat_tugas_id')
                            ->label('Tautan Surat Tugas')
                            ->options(function (Forms\Get $get) {
                                $userId = $get('user_id') ?: Auth::id();
                                return SuratTugas::where('user_id', $userId)
                                    ->latest('tanggal')
                                    ->take(30)
                                    ->get()
                                    ->mapWithKeys(function ($st) {
                                        $label = "No. " . ($st->nomor_surat_tugas ?: 'DRAFT') . " - " . ($st->keperluan ?: $st->tempat_tugas);
                                        return [$st->id => $label];
                                    });
                            })
                            ->searchable()
                            ->nullable()
                            ->placeholder('Pilih jika terkait Surat Tugas')
                            ->helperText('Kosongkan jika merupakan pekerjaan rutin harian non-surat tugas.')
                            ->columnSpan($isSuperAdmin ? 1 : 2),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'submitted' => 'Diajukan / Selesai',
                                'verified' => 'Diverifikasi Atasan',
                            ])
                            ->default('submitted')
                            ->required()
                            ->visible($isSuperAdmin)
                            ->columnSpan(1),
                    ]),

                Forms\Components\Section::make('Uraian Narasi Kegiatan')
                    ->description('Tulis catatan singkat atau gunakan Gemini AI untuk memoles bahasa laporan.')
                    ->schema([
                        Forms\Components\Textarea::make('raw_content')
                            ->label('Catatan Kasar / Draft Kegiatan')
                            ->placeholder('Contoh: Koordinasi sama diskominfo terkait data sektoral, benerin format tabel yg belum lengkap')
                            ->rows(3)
                            ->required()
                            ->hintAction(
                                Forms\Components\Actions\Action::make('ai_polish')
                                    ->label('✨ Poles dengan Gemini AI')
                                    ->color('warning')
                                    ->icon('heroicon-m-sparkles')
                                    ->action(function (Forms\Get $get, Forms\Set $set) {
                                        $raw = trim($get('raw_content') ?? '');
                                        if (empty($raw)) {
                                            Notification::make()
                                                ->warning()
                                                ->title('Catatan Masih Kosong')
                                                ->body('Silakan ketik catatan kasar terlebih dahulu sebelum memoles dengan AI.')
                                                ->send();
                                            return;
                                        }

                                        try {
                                            $aiService = app(DailyActivityAiService::class);
                                            $userId = $get('user_id') ?: Auth::id();
                                            $user = User::find($userId);
                                            $date = $get('activity_date') ?: Carbon::today()->format('d-m-Y');

                                            $formalized = $aiService->formalize($raw, [
                                                'user_name' => $user?->name ?? 'Pegawai',
                                                'tanggal' => $date,
                                            ]);

                                            $set('final_content', $formalized);
                                            $set('ai_formal_content', $formalized);

                                            Notification::make()
                                                ->success()
                                                ->title('✨ Narasi Berhasil Dipoles!')
                                                ->body('Narasi formal telah diisikan ke kolom Narasi Laporan di bawah.')
                                                ->send();
                                        } catch (\Throwable $e) {
                                            Notification::make()
                                                ->danger()
                                                ->title('Gagal Memproses AI')
                                                ->body($e->getMessage())
                                                ->send();
                                        }
                                    })
                            ),

                        Forms\Components\Textarea::make('final_content')
                            ->label('Narasi Formal Laporan (Siap Cetak)')
                            ->helperText('Teks ini yang akan dicantumkan pada berkas bukti dukung / dokumen Word.')
                            ->rows(4)
                            ->required(),
                    ]),

                Forms\Components\Section::make('Foto Dokumentasi & Bukti Visual')
                    ->description('Unggah satu atau beberapa foto kegiatan sebagai bukti dukung.')
                    ->schema([
                        Forms\Components\Repeater::make('attachments')
                            ->relationship('attachments')
                            ->label('Daftar Foto Bukti')
                            ->columns(3)
                            ->reorderable('sort_order')
                            ->schema([
                                Forms\Components\FileUpload::make('file_path')
                                    ->label('Foto Kegiatan')
                                    ->image()
                                    ->disk('public')
                                    ->directory('daily-activities')
                                    ->maxSize(5120)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state && is_string($state)) {
                                            $fullPath = storage_path('app/public/' . $state);
                                            $exif = app(\App\Services\ImageExifExtractorService::class)->extract($fullPath);
                                            if ($exif['latitude']) $set('latitude', $exif['latitude']);
                                            if ($exif['longitude']) $set('longitude', $exif['longitude']);
                                            if ($exif['captured_at']) $set('captured_at', $exif['captured_at']->format('Y-m-d H:i:s'));
                                        }
                                    })
                                    ->columnSpan(2),

                                Forms\Components\Grid::make(1)
                                    ->schema([
                                        Forms\Components\TextInput::make('caption')
                                            ->label('Keterangan / Kategori Foto')
                                            ->placeholder('Contoh: Koordinasi bersama Kades'),

                                        Forms\Components\DateTimePicker::make('captured_at')
                                            ->label('Waktu Foto')
                                            ->displayFormat('d/m/Y H:i'),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('latitude')
                                                    ->label('Latitude')
                                                    ->numeric()
                                                    ->placeholder('-6.8945'),

                                                Forms\Components\TextInput::make('longitude')
                                                    ->label('Longitude')
                                                    ->numeric()
                                                    ->placeholder('110.6385'),
                                            ]),

                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('pick_coordinate_map')
                                                ->label('Pilih Titik di Peta 📍')
                                                ->icon('heroicon-m-map-pin')
                                                ->color('warning')
                                                ->modalHeading('Pilih Titik Koordinat Lokasi di Peta')
                                                ->modalDescription('Cari nama wilayah/desa/kecamatan atau klik & geser penanda (marker) pada peta.')
                                                ->modalWidth(\Filament\Support\Enums\MaxWidth::FourExtraLarge)
                                                ->modalSubmitActionLabel('Gunakan Titik Ini 📍')
                                                ->form([
                                                    \App\Filament\Forms\Components\MapWithSearch::make('map_location')
                                                        ->label('Peta & Pencarian Wilayah')
                                                        ->columnSpanFull()
                                                        ->default(function (Forms\Get $get) {
                                                            $lat = (float) ($get('latitude') ?: -6.894561);
                                                            $lng = (float) ($get('longitude') ?: 110.637492);
                                                            return ['lat' => $lat, 'lng' => $lng];
                                                        }),
                                                ])
                                                ->action(function (array $data, Forms\Set $set) {
                                                    if (!empty($data['map_location']['lat']) && !empty($data['map_location']['lng'])) {
                                                        $set('latitude', round((float)$data['map_location']['lat'], 8));
                                                        $set('longitude', round((float)$data['map_location']['lng'], 8));
                                                    }
                                                }),
                                        ]),

                                        Forms\Components\Toggle::make('is_stamped')
                                            ->label('Sudah Ada Stempel (Open Camera)')
                                            ->helperText('Centang jika foto sudah memiliki watermark koordinat/waktu bawaan.')
                                            ->default(false),
                                    ])
                                    ->columnSpan(1),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $isSuperAdmin = Auth::user()?->hasAnyRole(['super_admin', 'admin']) ?? false;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('activity_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pegawai')
                    ->searchable()
                    ->sortable()
                    ->visible($isSuperAdmin),

                Tables\Columns\TextColumn::make('suratTugas.nomor_surat_tugas')
                    ->label('Surat Tugas')
                    ->badge()
                    ->color(fn ($state) => $state ? 'primary' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? "No. {$state}" : 'Kegiatan Rutin')
                    ->searchable(),

                Tables\Columns\TextColumn::make('final_content')
                    ->label('Uraian Narasi Kegiatan')
                    ->limit(65)
                    ->tooltip(fn (DailyActivity $record) => $record->final_content)
                    ->wrap(),

                Tables\Columns\TextColumn::make('attachments_count')
                    ->counts('attachments')
                    ->label('Foto')
                    ->badge()
                    ->icon('heroicon-m-camera')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'submitted',
                        'success' => 'verified',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft' => 'Draft',
                        'submitted' => 'Diajukan',
                        'verified' => 'Diverifikasi',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('laporan_perjalanan_dinas_id')
                    ->label('LPD')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (DailyActivity $record) => $record->laporan_perjalanan_dinas_id ? 'Sudah disalin ke LPD' : 'Belum disalin ke LPD'),
            ])
            ->defaultSort('activity_date', 'desc')
            ->filters([
                Tables\Filters\Filter::make('activity_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('activity_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('activity_date', '<=', $date));
                    }),

                Tables\Filters\TernaryFilter::make('has_surat_tugas')
                    ->label('Terkait Surat Tugas')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('surat_tugas_id'),
                        false: fn ($query) => $query->whereNull('surat_tugas_id'),
                    ),
            ])
            ->actions([
                // Aksi Satu-Klik Salin ke LPD
                Tables\Actions\Action::make('convert_to_lpd')
                    ->label('Salin ke LPD')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('success')
                    ->visible(fn (DailyActivity $record) => !empty($record->surat_tugas_id) && empty($record->laporan_perjalanan_dinas_id))
                    ->requiresConfirmation()
                    ->modalHeading('Konversi ke Laporan Perjalanan Dinas (LPD)')
                    ->modalDescription('Uraian kegiatan dan foto bukti dukung akan disalin secara otomatis ke draf LPD untuk nomor Surat Tugas terkait. Lanjutkan?')
                    ->action(function (DailyActivity $record) {
                        $st = $record->suratTugas;
                        if (!$st) {
                            Notification::make()->danger()->title('Surat Tugas Tidak Ditemukan')->send();
                            return;
                        }

                        // 1. Validasi Kuota LPD Surat Tugas (1 hari = 1 LPD, N hari = N LPD)
                        if (!LaporanPerjalananDinas::canCreateLpd($st)) {
                            $max = LaporanPerjalananDinas::getMaxLpdCount($st);
                            $msg = $max === 1
                                ? "Surat Tugas ini memiliki durasi 1 hari dan sudah memiliki 1 laporan LPD. Tidak dapat menambah LPD lagi."
                                : "Surat Tugas ini memiliki durasi {$max} hari dan telah mencapai batas maksimal {$max} laporan LPD.";

                            Notification::make()
                                ->danger()
                                ->title('Batas Kuota LPD Tercapai')
                                ->body($msg)
                                ->send();
                            return;
                        }

                        // 2. Validasi Rentang Tanggal Surat Tugas
                        $range = LaporanPerjalananDinas::getDateRangeForSuratTugas($st);
                        $actDate = \Carbon\Carbon::parse($record->activity_date)->startOfDay();

                        if ($range['start'] && $actDate->lt($range['start'])) {
                            Notification::make()
                                ->danger()
                                ->title('Tanggal di Luar Penugasan')
                                ->body("Tanggal kegiatan (" . $actDate->translatedFormat('d F Y') . ") mendahului rentang tanggal Surat Tugas (" . $range['start']->translatedFormat('d F Y') . ").")
                                ->send();
                            return;
                        }

                        if ($range['end'] && $actDate->gt($range['end'])) {
                            Notification::make()
                                ->danger()
                                ->title('Tanggal di Luar Penugasan')
                                ->body("Tanggal kegiatan (" . $actDate->translatedFormat('d F Y') . ") melebihi rentang tanggal Surat Tugas (" . $range['end']->translatedFormat('d F Y') . ").")
                                ->send();
                            return;
                        }

                        // 3. Validasi Konflik LPD pada Tanggal yang Sama untuk Pegawai yang Sama
                        $duplicateLpd = LaporanPerjalananDinas::getDuplicateForUser($record->user_id, $record->activity_date);
                        if ($duplicateLpd) {
                            Notification::make()
                                ->danger()
                                ->title('Bentrok Jadwal LPD')
                                ->body("Pegawai sudah memiliki Laporan Perjalanan Dinas pada tanggal " . $actDate->translatedFormat('d F Y') . " (No. ST: " . ($duplicateLpd->nomor_surat_tugas ?: 'Draft') . "). Satu pegawai hanya dapat memiliki 1 LPD per tanggal.")
                                ->send();
                            return;
                        }

                        // Buat Laporan Perjalanan Dinas jika semua validasi lolos
                        $lpd = LaporanPerjalananDinas::create([
                            'surat_tugas_id' => $st->id,
                            'nomor_surat_tugas' => $st->nomor_surat_tugas ?? '',
                            'tujuan' => $st->keperluan ?: $st->tempat_tugas ?: 'Pelaksanaan Penugasan Lapangan',
                            'tanggal_kunjungan' => $record->activity_date,
                            'uraian_kegiatan' => $record->final_content,
                            'nama_pejabat' => '-',
                            'desa_pejabat' => $st->tempat_tugas ?: '-',
                        ]);

                        // Salin foto ke laporan_foto
                        foreach ($record->attachments as $idx => $att) {
                            LaporanFoto::create([
                                'laporan_perjalanan_dinas_id' => $lpd->id,
                                'file_path' => $att->file_path,
                                'keterangan' => $att->caption ?: ('Dokumentasi ' . ($idx + 1)),
                                'urutan' => $idx + 1,
                            ]);
                        }

                        $record->update(['laporan_perjalanan_dinas_id' => $lpd->id]);

                        Notification::make()
                            ->success()
                            ->title('LPD Berhasil Dibuat!')
                            ->body('Data kegiatan dan foto berhasil divalidasi dan disalin ke Laporan Perjalanan Dinas.')
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('buka_lpd')
                                    ->label('Buka LPD')
                                    ->url(route('filament.admin.resources.laporan-perjalanan-dinas.edit', ['record' => $lpd->id])),
                            ])
                            ->send();
                    }),

                // Aksi Buka di Halaman Stempel Foto
                Tables\Actions\Action::make('open_photo_stamp')
                    ->label('Stempel Foto')
                    ->icon('heroicon-m-camera')
                    ->color('warning')
                    ->visible(fn (DailyActivity $record) => $record->attachments()->where('is_stamped', false)->exists())
                    ->url(route('filament.admin.pages.stempel-foto')),

                // Aksi Unduh DOCX
                Tables\Actions\Action::make('export_docx')
                    ->label('Unduh Word')
                    ->icon('heroicon-m-document-arrow-down')
                    ->color('info')
                    ->action(function (DailyActivity $record) {
                        try {
                            $generator = app(\App\Services\WorkReportGeneratorService::class);
                            $user = $record->user ?? Auth::user();
                            $res = $generator->generateWordReport($user, [$record], $record->activity_date->translatedFormat('d F Y'));

                            return response()->download($res['path'], $res['name']);
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Gagal Membuat Dokumen')->body($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_selected_docx')
                        ->label('Cetak Terpilih (Word .docx)')
                        ->icon('heroicon-m-document-arrow-down')
                        ->color('info')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            try {
                                $generator = app(\App\Services\WorkReportGeneratorService::class);
                                $firstRecord = $records->first();
                                $user = $firstRecord?->user ?? Auth::user();
                                $res = $generator->generateWordReport($user, $records, 'Kegiatan Terpilih');

                                return response()->download($res['path'], $res['name']);
                            } catch (\Throwable $e) {
                                Notification::make()->danger()->title('Gagal Ekspor Terpilih')->body($e->getMessage())->send();
                            }
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyActivities::route('/'),
            'create' => Pages\CreateDailyActivity::route('/create'),
            'edit' => Pages\EditDailyActivity::route('/{record}/edit'),
        ];
    }
}
