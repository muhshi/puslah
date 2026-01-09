<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\SuratTugasChartWidget;
use App\Filament\Widgets\LatestSuratTugasWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.pages.dashboard';
    #[Url] public ?string $dateFrom = null;
    #[Url] public ?string $dateUntil = null;

    protected function isAdmin(): bool
    {
        // Check for super_admin or roles that should see the dashboard
        return Auth::user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag']);
    }

    protected function isPegawai(): bool
    {
        return Auth::user()->roles[0]->name == 'Pegawai BPS';
    }

    public function getWidgets(): array
    {
        return $this->isAdmin()
            ? [
                StatsOverviewWidget::class,
                SuratTugasChartWidget::class,
                LatestSuratTugasWidget::class,
            ]
            : [];
    }

    public function getColumns(): int|string|array
    {
        // 2 kolom di desktop, 1 di mobile
        return $this->isAdmin()
            ? ['md' => 2, 'xl' => 2]
            : 1;
    }
    public function getViewData(): array
    {
        return [
            'isAdmin' => $this->isAdmin(),
            'isPegawai' => $this->isPegawai(),
        ];
    }
}
