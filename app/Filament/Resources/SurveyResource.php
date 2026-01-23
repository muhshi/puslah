<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyResource\Pages;
use App\Filament\Resources\SurveyResource\RelationManagers;
use App\Filament\Resources\SurveyResource\RelationManagers\ParticipantsRelationManager;
use App\Models\Survey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static ?string $navigationIcon = 'heroicon-m-clipboard-document-check';

    protected static ?string $navigationGroup = 'Manajemen Survei';
    protected static ?string $navigationLabel = 'Survei';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Survei')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('dasar_surat')
                    ->label('Dasar Surat')
                    ->placeholder('Contoh: DIPA BPS Kabupaten Demak Tahun Anggaran 2025...')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Mulai'),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Selesai'),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\TextInput::make('complete_rule')
                    ->required()
                    ->label('Aktif')
                    ->maxLength(255)
                    ->default('approved'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nama'),
                Tables\Columns\TextColumn::make('start_date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('participants_count')
                    ->counts('participants')
                    ->label('Peserta'),
                Tables\Columns\TextColumn::make('complete_rule')
                    ->searchable(),
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
                Tables\Actions\Action::make('copy')
                    ->label('Copy')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->modalHeading('Copy Survey')
                    ->modalDescription('Ubah data survey sebelum menyimpan. Petugas yang sudah di-assign akan ter-copy.')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Survei')
                            ->required()
                            ->maxLength(255)
                            ->default(fn(Survey $record) => 'Copy of ' . $record->name),
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi')
                            ->default(fn(Survey $record) => $record->description)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('dasar_surat')
                            ->label('Dasar Surat')
                            ->required()
                            ->default(fn(Survey $record) => $record->dasar_surat)
                            ->columnSpanFull(),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Mulai')
                            ->default(fn(Survey $record) => $record->start_date),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Selesai')
                            ->default(fn(Survey $record) => $record->end_date),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(fn(Survey $record) => $record->is_active),
                        Forms\Components\TextInput::make('complete_rule')
                            ->label('Rule')
                            ->required()
                            ->maxLength(255)
                            ->default(fn(Survey $record) => $record->complete_rule ?? 'approved'),
                        Forms\Components\Toggle::make('copy_participants')
                            ->label('Copy Petugas/Peserta')
                            ->helperText('Centang untuk meng-copy semua petugas yang sudah di-assign ke survey asli')
                            ->default(true),
                    ])
                    ->action(function (Survey $record, array $data): void {
                        // Create new survey
                        $copyParticipants = $data['copy_participants'] ?? false;
                        unset($data['copy_participants']);

                        $newSurvey = Survey::create($data);

                        // Copy participants if requested
                        if ($copyParticipants) {
                            $participants = $record->surveyUsers()->get();
                            foreach ($participants as $participant) {
                                $newSurvey->surveyUsers()->create([
                                    'user_id' => $participant->user_id,
                                    'status' => $participant->status,
                                    'registered_at' => now(),
                                    'notes' => $participant->notes,
                                ]);
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Survey berhasil di-copy!')
                            ->body($copyParticipants
                                ? 'Survey baru dibuat dengan ' . $participants->count() . ' petugas.'
                                : 'Survey baru dibuat tanpa petugas.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ParticipantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveys::route('/'),
            'create' => Pages\CreateSurvey::route('/create'),
            'edit' => Pages\EditSurvey::route('/{record}/edit'),
        ];
    }
}
