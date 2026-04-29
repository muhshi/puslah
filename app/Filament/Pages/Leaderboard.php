<?php

namespace App\Filament\Pages;

use App\Models\User;
use Carbon\Carbon;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class Leaderboard extends Page
{
    use HasPageShield;

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
        $this->currentMonthName = Carbon::now()->locale('id')->translatedFormat('F Y');
        $this->pegawaiData = $this->getLeaderboardData('Organik');
        $this->mitraData = $this->getLeaderboardData('Mitra');
    }

    protected function getLeaderboardData(string $roleName): Collection
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

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
