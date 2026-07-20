<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\User;

class DashboardStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Surat Tugas', SuratTugas::count())
                ->description('Surat tugas yang telah dibuat')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
                
            Stat::make('Total Survei', Survey::count())
                ->description('Survei yang terdaftar')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('success'),
                
            Stat::make('Total Pegawai', User::count())
                ->description('Pegawai BPS')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}
