<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyResource\Pages\CreateSurvey;
use App\Filament\Resources\SurveyResource\Pages\EditSurvey;
use App\Filament\Resources\SurveyResource\Pages\ListSurveyActivities;
use App\Filament\Resources\SurveyResource\Pages\ListSurveys;
use App\Filament\Resources\SurveyResource\RelationManagers\ParticipantsRelationManager;
use App\Models\Survey;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-m-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Kegiatan';

    protected static ?string $navigationLabel = 'Kegiatan';

    protected static ?string $modelLabel = 'Kegiatan';

    protected static ?string $pluralModelLabel = 'Kegiatan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Kegiatan')
                    ->required()
                    ->maxLength(255),
                    // ->withProperCase(),
                Select::make('jenis_kegiatan')
                    ->label('Jenis Kegiatan')
                    ->options([
                        'pelatihan'     => 'Pelatihan',
                        'non-pelatihan' => 'Non-Pelatihan',
                    ])
                    ->default('non-pelatihan')
                    ->required(),
                Select::make('metode')
                    ->label('Metode')
                    ->options([
                        'daring'  => 'Daring',
                        'luring'  => 'Luring',
                        'blended' => 'Blended',
                    ])
                    ->default('luring')
                    ->required(),
                Textarea::make('tujuan')
                    ->label('Tujuan Kegiatan')
                    // ->withProperCase()
                    ->columnSpanFull(),
                Textarea::make('dasar_surat')
                    ->label('Dasar Surat')
                    ->placeholder('Contoh: DIPA BPS Kabupaten Demak Tahun Anggaran 2026...')
                    // ->withProperCase()
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('start_date')
                    ->label('Mulai'),
                DatePicker::make('end_date')
                    ->label('Selesai'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->required(),
                Toggle::make('is_multiple')
                    ->label('Multiple Surat Tugas')
                    ->helperText('Jika diaktifkan, satu pegawai bisa dibuatkan beberapa surat tugas pada kegiatan ini')
                    ->default(false)
                    ->required(),
                Toggle::make('is_multiclass')
                    ->label('Kegiatan Multi-Kelas')
                    ->helperText('Aktifkan jika peserta dibagi ke beberapa kelas (Kelas A, Kelas B, dll)')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nama Kegiatan'),
                TextColumn::make('jenis_kegiatan')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'pelatihan'     => 'info',
                        'non-pelatihan' => 'warning',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pelatihan'     => 'Pelatihan',
                        'non-pelatihan' => 'Non-Pelatihan',
                        default         => $state ? ucfirst($state) : '-',
                    })
                    ->toggleable(),
                TextColumn::make('metode')
                    ->label('Metode')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'daring'  => 'success',
                        'luring'  => 'primary',
                        'blended' => 'warning',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '-')
                    ->toggleable(),
                TextColumn::make('start_date')
                    ->date('d M Y')
                    ->sortable()
                    ->label('Mulai'),
                TextColumn::make('end_date')
                    ->date('d M Y')
                    ->sortable()
                    ->label('Selesai'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Aktif'),
                ToggleColumn::make('is_multiclass')
                    ->label('Multi-Kelas')
                    ->tooltip('Aktifkan jika kegiatan memiliki beberapa kelas peserta'),
                TextColumn::make('participants_count')
                    ->counts('participants')
                    ->label('Peserta'),
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
                SelectFilter::make('jenis_kegiatan')
                    ->label('Jenis Kegiatan')
                    ->options([
                        'pelatihan'     => 'Pelatihan',
                        'non-pelatihan' => 'Non-Pelatihan',
                    ]),
                SelectFilter::make('metode')
                    ->label('Metode')
                    ->options([
                        'daring'  => 'Daring',
                        'luring'  => 'Luring',
                        'blended' => 'Blended',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('buat_undangan')
                    ->label('Undangan')
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->url(fn (Survey $record) => SuratUndanganResource::getUrl('create', ['survey_id' => $record->id])),
                Action::make('copy')
                    ->label('Copy')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->modalHeading('Copy Kegiatan')
                    ->modalDescription('Ubah data kegiatan sebelum menyimpan. Petugas yang sudah di-assign akan ter-copy.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Kegiatan')
                            ->required()
                            ->maxLength(255),
                            // // ->withProperCase()  (removed)
                            ->default(fn (Survey $record) => 'Copy of ' . $record->name),
                        Select::make('jenis_kegiatan')
                            ->label('Jenis Kegiatan')
                            ->options(['pelatihan' => 'Pelatihan', 'non-pelatihan' => 'Non-Pelatihan'])
                            ->default(fn (Survey $record) => $record->jenis_kegiatan ?? 'non-pelatihan')
                            ->required(),
                        Select::make('metode')
                            ->label('Metode')
                            ->options(['daring' => 'Daring', 'luring' => 'Luring', 'blended' => 'Blended'])
                            ->default(fn (Survey $record) => $record->metode ?? 'luring')
                            ->required(),
                        Textarea::make('tujuan')
                            ->label('Tujuan Kegiatan')
                            // // ->withProperCase()  (removed)
                            ->default(fn (Survey $record) => $record->tujuan)
                            ->columnSpanFull(),
                        Textarea::make('dasar_surat')
                            ->label('Dasar Surat')
                            ->required()
                            // // ->withProperCase()  (removed)
                            ->default(fn (Survey $record) => $record->dasar_surat)
                            ->columnSpanFull(),
                        DatePicker::make('start_date')
                            ->label('Mulai')
                            ->default(fn (Survey $record) => $record->start_date),
                        DatePicker::make('end_date')
                            ->label('Selesai')
                            ->default(fn (Survey $record) => $record->end_date),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(fn (Survey $record) => $record->is_active),
                        Toggle::make('is_multiple')
                            ->label('Multiple Surat Tugas')
                            ->default(fn (Survey $record) => $record->is_multiple),
                        Toggle::make('is_multiclass')
                            ->label('Kegiatan Multi-Kelas')
                            ->default(fn (Survey $record) => $record->is_multiclass),
                        Toggle::make('copy_participants')
                            ->label('Copy Petugas/Peserta')
                            ->helperText('Centang untuk meng-copy semua petugas yang sudah di-assign ke kegiatan asli')
                            ->default(true),
                    ])
                    ->action(function (Survey $record, array $data): void {
                        $copyParticipants = $data['copy_participants'] ?? false;
                        unset($data['copy_participants']);

                        $newSurvey = Survey::create($data);

                        $participants = collect();
                        if ($copyParticipants) {
                            $classMap = [];
                            if ($record->is_multiclass) {
                                foreach ($record->classes as $oldClass) {
                                    $newClass = $newSurvey->classes()->create([
                                        'name'        => $oldClass->name,
                                        'description' => $oldClass->description,
                                    ]);
                                    $classMap[$oldClass->id] = $newClass->id;
                                }
                            }

                            $participants = $record->surveyUsers()->get();
                            foreach ($participants as $participant) {
                                $newSurvey->surveyUsers()->create([
                                    'user_id'         => $participant->user_id,
                                    'survey_class_id' => $classMap[$participant->survey_class_id] ?? null,
                                    'role'            => $participant->role,
                                    'role_pelatihan'  => $participant->role_pelatihan,
                                    'kelas'           => $participant->kelas,
                                    'status'          => $participant->status,
                                    'registered_at'   => now(),
                                    'notes'           => $participant->notes,
                                ]);
                            }
                        }

                        Notification::make()
                            ->title('Kegiatan berhasil di-copy!')
                            ->body($copyParticipants
                                ? 'Kegiatan baru dibuat dengan ' . $participants->count() . ' petugas.'
                                : 'Kegiatan baru dibuat tanpa petugas.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                Action::make('activities')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->url(fn ($record) => SurveyResource::getUrl('activities', ['record' => $record])),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('is_active', 'desc')->orderBy('created_at', 'desc'))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_pelatihan', false)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
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
            'index'      => ListSurveys::route('/'),
            'create'     => CreateSurvey::route('/create'),
            'edit'       => EditSurvey::route('/{record}/edit'),
            'activities' => ListSurveyActivities::route('/{record}/activities'),
        ];
    }
}
