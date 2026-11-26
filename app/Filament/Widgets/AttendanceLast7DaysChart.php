<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class AttendanceLast7DaysChart extends ChartWidget
{
    protected ?string $heading = 'Hadir 7 Hari Terakhir';
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $tz = 'Asia/Jakarta';
        $labels = [];
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today($tz)->subDays($i);
            $labels[] = $d->format('d M');

            $count = Attendance::whereDate('created_at', $d->toDateString())
                ->distinct('user_id')->count('user_id');

            $data[] = $count;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Hadir',
                    'data' => $data,
                    // biarin default color dari Filament/Chart.js
                    'tension' => 0.3,
                    'fill' => false,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
