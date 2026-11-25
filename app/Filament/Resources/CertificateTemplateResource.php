<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use App\Filament\Resources\CertificateTemplateResource\Pages\ListCertificateTemplates;
use App\Filament\Resources\CertificateTemplateResource\Pages\CreateCertificateTemplate;
use App\Filament\Resources\CertificateTemplateResource\Pages\EditCertificateTemplate;
use App\Filament\Resources\CertificateTemplateResource\Pages;
use App\Filament\Resources\CertificateTemplateResource\RelationManagers;
use App\Models\CertificateTemplate;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Settings\SystemSettings;

class CertificateTemplateResource extends Resource
{
    protected static ?string $model = CertificateTemplate::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';
    protected static string | \UnitEnum | null $navigationGroup = 'Pengaturan';
    protected static ?string $navigationLabel = 'Template Sertifikat';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info Template')->schema([
                TextInput::make('name')->required()->maxLength(150),
                TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(80),
                Toggle::make('active')->label('Aktif')->default(true),
            ])->columns(3),

            Section::make('Canvas')->schema([
                Select::make('paper')->options([
                    'a4' => 'A4',
                    'letter' => 'Letter',
                ])->default('a4')->required(),
                Select::make('orientation')->options([
                    'portrait' => 'Portrait',
                    'landscape' => 'Landscape',
                ])->default('landscape')->required(),
                FileUpload::make('background_path')
                    ->label('Background (PNG/JPG)')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png'])
                    ->imageResizeMode('cover') // bisa "contain" juga
                    ->imageResizeTargetWidth(2200) // lebar max 2200 px (cukup untuk A4)
                    ->imageResizeTargetHeight(null) // biar proporsional
                    ->directory('cert_templates')
                    ->disk('public')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->required()
                    ->imageEditor()
                    ->downloadable()
                    ->openable(),
                Fieldset::make('Margin (px)')->schema([
                    TextInput::make('margin_top')->numeric()->default(40)->minValue(0),
                    TextInput::make('margin_right')->numeric()->default(40)->minValue(0),
                    TextInput::make('margin_bottom')->numeric()->default(40)->minValue(0),
                    TextInput::make('margin_left')->numeric()->default(40)->minValue(0),
                ])->columns(4),
            ])->columns(2),

            Section::make('QR Verifikasi')->schema([
                TextInput::make('qr_left')->numeric()->default(60)->suffix('px'),
                TextInput::make('qr_top')->numeric()->default(60)->suffix('px'),
                TextInput::make('qr_size')->numeric()->default(220)->suffix('px'),
            ])->columns(3),

            Section::make('Tanda Tangan')->schema([
                TextInput::make('city_label')
                    ->label('Kota/Label Lokasi')
                    ->placeholder('Demak,')
                    ->default(fn() => app(SystemSettings::class)->cert_city . ', '), // Auto-fill with comma
                TextInput::make('signer_name')
                    ->label('Nama Pejabat')
                    ->default(fn() => app(SystemSettings::class)->cert_signer_name),
                TextInput::make('signer_title')
                    ->label('Jabatan')
                    ->default(fn() => app(SystemSettings::class)->cert_signer_title),
                FileUpload::make('signer_image_path')
                    ->label('Gambar TTD/Cap (PNG transparan disarankan)')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg'])
                    ->directory('cert_templates/signs')
                    ->disk('public')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->downloadable()
                    ->openable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('background_path')->disk('public')->label('Background')->square(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('orientation')->badge()->color('primary'),
                IconColumn::make('active')->boolean()->label('Aktif'),
                TextColumn::make('updated_at')->since()->label('Diubah'),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCertificateTemplates::route('/'),
            'create' => CreateCertificateTemplate::route('/create'),
            'edit' => EditCertificateTemplate::route('/{record}/edit'),
        ];
    }
}
