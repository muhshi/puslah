<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\SuratTugas;
use App\Models\User;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\AttendanceResource\Pages\ListAttendances;
use App\Filament\Resources\AttendanceResource\Pages\CreateAttendance;
use App\Filament\Resources\AttendanceResource\Pages\ViewAttendance;
use App\Filament\Resources\AttendanceResource\Pages;
use App\Filament\Resources\AttendanceResource\RelationManagers;
use App\Models\Attendance;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Presensi';

    protected static ?string $navigationLabel = 'Presensi';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Nama')
                    ->relationship('user', 'name')
                    ->disabled()
                    ->required(),
                TextInput::make('schedule_latitude')
                    ->required()
                    ->numeric(),
                TextInput::make('schedule_longitude')
                    ->required()
                    ->numeric(),
                TextInput::make('schedule_start_time')
                    ->required(),
                TextInput::make('schedule_end_time')
                    ->required(),
                TextInput::make('start_latitude')
                    ->required()
                    ->numeric(),
                TextInput::make('start_longitude')
                    ->required()
                    ->numeric(),
                TextInput::make('start_time')
                    ->required(),
                TextInput::make('end_time')
                    ->required(),
                TextInput::make('end_latitude')
                    ->numeric(),
                TextInput::make('end_longitude')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with('user');

                if (!Auth::user()?->hasRole('super_admin')) {
                    $query->where('user_id', Auth::id());
                }
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->searchable()
                    ->date()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_late')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        return $record->isLate() ? 'Terlambat' : 'Tepat Waktu';
                    })
                    ->color(function ($record) {
                        return $record->isLate() ? 'danger' : 'success';
                    }),
                TextColumn::make('start_time')
                    ->label('Jam Datang')
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label('Jam Pulang')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?: ''),
                TextColumn::make('work_duration')
                    ->label('Durasi Kerja')
                    ->getStateUsing(fn($record) => $record->workDuration())
                    ->color(function ($record) {
                        if (!$record->end_time) {
                            return 'gray';        // belum checkout
                        }
                        return $record->underSevenHours() ? 'danger' : 'success';
                    }),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('survey_id')
                    ->label('Kegiatan Aktif')
                    ->options(fn() => Survey::where('is_active', true)
                        ->has('participants')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $surveyId = $data['value'];
                        $suUserIds = SurveyUser::where('survey_id', $surveyId)->pluck('user_id');
                        $stUserIds = SuratTugas::where('survey_id', $surveyId)->pluck('user_id');
                        $userIds = $suUserIds->merge($stUserIds)->unique()->filter()->values();

                        return $query->whereIn('attendances.user_id', $userIds);
                    }),

                SelectFilter::make('user_id')
                    ->label('Pegawai')
                    ->options(User::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                //Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export XLSX')
                    ->color('success')
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
            'index' => ListAttendances::route('/'),
            'create' => CreateAttendance::route('/create'),
            'view' => ViewAttendance::route('/{record}'),
            //'edit' => Pages\EditAttendance::route('/{record}/edit'),
        ];
    }
}
