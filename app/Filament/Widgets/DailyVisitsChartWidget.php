<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DailyVisitsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Trend Kunjungan Sistem (7 Hari Terakhir)';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $labels = [];
        $visitsData = [];
        $requestsData = [];

        // Ambil data untuk 7 hari terakhir
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStart = $date->startOfDay()->timestamp;
            $dayEnd = $date->endOfDay()->timestamp;
            
            $labels[] = $date->translatedFormat('d M');
            
            $visitsData[] = DB::table('pulse_aggregates')
                ->where('period', 1440)
                ->where('type', 'user_request')
                ->whereBetween('bucket', [$dayStart, $dayEnd])
                ->count('key_hash');
                
            $requestsData[] = (int) DB::table('pulse_aggregates')
                ->where('period', 1440)
                ->where('type', 'user_request')
                ->whereBetween('bucket', [$dayStart, $dayEnd])
                ->sum('value');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Kunjungan Unik',
                    'data' => $visitsData,
                    'borderColor' => '#10b981', // green / success
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Total Request',
                    'data' => $requestsData,
                    'borderColor' => '#3b82f6', // blue / info
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
