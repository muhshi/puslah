<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaporanLemburResource\Pages;
use App\Filament\Resources\LaporanLemburResource\RelationManagers;
use App\Models\LaporanLembur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Services\LemburExportService;

class LaporanLemburResource extends Resource
{
    protected static ?string $model = LaporanLembur::class;

    protected static ?string $navigationGroup = 'Manajemen Dokumen';
    protected static ?string $navigationLabel = 'Laporan Lembur';
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pegawai & Waktu')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Pegawai')
                            ->relationship('user', 'name')
                            ->default(fn() => auth()->id())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(fn() => !auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag']))
                            ->dehydrated(),
                        Forms\Components\DatePicker::make('waktu')
                            ->label('Hari/Tanggal')
                            ->required()
                            ->default(now()),
                        Forms\Components\TimePicker::make('mulai')
                            ->label('Waktu Mulai')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('H:i')
                            ->required()
                            ->default(now()),
                        Forms\Components\TimePicker::make('selesai')
                            ->label('Waktu Selesai')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('H:i')
                            ->required()
                            ->default(now()->addHours(2)),
                    ])->columns(2),

                Forms\Components\Section::make('Uraian Pekerjaan')
                    ->schema([
                        Forms\Components\RichEditor::make('pekerjaan')
                            ->label('Uraian Pekerjaan / Output')
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
                            ->helperText('Gunakan bullets, numbering (1, 2, ...), atau format teks tebal/miring.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Dokumentasi Foto')
                    ->description('Wajib melampirkan minimal 2 foto.')
                    ->schema([
                        Forms\Components\FileUpload::make('foto_1')
                            ->label('Foto 1')
                            ->image()
                            ->directory('lembur_photos')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable()
                            ->hintAction(
                                Forms\Components\Actions\Action::make('download_foto_1')
                                    ->label('Download Foto')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('success')
                                    ->visible(fn ($state) => !empty($state))
                                    ->url(function ($state) {
                                        $path = is_array($state) ? (array_values($state)[0] ?? null) : $state;
                                        return $path ? url('/download/file?path=' . urlencode($path)) : null;
                                    }, shouldOpenInNewTab: false)
                            )
                            ->required(),
                        Forms\Components\FileUpload::make('foto_2')
                            ->label('Foto 2')
                            ->image()
                            ->directory('lembur_photos')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable()
                            ->hintAction(
                                Forms\Components\Actions\Action::make('download_foto_2')
                                    ->label('Download Foto')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('success')
                                    ->visible(fn ($state) => !empty($state))
                                    ->url(function ($state) {
                                        $path = is_array($state) ? (array_values($state)[0] ?? null) : $state;
                                        return $path ? url('/download/file?path=' . urlencode($path)) : null;
                                    }, shouldOpenInNewTab: false)
                            )
                            ->required(),
                        Forms\Components\FileUpload::make('foto_3')
                            ->label('Foto 3 (Opsional)')
                            ->image()
                            ->directory('lembur_photos')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable()
                            ->hintAction(
                                Forms\Components\Actions\Action::make('download_foto_3')
                                    ->label('Download Foto')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('success')
                                    ->visible(fn ($state) => !empty($state))
                                    ->url(function ($state) {
                                        $path = is_array($state) ? (array_values($state)[0] ?? null) : $state;
                                        return $path ? url('/download/file?path=' . urlencode($path)) : null;
                                    }, shouldOpenInNewTab: false)
                            ),
                        Forms\Components\FileUpload::make('foto_4')
                            ->label('Foto 4 (Opsional)')
                            ->image()
                            ->directory('lembur_photos')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable()
                            ->hintAction(
                                Forms\Components\Actions\Action::make('download_foto_4')
                                    ->label('Download Foto')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('success')
                                    ->visible(fn ($state) => !empty($state))
                                    ->url(function ($state) {
                                        $path = is_array($state) ? (array_values($state)[0] ?? null) : $state;
                                        return $path ? url('/download/file?path=' . urlencode($path)) : null;
                                    }, shouldOpenInNewTab: false)
                            ),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pegawai')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('waktu')
                    ->label('Hari/Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('mulai')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('selesai')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('fotos_count')
                    ->label('Foto')
                    ->badge()
                    ->getStateUsing(function (LaporanLembur $record) {
                        $count = 0;
                        foreach (['foto_1', 'foto_2', 'foto_3', 'foto_4'] as $f) {
                            if (!empty($record->{$f})) $count++;
                        }
                        return $count;
                    })
                    ->color(fn(int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->action(
                        Tables\Actions\Action::make('columnLemburPhotos')
                            ->modalHeading(fn(LaporanLembur $record) => 'Dokumentasi Foto Lembur - ' . ($record->user?->name ?? 'Pegawai'))
                            ->modalWidth(\Filament\Support\Enums\MaxWidth::FourExtraLarge)
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Tutup')
                            ->modalContent(fn(LaporanLembur $record) => view('filament.resources.laporan-lembur.photos-modal', [
                                'record' => $record,
                            ]))
                    ),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag'])),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (LaporanLembur $record) {
                        return $record->status === 'pending' && auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag']);
                    })
                    ->action(fn(LaporanLembur $record) => $record->update(['status' => 'approved'])),
                Tables\Actions\Action::make('word')
                    ->label('Word')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(fn(LaporanLembur $record) => app(LemburExportService::class)->downloadWord($record)),
                Tables\Actions\Action::make('photos')
                    ->label('Foto')
                    ->icon('heroicon-o-camera')
                    ->color('warning')
                    ->visible(fn (LaporanLembur $record) => !empty($record->foto_1) || !empty($record->foto_2) || !empty($record->foto_3) || !empty($record->foto_4))
                    ->modalHeading(fn (LaporanLembur $record) => 'Dokumentasi Foto Lembur - ' . ($record->user?->name ?? 'Pegawai'))
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::FourExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (LaporanLembur $record) => view('filament.resources.laporan-lembur.photos-modal', [
                        'record' => $record,
                    ])),
                Tables\Actions\Action::make('activities')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->url(fn ($record) => LaporanLemburResource::getUrl('activities', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('downloadBulk')
                        ->label('Download Semua (ZIP)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(fn(\Illuminate\Database\Eloquent\Collection $records) => app(LemburExportService::class)->downloadBulkZip($records)),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function processWordDocument(LaporanLembur $record, $isBulk = false): ?array
    {
        return app(LemburExportService::class)->processWordDocument($record, $isBulk);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Admin/Atasan can see all, regular user only theirs
        if ($user && !$user->hasRole(['super_admin', 'Kasubag', 'Kepala'])) {
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
            'index' => Pages\ListLaporanLemburs::route('/'),
            'create' => Pages\CreateLaporanLembur::route('/create'),
            'edit' => Pages\EditLaporanLembur::route('/{record}/edit'),
            'activities' => Pages\ListLaporanLemburActivities::route('/{record}/activities'),
        ];
    }
}
