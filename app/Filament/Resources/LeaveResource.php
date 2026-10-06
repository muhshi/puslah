<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveResource\Pages;
use App\Filament\Resources\LeaveResource\RelationManagers;
use App\Models\Leave;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class LeaveResource extends Resource
{
    protected static ?string $model = Leave::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Manajemen Presensi';
    protected static ?string $navigationLabel = 'Izin';
    protected static ?string $modelLabel = 'Izin';
    protected static ?string $pluralModelLabel = 'Data Izin';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Formulir Permohonan & Verifikasi Izin')
                    ->description('Kelola permohonan izin tidak masuk kerja bagi pegawai dan proses persetujuan verifikator.')
                    ->icon('heroicon-o-document-check')
                    ->schema([

                        // Fieldset 1: Informasi Pemohon & Tanggal
                        Forms\Components\Fieldset::make('Informasi Pegawai & Waktu Izin')
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Nama Pegawai')
                                    ->prefixIcon('heroicon-m-user')
                                    ->options(\App\Models\User::query()->orderBy('name')->pluck('name', 'id'))
                                    ->default(Auth::id())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled(fn () => ! (Auth::user()?->hasRole('super_admin') ?? false))
                                    ->dehydrated()
                                    ->helperText(fn () => (Auth::user()?->hasRole('super_admin') ?? false) ? 'Super Admin dapat mengajukan izin atas nama pegawai lain.' : null)
                                    ->columnSpanFull(),

                                Forms\Components\Group::make([
                                    Forms\Components\DatePicker::make('start_date')
                                        ->label('Tanggal Mulai Izin')
                                        ->prefixIcon('heroicon-m-calendar-days')
                                        ->native(false)
                                        ->displayFormat('d F Y')
                                        ->closeOnDateSelection()
                                        ->required(),

                                    Forms\Components\DatePicker::make('end_date')
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

                                Forms\Components\Textarea::make('reason')
                                    ->label('Alasan / Keperluan Izin')
                                    ->placeholder('Tuliskan alasan permohonan izin tidak masuk kerja secara jelas...')
                                    ->rows(3)
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        // Fieldset 2: Verifikasi & Persetujuan
                        Forms\Components\Fieldset::make('Status Verifikasi & Catatan')
                            ->schema([
                                Forms\Components\ToggleButtons::make('status')
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

                                Forms\Components\Textarea::make('note')
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
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Mulai Izin')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Selesai Izin')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Durasi')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(function (Leave $record) {
                        $start = $record->start_date ? \Carbon\Carbon::parse($record->start_date) : null;
                        $end = $record->end_date ? \Carbon\Carbon::parse($record->end_date) : null;
                        if (!$start || !$end) return '-';
                        $days = $start->diffInDays($end) + 1;
                        return "{$days} hari";
                    }),
                Tables\Columns\TextColumn::make('status')
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
                Tables\Columns\TextColumn::make('reason')
                    ->label('Keperluan Izin')
                    ->limit(35)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('note')
                    ->label('Catatan Verifikator')
                    ->limit(35)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diajukan Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options([
                        'pending' => 'Menunggu Verifikasi',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListLeaves::route('/'),
            'create' => Pages\CreateLeave::route('/create'),
            'view' => Pages\ViewLeave::route('/{record}'),
            'edit' => Pages\EditLeave::route('/{record}/edit'),
        ];
    }
}
