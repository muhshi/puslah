<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AttendanceLast7DaysChart;
use App\Filament\Widgets\TodayAttendanceStats;
use App\Filament\Widgets\TodayPresencePie;
use Faker\Provider\Base;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.dashboard';

    protected function isAdmin(): bool
    {
        // shield/spatie-roles
        return Auth::user()->roles[0]->name == 'super_admin';
        // kalau kamu pakai nama role lain, tambah OR di sini
        // || Auth::user()?->hasRole('admin')
    }

    public function getWidgets(): array
    {
        return $this->isAdmin()
            ? [
                TodayAttendanceStats::class,
                AttendanceLast7DaysChart::class,
                TodayPresencePie::class,
            ]
            : [];
    }

    public function getColumns(): int|string|array
    {
        // 2 kolom di desktop, 1 di mobile
        return $this->isAdmin()
            ? ['md' => 2, 'xl' => 2]
            : 1;
    }
    public function getViewData(): array
    {
        return [
            'isAdmin' => $this->isAdmin(),
        ];
    }
}
