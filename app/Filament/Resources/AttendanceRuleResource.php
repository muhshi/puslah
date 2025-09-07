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
            Forms\Components\Select::make('user_id')
                ->label('User')
                ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()->preload()->required(),

            Forms\Components\Select::make('type')
                ->label('Tipe Aturan')
                ->options([
                    'WFA' => 'WFA (Work From Anywhere)',
                    'BANNED' => 'BANNED (blokir presensi)',
                ])->required(),

            Forms\Components\DatePicker::make('start_date')
                ->label('Mulai')->native(false)->displayFormat('Y-m-d')->required(),

            Forms\Components\DatePicker::make('end_date')
                ->label('Selesai')->native(false)->displayFormat('Y-m-d')
                ->required()->rule('after_or_equal:start_date'),

            Forms\Components\Textarea::make('reason')
                ->label('Alasan')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                if (Auth::user()->roles[0]->name != 'super_admin') {
                    $query->where('user_id', Auth::user()->id);
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
                Tables\Columns\TextColumn::make('start_date')->label('Mulai')->date(),
                Tables\Columns\TextColumn::make('end_date')->label('Selesai')->date(),

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
