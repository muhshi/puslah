<?php

namespace App\Filament\Pages;

use App\Models\UserProfile;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileMe extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static ?string $slug = 'profile-me';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Pengaturan Akun';
    protected static ?string $navigationLabel = 'Profil Saya';
    protected static ?string $title = 'Profil Saya';
    protected static bool $shouldRegisterNavigation = true;
    protected static string $view = 'filament.pages.profile-me';

    public ?array $data = [];          // form profil
    public ?array $passwordData = [];  // form password
    public ?UserProfile $profile;

    public function mount(): void
    {
        $this->profile = UserProfile::firstOrCreate(['user_id' => Auth::id()], [
            'employment_status' => 'aktif',
            'full_name' => Auth::user()->name ?? null,
        ]);

        // Prefill profil
        $this->profileForm->fill([
            'avatar_path' => $this->profile->avatar_path,
            'full_name' => $this->profile->full_name,
            'nickname' => $this->profile->nickname,
            'birth_place' => $this->profile->birth_place,
            'birth_date' => $this->profile->birth_date?->format('Y-m-d'),
            'gender' => $this->profile->gender,
            'address' => $this->profile->address,
            'phone' => $this->profile->phone,
            'employment_status' => $this->profile->employment_status,
        ]);

        // Kosongkan form password
        $this->passwordForm->fill();
    }

    protected function getForms(): array
    {
        return [
            'profileForm',
            'passwordForm',
        ];
    }

    public function profileForm(Form $form): Form
    {
        return $form
            ->model($this->profile)
            ->statePath('data')
            ->schema([
                Section::make('Foto Profil')->schema([
                    FileUpload::make('avatar_path')
                        ->label('Foto (untuk sertifikat)')
                        ->image()
                        ->imagePreviewHeight('250')
                        ->directory('avatars')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->columnSpanFull(),
                ]),
                Section::make('Informasi Pribadi')->schema([
                    TextInput::make('full_name')->label('Nama lengkap')->required()->maxLength(100),
                    TextInput::make('nickname')->label('Nama panggilan')->maxLength(50),
                    TextInput::make('birth_place')->label('Tempat lahir')->maxLength(100),
                    DatePicker::make('birth_date')
                        ->label('Tanggal lahir')
                        ->native(false),
                    Select::make('gender')
                        ->label('Jenis kelamin')
                        ->options([
                            'L' => 'Laki-laki',
                            'P' => 'Perempuan',
                        ])->nullable(),
                    Textarea::make('address')
                        ->label('Alamat domisili')
                        ->rows(3)
                        ->columnSpanFull(),
                    TextInput::make('phone')
                        ->label('No. HP/WA')
                        ->tel()
                        ->maxLength(30),
                    TextInput::make('employment_status')
                        ->label('Status kerja')
                        ->default('aktif')
                        ->disabled(),
                ])->columns(2),
            ]);
    }

    public function saveProfile(): void
    {
        $data = $this->profileForm->getState();

        $this->profile->update([
            'avatar_path' => $data['avatar_path'] ?? null,
            'full_name' => $data['full_name'] ?? null,
            'nickname' => $data['nickname'] ?? null,
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'gender' => $data['gender'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        Notification::make()->title('Profil berhasil disimpan')->success()->send();
        $this->redirect(static::getUrl(), navigate: true);
    }

    public function passwordForm(Form $form): Form
    {
        return $form
            ->statePath('passwordData')
            ->schema([
                Section::make('Update Password')
                    ->description('Pastikan password panjang & acak.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Password saat ini')
                            ->password()
                            ->required()
                            ->rule('current_password'),
                        TextInput::make('password')
                            ->label('Password baru')
                            ->password()
                            ->required()
                            ->rule(Password::min(8))
                            ->dehydrated(false)
                            ->revealable(),
                        TextInput::make('password_confirmation')
                            ->label('Konfirmasi password baru')
                            ->password()
                            ->required()
                            ->same('password'),
                    ])->columns(2),
            ]);
    }

    public function savePassword(): void
    {
        $data = $this->passwordForm->getState();

        // current_password sudah divalidasi rule di atas
        Auth::user()->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        $this->passwordForm->fill();
        Notification::make()->title('Password berhasil diperbarui')->success()->send();
    }
}
