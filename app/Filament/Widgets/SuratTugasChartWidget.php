<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class SuratTugasChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Grafik Surat Tugas (Tahun Ini)';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = \Flowframe\Trend\Trend::model(\App\Models\SuratTugas::class)
            ->between(
                start: now()->startOfYear(),
                end: now()->endOfYear(),
            )
            ->perMonth()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Surat Tugas Dibuat',
                    'data' => $data->map(fn(\Flowframe\Trend\TrendValue $value) => $value->aggregate),
                    'borderColor' => '#005596', // BPS Blue
                ],
            ],
            'labels' => $data->map(fn(\Flowframe\Trend\TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
