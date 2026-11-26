<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KopSuratResource\Pages;
use App\Models\KopSurat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class KopSuratResource extends Resource
{
    protected static ?string $model = KopSurat::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Manajemen Dokumen';
    protected static ?string $navigationLabel = 'Kop Surat';
    protected static ?string $modelLabel = 'Kop Surat';
    protected static ?string $pluralModelLabel = 'Kop Surat';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Kop Surat')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Kop Surat')
                            ->placeholder('Contoh: Kop SE2026 / Kop BerAKHLAK')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('instansi_teks')
                            ->label('Teks Alamat & Kontak Kop')
                            ->rows(3)
                            ->default("Jl. Sultan Hadiwijaya No. 23 Demak Telp/Fax : (0291) 685445\nHomepage: http://demakkab.bps.go.id, E-mail : bps3321@bps.go.id")
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('logo_kiri_path')
                            ->label('Logo Kiri (Logo BPS)')
                            ->image()
                            ->disk('public')
                            ->directory('kop-surat')
                            ->default('images/kop/logo_bps_header.png')
                            ->helperText('Kosongkan untuk memakai logo BPS default.'),

                        Forms\Components\FileUpload::make('logo_kanan_path')
                            ->label('Logo Kanan (Opsional: BerAKHLAK / SE2026 / Lainnya)')
                            ->image()
                            ->disk('public')
                            ->directory('kop-surat')
                            ->helperText('Upload gambar PNG/JPG untuk logo di pojok kanan atas kop surat.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif (Tampilkan di pilihan generator surat)')
                            ->default(true),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Jadikan Kop Default')
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Kop')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('instansi_teks')
                    ->label('Alamat / Kontak')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif'),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
