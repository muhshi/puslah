<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KopSuratResource\Pages;
use App\Models\KopSurat;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class KopSuratResource extends Resource
{
    protected static ?string $model = KopSurat::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Dokumen';

    protected static ?string $navigationLabel = 'Kop Surat';

    protected static ?string $modelLabel = 'Kop Surat';

    protected static ?string $pluralModelLabel = 'Kop Surat';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Kop Surat (.docx)')
                    ->description('Kop surat menggunakan file Word (.docx) yang sudah memiliki Header & Borderline resmi. Kop tidak dapat diedit teksnya melalui aplikasi; untuk mengubah kop, silakan upload ulang file .docx baru.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Kop Surat')
                            ->placeholder('Contoh: Kop SE2026 / Kop BerAKHLAK')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        FileUpload::make('template_docx_path')
                            ->label('File Template Word Kop Surat (.docx)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->disk('public')
                            ->directory('kop-surat')
                            ->preserveFilenames()
                            ->required()
                            ->helperText('Upload file .docx yang bagian Header-nya sudah berisi logo BPS + logo pendamping (SE2026 / BerAKHLAK / lainnya) beserta garis pembatas.')
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Aktif (Tampilkan di pilihan generator surat)')
                            ->default(true),

                        Toggle::make('is_default')
                            ->label('Jadikan Kop Default')
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kop')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('template_docx_path')
                    ->label('File Template (.docx)')
                    ->formatStateUsing(fn (?string $state) => $state ? basename($state) : '-')
                    ->badge()
                    ->color('info'),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('download_template')
                    ->label('Unduh DOCX')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (KopSurat $record) => ! empty($record->template_docx_path) && Storage::disk('public')->exists($record->template_docx_path))
                    ->action(fn (KopSurat $record) => Storage::disk('public')->download($record->template_docx_path)),
                EditAction::make()
                    ->label('Upload Ulang / Edit'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListKopSurats::route('/'),
            'create' => Pages\CreateKopSurat::route('/create'),
            'edit'   => Pages\EditKopSurat::route('/{record}/edit'),
        ];
    }
}
