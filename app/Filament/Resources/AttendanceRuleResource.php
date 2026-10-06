<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceRuleResource\Pages;
use App\Filament\Resources\AttendanceRuleResource\RelationManagers;
use App\Models\AttendanceRule;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class AttendanceRuleResource extends Resource
{
    protected static ?string $model = AttendanceRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Manajemen Presensi';
    protected static ?string $navigationLabel = 'Aturan Presensi';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Konfigurasi Aturan Presensi')
                ->description('Tentukan hak akses presensi khusus (Work From Anywhere atau BANNED) untuk pegawai / peserta survei.')
                ->icon('heroicon-o-shield-check')
                ->schema([

                    // Fieldset 1: Target Survei & Pegawai
                    Forms\Components\Fieldset::make('Target Survei & Pegawai')
                        ->schema([
                            // Kontainer Khusus Saat Create (Multiple User via Survei)
                            Forms\Components\Group::make([
                                Forms\Components\Select::make('survey_id')
                                    ->label('Pilih Survei')
                                    ->placeholder('Pilih survei untuk memfilter peserta...')
                                    ->prefixIcon('heroicon-m-clipboard-document-list')
                                    ->options(function () {
                                        return \App\Models\Survey::query()
                                            ->orderByDesc('is_active')
                                            ->orderByDesc('start_date')
                                            ->orderByDesc('id')
                                            ->get()
                                            ->mapWithKeys(function ($survey) {
                                                $status = $survey->is_active ? 'Aktif' : 'Selesai';
                                                $dates = '';
                                                if ($survey->start_date && $survey->end_date) {
                                                    $dates = ' (' . $survey->start_date->format('d/m/Y') . ' - ' . $survey->end_date->format('d/m/Y') . ')';
                                                }
                                                return [$survey->id => "{$survey->name}{$dates} [{$status}]"];
                                            });
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $set('user_ids', []);
                                        if ($state) {
                                            $survey = \App\Models\Survey::find($state);
                                            if ($survey) {
                                                if ($survey->start_date) {
                                                    $set('start_date', $survey->start_date->format('Y-m-d'));
                                                }
                                                if ($survey->end_date) {
                                                    $set('end_date', $survey->end_date->format('Y-m-d'));
                                                }
                                                if (empty($get('reason'))) {
                                                    $set('reason', "Penugasan Survei: {$survey->name}");
                                                }
                                            }
                                        }
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Pilih survei terlebih dahulu.',
                                    ])
                                    ->helperText('Pilih survei terlebih dahulu agar ribuan user difilter menjadi peserta survei terkait.')
                                    ->columnSpanFull(),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('selectAll')
                                        ->label('Pilih Semua Peserta')
                                        ->icon('heroicon-m-check-badge')
                                        ->color('primary')
                                        ->button()
                                        ->visible(fn (Forms\Get $get) => (bool) $get('survey_id'))
                                        ->action(function (Forms\Set $set, Forms\Get $get) {
                                            $surveyId = $get('survey_id');
                                            if (! $surveyId) return;

                                            $userIds = \App\Models\SurveyUser::where('survey_id', $surveyId)
                                                ->pluck('user_id')
                                                ->map(fn($id) => (int) $id)
                                                ->toArray();

                                            if (empty($userIds)) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Survei ini tidak memiliki peserta terdaftar.')
                                                    ->warning()
                                                    ->send();
                                                return;
                                            }

                                            $set('user_ids', $userIds);

                                            \Filament\Notifications\Notification::make()
                                                ->title(count($userIds) . ' peserta survei berhasil dipilih.')
                                                ->success()
                                                ->send();
                                        }),

                                    Forms\Components\Actions\Action::make('selectMitra')
                                        ->label('Hanya Mitra')
                                        ->icon('heroicon-m-user-group')
                                        ->color('info')
                                        ->button()
                                        ->visible(fn (Forms\Get $get) => (bool) $get('survey_id'))
                                        ->action(function (Forms\Set $set, Forms\Get $get) {
                                            $surveyId = $get('survey_id');
                                            if (! $surveyId) return;

                                            $userIds = \App\Models\SurveyUser::where('survey_id', $surveyId)
                                                ->whereHas('user.roles', fn($q) => $q->where('name', 'Mitra'))
                                                ->pluck('user_id')
                                                ->map(fn($id) => (int) $id)
                                                ->toArray();

                                            if (empty($userIds)) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Tidak ada peserta dengan role Mitra pada survei ini.')
                                                    ->warning()
                                                    ->send();
                                                return;
                                            }

                                            $set('user_ids', $userIds);

                                            \Filament\Notifications\Notification::make()
                                                ->title(count($userIds) . ' peserta Mitra berhasil dipilih.')
                                                ->info()
                                                ->send();
                                        }),

                                    Forms\Components\Actions\Action::make('selectOrganik')
                                        ->label('Hanya Organik (BPS)')
                                        ->icon('heroicon-m-building-office-2')
                                        ->color('warning')
                                        ->button()
                                        ->visible(fn (Forms\Get $get) => (bool) $get('survey_id'))
                                        ->action(function (Forms\Set $set, Forms\Get $get) {
                                            $surveyId = $get('survey_id');
                                            if (! $surveyId) return;

                                            $userIds = \App\Models\SurveyUser::where('survey_id', $surveyId)
                                                ->whereHas('user.roles', fn($q) => $q->where('name', 'Organik'))
                                                ->pluck('user_id')
                                                ->map(fn($id) => (int) $id)
                                                ->toArray();

                                            if (empty($userIds)) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Tidak ada pegawai Organik pada survei ini.')
                                                    ->warning()
                                                    ->send();
                                                return;
                                            }

                                            $set('user_ids', $userIds);

                                            \Filament\Notifications\Notification::make()
                                                ->title(count($userIds) . ' pegawai Organik berhasil dipilih.')
                                                ->info()
                                                ->send();
                                        }),

                                    Forms\Components\Actions\Action::make('deselectAll')
                                        ->label('Kosongkan Pilihan')
                                        ->icon('heroicon-m-x-circle')
                                        ->color('gray')
                                        ->button()
                                        ->visible(fn (Forms\Get $get) => (bool) $get('survey_id'))
                                        ->action(function (Forms\Set $set) {
                                            $set('user_ids', []);
                                        }),
                                ])
                                ->columnSpanFull(),

                                Forms\Components\Select::make('user_ids')
                                    ->label('Daftar Pegawai / Peserta Survei')
                                    ->placeholder(fn (Forms\Get $get) => $get('survey_id') ? 'Pilih peserta satu per satu atau gunakan tombol aksi di atas...' : 'Pilih survei terlebih dahulu...')
                                    ->prefixIcon('heroicon-m-users')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->disabled(fn (Forms\Get $get) => ! $get('survey_id'))
                                    ->options(function (Forms\Get $get) {
                                        $surveyId = $get('survey_id');
                                        if (! $surveyId) {
                                            return [];
                                        }

                                        return \App\Models\SurveyUser::where('survey_id', $surveyId)
                                            ->with(['user.profile', 'user.roles'])
                                            ->get()
                                            ->mapWithKeys(function ($su) {
                                                if (! $su->user) {
                                                    return [];
                                                }
                                                $role = $su->user->roles->first()?->name ?? 'User';
                                                $jabatan = $su->user->profile->jabatan ?? null;
                                                $label = $su->user->name;
                                                if ($jabatan) {
                                                    $label .= " ({$jabatan} - {$role})";
                                                } else {
                                                    $label .= " ({$role})";
                                                }
                                                return [$su->user_id => $label];
                                            });
                                    })
                                    ->validationMessages([
                                        'required' => 'Pilih minimal satu pegawai untuk menerapkan aturan presensi.',
                                    ])
                                    ->helperText(function (Forms\Get $get) {
                                        $surveyId = $get('survey_id');
                                        if (! $surveyId) {
                                            return 'Pilih survei di atas terlebih dahulu.';
                                        }
                                        $selectedCount = count($get('user_ids') ?? []);
                                        $totalPeserta = \App\Models\SurveyUser::where('survey_id', $surveyId)->count();
                                        return "Terpilih: {$selectedCount} dari total {$totalPeserta} peserta survei. Anda dapat memilih satu per satu atau menggunakan tombol aksi di atas.";
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn (string $operation) => $operation === 'create')
                            ->columnSpanFull(),

                            // Kontainer Khusus Saat Edit (Single Record)
                            Forms\Components\Select::make('user_id')
                                ->label('Pegawai')
                                ->prefixIcon('heroicon-m-user')
                                ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled()
                                ->helperText('Pegawai pemilik aturan presensi ini (tidak dapat diubah saat mengedit).')
                                ->visible(fn (string $operation) => $operation === 'edit')
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull(),

                    // Fieldset 2: Detail Aturan & Periode
                    Forms\Components\Fieldset::make('Detail Aturan & Periode')
                        ->schema([
                            Forms\Components\ToggleButtons::make('type')
                                ->label('Tipe Aturan')
                                ->options([
                                    'WFA' => 'WFA (Work From Anywhere)',
                                    'BANNED' => 'BANNED (Blokir Presensi)',
                                ])
                                ->colors([
                                    'WFA' => 'success',
                                    'BANNED' => 'danger',
                                ])
                                ->icons([
                                    'WFA' => 'heroicon-m-globe-alt',
                                    'BANNED' => 'heroicon-m-no-symbol',
                                ])
                                ->inline()
                                ->grouped()
                                ->default('WFA')
                                ->live()
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\Group::make([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Tanggal Mulai')
                                    ->prefixIcon('heroicon-m-calendar')
                                    ->native(false)
                                    ->displayFormat('Y-m-d')
                                    ->required(),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Tanggal Selesai')
                                    ->prefixIcon('heroicon-m-calendar')
                                    ->native(false)
                                    ->displayFormat('Y-m-d')
                                    ->required()
                                    ->rule('after_or_equal:start_date'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                            Forms\Components\TextInput::make('radius_override_m')
                                ->label('Radius Override (Meter)')
                                ->prefixIcon('heroicon-m-map-pin')
                                ->numeric()
                                ->minValue(1)
                                ->placeholder('Biarkan kosong jika mengikuti radius default kantor')
                                ->helperText('Khusus tipe WFA jika ingin membatasi radius presensi tertentu dalam meter.')
                                ->visible(fn (Forms\Get $get) => $get('type') === 'WFA')
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('reason')
                                ->label('Alasan / Keterangan')
                                ->rows(3)
                                ->placeholder('Tuliskan alasan pemberian aturan WFA/BANNED...')
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull(),

                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                if (! Auth::user()?->hasRole('super_admin')) {
                    $query->where('user_id', Auth::user()?->id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'WFA' => 'success',
                        'BANNED' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('start_date')->label('Mulai')->date()->sortable(),
                Tables\Columns\TextColumn::make('end_date')->label('Selesai')->date()->sortable(),
                Tables\Columns\TextColumn::make('radius_override_m')
                    ->label('Radius')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} m" : '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(35)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('approver.name')->label('Approved By')->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (AttendanceRule $r) {
                        $today = now('Asia/Jakarta')->toDateString();
                        if ($r->start_date->toDateString() <= $today && $r->end_date->toDateString() >= $today)
                            return 'Aktif hari ini';
                        if ($r->start_date->toDateString() > $today)
                            return 'Akan datang';
                        return 'Lewat';
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'Aktif hari ini' => 'success',
                        'Akan datang' => 'warning',
                        'Lewat' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe')->options(['WFA' => 'WFA', 'BANNED' => 'BANNED']),
                Tables\Filters\Filter::make('active_today')->label('Aktif Hari Ini')
                    ->query(fn($q) => $q->whereDate('start_date', '<=', now('Asia/Jakarta')->toDateString())
                        ->whereDate('end_date', '>=', now('Asia/Jakarta')->toDateString())),
                Tables\Filters\Filter::make('upcoming')->label('Akan Datang')
                    ->query(fn($q) => $q->whereDate('start_date', '>', now('Asia/Jakarta')->toDateString())),
                Tables\Filters\Filter::make('past')->label('Sudah Lewat')
                    ->query(fn($q) => $q->whereDate('end_date', '<', now('Asia/Jakarta')->toDateString())),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('start_date', 'desc');
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
            'index' => Pages\ListAttendanceRules::route('/'),
            'create' => Pages\CreateAttendanceRule::route('/create'),
            'edit' => Pages\EditAttendanceRule::route('/{record}/edit'),
        ];
    }
}
