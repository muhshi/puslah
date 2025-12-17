<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Laravel\Passport\Client;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'SSO Clients';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('App Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('redirect')
                    ->label('Redirect URL')
                    ->required()
                    ->helperText('Use commas to separate multiple URLs')
                    ->maxLength(2000),
                Forms\Components\Checkbox::make('personal_access_client')
                    ->label('Personal Access Client')
                    ->disabled(),
                Forms\Components\Checkbox::make('password_client')
                    ->label('Password Grant Client')
                    ->disabled(),
                Forms\Components\Checkbox::make('revoked')
                    ->label('Revoked'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('id')
                    ->label('Client ID')
                    ->copyable(),
                Tables\Columns\TextColumn::make('secret')
                    ->label('Secret')
                    ->formatStateUsing(fn($state) => $state ? '****************' : 'No Secret')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
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
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
