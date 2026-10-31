<?php

namespace App\Filament\Pages;

use App\Models\SuratTugas;
use App\Models\User;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class WorkloadHeatmap extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Manajemen Dokumen';
    protected static ?string $navigationLabel = 'Heatmap Beban Petugas';
    protected static ?string $title = 'Heatmap Distribusi Beban Petugas';
    protected static string $view = 'filament.pages.workload-heatmap';
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        return $user->hasAnyRole(['super_admin', 'Kepala', 'Kasubag', 'Operator', 'Ketua Tim', 'Pegawai']);
    }

    public int $selectedMonth;
    public int $selectedYear;
    public string $selectedRole = 'all';
    public string $search = '';

    public function mount(): void
    {
        $this->selectedMonth = (int) now()->month;
        $this->selectedYear = (int) now()->year;
    }

    public function getMonthsProperty(): array
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
    }

    public function getYearsProperty(): array
    {
        $current = (int) now()->year;
        return [
            $current - 1 => (string) ($current - 1),
            $current => (string) $current,
            $current + 1 => (string) ($current + 1),
        ];
    }

    public function getRolesProperty(): array
    {
        return \Spatie\Permission\Models\Role::query()
            ->whereNotIn('name', ['Terlampir'])
            ->orderBy('name')
            ->pluck('name', 'name')
            ->toArray();
    }

    public function getHeatmapData(): array
    {
        $startOfMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfDay();
        $daysInMonth = $startOfMonth->daysInMonth;
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        // Query users with profile and roles
        $usersQuery = User::query()
            ->with(['profile', 'roles'])
            ->whereHas('roles')
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'Terlampir');
            });

        if (!empty($this->search)) {
            $search = '%' . strtolower($this->search) . '%';
            $usersQuery->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', [$search])
                  ->orWhereHas('profile', function ($pq) use ($search) {
                      $pq->whereRaw('LOWER(full_name) LIKE ?', [$search])
                         ->orWhereRaw('LOWER(nip) LIKE ?', [$search]);
                  });
            });
        }

        if ($this->selectedRole !== 'all') {
            $usersQuery->whereHas('roles', function ($q) {
                $q->where('name', $this->selectedRole);
            });
        }

        $users = $usersQuery->orderBy('name')->get();

        // Get all SuratTugas in this month
        $suratTugasList = SuratTugas::query()
            ->with('survey:id,name')
            ->where('waktu_mulai', '<=', $endOfMonth)
            ->where('waktu_selesai', '>=', $startOfMonth)
            ->whereIn('user_id', $users->pluck('id'))
            ->get();

        // Group surat tugas by user_id
        $suratByUser = $suratTugasList->groupBy('user_id');

        $result = [];

        foreach ($users as $user) {
            $userSurats = $suratByUser->get($user->id, collect());
            $days = array_fill(1, $daysInMonth, ['count' => 0, 'tasks' => []]);
            $totalActiveDays = 0;

            foreach ($userSurats as $st) {
                $taskStart = Carbon::parse($st->waktu_mulai);
                $taskEnd = Carbon::parse($st->waktu_selesai);

                // Clamp to selected month
                $effectiveStart = $taskStart->greaterThan($startOfMonth) ? (int) $taskStart->day : 1;
                $effectiveEnd = $taskEnd->lessThan($endOfMonth) ? (int) $taskEnd->day : $daysInMonth;

                // Mark days
                for ($d = $effectiveStart; $d <= $effectiveEnd; $d++) {
                    $days[$d]['count']++;
                    $days[$d]['tasks'][] = [
                        'nomor' => $st->nomor_surat ?? 'Draft',
                        'keperluan' => $st->keperluan ?? ($st->survey?->name ?? 'Tugas Lapangan'),
                        'tempat' => $st->tempat_tugas ?? '-',
                        'status' => $st->status,
                    ];
                }
            }

            foreach ($days as $dayData) {
                if ($dayData['count'] > 0) {
                    $totalActiveDays++;
                }
            }

            $primaryRole = $user->roles->first()?->name ?? 'User';

            $result[] = [
                'id' => $user->id,
                'name' => $user->profile?->full_name ?? $user->name,
                'nip' => $user->profile?->nip ?? '-',
                'role' => $primaryRole,
                'days' => $days,
                'total_active_days' => $totalActiveDays,
                'total_tasks' => $userSurats->count(),
            ];
        }

        // Sort: user with highest workload first, then name
        usort($result, function ($a, $b) {
            if ($b['total_active_days'] === $a['total_active_days']) {
                return strcmp($a['name'], $b['name']);
            }
            return $b['total_active_days'] <=> $a['total_active_days'];
        });

        return [
            'days_in_month' => $daysInMonth,
            'start_of_month' => $startOfMonth,
            'users' => $result,
        ];
    }
}
