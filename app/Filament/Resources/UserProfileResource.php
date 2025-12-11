<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserProfileResource\Pages;
use App\Filament\Resources\UserProfileResource\RelationManagers;
use App\Models\UserProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserProfileResource extends Resource
{
    protected static ?string $model = UserProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Pengaturan Akun';
    protected static ?string $navigationLabel = 'Profil Pengguna';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\FileUpload::make('avatar_path')
                    ->label('Foto')
                    ->image()
                    ->directory('avatars')        // file akan ke storage/app/public/avatars
                    ->disk('public')              // pake disk public
                    ->visibility('public')        // biar bisa diakses via /storage/...
                    ->imageEditor()               // optional: crop/rotate
                    ->imagePreviewHeight('200')   // optional
                    ->maxSize(2048)               // 2 MB
                    ->helperText('JPG/PNG, maks 2MB'),
                Forms\Components\TextInput::make('full_name')
                    ->required(),
                Forms\Components\TextInput::make('nickname'),
                Forms\Components\TextInput::make('birth_place'),
                Forms\Components\DatePicker::make('birth_date'),
                Forms\Components\TextInput::make('gender'),
                Forms\Components\Textarea::make('address')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('phone')
                    ->tel(),
                Forms\Components\TextInput::make('employment_status')
                    ->required(),
                Forms\Components\TextInput::make('jabatan')
                    ->label('Jabatan')
                    ->maxLength(100),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('Foto')
                    ->disk('public')       // pastikan sama dengan FileUpload
                    ->height(48)
                    ->width(48)
                    ->circular(),

                TextColumn::make('full_name')
                    ->label('Nama Lengkap')
                    ->searchable(),

                TextColumn::make('nickname')
                    ->label('Panggilan')
                    ->searchable(),

                TextColumn::make('gender')
                    ->label('JK')
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                        default => '',
                    })
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('HP/WA')
                    ->searchable(),

                TextColumn::make('employment_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(?string $state) => $state === 'aktif' ? 'success' : 'gray'),

                TextColumn::make('jabatan')
                    ->label('Jabatan')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUserProfiles::route('/'),
            'create' => Pages\CreateUserProfile::route('/create'),
            'edit' => Pages\EditUserProfile::route('/{record}/edit'),
        ];
    }
}
