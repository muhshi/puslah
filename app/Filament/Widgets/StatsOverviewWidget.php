<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\SuratTugas;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Surat Tugas Bulan Ini', SuratTugas::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count())
                ->description('Total surat tugas dibuat bulan ini')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('Pending Approval', SuratTugas::where('status', 'pending')->count())
                ->description('Menunggu persetujuan')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Survey Aktif', \App\Models\Survey::whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now())
                ->count())
                ->description('Sedang berlangsung')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('success'),

            Stat::make('Sertifikat Terbit', \App\Models\Certificate::count())
                ->description('Total sertifikat dikeluarkan')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),
        ];
    }
}
