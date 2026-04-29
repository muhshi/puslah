<?php

namespace App\Filament\Pages;

use App\Models\User;
use Carbon\Carbon;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class Leaderboard extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    public ?array $filter = [
        'month' => null,
        'year' => null,
    ];

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static string $view = 'filament.pages.leaderboard';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Leaderboard';

    protected static ?string $title = 'Leaderboard Tugas';

    protected static ?int $navigationSort = 10;

    public Collection $pegawaiData;
    public Collection $mitraData;
    public string $currentMonthName;

    public function mount(): void
    {
        $this->filter['month'] = now()->month;
        $this->filter['year'] = now()->year;
        $this->updateData();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    Select::make('month')
                        ->label('Pilih Bulan')
                        ->options([
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                        ])
                        ->native(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn () => $this->updateData()),
                    Select::make('year')
                        ->label('Pilih Tahun')
                        ->options(function () {
                            $years = range(now()->year, 2024);
                            return array_combine($years, $years);
                        })
                        ->native(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn () => $this->updateData()),
                ])
            ])
            ->statePath('filter');
    }

    public function updateData(): void
    {
        $month = $this->filter['month'];
        $year = $this->filter['year'];
        $date = Carbon::create($year, $month, 1);

        $this->currentMonthName = $date->locale('id')->translatedFormat('F Y');
        $this->pegawaiData = $this->getLeaderboardData('Organik', $month, $year);
        $this->mitraData = $this->getLeaderboardData('Mitra', $month, $year);
    }

    protected function getLeaderboardData(string $roleName, int $month, int $year): Collection
    {
        $date = Carbon::create($year, $month, 1);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        return User::role($roleName)
            ->with(['profile'])
            ->withCount(['suratTugas' => function ($query) use ($startOfMonth, $endOfMonth) {
                $query->whereBetween('tanggal', [$startOfMonth, $endOfMonth]);
            }])
            ->orderBy('surat_tugas_count', 'desc')
            ->take(12) // Show top 12
            ->get()
            ->map(function ($user) {
                return (object) [
                    'id' => $user->id,
                    'name' => $user->name,
                    'jabatan' => $user->jabatan ?? $user->profile?->jabatan ?? 'Pegawai',
                    'avatar' => $user->profile?->avatar_path,
                    'count' => $user->surat_tugas_count,
                    'initials' => collect(explode(' ', $user->name))
                        ->map(fn ($n) => mb_substr($n, 0, 1))
                        ->take(2)
                        ->join(''),
                ];
            });
    }
}
