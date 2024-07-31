<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Filament\Resources\ScheduleResource\RelationManagers;
use App\Models\Schedule;
use Filament\Forms;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Presensi Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->unique()
                            ->preload()
                            ->native(false)
                            ->required(),
                        Forms\Components\Select::make('shift_id')
                            ->relationship('shift', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),
                        Forms\Components\Select::make('office_id')
                            ->relationship('office', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),
                        Forms\Components\Toggle::make('is_wfa')
                            ->default(false)
                            ->required(),
                        Forms\Components\Toggle::make('is_banned'),


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
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email'),
                Auth::user()->roles[0]->name == 'super_admin' ?
                    Tables\Columns\ToggleColumn::make('is_banned')
                    ->label('Banned') :
                    Tables\Columns\TextColumn::make('is_banned')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        return $record->isBanned() ? 'Banned' : 'Active';
                    })
                    ->color(function ($record) {
                        return $record->isBanned() ? 'danger' : 'success';
                    }),

                Tables\Columns\TextColumn::make('shift.name')
                    ->description(fn(Schedule $record): string => $record->shift->start_time . ' - ' . $record->shift->end_time)
                    ->sortable(),
                //setting WFA toggle untuk admin, tapi icon untuk pegawai
                Auth::user()->roles[0]->name == 'super_admin' ?
                    Tables\Columns\ToggleColumn::make('is_wfa')
                    ->label('WFA')
                    ->visible(fn() => Auth::user()->roles[0]->name == 'super_admin') :
                    Tables\Columns\IconColumn::make('is_wfa')
                    ->label('WFA')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('office.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            'index' => Pages\ListSchedules::route('/'),
            //'create' => Pages\CreateSchedule::route('/create'),
            //'view' => Pages\ViewSchedule::route('/{record}'),
            //'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }
}
