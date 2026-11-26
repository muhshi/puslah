<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Select;
use App\Models\User;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Tables\Columns\TextColumn;
use Carbon\Carbon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\LeaveResource\Pages\ListLeaves;
use App\Filament\Resources\LeaveResource\Pages\CreateLeave;
use App\Filament\Resources\LeaveResource\Pages\ViewLeave;
use App\Filament\Resources\LeaveResource\Pages\EditLeave;
use App\Filament\Resources\LeaveResource\Pages;
use App\Filament\Resources\LeaveResource\RelationManagers;
use App\Models\Leave;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class LeaveResource extends Resource
{
    protected static ?string $model = Leave::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-check';

    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Presensi';
    protected static ?string $navigationLabel = 'Izin';
    protected static ?string $modelLabel = 'Izin';
    protected static ?string $pluralModelLabel = 'Data Izin';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Formulir Permohonan & Verifikasi Izin')
                    ->description('Kelola permohonan izin tidak masuk kerja bagi pegawai dan proses persetujuan verifikator.')
                    ->icon('heroicon-o-document-check')
                    ->schema([

                        // Fieldset 1: Informasi Pemohon & Tanggal
                        Fieldset::make('Informasi Pegawai & Waktu Izin')
                            ->schema([
                                Select::make('user_id')
                                    ->label('Nama Pegawai')
                                    ->prefixIcon('heroicon-m-user')
                                    ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                                    ->default(Auth::id())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled(fn () => ! (Auth::user()?->hasRole('super_admin') ?? false))
                                    ->dehydrated()
                                    ->helperText(fn () => (Auth::user()?->hasRole('super_admin') ?? false) ? 'Super Admin dapat mengajukan izin atas nama pegawai lain.' : null)
                                    ->columnSpanFull(),

                                Group::make([
                                    DatePicker::make('start_date')
                                        ->label('Tanggal Mulai Izin')
                                        ->prefixIcon('heroicon-m-calendar-days')
                                        ->native(false)
                                        ->displayFormat('d F Y')
                                        ->closeOnDateSelection()
                                        ->required(),

                                    DatePicker::make('end_date')
                                        ->label('Tanggal Selesai Izin')
                                        ->prefixIcon('heroicon-m-calendar-days')
                                        ->native(false)
                                        ->displayFormat('d F Y')
                                        ->closeOnDateSelection()
                                        ->required()
                                        ->rule('after_or_equal:start_date'),
                                ])
                                ->columns(2)
                                ->columnSpanFull(),

                                Textarea::make('reason')
                                    ->label('Alasan / Keperluan Izin')
                                    ->placeholder('Tuliskan alasan permohonan izin tidak masuk kerja secara jelas...')
                                    ->rows(3)
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        // Fieldset 2: Verifikasi & Persetujuan
                        Fieldset::make('Status Verifikasi & Catatan')
                            ->schema([
                                ToggleButtons::make('status')
                                    ->label('Status Permohonan')
                                    ->options([
                                        'pending' => 'Menunggu Verifikasi',
                                        'approved' => 'Disetujui',
                                        'rejected' => 'Ditolak',
                                    ])
                                    ->colors([
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                    ])
                                    ->icons([
                                        'pending' => 'heroicon-m-clock',
                                        'approved' => 'heroicon-m-check-circle',
                                        'rejected' => 'heroicon-m-x-circle',
                                    ])
                                    ->inline()
                                    ->grouped()
                                    ->default('pending')
                                    ->disabled(fn () => ! (Auth::user()?->hasRole('super_admin') ?? false))
                                    ->dehydrated()
                                    ->columnSpanFull(),

                                Textarea::make('note')
                                    ->label('Catatan Verifikator')
                                    ->placeholder('Catatan atau alasan persetujuan / penolakan izin jika ada...')
                                    ->rows(2)
                                    ->disabled(fn () => ! (Auth::user()?->hasRole('super_admin') ?? false))
                                    ->dehydrated()
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn (string $operation) => $operation !== 'create' || (Auth::user()?->hasRole('super_admin') ?? false))
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
                    $query->where('user_id', Auth::id());
                }
            })
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Mulai Izin')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Selesai Izin')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('Durasi')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(function (Leave $record) {
                        $start = $record->start_date ? Carbon::parse($record->start_date)->startOfDay() : null;
                        $end = $record->end_date ? Carbon::parse($record->end_date)->startOfDay() : null;
                        if (!$start || !$end) return '-';
                        $days = (int) round($start->diffInDays($end, true)) + 1;
                        return "{$days} hari";
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => 'Menunggu Verifikasi',
                    })
                    ->color(fn(Leave $record): string => match ($record->status) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->searchable(),
                TextColumn::make('reason')
                    ->label('Keperluan Izin')
                    ->limit(35)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(),
                TextColumn::make('note')
                    ->label('Catatan Verifikator')
                    ->limit(35)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Diajukan Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options([
                        'pending' => 'Menunggu Verifikasi',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => ListLeaves::route('/'),
            'create' => CreateLeave::route('/create'),
            'view' => ViewLeave::route('/{record}'),
            'edit' => EditLeave::route('/{record}/edit'),
        ];
    }
}
