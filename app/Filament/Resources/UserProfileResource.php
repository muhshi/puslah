<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserProfileResource\Pages\ListUserProfiles;
use App\Filament\Resources\UserProfileResource\Pages\CreateUserProfile;
use App\Filament\Resources\UserProfileResource\Pages\EditUserProfile;
use App\Filament\Resources\UserProfileResource\Pages;
use App\Filament\Resources\UserProfileResource\RelationManagers;
use App\Models\UserProfile;
use Filament\Forms;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | \UnitEnum | null $navigationGroup = 'Pengaturan Akun';
    protected static ?string $navigationLabel = 'Profil Pengguna';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Nama User')
                    ->relationship('user', 'name')
                    ->required(),
                FileUpload::make('avatar_path')
                    ->label('Foto Profil')
                    ->image()
                    ->directory('avatars')
                    ->disk('public')
                    ->visibility('public')
                    ->imageEditor()
                    ->maxSize(2048)
                    ->downloadable()
                    ->openable()
                    ->helperText('JPG/PNG, maks 2MB'),
                TextInput::make('full_name')
                    ->label('Nama Lengkap')
                    ->required(),
                TextInput::make('nickname')
                    ->label('Nama Panggilan'),
                TextInput::make('birth_place')
                    ->label('Tempat Lahir'),
                DatePicker::make('birth_date')
                    ->label('Tanggal Lahir'),
                Select::make('gender')
                    ->label('Jenis Kelamin')
                    ->options([
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                    ]),
                Textarea::make('address')
                    ->label('Alamat')
                    ->columnSpanFull(),
                TextInput::make('phone')
                    ->label('No. HP / WA')
                    ->tel(),
                TextInput::make('employment_status')
                    ->label('Status Kepegawaian')
                    ->required(),
                TextInput::make('jabatan')
                    ->label('Jabatan')
                    ->maxLength(100),
                TextInput::make('nip')
                    ->label('NIP')
                    ->maxLength(50)
                    ->helperText('Khusus Pegawai BPS'),
                TextInput::make('pangkat_golongan')
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
            ->recordActions([
                Action::make('sipetra')
                    ->label('SIPETRA')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->tooltip('Buka Akun SIPETRA')
                    ->url(fn (): string => rtrim(config('services.sipetra.base_url', 'https://bpsdemak.com'), '/') . '/account')
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListUserProfiles::route('/'),
            'create' => CreateUserProfile::route('/create'),
            'edit' => EditUserProfile::route('/{record}/edit'),
        ];
    }
}
