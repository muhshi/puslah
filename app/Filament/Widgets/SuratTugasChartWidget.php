<?php

namespace App\Filament\Widgets;

use App\Models\SuratTugas;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class SuratTugasChartWidget extends ChartWidget
{
    protected ?string $heading = 'Grafik Surat Tugas (Tahun Ini)';
    protected static ?int $sort = 2;
    protected static ?bool $isLazyLoaded = true;

    protected function getData(): array
    {
        $data = Trend::model(SuratTugas::class)
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
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                    'borderColor' => '#005596', // BPS Blue
                ],
            ],
            'labels' => $data->map(fn(TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
