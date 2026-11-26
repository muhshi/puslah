<?php

namespace App\Filament\Resources;

use App\Exports\PelatihanSpjExport;
use App\Filament\Resources\PelatihanResource\Pages\CreatePelatihan;
use App\Filament\Resources\PelatihanResource\Pages\EditPelatihan;
use App\Filament\Resources\PelatihanResource\Pages\ListPelatihans;
use App\Filament\Resources\SurveyResource\RelationManagers\ParticipantsRelationManager;
use App\Models\Survey;
use App\Services\PelatihanUndanganService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class PelatihanResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static ?string $slug = 'pelatihans';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Kegiatan';

    protected static ?string $navigationLabel = 'Pelatihan';

    protected static ?string $modelLabel = 'Pelatihan';

    protected static ?string $pluralModelLabel = 'Pelatihan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pelatihan')
                    ->description('Informasi dasar kegiatan pelatihan dan tempat pelaksanaan')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Pelatihan')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Pelatihan Petugas Lapangan Sensus Pertanian 2026')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('dasar_surat')
                            ->label('Dasar Surat')
                            ->placeholder('Contoh: DIPA BPS Kabupaten Demak Tahun Anggaran 2026 Nomor SP DIPA-054.01.2.433210/2026...')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),
                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('Tanggal Selesai')
                            ->required(),
                        TextInput::make('lokasi')
                            ->label('Tempat Pelatihan / Hotel')
                            ->placeholder('Contoh: Hotel Grasia Semarang')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('kota_kegiatan')
                            ->label('Kota Pelaksanaan')
                            ->default('Semarang')
                            ->required()
                            ->maxLength(100),
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->required(),
                        Toggle::make('is_multiple')
                            ->label('Multiple Surat Tugas')
                            ->helperText('Jika diaktifkan, satu pegawai bisa dibuatkan beberapa surat tugas pada pelatihan ini')
                            ->default(false)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Surat Undangan')
                    ->description('Nomor, tanggal, dan rujukan surat undangan resmi (isi surat dan jadwal dapat disesuaikan kembali pada file Word hasil unduhan)')
                    ->schema([
                        TextInput::make('nomor_surat_undangan')
                            ->label('Nomor Surat Undangan')
                            ->placeholder('Contoh: B-0123/33210/VS.100/2026')
                            ->maxLength(100),
                        DatePicker::make('tanggal_surat_undangan')
                            ->label('Tanggal Surat Undangan'),
                        Textarea::make('rujukan_surat')
                            ->label('Rujukan Surat / Konsiderans')
                            ->placeholder('Contoh: Surat Kepala BPS Provinsi Jawa Tengah Nomor B-1234/33000/VS.100/2026 perihal Pemanggilan Peserta Pelatihan...')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Anggaran DIPA & Translok')
                    ->description('Pembebanan anggaran DIPA dan tarif standar transportasi serta uang harian')
                    ->schema([
                        TextInput::make('program')
                            ->label('Program')
                            ->default('PROGRAM PENYEDIAAN DAN PELAYANAN INFORMASI STATISTIK (054.01.GG)')
                            ->columnSpanFull(),
                        TextInput::make('kegiatan_dipa')
                            ->label('Kegiatan DIPA')
                            ->default('PENYEDIAAN DAN PENGEMBANGAN STATISTIK HARGA (2903)'),
                        TextInput::make('klasifikasi_ro')
                            ->label('Klasifikasi RO (KRO)')
                            ->default('DATA DAN INFORMASI PUBLIK (2903.BMA)'),
                        TextInput::make('rincian_output')
                            ->label('Rincian Output (RO)')
                            ->default('PUBLIKASI/LAPORAN SPKRT (2903.BMA.007)'),
                        TextInput::make('komponen')
                            ->label('Komponen')
                            ->default('PERSIAPAN (2903.BMA.009.051A)'),
                        TextInput::make('mak')
                            ->label('Mata Anggaran Keluaran (MAK)')
                            ->default('BELANJA PERJALANAN DINAS DALAM KOTA (524113)')
                            ->required(),
                        TextInput::make('biaya_transport')
                            ->label('Biaya Transportasi Standar (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(100000)
                            ->required(),
                        TextInput::make('biaya_uang_harian')
                            ->label('Uang Harian Standar (Rp/hari)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(130000)
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Perlengkapan ATK (Opsional)')
                    ->description('Pengaturan tanda terima perlengkapan peserta. Data PPK, Bendahara, dan Penanggung Jawab (Kasubag Umum) otomatis diambil dari menu Pengaturan Sistem.')
                    ->schema([
                        Toggle::make('include_atk')
                            ->label('Sertakan Tanda Terima ATK pada Paket SPJ')
                            ->default(false)
                            ->live(),
                        TagsInput::make('atk_items')
                            ->label('Daftar Item ATK / Perlengkapan')
                            ->default(['Totebag', 'Pulpen', 'Pensil 2B', 'Penghapus 2 buah', 'Rautan 1 buah', 'Blocknote'])
                            ->placeholder('Ketik nama barang lalu tekan Enter')
                            ->visible(fn ($get) => (bool) $get('include_atk'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nama Pelatihan'),
                TextColumn::make('lokasi')
                    ->searchable()
                    ->label('Tempat / Lokasi'),
                TextColumn::make('kota_kegiatan')
                    ->label('Kota'),
                TextColumn::make('start_date')
                    ->date('d M Y')
                    ->sortable()
                    ->label('Mulai'),
                TextColumn::make('end_date')
                    ->date('d M Y')
                    ->sortable()
                    ->label('Selesai'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Aktif'),
                TextColumn::make('participants_count')
                    ->counts('participants')
                    ->label('Peserta'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('downloadUndangan')
                        ->label('Download Undangan (.docx)')
                        ->icon('heroicon-o-document-text')
                        ->action(fn (Survey $record) => app(PelatihanUndanganService::class)->downloadUndanganWord($record)),

                    Action::make('downloadSpj')
                        ->label('Download Paket SPJ (.xlsx)')
                        ->icon('heroicon-o-table-cells')
                        ->action(fn (Survey $record) => Excel::download(
                            new PelatihanSpjExport($record),
                            'Paket_SPJ_'.Str::slug($record->name ?: 'Pelatihan').'.xlsx'
                        )),

                    Action::make('cetakDaftarHadir')
                        ->label('Cetak Daftar Hadir (PDF)')
                        ->icon('heroicon-o-printer')
                        ->action(fn (Survey $record) => response()->streamDownload(
                            fn () => print (app(PelatihanUndanganService::class)->renderDaftarHadirPdf($record)->output()),
                            'Daftar_Hadir_'.Str::slug($record->name ?: 'Pelatihan').'.pdf',
                            ['Content-Type' => 'application/pdf']
                        )),

                    Action::make('cetakPernyataanRiil')
                        ->label('Cetak Pernyataan Riil (PDF)')
                        ->icon('heroicon-o-document-check')
                        ->action(fn (Survey $record) => response()->streamDownload(
                            fn () => print (app(PelatihanUndanganService::class)->renderPernyataanRiilPdf($record)->output()),
                            'Pernyataan_Riil_'.Str::slug($record->name ?: 'Pelatihan').'.pdf',
                            ['Content-Type' => 'application/pdf']
                        )),

                    Action::make('cetakPernyataanTidakKendaraan')
                        ->label('Cetak Surat Pernyataan Tdk Kendaraan (PDF)')
                        ->icon('heroicon-o-truck')
                        ->action(fn (Survey $record) => response()->streamDownload(
                            fn () => print (app(PelatihanUndanganService::class)->renderPernyataanTidakKendaraanPdf($record)->output()),
                            'Surat_Pernyataan_Tidak_Kendaraan_'.Str::slug($record->name ?: 'Pelatihan').'.pdf',
                            ['Content-Type' => 'application/pdf']
                        )),
                ])
                    ->label('Dokumen Pelatihan')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('primary'),

                Action::make('buatSuratTugas')
                    ->label('Buat Surat Tugas')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->visible(fn (): bool => SuratTugasResource::canCreate())
                    ->url(fn (Survey $record): string => SuratTugasResource::getUrl('create-bulk', ['survey_id' => $record->id])),

                Action::make('copy')
                    ->label('Copy')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->modalHeading('Copy Pelatihan')
                    ->modalDescription('Ubah data pelatihan sebelum menyimpan. Petugas yang sudah di-assign dapat ikut ter-copy.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Pelatihan')
                            ->required()
                            ->maxLength(255)
                            ->default(fn (Survey $record) => 'Copy of '.$record->name),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->default(fn (Survey $record) => $record->description)
                            ->columnSpanFull(),
                        Textarea::make('dasar_surat')
                            ->label('Dasar Surat')
                            ->required()
                            ->default(fn (Survey $record) => $record->dasar_surat)
                            ->columnSpanFull(),
                        DatePicker::make('start_date')
                            ->label('Mulai')
                            ->default(fn (Survey $record) => $record->start_date),
                        DatePicker::make('end_date')
                            ->label('Selesai')
                            ->default(fn (Survey $record) => $record->end_date),
                        TextInput::make('lokasi')
                            ->label('Tempat / Lokasi')
                            ->default(fn (Survey $record) => $record->lokasi),
                        TextInput::make('kota_kegiatan')
                            ->label('Kota')
                            ->default(fn (Survey $record) => $record->kota_kegiatan),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(fn (Survey $record) => $record->is_active),
                        Toggle::make('is_multiple')
                            ->label('Multiple Surat Tugas')
                            ->default(fn (Survey $record) => $record->is_multiple),
                        Toggle::make('copy_participants')
                            ->label('Copy Petugas/Peserta')
                            ->helperText('Centang untuk meng-copy semua petugas yang sudah di-assign')
                            ->default(true),
                    ])
                    ->action(function (Survey $record, array $data): void {
                        $copyParticipants = $data['copy_participants'] ?? false;
                        unset($data['copy_participants']);

                        $data['is_pelatihan'] = true;
                        $data['program'] = $record->program;
                        $data['kegiatan_dipa'] = $record->kegiatan_dipa;
                        $data['klasifikasi_ro'] = $record->klasifikasi_ro;
                        $data['rincian_output'] = $record->rincian_output;
                        $data['komponen'] = $record->komponen;
                        $data['mak'] = $record->mak;
                        $data['biaya_transport'] = $record->biaya_transport;
                        $data['biaya_uang_harian'] = $record->biaya_uang_harian;
                        $data['include_atk'] = $record->include_atk;
                        $data['atk_items'] = $record->atk_items;

                        $newSurvey = Survey::create($data);

                        if ($copyParticipants) {
                            $participants = $record->surveyUsers()->get();
                            foreach ($participants as $participant) {
                                $newSurvey->surveyUsers()->create([
                                    'user_id' => $participant->user_id,
                                    'status' => $participant->status,
                                    'registered_at' => now(),
                                    'notes' => $participant->notes,
                                    'role_pelatihan' => $participant->role_pelatihan,
                                    'kelas' => $participant->kelas,
                                    'asal' => $participant->asal,
                                    'tujuan' => $participant->tujuan,
                                    'transport_override' => $participant->transport_override,
                                    'uang_harian_override' => $participant->uang_harian_override,
                                ]);
                            }
                        }

                        Notification::make()
                            ->title('Pelatihan berhasil di-copy!')
                            ->body($copyParticipants
                                ? 'Pelatihan baru dibuat dengan '.$participants->count().' peserta.'
                                : 'Pelatihan baru dibuat tanpa peserta.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('is_active', 'desc')->orderBy('created_at', 'desc'))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_pelatihan', true)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ParticipantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPelatihans::route('/'),
            'create' => CreatePelatihan::route('/create'),
            'edit' => EditPelatihan::route('/{record}/edit'),
        ];
    }
}
