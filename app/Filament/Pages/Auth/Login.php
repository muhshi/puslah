<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    public bool $showFallback = false;

    public function mount(): void
    {
        parent::mount();

        if (request()->query('fallback') === '1' || request()->query('fallback') === 'true') {
            $this->showFallback = true;
        }
    }

    public function toggleFallback(): void
    {
        $this->showFallback = ! $this->showFallback;
    }

    public function getHeading(): string | Htmlable
    {
        return 'Masuk ke DINAMIT';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Sistem Informasi Presensi & Kinerja Pegawai BPS Kab. Demak';
    }
}
