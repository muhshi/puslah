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

    protected static ?string $navigationGroup = 'Manajemen Kegiatan';
    protected static ?string $navigationLabel = 'Kegiatan';
    protected static ?string $modelLabel = 'Kegiatan';
    protected static ?string $pluralModelLabel = 'Kegiatan';

    /**
     * Helper: Tombol Proper Case — mengubah teks menjadi Title Case
     * tanpa logika akronim yang rumit. User cukup klik tombol jika dibutuhkan.
     */
    public static function toProperCase(string $value): string
    {
        // Normalize whitespace dulu
        $value = trim(preg_replace('/\s+/', ' ', $value));
        // mb_convert_case dengan MB_CASE_TITLE
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    protected static function nameFieldSchema(string $defaultValue = ''): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Nama Kegiatan')
                ->required()
                ->maxLength(255)
                ->withProperCase()
                ->rules([
                    fn ($record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));
                        $exists = Survey::withoutGlobalScopes()
                            ->whereNull('deleted_at')
                            ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                            ->get(['id', 'name'])
                            ->contains(function ($item) use ($normalized) {
                                $itemNorm = mb_strtolower(trim(preg_replace('/\s+/', ' ', $item->name)));
                                return $itemNorm === $normalized;
                            });
                        if ($exists) {
                            $fail('Nama kegiatan sudah terdaftar (terdeteksi nama serupa meski berbeda spasi/huruf besar-kecil).');
                        }
                    }
                ]),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Kegiatan')
                    ->required()
                    ->maxLength(255)
                    ->withProperCase()
                    ->rules([
                        fn ($record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                            $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));
                            $exists = Survey::withoutGlobalScopes()
                                ->whereNull('deleted_at')
                                ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                                ->get(['id', 'name'])
                                ->contains(function ($item) use ($normalized) {
                                    $itemNorm = mb_strtolower(trim(preg_replace('/\s+/', ' ', $item->name)));
                                    return $itemNorm === $normalized;
                                });
                            if ($exists) {
                                $fail('Nama kegiatan sudah terdaftar (terdeteksi nama serupa meski berbeda spasi/huruf besar-kecil).');
                            }
                        }
                    ]),

                Forms\Components\Select::make('jenis_kegiatan')
                    ->label('Jenis Kegiatan')
                    ->options([
                        'pelatihan'     => 'Pelatihan',
                        'non-pelatihan' => 'Non-Pelatihan',
                    ])
                    ->default('pelatihan')
                    ->required(),

                Forms\Components\Select::make('metode')
                    ->label('Metode')
                    ->options([
                        'daring'   => 'Daring (Online)',
                        'luring'   => 'Luring (Tatap Muka)',
                        'blended'  => 'Blended (Hybrid)',
                    ])
                    ->default('luring')
                    ->required(),

                Forms\Components\Textarea::make('tujuan')
                    ->label('Tujuan Kegiatan')
                    ->withProperCase()
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('dasar_surat')
                    ->label('Dasar Surat')
                    ->placeholder('Contoh: DIPA BPS Kabupaten Demak Tahun Anggaran 2025...')
                    ->withProperCase()
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Mulai'),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Selesai'),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),

                Forms\Components\Toggle::make('is_multiple')
                    ->label('Multiple Surat Tugas')
                    ->helperText('Jika diaktifkan, satu pegawai bisa dibuatkan beberapa surat tugas pada kegiatan ini')
                    ->default(false)
                    ->required(),

                Forms\Components\Toggle::make('is_multiclass')
                    ->label('Kegiatan Multi-Kelas')
                    ->helperText('Aktifkan jika kegiatan ini dibagi menjadi beberapa kelas pelatihan/pembelajaran')
                    ->default(false)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nama Kegiatan'),
                Tables\Columns\TextColumn::make('jenis_kegiatan')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pelatihan'     => 'info',
                        'non-pelatihan' => 'warning',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pelatihan'     => 'Pelatihan',
                        'non-pelatihan' => 'Non-Pelatihan',
                        default         => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('metode')
                    ->label('Metode')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'daring'   => 'success',
                        'luring'   => 'primary',
                        'blended'  => 'warning',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'daring'  => 'Daring',
                        'luring'  => 'Luring',
                        'blended' => 'Blended',
                        default   => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('start_date')
                    ->date('d M Y')
                    ->sortable()
                    ->label('Mulai'),
                Tables\Columns\TextColumn::make('end_date')
                    ->date('d M Y')
                    ->sortable()
                    ->label('Selesai'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Aktif'),
                Tables\Columns\ToggleColumn::make('is_multiclass')
                    ->label('Multi-Kelas')
                    ->tooltip('Aktifkan jika kegiatan memiliki beberapa kelas peserta'),
                Tables\Columns\TextColumn::make('participants_count')
                    ->counts('participants')
                    ->label('Peserta'),
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
                Tables\Filters\SelectFilter::make('jenis_kegiatan')
                    ->label('Jenis Kegiatan')
                    ->options([
                        'pelatihan'     => 'Pelatihan',
                        'non-pelatihan' => 'Non-Pelatihan',
                    ]),
                Tables\Filters\SelectFilter::make('metode')
                    ->label('Metode')
                    ->options([
                        'daring'  => 'Daring',
                        'luring'  => 'Luring',
                        'blended' => 'Blended',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('copy')
                    ->label('Copy')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->modalHeading('Copy Kegiatan')
                    ->modalDescription('Ubah data kegiatan sebelum menyimpan. Petugas yang sudah di-assign akan ter-copy.')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Kegiatan')
                            ->required()
                            ->maxLength(255)
                            ->withProperCase()
                            ->default(fn (Survey $record) => 'Copy of ' . $record->name),
                        Forms\Components\Select::make('jenis_kegiatan')
                            ->label('Jenis Kegiatan')
                            ->options(['pelatihan' => 'Pelatihan', 'non-pelatihan' => 'Non-Pelatihan'])
                            ->default(fn (Survey $record) => $record->jenis_kegiatan ?? 'pelatihan')
                            ->required(),
                        Forms\Components\Select::make('metode')
                            ->label('Metode')
                            ->options(['daring' => 'Daring', 'luring' => 'Luring', 'blended' => 'Blended'])
                            ->default(fn (Survey $record) => $record->metode ?? 'luring')
                            ->required(),
                        Forms\Components\Textarea::make('tujuan')
                            ->label('Tujuan Kegiatan')
                            ->withProperCase()
                            ->default(fn (Survey $record) => $record->tujuan)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('dasar_surat')
                            ->label('Dasar Surat')
                            ->required()
                            ->withProperCase()
                            ->default(fn (Survey $record) => $record->dasar_surat)
                            ->columnSpanFull(),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Mulai')
                            ->default(fn (Survey $record) => $record->start_date),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Selesai')
                            ->default(fn (Survey $record) => $record->end_date),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(fn (Survey $record) => $record->is_active),
                        Forms\Components\Toggle::make('is_multiple')
                            ->label('Multiple Surat Tugas')
                            ->default(fn (Survey $record) => $record->is_multiple),
                        Forms\Components\Toggle::make('is_multiclass')
                            ->label('Kegiatan Multi-Kelas')
                            ->default(fn (Survey $record) => $record->is_multiclass),
                        Forms\Components\Toggle::make('copy_participants')
                            ->label('Copy Peserta')
                            ->helperText('Centang untuk meng-copy semua peserta yang sudah di-assign ke kegiatan asli')
                            ->default(true),
                    ])
                    ->action(function (Survey $record, array $data): void {
                        $copyParticipants = $data['copy_participants'] ?? false;
                        unset($data['copy_participants']);

                        $newSurvey = Survey::create($data);

                        if ($copyParticipants) {
                            $classMap = [];
                            if ($record->is_multiclass) {
                                foreach ($record->classes as $oldClass) {
                                    $newClass = $newSurvey->classes()->create([
                                        'name' => $oldClass->name,
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
                                    'status'          => $participant->status,
                                    'registered_at'   => now(),
                                    'notes'           => $participant->notes,
                                ]);
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Kegiatan berhasil di-copy!')
                            ->body($copyParticipants
                                ? 'Kegiatan baru dibuat dengan ' . (isset($participants) ? $participants->count() : 0) . ' peserta.'
                                : 'Kegiatan baru dibuat tanpa peserta.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('activities')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->url(fn ($record) => SurveyResource::getUrl('activities', ['record' => $record])),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('is_active', 'desc')->orderBy('created_at', 'desc'))
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
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
            'index'      => Pages\ListSurveys::route('/'),
            'create'     => Pages\CreateSurvey::route('/create'),
            'edit'       => Pages\EditSurvey::route('/{record}/edit'),
            'activities' => Pages\ListSurveyActivities::route('/{record}/activities'),
        ];
    }
}
