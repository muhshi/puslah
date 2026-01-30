<?php

namespace App\Filament\Resources\SuratTugasResource\Widgets;

use App\Models\SuratTugas;
use Filament\Widgets\Widget;

class SkippedNumbersInfoWidget extends Widget
{
    protected static string $view = 'filament.resources.surat-tugas-resource.widgets.skipped-numbers-info';

    protected int|string|array $columnSpan = 'full';

    public int $selectedYear;

    public function mount(): void
    {
        $this->selectedYear = now()->year;
    }

    public function getSkippedNumbersByMonth(): array
    {
        return SuratTugas::getSkippedNumbersByMonth($this->selectedYear);
    }

    public function getAvailableYears(): array
    {
        $years = SuratTugas::query()
            ->selectRaw('YEAR(tanggal) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        if (empty($years)) {
            return [now()->year => now()->year];
        }

        // Include current year if not in list
        if (!in_array(now()->year, $years)) {
            array_unshift($years, now()->year);
        }

        return array_combine($years, $years);
    }

    public function updatedSelectedYear(): void
    {
        // Widget akan re-render otomatis saat year berubah
    }
}
