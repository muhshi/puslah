<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Rekap Presensi per Individu, ter-filter per Survey.
 * - Jika user bukan role "super_admin", hanya menampilkan datanya sendiri.
 */
class AttendanceRecap extends Page implements Tables\Contracts\HasTable, Forms\Contracts\HasForms
{
    use Tables\Concerns\InteractsWithTable;
    use Forms\Concerns\InteractsWithForms;
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-c-clipboard-document-list';
    protected static ?string $navigationGroup = 'Manajemen Presensi';
    protected static ?string $navigationLabel = 'Rekap Presensi';
    protected static string $view = 'filament.pages.attendance-recap';
    protected static ?int $navigationSort = 10;

    public ?int $surveyId = null;

    public function mount(): void
    {
        // default ambil survey aktif kalau ada
        $active = Survey::query()->where('is_active', true)->latest('start_date')->first();
        if ($active) {
            $this->surveyId = $active->id;
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Group::make()
                ->schema([
                    Section::make('Filter')
                        ->schema([
                            Select::make('surveyId')
                                ->label('Pilih Survey')
                                ->options(fn() => $this->surveyOptions())
                                ->searchable()
                                ->reactive()
                                ->required(),
                        ])
                        ->columns(1)
                        ->collapsible(false),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return \Filament\Tables\Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading('Rekap per Individu')
            ->defaultSort('name', 'asc') // pakai default sort di Table, bukan di query
            ->query(fn() => $this->baseQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->sortable(),

                Tables\Columns\TextColumn::make('present_count')->label('Hadir')
                    ->sortable(query: fn($query, $direction) => $query->orderBy('present_count', $direction)),

                Tables\Columns\TextColumn::make('late_count')
                    ->label('Terlambat')
                    ->sortable(),

                Tables\Columns\TextColumn::make('under_7h_count')
                    ->label('< 7 Jam')
                    ->sortable(),

                Tables\Columns\TextColumn::make('no_checkout_count')
                    ->label('Tidak Checkout')
                    ->sortable(),

                Tables\Columns\TextColumn::make('leave_approved_days')
                    ->label('Cuti (approved)')
                    ->sortable(),

                // alpa: sekarang sudah alias selectSub → bisa sortable biasa
                Tables\Columns\TextColumn::make('alpa_days')
                    ->label('Alpa (tanpa izin)')
                    ->tooltip('Perkiraan: Hari kerja − Hadir − Cuti')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->headerActions([
                Tables\Actions\Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        /** @var \Illuminate\Support\Collection<int,array<string,int|string>> $rows */
                        $rows = $this->baseQuery()->get()->map(function ($u) {
                            return [
                                'Nama' => $u->name,
                                'Email' => $u->email,
                                'Hadir' => (int) $u->present_count,
                                'Terlambat' => (int) $u->late_count,
                                '< 7 Jam' => (int) $u->under_7h_count,
                                'Tidak Checkout' => (int) $u->no_checkout_count,
                                'Cuti (approved)' => (int) $u->leave_approved_days,
                                'Alpa' => (int) $u->alpa_days,
                            ];
                        });

                        $filename = 'rekap-presensi-' . now()->format('Ymd-His') . '.csv';

                        return response()->streamDownload(function () use ($rows) {
                            $out = fopen('php://output', 'w');
                            if ($rows->isNotEmpty()) {
                                fputcsv($out, array_keys($rows->first()));
                                foreach ($rows as $r)
                                    fputcsv($out, $r);
                            } else {
                                fputcsv($out, ['(kosong)']);
                            }
                            fclose($out);
                        }, $filename, [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ]);
    }

    /**
     * Query rekap pakai tabel attendance_daily_recaps
     * - Filter peserta dari survey + rentang tanggal survey (end dibatasi hari ini).
     * - Super admin lihat semua, selain itu hanya dirinya sendiri.
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\User>
     */
    protected function baseQuery(): Builder
    {
        if (!$this->surveyId) {
            return User::query()->whereRaw('1=0');
        }

        $survey = Survey::find($this->surveyId);
        if (!$survey) {
            return User::query()->whereRaw('1=0');
        }

        $start = optional($survey->start_date)?->toDateString();
        $end = optional($survey->end_date)?->toDateString();
        $today = now('Asia/Jakarta')->toDateString();

        if ($end && $end > $today) {
            $end = $today;
        }

        // peserta survey
        $participantIds = SurveyUser::where('survey_id', $this->surveyId)
            ->pluck('user_id');

        // akses: selain super_admin, hanya dirinya
        if (!isAdmin() && auth()->check()) {
            $participantIds = $participantIds->intersect([auth()->id()]);
        }

        // query: SUM recap per user
        $query = User::query()
            ->whereIn('users.id', $participantIds)
            ->leftJoin('attendance_daily_recaps as r', function ($join) use ($start, $end) {
                $join->on('r.user_id', '=', 'users.id')
                    ->whereBetween('r.work_date', [$start, $end]);
            })
            ->select('users.*')
            ->selectRaw('COALESCE(SUM(r.present),0)      as present_count')
            ->selectRaw('COALESCE(SUM(r.late),0)         as late_count')
            ->selectRaw('COALESCE(SUM(r.under_7h),0)     as under_7h_count')
            ->selectRaw('COALESCE(SUM(r.no_checkout),0)  as no_checkout_count')
            ->selectRaw('COALESCE(SUM(r.leave),0)        as leave_approved_days')
            ->selectRaw('COALESCE(SUM(r.alpa),0)         as alpa_days')
            ->groupBy('users.id');

        return $query;
    }


    protected function surveyOptions(): array
    {
        $query = Survey::query()->orderBy('start_date', 'desc');

        if (!isAdmin() && auth()->check()) {
            $userSurveyIds = SurveyUser::where('user_id', auth()->id())
                ->pluck('survey_id');
            $query->whereIn('id', $userSurveyIds);
        }

        return $query->pluck('name', 'id')->toArray();
    }

    /**
     * Hitung jumlah hari kerja (Mon–Fri) dalam rentang.
     */
    protected function estimateWorkdays(?Carbon $start, ?Carbon $end): int
    {
        if (!$start || !$end)
            return 0;
        $count = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dow = (int) $cursor->dayOfWeekIso; // 1=Mon..7=Sun
            if ($dow >= 1 && $dow <= 5)
                $count++;
            $cursor->addDay();
        }
        return $count;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
