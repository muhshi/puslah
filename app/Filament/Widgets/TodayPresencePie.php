<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class TodayPresencePie extends ChartWidget
{
    protected ?string $heading = 'Distribusi Hari Ini';
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        $total = User::count();

        $hadir = Attendance::whereDate('created_at', $today)
            ->distinct('user_id')->count('user_id');

        $izin = Leave::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->distinct('user_id')->count('user_id');

        $tidak = max($total - $hadir - $izin, 0);

        return [
            'labels' => ['Hadir', 'Izin/Cuti', 'Tidak Hadir'],
            'datasets' => [
                [
                    'data' => [$hadir, $izin, $tidak],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
