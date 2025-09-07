<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AttendanceLast7DaysChart;
use App\Filament\Widgets\TodayAttendanceStats;
use App\Filament\Widgets\TodayPresencePie;
use Faker\Provider\Base;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.dashboard';

    public function getWidgets(): array
    {
        return [
            TodayAttendanceStats::class,
            AttendanceLast7DaysChart::class,
            TodayPresencePie::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        // 2 kolom di desktop, 1 di mobile
        return [
            'sm' => 1,
            'xl' => 2,
        ];
    }
}
