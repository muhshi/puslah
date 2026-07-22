<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DailyVisitsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Gunakan pulse_aggregates dengan period 1440 (1 hari = 1440 menit) untuk data harian
        $startOfDay = now()->startOfDay()->timestamp;

        // Hitung kunjungan (user_request) unik hari ini dari Laravel Pulse aggregates
        $uniqueUsersToday = DB::table('pulse_aggregates')
            ->where('period', 1440)
            ->where('type', 'user_request')
            ->where('bucket', $startOfDay)
            ->count('key_hash');
            
        // Hitung total request hari ini dari Pulse aggregates
        $totalRequestsToday = DB::table('pulse_aggregates')
            ->where('period', 1440)
            ->where('type', 'user_request')
            ->where('bucket', $startOfDay)
            ->sum('value');
            
        // Hitung jumlah aktivitas hari ini dari activity_log
        $activityCountToday = DB::table('activity_log')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
            
        // Generate trend untuk 7 hari terakhir
        $visitsTrend = [];
        $requestsTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayBucket = now()->subDays($i)->startOfDay()->timestamp;
            
            $visitsTrend[] = DB::table('pulse_aggregates')
                ->where('period', 1440)
                ->where('type', 'user_request')
                ->where('bucket', $dayBucket)
                ->count('key_hash');
                
            $requestsTrend[] = (int) DB::table('pulse_aggregates')
                ->where('period', 1440)
                ->where('type', 'user_request')
                ->where('bucket', $dayBucket)
                ->sum('value');
        }

        return [
            Stat::make('Kunjungan Unik (Hari Ini)', $uniqueUsersToday)
                ->description('Jumlah user unik yang mengakses hari ini')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->chart($visitsTrend),
                
            Stat::make('Total Interaksi / Request', $totalRequestsToday)
                ->description('Jumlah hits request hari ini')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info')
                ->chart($requestsTrend),
                
            Stat::make('Log Aktivitas Sistem', $activityCountToday)
                ->description('Tercatat di activity log hari ini')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('warning'),
        ];
    }
}
