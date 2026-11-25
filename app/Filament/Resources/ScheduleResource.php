<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ScheduleResource\Pages\ListSchedules;
use App\Filament\Resources\ScheduleResource\Pages;
use App\Filament\Resources\ScheduleResource\RelationManagers;
use App\Models\Schedule;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Kantor Cabang';
    protected static ?string $navigationLabel = 'Jadwal';
    protected static ?int $navigationSort = 81;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->unique()
                            ->preload()
                            ->native(false)
                            ->required(),
                        Select::make('shift_id')
                            ->relationship('shift', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),
                        Select::make('office_id')
                            ->relationship('office', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),

                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                //if(Auth::user()->hasRole('Doctor')) -> pakai ini bisa tapi terdeteksi error sama intelephense
                if (Auth::user()->roles[0]->name != 'super_admin') {
                    $query->where('user_id', Auth::user()->id);
                }
            })
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email'),

                TextColumn::make('shift.name')
                    ->description(fn(Schedule $record): string => $record->shift->start_time . ' - ' . $record->shift->end_time)
                    ->sortable(),

                TextColumn::make('office.name')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            'index' => ListSchedules::route('/'),
            //'create' => Pages\CreateSchedule::route('/create'),
            //'view' => Pages\ViewSchedule::route('/{record}'),
            //'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }
}
