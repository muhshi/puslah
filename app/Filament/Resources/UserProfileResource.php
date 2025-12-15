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
                    ->label('Nama User')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\FileUpload::make('avatar_path')
                    ->label('Foto Profil')
                    ->image()
                    ->directory('avatars')
                    ->disk('public')
                    ->visibility('public')
                    ->imageEditor()
                    ->maxSize(2048)
                    ->helperText('JPG/PNG, maks 2MB'),
                Forms\Components\TextInput::make('full_name')
                    ->label('Nama Lengkap')
                    ->required(),
                Forms\Components\TextInput::make('nickname')
                    ->label('Nama Panggilan'),
                Forms\Components\TextInput::make('birth_place')
                    ->label('Tempat Lahir'),
                Forms\Components\DatePicker::make('birth_date')
                    ->label('Tanggal Lahir'),
                Forms\Components\Select::make('gender')
                    ->label('Jenis Kelamin')
                    ->options([
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                    ]),
                Forms\Components\Textarea::make('address')
                    ->label('Alamat')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('phone')
                    ->label('No. HP / WA')
                    ->tel(),
                Forms\Components\TextInput::make('employment_status')
                    ->label('Status Kepegawaian')
                    ->required(),
                Forms\Components\TextInput::make('jabatan')
                    ->label('Jabatan')
                    ->maxLength(100),
                Forms\Components\TextInput::make('nip')
                    ->label('NIP')
                    ->maxLength(50)
                    ->helperText('Khusus Pegawai BPS'),
                Forms\Components\TextInput::make('pangkat_golongan')
                    ->label('Pangkat/Golongan')
                    ->maxLength(100)
                    ->helperText('Contoh: Penata Muda Tingkat 1 / IIIb'),
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

                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('pangkat_golongan')
                    ->label('Pangkat')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
