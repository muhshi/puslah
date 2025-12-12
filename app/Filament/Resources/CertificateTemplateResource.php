<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CertificateTemplateResource\Pages;
use App\Filament\Resources\CertificateTemplateResource\RelationManagers;
use App\Models\CertificateTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Settings\SystemSettings;

class CertificateTemplateResource extends Resource
{
    protected static ?string $model = CertificateTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?string $navigationLabel = 'Template Sertifikat';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Info Template')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(150),
                Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(80),
                Forms\Components\Toggle::make('active')->label('Aktif')->default(true),
            ])->columns(3),

            Forms\Components\Section::make('Canvas')->schema([
                Forms\Components\Select::make('paper')->options([
                    'a4' => 'A4',
                    'letter' => 'Letter',
                ])->default('a4')->required(),
                Forms\Components\Select::make('orientation')->options([
                    'portrait' => 'Portrait',
                    'landscape' => 'Landscape',
                ])->default('landscape')->required(),
                Forms\Components\FileUpload::make('background_path')
                    ->label('Background (PNG/JPG)')
                    ->image()
                    ->optimize('webp') // simpan sebagai webp terkompres
                    ->imageResizeMode('cover') // bisa "contain" juga
                    ->imageResizeTargetWidth(2200) // lebar max 2200 px (cukup untuk A4)
                    ->imageResizeTargetHeight(null) // biar proporsional
                    ->directory('cert_templates')
                    ->disk('public')
                    ->preserveFilenames()
                    ->required()
                    ->imageEditor(),
                Forms\Components\Fieldset::make('Margin (px)')->schema([
                    Forms\Components\TextInput::make('margin_top')->numeric()->default(40)->minValue(0),
                    Forms\Components\TextInput::make('margin_right')->numeric()->default(40)->minValue(0),
                    Forms\Components\TextInput::make('margin_bottom')->numeric()->default(40)->minValue(0),
                    Forms\Components\TextInput::make('margin_left')->numeric()->default(40)->minValue(0),
                ])->columns(4),
            ])->columns(2),

            Forms\Components\Section::make('QR Verifikasi')->schema([
                Forms\Components\TextInput::make('qr_left')->numeric()->default(60)->suffix('px'),
                Forms\Components\TextInput::make('qr_top')->numeric()->default(60)->suffix('px'),
                Forms\Components\TextInput::make('qr_size')->numeric()->default(220)->suffix('px'),
            ])->columns(3),

            Forms\Components\Section::make('Tanda Tangan')->schema([
                Forms\Components\TextInput::make('city_label')
                    ->label('Kota/Label Lokasi')
                    ->placeholder('Demak,')
                    ->default(fn() => app(SystemSettings::class)->cert_city . ', '), // Auto-fill with comma
                Forms\Components\TextInput::make('signer_name')
                    ->label('Nama Pejabat')
                    ->default(fn() => app(SystemSettings::class)->cert_signer_name),
                Forms\Components\TextInput::make('signer_title')
                    ->label('Jabatan')
                    ->default(fn() => app(SystemSettings::class)->cert_signer_title),
                Forms\Components\FileUpload::make('signer_image_path')
                    ->label('Gambar TTD/Cap (opsional)')
                    ->image()
                    ->optimize('webp')
                    ->directory('cert_templates/signs')->disk('public')->preserveFilenames(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('background_path')->disk('public')->label('Background')->square(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('orientation')->colors(['primary']),
                Tables\Columns\IconColumn::make('active')->boolean()->label('Aktif'),
                Tables\Columns\TextColumn::make('updated_at')->since()->label('Diubah'),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCertificateTemplates::route('/'),
            'create' => Pages\CreateCertificateTemplate::route('/create'),
            'edit' => Pages\EditCertificateTemplate::route('/{record}/edit'),
        ];
    }
}
