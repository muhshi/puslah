<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class PulseAnalytics extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 99;
    protected static ?string $title = 'Pulse Analytics';

    protected static string $view = 'filament.pages.pulse-analytics';

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        return url('/pulse');
    }

    public function mount(): void
    {
        redirect()->to(url('/pulse'))->send();
    }
}
