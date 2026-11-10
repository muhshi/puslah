<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyActivityResource\Pages;
use App\Models\DailyActivity;
use App\Models\LaporanFoto;
use App\Models\LaporanPerjalananDinas;
use App\Models\SuratTugas;
use App\Models\User;
use App\Services\DailyActivityAiService;
use App\Services\LpdAiService;
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

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user', 'suratTugas', 'attachments', 'sharedWorkReports', 'sharedWorkReport']);
        $user = Auth::user();

        // Hanya tampilkan kegiatan milik user yang sedang login (fokus pada laporan mandiri)
        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Kegiatan')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Hidden::make('user_id')
                            ->default(fn () => Auth::id()),

                        Forms\Components\DatePicker::make('activity_date')
                            ->label('Tanggal Kegiatan')
                            ->required()
                            ->default(Carbon::today())
                            ->displayFormat('d/m/Y')
                            ->columnSpan(1),

                        Forms\Components\Select::make('surat_tugas_id')
                            ->label('Tautan Surat Tugas')
                            ->options(function (Forms\Get $get) {
                                $userId = Auth::id();
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
                            ->columnSpan(1),

                        Forms\Components\Select::make('sharedWorkReports')
                            ->relationship('sharedWorkReports', 'title', modifyQueryUsing: fn ($query) => $query->where('user_id', Auth::id())->where('is_active', true)->latest())
                            ->label('Wadah Butir KIPAPP / E-Kinerja')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->placeholder('Pilih satu atau beberapa wadah butir KIPAPP...')
                            ->helperText('1 kegiatan dapat dimasukkan ke lebih dari 1 wadah butir KIPAPP sekaligus.')
                            ->columnSpan(2),

                        Forms\Components\Hidden::make('status')
                            ->default('submitted'),
                    ]),

                Forms\Components\Section::make('Uraian Narasi Kegiatan')
                    ->description('Tulis catatan singkat atau gunakan Gemini AI untuk memoles bahasa laporan.')
                    ->schema([
                        Forms\Components\Textarea::make('raw_content')
                            ->label('Catatan Kasar / Draft Kegiatan')
                            ->placeholder('Contoh: Koordinasi sama diskominfo terkait data sektoral, benerin format tabel yg belum lengkap')
                            ->rows(3)
                            ->required()
                            ->hintActions([
                                Forms\Components\Actions\Action::make('ai_polish')
                                    ->label('✨ Poles AI')
                                    ->color('primary')
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
                                    }),
                            ]),

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
                            ->columns(2)
                            ->reorderable('sort_order')
                            ->schema([
                                Forms\Components\Grid::make(1)
                                    ->schema([
                                        Forms\Components\FileUpload::make('file_path')
                                            ->label('Berkas Bukti (Foto / PDF)')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                            ->disk('public')
                                            ->directory('daily-activities')
                                            ->maxSize(10240)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if (!$state) return;

                                                $realPath = null;
                                                if (is_string($state)) {
                                                    $realPath = storage_path('app/public/' . $state);
                                                } elseif (is_object($state) && method_exists($state, 'getRealPath')) {
                                                    $realPath = $state->getRealPath();
                                                }

                                                if ($realPath && file_exists($realPath)) {
                                                    $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
                                                    if ($ext === 'pdf') {
                                                        $set('captured_at', now()->format('Y-m-d H:i:s'));
                                                        $set('is_stamped', true);
                                                    } else {
                                                        $exif = app(\App\Services\ImageExifExtractorService::class)->extract($realPath);
                                                        if (!empty($exif['latitude'])) $set('latitude', $exif['latitude']);
                                                        if (!empty($exif['longitude'])) $set('longitude', $exif['longitude']);
                                                        if (!empty($exif['captured_at'])) {
                                                            $set('captured_at', $exif['captured_at']->format('Y-m-d H:i:s'));
                                                        } else {
                                                            $set('captured_at', now()->format('Y-m-d H:i:s'));
                                                        }
                                                    }
                                                }
                                            }),

                                        Forms\Components\ViewField::make('stamped_preview')
                                            ->view('filament.forms.components.stamped-image-preview'),

                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('burn_stamp_now')
                                                ->label(fn (Forms\Get $get) => $get('is_stamped') ? '✅ Foto Sudah Berstempel Resmi' : '⚡ Cap Stempel Permanen ke File')
                                                ->icon(fn (Forms\Get $get) => $get('is_stamped') ? 'heroicon-m-check-badge' : 'heroicon-m-sparkles')
                                                ->color(fn (Forms\Get $get) => $get('is_stamped') ? 'gray' : 'warning')
                                                ->disabled(fn (Forms\Get $get) => (bool) $get('is_stamped'))
                                                ->requiresConfirmation()
                                                ->modalHeading('Cap Stempel Permanen ke File')
                                                ->modalDescription('Koordinat, waktu, dan logo instansi akan digambar langsung (burn-in) secara permanen ke dalam file foto menggunakan PHP GD. Lanjutkan?')
                                                ->action(function (Forms\Get $get, Forms\Set $set) {
                                                    if ($get('is_stamped')) {
                                                        Notification::make()->info()->title('Foto Sudah Berstempel')->body('Foto ini sudah memiliki stempel resmi BPS.')->send();
                                                        return;
                                                    }

                                                    $filePath = $get('file_path');
                                                    if (!$filePath) {
                                                        Notification::make()->warning()->title('Belum Ada File')->body('Unggah berkas bukti kegiatan terlebih dahulu.')->send();
                                                        return;
                                                    }

                                                    if (is_string($filePath) && str_ends_with(strtolower($filePath), '.pdf')) {
                                                        Notification::make()->info()->title('Dokumen PDF')->body('Stempel visual kamera hanya berlaku untuk berkas foto/gambar. Dokumen PDF otomatis siap dilampirkan.')->send();
                                                        return;
                                                    }

                                                    $realPath = is_string($filePath) ? storage_path('app/public/' . $filePath) : null;
                                                    if (!$realPath || !file_exists($realPath)) {
                                                        Notification::make()->danger()->title('File Tidak Ditemukan')->body('Simpan kegiatan terlebih dahulu sebelum mencap stempel permanen.')->send();
                                                        return;
                                                    }

                                                    $lat = (float) ($get('latitude') ?: -6.894561);
                                                    $lng = (float) ($get('longitude') ?: 110.637492);
                                                    $time = $get('captured_at') ?: now();
                                                    $caption = $get('caption') ?: 'Dokumentasi Pelaksanaan Tugas';

                                                    $ok = app(\App\Services\PhotoWatermarkService::class)->applyWatermark($realPath, [
                                                        'latitude' => $lat,
                                                        'longitude' => $lng,
                                                        'captured_at' => $time,
                                                        'caption' => $caption,
                                                        'office_name' => 'BPS KABUPATEN DEMAK',
                                                        'position' => $get('stamp_position') ?: 'bottom-left',
                                                    ]);

                                                    if ($ok) {
                                                        $set('is_stamped', true);
                                                        Notification::make()->success()->title('✅ Stempel Berhasil Dicap Permanen')->body('File gambar telah berhasil dibakar stempel fisik resmi.')->send();
                                                    } else {
                                                        Notification::make()->danger()->title('Gagal Mencap Stempel')->body('Terjadi kendala saat memproses gambar fisik.')->send();
                                                    }
                                                }),
                                        ])->extraAttributes(['class' => 'mt-3 mb-1']),
                                    ])
                                    ->columnSpan(1),

                                Forms\Components\Grid::make(1)
                                    ->schema([
                                        Forms\Components\TextInput::make('caption')
                                            ->label('Keterangan / Kategori Foto')
                                            ->placeholder('Contoh: Koordinasi bersama Kades')
                                            ->live(),

                                        Forms\Components\DateTimePicker::make('captured_at')
                                            ->label('Waktu Foto')
                                            ->displayFormat('d/m/Y H:i')
                                            ->live(),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('latitude')
                                                    ->label('Latitude')
                                                    ->numeric()
                                                    ->placeholder('-6.8945')
                                                    ->live(),

                                                Forms\Components\TextInput::make('longitude')
                                                    ->label('Longitude')
                                                    ->numeric()
                                                    ->placeholder('110.6385')
                                                    ->live(),
                                            ]),

                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('pick_coordinate_map')
                                                ->label('Pilih Titik di Peta 📍')
                                                ->icon('heroicon-m-map-pin')
                                                ->color('info')
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
                                        ])->extraAttributes(['class' => 'my-3']),

                                        Forms\Components\Toggle::make('is_stamped')
                                            ->label('Sudah Ada Stempel Bawaan')
                                            ->helperText('Aktifkan jika foto sudah memiliki cap koordinat & waktu bawaan (misal: Open Camera).')
                                            ->live()
                                            ->default(false),

                                        Forms\Components\Select::make('stamp_position')
                                            ->label('Posisi Stempel Watermark')
                                            ->options([
                                                'bottom-left' => 'Pojok Kiri Bawah (Standar)',
                                                'bottom-right' => 'Pojok Kanan Bawah',
                                                'top-left' => 'Pojok Kiri Atas',
                                            ])
                                            ->default('bottom-left')
                                            ->helperText('Pilih posisi agar stempel tidak menutupi wajah atau objek utama foto.')
                                            ->visible(fn (Forms\Get $get) => !$get('is_stamped')),
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
            ->recordUrl(null)
            ->recordAction(null)
            ->columns([
                Tables\Columns\TextColumn::make('activity_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pegawai')
                    ->visible(false),

                Tables\Columns\TextColumn::make('surat_tugas_id')
                    ->label('Surat Tugas')
                    ->state(function (DailyActivity $record): string {
                        return $record->suratTugas?->nomor_surat ? "No. {$record->suratTugas->nomor_surat}" : 'Kegiatan Rutin';
                    })
                    ->badge()
                    ->color(fn ($state) => $state !== 'Kegiatan Rutin' ? 'primary' : 'gray')
                    ->icon(fn ($state) => $state !== 'Kegiatan Rutin' ? 'heroicon-m-document-text' : 'heroicon-m-calendar')
                    ->tooltip('Klik untuk menautkan atau melepas Surat Tugas')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('suratTugas', function ($q) use ($search) {
                            $q->where('nomor_surat', 'like', "%{$search}%")
                              ->orWhere('nomor_surat_tugas', 'like', "%{$search}%")
                              ->orWhere('keperluan', 'like', "%{$search}%");
                        });
                    })
                    ->action(
                        Tables\Actions\Action::make('change_surat_tugas')
                            ->label('Atur Surat Tugas')
                            ->modalHeading('Atur Surat Tugas Kegiatan')
                            ->modalDescription('Tautkan kegiatan ini ke Surat Tugas yang sesuai, atau kosongkan untuk menjadikannya Kegiatan Rutin.')
                            ->modalWidth(\Filament\Support\Enums\MaxWidth::Medium)
                            ->modalSubmitActionLabel('Simpan Perubahan')
                            ->fillForm(fn (DailyActivity $record): array => [
                                'surat_tugas_id' => $record->surat_tugas_id,
                            ])
                            ->form([
                                Forms\Components\Placeholder::make('info_kegiatan')
                                    ->label('Kegiatan')
                                    ->content(fn (DailyActivity $record) => $record->activity_date->translatedFormat('l, d F Y') . ' — ' . \Illuminate\Support\Str::limit($record->final_content, 120)),

                                Forms\Components\Select::make('surat_tugas_id')
                                    ->label('Pilih Surat Tugas')
                                    ->options(function (DailyActivity $record) {
                                        $userId = $record->user_id ?: Auth::id();
                                        return SuratTugas::where('user_id', $userId)
                                            ->where('status', '!=', 'rejected')
                                            ->latest('tanggal')
                                            ->take(50)
                                            ->get()
                                            ->mapWithKeys(function ($st) {
                                                $tgl = $st->tanggal ? \Carbon\Carbon::parse($st->tanggal)->format('d/m/Y') : '-';
                                                $nomor = $st->nomor_surat ?: ($st->nomor_surat_tugas ?: 'DRAFT');
                                                $keperluan = $st->keperluan ?: $st->tempat_tugas ?: 'Penugasan Lapangan';
                                                return [$st->id => "[{$tgl}] No. {$nomor} — {$keperluan}"];
                                            });
                                    })
                                    ->searchable()
                                    ->nullable()
                                    ->placeholder('— Tanpa Surat Tugas (Kegiatan Rutin) —')
                                    ->helperText('Pilih surat tugas yang relevan, atau kosongkan jika ini kegiatan rutin non-surat tugas.'),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('lepas_st_sekarang')
                                        ->label('🗑️ Lepas Surat Tugas Sekarang')
                                        ->color('danger')
                                        ->visible(fn (DailyActivity $record) => !empty($record->surat_tugas_id))
                                        ->requiresConfirmation()
                                        ->modalHeading('Lepas Kaitan Surat Tugas?')
                                        ->modalDescription('Kegiatan ini akan dikembalikan menjadi kegiatan rutin non-surat tugas.')
                                        ->action(function (DailyActivity $record, Forms\Set $set) {
                                            $record->update(['surat_tugas_id' => null]);
                                            $set('surat_tugas_id', null);
                                            Notification::make()
                                                ->info()
                                                ->title('Surat Tugas Dilepas')
                                                ->body('Kegiatan kini tercatat sebagai Kegiatan Rutin (non-surat tugas).')
                                                ->send();
                                        }),
                                ])->extraAttributes(['class' => 'mt-2']),
                            ])
                            ->action(function (DailyActivity $record, array $data) {
                                $newStId = !empty($data['surat_tugas_id']) ? (int) $data['surat_tugas_id'] : null;
                                $record->update(['surat_tugas_id' => $newStId]);

                                if ($newStId) {
                                    $st = SuratTugas::find($newStId);
                                    Notification::make()
                                        ->success()
                                        ->title('Surat Tugas Ditautkan')
                                        ->body('Kegiatan berhasil ditautkan ke ST No. ' . ($st?->nomor_surat ?: 'Draft'))
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->info()
                                        ->title('Surat Tugas Dilepas')
                                        ->body('Kegiatan kini tercatat sebagai Kegiatan Rutin (non-surat tugas).')
                                        ->send();
                                }
                            })
                    ),

                Tables\Columns\TextColumn::make('final_content')
                    ->label('Uraian Narasi Kegiatan')
                    ->limit(70)
                    ->wrap()
                    ->tooltip(fn (DailyActivity $record) => $record->final_content)
                    ->description(fn (DailyActivity $record) => view('filament.tables.columns.activity-wadah-badges', ['record' => $record])),

                Tables\Columns\TextColumn::make('attachments_count')
                    ->counts('attachments')
                    ->label('Foto')
                    ->badge()
                    ->icon('heroicon-m-camera')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

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

                Tables\Filters\SelectFilter::make('wadah_kipapp')
                    ->label('Wadah KIPAPP')
                    ->placeholder('Semua Kegiatan')
                    ->options(function () {
                        $options = ['unassigned' => '⚠️ Belum Masuk Wadah'];
                        $reports = \App\Models\SharedWorkReport::where('user_id', Auth::id())
                            ->where('is_active', true)
                            ->latest()
                            ->pluck('title', 'id')
                            ->toArray();
                        return $options + $reports;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        if ($data['value'] === 'unassigned') {
                            return $query->whereDoesntHave('sharedWorkReports')
                                ->whereNull('shared_work_report_id');
                        }

                        $reportId = (int) $data['value'];
                        return $query->where(function ($q) use ($reportId) {
                            $q->whereHas('sharedWorkReports', fn ($sq) => $sq->where('shared_work_reports.id', $reportId))
                              ->orWhere('shared_work_report_id', $reportId);
                        });
                    }),
            ])
            ->actions([
                // Kelompok Aksi Manajemen Baris Kegiatan
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('convert_to_lpd')
                        ->label('Salin Baris Ini ke LPD')
                        ->icon('heroicon-m-arrow-top-right-on-square')
                        ->color('success')
                        ->visible(fn (DailyActivity $record) => !empty($record->surat_tugas_id) && empty($record->laporan_perjalanan_dinas_id))
                        ->modalHeading('Konversi Bukti Dukung ke Laporan Perjalanan Dinas (LPD)')
                        ->modalDescription('Gabungkan seluruh catatan kegiatan dan dokumentasi foto pada tanggal ini menjadi satu berkas LPD resmi.')
                        ->modalWidth(\Filament\Support\Enums\MaxWidth::FourExtraLarge)
                        ->modalSubmitActionLabel('Buat LPD Resmi 🚀')
                        ->fillForm(function (DailyActivity $record): array {
                            $st = $record->suratTugas;
                            if (!$st) return [];

                            $sameDayActivities = DailyActivity::where('user_id', $record->user_id)
                                ->whereDate('activity_date', $record->activity_date)
                                ->where('surat_tugas_id', $record->surat_tugas_id)
                                ->with('attachments')
                                ->get();

                            $lpdAiService = app(LpdAiService::class);
                            $actDate = \Carbon\Carbon::parse($record->activity_date);
                            $user = $record->user ?? Auth::user();

                            $generated = $lpdAiService->generateChronologicalFromActivities(
                                $sameDayActivities,
                                $st,
                                $user,
                                $actDate
                            );

                            return [
                                'tujuan' => $st->keperluan ?: $st->tempat_tugas ?: 'Pelaksanaan Penugasan Lapangan',
                                'desa_pejabat' => $generated['desa_pejabat'] ?? ($st->tempat_tugas ?: 'Kabupaten Demak'),
                                'nama_pejabat' => $generated['nama_pejabat'] ?? '-',
                                'uraian_kegiatan' => $generated['narasi'],
                            ];
                        })
                        ->form(function (DailyActivity $record): array {
                            $st = $record->suratTugas;
                            $sameDayActivities = DailyActivity::where('user_id', $record->user_id)
                                ->whereDate('activity_date', $record->activity_date)
                                ->where('surat_tugas_id', $record->surat_tugas_id)
                                ->with('attachments')
                                ->get();

                            $totalAct = $sameDayActivities->count();
                            $totalPhotos = $sameDayActivities->flatMap->attachments->count();
                            $dateStr = \Carbon\Carbon::parse($record->activity_date)->translatedFormat('l, d F Y');
                            $stNo = $st?->nomor_surat_tugas ?: 'DRAFT';

                            return [
                                Forms\Components\Placeholder::make('agregator_info')
                                    ->hiddenLabel()
                                    ->content(new \Illuminate\Support\HtmlString("
                                        <div class='p-3.5 rounded-xl border border-primary-200 bg-primary-50/60 dark:bg-primary-950/20 text-xs space-y-1.5'>
                                            <div class='font-bold text-primary-900 dark:text-primary-200 flex items-center gap-1.5'>
                                                <span>⚡ Agregator Bukti Dukung Harian BPS</span>
                                            </div>
                                            <div class='text-slate-600 dark:text-slate-400'>
                                                📅 <strong>Tanggal:</strong> {$dateStr} &bull; 🔖 <strong>Surat Tugas:</strong> No. {$stNo}
                                            </div>
                                            <div class='text-emerald-700 dark:text-emerald-400 font-medium'>
                                                ✅ Ditemukan <strong>{$totalAct} catatan bukti dukung</strong> dan <strong>{$totalPhotos} berkas foto</strong> pada hari ini yang akan otomatis disintesis secara kronologis dan dilampirkan ke LPD.
                                            </div>
                                        </div>
                                    ")),

                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('tujuan')
                                        ->label('Tujuan / Keperluan')
                                        ->required()
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('desa_pejabat')
                                        ->label('Lokasi / Instansi Tujuan')
                                        ->required()
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make('nama_pejabat')
                                        ->label('Nama Pejabat yang Ditemui')
                                        ->placeholder('Contoh: Bapak Iman Teguh Raharto, S.Si, M.Si (atau - jika umum)')
                                        ->columnSpan(3),
                                ]),

                                Forms\Components\RichEditor::make('uraian_kegiatan')
                                    ->label('Uraian Narasi Kronologis LPD (Per Jam)')
                                    ->required()
                                    ->helperText('Format standar BPS: Runtut per jam dari keberangkatan pagi, substansi & koordinasi teknis, ISHOMA, hingga kepulangan.')
                                    ->hintAction(
                                        Forms\Components\Actions\Action::make('regenerate_ai')
                                            ->label('✨ Susun Ulang Kronologis via AI')
                                            ->icon('heroicon-m-sparkles')
                                            ->color('primary')
                                            ->action(function (Forms\Set $set, DailyActivity $record) {
                                                $st = $record->suratTugas;
                                                if (!$st) return;

                                                $sameDayActivities = DailyActivity::where('user_id', $record->user_id)
                                                    ->whereDate('activity_date', $record->activity_date)
                                                    ->where('surat_tugas_id', $record->surat_tugas_id)
                                                    ->with('attachments')
                                                    ->get();

                                                $lpdAiService = app(LpdAiService::class);
                                                $actDate = \Carbon\Carbon::parse($record->activity_date);
                                                $user = $record->user ?? Auth::user();

                                                $gen = $lpdAiService->generateChronologicalFromActivities(
                                                    $sameDayActivities,
                                                    $st,
                                                    $user,
                                                    $actDate
                                                );

                                                $set('uraian_kegiatan', $gen['narasi']);
                                                if (!empty($gen['nama_pejabat'])) {
                                                    $set('nama_pejabat', $gen['nama_pejabat']);
                                                }
                                                if (!empty($gen['desa_pejabat'])) {
                                                    $set('desa_pejabat', $gen['desa_pejabat']);
                                                }

                                                Notification::make()->success()->title('Narasi Kronologis Diperbarui! ✨')->send();
                                            })
                                    ),
                            ];
                        })
                        ->action(function (DailyActivity $record, array $data) {
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

                            // 4. Buat Laporan Perjalanan Dinas
                            $lpd = LaporanPerjalananDinas::create([
                                'surat_tugas_id' => $st->id,
                                'nomor_surat_tugas' => $st->nomor_surat_tugas ?? '',
                                'tujuan' => $data['tujuan'] ?? ($st->keperluan ?: $st->tempat_tugas ?: 'Pelaksanaan Penugasan Lapangan'),
                                'tanggal_kunjungan' => $record->activity_date,
                                'uraian_kegiatan' => $data['uraian_kegiatan'],
                                'nama_pejabat' => $data['nama_pejabat'] ?? '-',
                                'desa_pejabat' => $data['desa_pejabat'] ?? ($st->tempat_tugas ?: '-'),
                            ]);

                            // 5. Agregasi seluruh foto dari semua kegiatan pada hari & ST yang sama
                            $sameDayActivities = DailyActivity::where('user_id', $record->user_id)
                                ->whereDate('activity_date', $record->activity_date)
                                ->where('surat_tugas_id', $record->surat_tugas_id)
                                ->with('attachments')
                                ->get();

                            $allAttachments = $sameDayActivities->flatMap->attachments->sortBy(function ($att) {
                                return $att->captured_at ? \Carbon\Carbon::parse($att->captured_at)->timestamp : ($att->created_at?->timestamp ?? 0);
                            });

                            $fotoOrder = 1;
                            foreach ($allAttachments as $att) {
                                LaporanFoto::create([
                                    'laporan_perjalanan_dinas_id' => $lpd->id,
                                    'file_path' => $att->file_path,
                                    'keterangan' => $att->caption ?: ('Dokumentasi Lapangan ' . $fotoOrder),
                                    'urutan' => $fotoOrder,
                                ]);
                                $fotoOrder++;
                            }

                            // 6. Tandai seluruh kegiatan hari tersebut bahwa sudah disalin ke LPD
                            DailyActivity::whereIn('id', $sameDayActivities->pluck('id'))
                                ->update(['laporan_perjalanan_dinas_id' => $lpd->id]);

                            $totalFotoDisalin = $fotoOrder - 1;
                            Notification::make()
                                ->success()
                                ->title('LPD Berhasil Diterbitkan! 🎉')
                                ->body("Data {$sameDayActivities->count()} bukti dukung dan {$totalFotoDisalin} foto dokumentasi hari ini telah berhasil disintesis ke LPD resmi.")
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('buka_lpd')
                                        ->label('Buka LPD')
                                        ->url(route('filament.admin.resources.laporan-perjalanan-dinas.edit', ['record' => $lpd->id])),
                                ])
                                ->send();
                        }),

                    Tables\Actions\Action::make('export_docx')
                        ->label('Unduh Dokumen Word')
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

                    Tables\Actions\Action::make('manage_wadah')
                        ->label('Atur Wadah KIPAPP')
                        ->icon('heroicon-m-folder')
                        ->color('success')
                        ->modalHeading('Atur Wadah KIPAPP Kegiatan')
                        ->modalDescription('Pilih satu atau beberapa wadah KIPAPP untuk membundel bukti dukung ini, atau salin tautan berkas publiknya.')
                        ->modalWidth(\Filament\Support\Enums\MaxWidth::Large)
                        ->modalSubmitActionLabel('Simpan Pilihan Wadah')
                        ->fillForm(function (DailyActivity $record): array {
                            $reportIds = $record->sharedWorkReports->pluck('id')->toArray();
                            if (empty($reportIds) && $record->shared_work_report_id) {
                                $reportIds = [$record->shared_work_report_id];
                            }
                            return [
                                'shared_work_reports' => $reportIds,
                            ];
                        })
                        ->form([
                            Forms\Components\Placeholder::make('activity_summary')
                                ->label('Kegiatan')
                                ->content(fn (DailyActivity $record) => $record->activity_date->translatedFormat('l, d F Y') . ' — ' . \Illuminate\Support\Str::limit($record->final_content, 120)),

                            Forms\Components\Select::make('shared_work_reports')
                                ->label('Pilih Wadah KIPAPP')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->options(function (DailyActivity $record) {
                                    $userId = $record->user_id ?: Auth::id();
                                    return \App\Models\SharedWorkReport::where('user_id', $userId)
                                        ->where('is_active', true)
                                        ->latest()
                                        ->pluck('title', 'id');
                                })
                                ->placeholder('Pilih satu atau beberapa wadah...')
                                ->helperText('Pilih wadah yang ingin ditautkan. Anda juga bisa mengosongkan pilihan untuk mengeluarkan kegiatan dari semua wadah.')
                                ->required(false),

                            Forms\Components\ViewField::make('wadah_links_preview')
                                ->view('filament.forms.components.wadah-modal-links'),
                        ])
                        ->action(function (DailyActivity $record, array $data) {
                            $selectedIds = $data['shared_work_reports'] ?? [];
                            $record->sharedWorkReports()->sync($selectedIds);
                            $record->update([
                                'shared_work_report_id' => !empty($selectedIds) ? (int) $selectedIds[0] : null,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Wadah KIPAPP Diperbarui')
                                ->body('Tautan wadah kegiatan berhasil disimpan.')
                                ->send();
                        }),

                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                ->tooltip('Pilihan Aksi')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // 1. Buat Laporan Perjalanan Dinas (LPD) dari Baris Terpilih
                    Tables\Actions\BulkAction::make('create_lpd_from_selected')
                        ->label('📝 Buat Laporan Perjalanan Dinas (LPD)')
                        ->icon('heroicon-m-document-text')
                        ->color('success')
                        ->modalHeading('Buat Laporan Perjalanan Dinas (LPD) dari Kegiatan Terpilih')
                        ->modalDescription('Pilih Survey & Surat Tugas untuk menyatukan kegiatan dan dokumentasi foto terpilih menjadi satu berkas LPD resmi.')
                        ->modalWidth(\Filament\Support\Enums\MaxWidth::FourExtraLarge)
                        ->modalSubmitActionLabel('Buat LPD Resmi & Lampirkan Foto 🚀')
                        ->fillForm(function (\Illuminate\Database\Eloquent\Collection $records): array {
                            $user = Auth::user();
                            $firstRecord = $records->sortBy('activity_date')->first();
                            $existingStId = $records->firstWhere('surat_tugas_id', '!=', null)?->surat_tugas_id;
                            $st = $existingStId ? \App\Models\SuratTugas::find($existingStId) : null;

                            $tanggalKunjungan = $firstRecord?->activity_date
                                ? \Carbon\Carbon::parse($firstRecord->activity_date)->format('Y-m-d')
                                : now()->format('Y-m-d');

                            $narasiAwal = '';
                            $namaPejabat = '-';
                            $desaPejabat = $st?->tempat_tugas ?: 'Kabupaten Demak';

                            if ($st && $user) {
                                try {
                                    $lpdAiService = app(LpdAiService::class);
                                    $actDate = \Carbon\Carbon::parse($tanggalKunjungan);
                                    $gen = $lpdAiService->generateChronologicalFromActivities(
                                        $records,
                                        $st,
                                        $user,
                                        $actDate
                                    );
                                    $narasiAwal = $gen['narasi'];
                                    if (!empty($gen['nama_pejabat'])) $namaPejabat = $gen['nama_pejabat'];
                                    if (!empty($gen['desa_pejabat'])) $desaPejabat = $gen['desa_pejabat'];
                                } catch (\Throwable $e) {
                                    // fallback jika ai error
                                }
                            }

                            if (empty($narasiAwal)) {
                                $poinList = [];
                                foreach ($records as $rec) {
                                    $poinList[] = $rec->final_content ?: $rec->raw_content;
                                }
                                $narasiAwal = "<p><strong>Pukul 08.00–16.00 WIB:</strong> " . htmlspecialchars(implode('. ', array_filter($poinList))) . ".</p>";
                            }

                            return [
                                'survey_id' => $st?->survey_id,
                                'surat_tugas_id' => $st?->id,
                                'tanggal_kunjungan' => $tanggalKunjungan,
                                'tujuan' => $st?->keperluan ?: ($st?->tempat_tugas ?: 'Pelaksanaan Penugasan Lapangan'),
                                'desa_pejabat' => $desaPejabat,
                                'nama_pejabat' => $namaPejabat,
                                'uraian_kegiatan' => $narasiAwal,
                            ];
                        })
                        ->form([
                            Forms\Components\Placeholder::make('selected_summary')
                                ->hiddenLabel()
                                ->content(function (\Illuminate\Database\Eloquent\Collection $records) {
                                    $count = $records->count();
                                    $totalPhotos = $records->sum(fn($r) => $r->attachments->count());
                                    $dates = $records->sortBy('activity_date')->pluck('activity_date')
                                        ->map(fn($d) => \Carbon\Carbon::parse($d)->translatedFormat('d F Y'))
                                        ->unique()
                                        ->values()
                                        ->join(', ');

                                    return new \Illuminate\Support\HtmlString("
                                        <div class='p-3.5 rounded-xl border border-emerald-200 bg-emerald-50/70 dark:bg-emerald-950/20 text-xs space-y-1.5'>
                                            <div class='font-bold text-emerald-900 dark:text-emerald-200 flex items-center gap-1.5'>
                                                <span>📋 Seleksi Baris Kegiatan ({$count} Catatan Bukti Dukung)</span>
                                            </div>
                                            <div class='text-slate-600 dark:text-slate-400'>
                                                📅 <strong>Tanggal Kegiatan Terpilih:</strong> {$dates}
                                            </div>
                                            <div class='text-emerald-700 dark:text-emerald-400 font-medium'>
                                                📸 Sebanyak <strong>{$totalPhotos} berkas foto</strong> dari kegiatan-kegiatan ini akan otomatis dilampirkan ke LPD dan diurutkan secara kronologis.
                                            </div>
                                        </div>
                                    ");
                                }),

                            Forms\Components\Section::make('Penugasan & Identitas Surat Tugas')
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\Select::make('survey_id')
                                            ->label('Filter Survei / Kegiatan')
                                            ->placeholder('Semua Kegiatan / Survei')
                                            ->options(fn () => \App\Models\Survey::orderBy('name')->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn (Forms\Set $set) => $set('surat_tugas_id', null)),

                                        Forms\Components\Select::make('surat_tugas_id')
                                            ->label('Pilih Nomor Surat Tugas Terkait')
                                            ->placeholder('Pilih Surat Tugas...')
                                            ->options(function (Forms\Get $get) {
                                                $surveyId = $get('survey_id');
                                                $userId = Auth::id();
                                                $q = \App\Models\SuratTugas::query();
                                                if ($userId && !Auth::user()->hasRole('super_admin')) {
                                                    $q->where('user_id', $userId);
                                                }
                                                if ($surveyId) {
                                                    $q->where('survey_id', $surveyId);
                                                }
                                                return $q->latest()->take(60)->get()->mapWithKeys(function ($st) {
                                                    $no = $st->nomor_surat_tugas ?: 'DRAFT';
                                                    $kep = $st->keperluan ?: $st->tempat_tugas ?: 'Penugasan';
                                                    return [$st->id => "No. {$no} — {$kep}"];
                                                });
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, \Illuminate\Database\Eloquent\Collection $records) {
                                                if (!$state) return;
                                                $st = \App\Models\SuratTugas::find($state);
                                                if ($st) {
                                                    if ($st->survey_id) $set('survey_id', $st->survey_id);
                                                    $set('tujuan', $st->keperluan ?: $st->tempat_tugas ?: 'Pelaksanaan Penugasan Lapangan');
                                                    $set('desa_pejabat', $st->tempat_tugas ?: 'Kabupaten Demak');

                                                    // Trigger penyusunan narasi AI otomatis berdasarkan ST baru
                                                    try {
                                                        $lpdAiService = app(LpdAiService::class);
                                                        $tgl = $get('tanggal_kunjungan') ?: now();
                                                        $gen = $lpdAiService->generateChronologicalFromActivities(
                                                            $records,
                                                            $st,
                                                            Auth::user(),
                                                            \Carbon\Carbon::parse($tgl)
                                                        );
                                                        $set('uraian_kegiatan', $gen['narasi']);
                                                        if (!empty($gen['nama_pejabat'])) $set('nama_pejabat', $gen['nama_pejabat']);
                                                        if (!empty($gen['desa_pejabat'])) $set('desa_pejabat', $gen['desa_pejabat']);
                                                    } catch (\Throwable $e) {}
                                                }
                                            }),
                                    ]),
                                ])
                                ->compact(),

                            Forms\Components\Section::make('Rincian Kunjungan & Laporan')
                                ->schema([
                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\DatePicker::make('tanggal_kunjungan')
                                            ->label('Tanggal Kunjungan')
                                            ->required()
                                            ->live()
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('tujuan')
                                            ->label('Tujuan / Keperluan Tugas')
                                            ->required()
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('desa_pejabat')
                                            ->label('Lokasi / Instansi Tujuan')
                                            ->required()
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('nama_pejabat')
                                            ->label('Nama Pejabat yang Ditemui')
                                            ->placeholder('Contoh: Bapak Iman Teguh Raharto, S.Si, M.Si (atau - jika umum)')
                                            ->columnSpan(2),
                                    ]),

                                    Forms\Components\RichEditor::make('uraian_kegiatan')
                                        ->label('Uraian Narasi Kronologis LPD (Per Jam)')
                                        ->required()
                                        ->helperText('Format standar BPS: Runtut per jam dari keberangkatan pagi, substansi & koordinasi teknis, ISHOMA, hingga kepulangan.')
                                        ->hintAction(
                                            Forms\Components\Actions\Action::make('regenerate_ai')
                                                ->label('✨ Susun Ulang Kronologis via AI')
                                                ->icon('heroicon-m-sparkles')
                                                ->color('primary')
                                                ->action(function (Forms\Set $set, Forms\Get $get, \Illuminate\Database\Eloquent\Collection $records) {
                                                    $stId = $get('surat_tugas_id');
                                                    $st = $stId ? \App\Models\SuratTugas::find($stId) : null;
                                                    if (!$st) {
                                                        Notification::make()->warning()->title('Pilih Surat Tugas Terlebih Dahulu')->send();
                                                        return;
                                                    }

                                                    $lpdAiService = app(LpdAiService::class);
                                                    $tgl = $get('tanggal_kunjungan') ?: now();
                                                    $actDate = \Carbon\Carbon::parse($tgl);
                                                    $user = Auth::user();

                                                    $gen = $lpdAiService->generateChronologicalFromActivities(
                                                        $records,
                                                        $st,
                                                        $user,
                                                        $actDate
                                                    );

                                                    $set('uraian_kegiatan', $gen['narasi']);
                                                    if (!empty($gen['nama_pejabat'])) $set('nama_pejabat', $gen['nama_pejabat']);
                                                    if (!empty($gen['desa_pejabat'])) $set('desa_pejabat', $gen['desa_pejabat']);

                                                    Notification::make()->success()->title('Narasi Kronologis Diperbarui! ✨')->send();
                                                })
                                        ),
                                ])
                                ->compact(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $user = Auth::user();
                            if (!$user) return;

                            $stId = $data['surat_tugas_id'] ?? null;
                            $st = $stId ? \App\Models\SuratTugas::find($stId) : null;
                            if (!$st) {
                                Notification::make()->danger()->title('Surat Tugas Wajib Dipilih')->send();
                                return;
                            }

                            $tglKunjungan = $data['tanggal_kunjungan'];
                            $actDate = \Carbon\Carbon::parse($tglKunjungan)->startOfDay();

                            // 1. Validasi Kuota LPD
                            if (!\App\Models\LaporanPerjalananDinas::canCreateLpd($st)) {
                                $max = \App\Models\LaporanPerjalananDinas::getMaxLpdCount($st);
                                $msg = $max === 1
                                    ? "Surat Tugas ini memiliki durasi 1 hari dan sudah memiliki 1 laporan LPD. Tidak dapat menambah LPD lagi."
                                    : "Surat Tugas ini memiliki durasi {$max} hari dan telah mencapai batas maksimal {$max} laporan LPD.";

                                Notification::make()->danger()->title('Batas Kuota LPD Tercapai')->body($msg)->send();
                                return;
                            }

                            // 2. Validasi Konflik LPD pada Tanggal yang Sama untuk Pegawai yang Sama
                            $duplicateLpd = \App\Models\LaporanPerjalananDinas::getDuplicateForUser($user->id, $tglKunjungan);
                            if ($duplicateLpd) {
                                Notification::make()
                                    ->danger()
                                    ->title('Bentrok Jadwal LPD')
                                    ->body("Anda sudah memiliki Laporan Perjalanan Dinas pada tanggal " . $actDate->translatedFormat('d F Y') . " (No. ST: " . ($duplicateLpd->nomor_surat_tugas ?: 'Draft') . "). Satu pegawai hanya dapat memiliki 1 LPD per tanggal.")
                                    ->send();
                                return;
                            }

                            // 3. Buat Laporan Perjalanan Dinas
                            $lpd = \App\Models\LaporanPerjalananDinas::create([
                                'surat_tugas_id' => $st->id,
                                'nomor_surat_tugas' => $st->nomor_surat_tugas ?? '',
                                'tujuan' => $data['tujuan'] ?? ($st->keperluan ?: $st->tempat_tugas ?: 'Pelaksanaan Penugasan Lapangan'),
                                'tanggal_kunjungan' => $tglKunjungan,
                                'uraian_kegiatan' => $data['uraian_kegiatan'],
                                'nama_pejabat' => $data['nama_pejabat'] ?? '-',
                                'desa_pejabat' => $data['desa_pejabat'] ?? ($st->tempat_tugas ?: '-'),
                            ]);

                            // 4. Salin seluruh foto lampiran dari semua baris yang dipilih ke LaporanFoto
                            $allAttachments = $records->flatMap->attachments->sortBy(function ($att) {
                                return $att->captured_at ? \Carbon\Carbon::parse($att->captured_at)->timestamp : ($att->created_at?->timestamp ?? 0);
                            });

                            $fotoOrder = 1;
                            foreach ($allAttachments as $att) {
                                \App\Models\LaporanFoto::create([
                                    'laporan_perjalanan_dinas_id' => $lpd->id,
                                    'file_path' => $att->file_path,
                                    'keterangan' => $att->caption ?: ('Dokumentasi Lapangan ' . $fotoOrder),
                                    'urutan' => $fotoOrder,
                                ]);
                                $fotoOrder++;
                            }

                            // 5. Update seluruh baris kegiatan yang dipilih: kaitkan LPD dan Surat Tugas
                            foreach ($records as $rec) {
                                $updateData = ['laporan_perjalanan_dinas_id' => $lpd->id];
                                if (empty($rec->surat_tugas_id)) {
                                    $updateData['surat_tugas_id'] = $st->id;
                                }
                                $rec->update($updateData);
                            }

                            $totalFoto = $fotoOrder - 1;
                            Notification::make()
                                ->success()
                                ->persistent()
                                ->title('LPD Berhasil Diterbitkan! 🎉')
                                ->body("Laporan Perjalanan Dinas telah dibuat dari {$records->count()} kegiatan dengan {$totalFoto} foto terlampir otomatis. Anda dapat langsung mengedit atau menambahkan berkas di menu LPD.")
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('buka_lpd')
                                        ->label('Buka & Edit LPD 🚀')
                                        ->url(route('filament.admin.resources.laporan-perjalanan-dinas.edit', ['record' => $lpd->id]), shouldOpenInNewTab: false),
                                ])
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('assign_to_shared_report')
                        ->label('📁 Tautkan ke Wadah KIPAPP')
                        ->icon('heroicon-m-folder-plus')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('shared_work_report_id')
                                ->label('Pilih Wadah KIPAPP Tujuan')
                                ->options(fn () => \App\Models\SharedWorkReport::where('user_id', Auth::id())->where('is_active', true)->latest()->pluck('title', 'id'))
                                ->required()
                                ->searchable()
                                ->placeholder('Pilih wadah berkas KIPAPP Anda...'),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $rep = \App\Models\SharedWorkReport::find($data['shared_work_report_id']);
                            if (!$rep) return;

                            foreach ($records as $rec) {
                                $rec->update(['shared_work_report_id' => $rep->id]);
                                $rec->sharedWorkReports()->syncWithoutDetaching([$rep->id]);
                            }

                            Notification::make()
                                ->success()
                                ->title('Kegiatan Berhasil Ditautkan!')
                                ->body("Sebanyak {$records->count()} kegiatan telah dimasukkan ke wadah \"{$rep->title}\".")
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('buka_berkas')
                                        ->label('Buka Berkas Publik 🌐')
                                        ->url(route('shared-work-report.show', ['uuid' => $rep->uuid]), shouldOpenInNewTab: true),
                                ])
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('create_shared_report')
                        ->label('🔗 Buat Berkas Publik Multi-Hari (Link E-Kinerja)')
                        ->icon('heroicon-m-link')
                        ->color('success')
                        ->modalHeading('Buat Berkas Publik Bukti Dukung Multi-Hari')
                        ->modalDescription('Gabungkan kegiatan-kegiatan terpilih (baik rentang tanggal berurutan maupun melompat) menjadi 1 berkas resmi dengan tautan publik yang dapat dilampirkan ke E-Kinerja BPS.')
                        ->modalSubmitActionLabel('Buat Berkas & Tautan Publik 🚀')
                        ->form([
                            Forms\Components\TextInput::make('title')
                                ->label('Judul Berkas Bukti Dukung')
                                ->placeholder('Contoh: Pelaksanaan Ground Check SBR Kecamatan Wedung')
                                ->required()
                                ->default(function (\Illuminate\Database\Eloquent\Collection $records) {
                                    $first = $records->first();
                                    if ($first?->suratTugas?->keperluan) {
                                        return $first->suratTugas->keperluan;
                                    }
                                    return 'Laporan Pelaksanaan Tugas Kegiatan';
                                }),

                            Forms\Components\Textarea::make('description')
                                ->label('Keterangan / Catatan Tambahan (Opsional)')
                                ->placeholder('Tuliskan ringkasan atau lingkup pekerjaan multi-hari ini...')
                                ->rows(2),

                            Forms\Components\Placeholder::make('summary_info')
                                ->label('Ringkasan Kegiatan Terpilih')
                                ->content(function (\Illuminate\Database\Eloquent\Collection $records) {
                                    $count = $records->count();
                                    $dates = $records->sortBy('activity_date')->pluck('activity_date')
                                        ->map(fn($d) => \Carbon\Carbon::parse($d)->translatedFormat('d M Y'))
                                        ->unique()
                                        ->values()
                                        ->join(', ');
                                    $totalPhotos = $records->sum(fn($r) => $r->attachments->count());
                                    return "{$count} catatan kegiatan ({$totalPhotos} foto bukti) pada tanggal: {$dates}";
                                }),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $user = Auth::user();
                            if (!$user) return;

                            $sortedIds = $records->sortBy('activity_date')->pluck('id')->values()->toArray();
                            $stId = $records->firstWhere('surat_tugas_id', '!=', null)?->surat_tugas_id;

                            $report = \App\Models\SharedWorkReport::create([
                                'user_id' => $user->id,
                                'title' => $data['title'],
                                'description' => $data['description'] ?? null,
                                'activity_ids' => $sortedIds,
                                'surat_tugas_id' => $stId,
                                'is_active' => true,
                            ]);

                            // Sinkronkan ke tabel pivot many-to-many dan relasi kegiatan
                            $report->dailyActivities()->syncWithoutDetaching($sortedIds);
                            foreach ($records as $rec) {
                                $rec->update(['shared_work_report_id' => $report->id]);
                                $rec->sharedWorkReports()->syncWithoutDetaching([$report->id]);
                            }

                            $url = route('shared-work-report.show', ['uuid' => $report->uuid]);

                            Notification::make()
                                ->success()
                                ->persistent()
                                ->title('🎉 Berkas Publik Berhasil Dibuat!')
                                ->body("Tautan resmi untuk dilampirkan ke E-Kinerja:\n\n{$url}")
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('open_public_link')
                                        ->label('🌐 Buka Halaman Publik')
                                        ->url($url, shouldOpenInNewTab: true),
                                ])
                                ->send();
                        }),

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
