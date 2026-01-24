<?php

namespace App\Filament\Widgets;

use App\Models\Survey;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class SurveyChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Grafik Jumlah Survei (Tahun Ini)';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $data = Trend::model(Survey::class)
            ->between(
                start: now()->startOfYear(),
                end: now()->endOfYear(),
            )
            ->perMonth()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Survei Dibuat',
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                    'borderColor' => '#6CBE45', // BPS Green
                    'backgroundColor' => 'rgba(108, 190, 69, 0.1)',
                    'fill' => 'start',
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
