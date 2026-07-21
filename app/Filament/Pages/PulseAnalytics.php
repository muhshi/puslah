<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PulseAnalytics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 99;
    protected static ?string $title = 'Pulse Analytics';
    
    // Gunakan view kosong karena kita akan langsung redirect
    protected static string $view = 'filament.pages.pulse-analytics';

    public function mount()
    {
        return redirect()->to('/pulse');
    }
}
