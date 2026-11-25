<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\OfficeResource\Pages\ListOffices;
use App\Filament\Resources\OfficeResource\Pages\CreateOffice;
use App\Filament\Resources\OfficeResource\Pages\EditOffice;
use App\Filament\Resources\OfficeResource\Pages;
use App\Filament\Resources\OfficeResource\RelationManagers;
use App\Models\Office;
use Dotswan\MapPicker\Fields\Map;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OfficeResource extends Resource
{
    protected static ?string $model = Office::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Kantor Cabang';
    protected static ?string $navigationLabel = 'Kantor';
    protected static ?int $navigationSort = 80;


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('name')
                                    ->required(),
                                Map::make('location')
                                    ->label('Location')
                                    ->columnSpanFull()
                                    ->default([
                                        'lat' => -6.894561,
                                        'lng' => 110.637492
                                    ])
                                    ->afterStateUpdated(function (Set $set, ?array $state): void {
                                        $set('latitude', $state['lat']);
                                        $set('longitude', $state['lng']);
                                    })
                                    ->afterStateHydrated(function ($state, $record, Set $set): void {
                                        if ($record) {
                                            $set('location', ['lat' => $record->latitude, 'lng' => $record->longitude]);
                                        }
                                    })

                                    ->liveLocation()
                                    ->showMarker()
                                    ->markerColor("#22c55eff")
                                    ->showFullscreenControl()
                                    ->showZoomControl()
                                    ->draggable()
                                    ->tilesUrl("http://mt0.google.com/vt/lyrs=y&hl=en&x={x}&y={y}&z={z}&s=Ga")
                                    ->zoom(15)
                                    ->detectRetina()
                                    ->extraTileControl([])
                                    ->extraControl([
                                        'zoomDelta' => 1,
                                        'zoomSnap' => 2,
                                    ]),
                                Group::make()
                                    ->schema([
                                        TextInput::make('latitude')
                                            ->required()
                                            ->numeric(),
                                        TextInput::make('longitude')
                                            ->numeric(),
                                    ])->columns(2),
                            ]),
                    ]),
                Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('radius')
                                    ->required()
                                    ->numeric(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('latitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('longitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('radius')
                    ->numeric()
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
            'index' => ListOffices::route('/'),
            'create' => CreateOffice::route('/create'),
            //'view' => Pages\ViewOffice::route('/{record}'),
            'edit' => EditOffice::route('/{record}/edit'),
        ];
    }
}
