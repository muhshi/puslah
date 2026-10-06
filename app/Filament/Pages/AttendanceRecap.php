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
    public ?array $data = [];

    public function mount(): void
    {
        // Default pilih survey aktif yang anggotanya memiliki aktivitas presensi
        $activeWithAttendance = Survey::query()
            ->where('is_active', true)
            ->withAttendanceActivity()
            ->latest('start_date')
            ->first();

        if ($activeWithAttendance) {
            $this->surveyId = $activeWithAttendance->id;
        } else {
            $anyWithAttendance = Survey::query()
                ->withAttendanceActivity()
                ->latest('start_date')
                ->first();
            $this->surveyId = $anyWithAttendance?->id ?? Survey::query()->where('is_active', true)->latest('start_date')->first()?->id;
        }

        $this->form->fill([
            'surveyId' => $this->surveyId,
        ]);
    }

    public function updated($property): void
    {
        if ($property === 'data.surveyId' || $property === 'surveyId') {
            $this->surveyId = (int) ($this->data['surveyId'] ?? $this->surveyId);
            $this->resetTable();
        }
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Filter Rekap Presensi')
                            ->description('Pilih kegiatan / survei untuk melihat ringkasan rekap kehadiran petugas.')
                            ->icon('heroicon-o-funnel')
                            ->schema([
                                Select::make('surveyId')
                                    ->label('Pilih Kegiatan / Survei')
                                    ->prefixIcon('heroicon-m-clipboard-document-check')
                                    ->options(fn() => $this->surveyOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state) {
                                        $this->surveyId = $state ? (int) $state : null;
                                        $this->resetTable();
                                    })
                                    ->required()
                                    ->helperText('Hanya menampilkan kegiatan/survei yang memiliki aktivitas presensi dari anggotanya.'),
                            ])
                            ->columns(1)
                            ->collapsible(false),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /**
     * @return \Filament\Tables\Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading('Rekap Presensi per Petugas')
            ->defaultSort('name', 'asc')
            ->query(fn() => $this->baseQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('present_count')
                    ->label('Hadir')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('late_count')
                    ->label('Terlambat')
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'warning' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('under_7h_count')
                    ->label('< 7 Jam')
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'warning' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('no_checkout_count')
                    ->label('Tidak Checkout')
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'danger' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('leave_approved_days')
                    ->label('Izin (Approved)')
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'info' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('alpa_days')
                    ->label('Alpa (tanpa izin)')
                    ->tooltip('Perkiraan: Hari kerja − Hadir − Izin')
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'danger' : 'gray')
                    ->getStateUsing(function ($record) {
                        $today = now('Asia/Jakarta')->toDateString();
                        $survey = $this->surveyId ? Survey::find($this->surveyId) : null;
                        $start = $survey?->start_date;
                        $end = $survey?->end_date;
                        if ($end && $end->toDateString() > $today) {
                            $end = Carbon::parse($today);
                        }
                        $workdays = $this->estimateWorkdays($start, $end);
                        return max(0, $workdays - (int) $record->present_count - (int) $record->leave_approved_days);
                    }),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->headerActions([
                Tables\Actions\Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $today = now('Asia/Jakarta')->toDateString();
                        $survey = $this->surveyId ? Survey::find($this->surveyId) : null;
                        $start = $survey?->start_date;
                        $end = $survey?->end_date;
                        if ($end && $end->toDateString() > $today) {
                            $end = Carbon::parse($today);
                        }
                        $workdays = $this->estimateWorkdays($start, $end);

                        /** @var \Illuminate\Support\Collection<int,array<string,int|string>> $rows */
                        $rows = $this->baseQuery()->get()->map(function ($u) use ($workdays) {
                            return [
                                'Nama' => $u->name,
                                'Email' => $u->email,
                                'Hadir' => (int) $u->present_count,
                                'Terlambat' => (int) $u->late_count,
                                '< 7 Jam' => (int) $u->under_7h_count,
                                'Tidak Checkout' => (int) $u->no_checkout_count,
                                'Izin (Approved)' => (int) $u->leave_approved_days,
                                'Alpa' => max(0, $workdays - (int) $u->present_count - (int) $u->leave_approved_days),
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
     * Query rekap realtime langsung dari tabel attendances dan leaves
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

        $isSqlite = DB::getDriverName() === 'sqlite';
        $timeDiffExpr = $isSqlite
            ? "((strftime('%s', attendances.end_time) - strftime('%s', attendances.start_time)) / 60) < 420"
            : "TIMESTAMPDIFF(MINUTE, attendances.start_time, attendances.end_time) < 420";

        return User::query()
            ->whereIn('users.id', $participantIds)
            ->select('users.*')
            ->selectSub(function ($q) use ($start, $end) {
                $q->from('attendances')
                    ->whereColumn('attendances.user_id', 'users.id')
                    ->when($start, fn($sq) => $sq->whereDate('attendances.created_at', '>=', $start))
                    ->when($end, fn($sq) => $sq->whereDate('attendances.created_at', '<=', $end))
                    ->selectRaw('COUNT(DISTINCT DATE(attendances.created_at))');
            }, 'present_count')
            ->selectSub(function ($q) use ($start, $end) {
                $q->from('attendances')
                    ->whereColumn('attendances.user_id', 'users.id')
                    ->when($start, fn($sq) => $sq->whereDate('attendances.created_at', '>=', $start))
                    ->when($end, fn($sq) => $sq->whereDate('attendances.created_at', '<=', $end))
                    ->whereColumn('attendances.start_time', '>', 'attendances.schedule_start_time')
                    ->selectRaw('COUNT(*)');
            }, 'late_count')
            ->selectSub(function ($q) use ($start, $end, $timeDiffExpr) {
                $q->from('attendances')
                    ->whereColumn('attendances.user_id', 'users.id')
                    ->when($start, fn($sq) => $sq->whereDate('attendances.created_at', '>=', $start))
                    ->when($end, fn($sq) => $sq->whereDate('attendances.created_at', '<=', $end))
                    ->whereNotNull('attendances.end_time')
                    ->whereRaw($timeDiffExpr)
                    ->selectRaw('COUNT(*)');
            }, 'under_7h_count')
            ->selectSub(function ($q) use ($start, $end) {
                $q->from('attendances')
                    ->whereColumn('attendances.user_id', 'users.id')
                    ->when($start, fn($sq) => $sq->whereDate('attendances.created_at', '>=', $start))
                    ->when($end, fn($sq) => $sq->whereDate('attendances.created_at', '<=', $end))
                    ->where(function ($sq) {
                        $sq->whereNull('attendances.end_time')->orWhere('attendances.end_time', '');
                    })
                    ->selectRaw('COUNT(*)');
            }, 'no_checkout_count')
            ->selectSub(function ($q) use ($start, $end) {
                $q->from('leaves')
                    ->whereColumn('leaves.user_id', 'users.id')
                    ->where('leaves.status', 'approved')
                    ->when($start, fn($sq) => $sq->whereDate('leaves.end_date', '>=', $start))
                    ->when($end, fn($sq) => $sq->whereDate('leaves.start_date', '<=', $end))
                    ->selectRaw('COUNT(*)');
            }, 'leave_approved_days');
    }

    protected function surveyOptions(): array
    {
        $query = Survey::query()
            ->withAttendanceActivity()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date');

        if (!isAdmin() && auth()->check()) {
            $userSurveyIds = SurveyUser::where('user_id', auth()->id())
                ->pluck('survey_id');
            $query->whereIn('id', $userSurveyIds);
        }

        $surveys = $query->get();

        if ($surveys->isEmpty()) {
            $surveys = Survey::query()->where('is_active', true)->orderByDesc('start_date')->get();
        }

        return $surveys->mapWithKeys(function ($survey) {
            $status = $survey->is_active ? 'Aktif' : 'Non-aktif';
            $dates = '';
            if ($survey->start_date && $survey->end_date) {
                $dates = ' (' . $survey->start_date->format('d/m/Y') . ' - ' . $survey->end_date->format('d/m/Y') . ')';
            }
            return [$survey->id => "{$survey->name}{$dates} [{$status}]"];
        })->toArray();
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
