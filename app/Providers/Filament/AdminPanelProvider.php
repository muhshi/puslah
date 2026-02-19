<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ProfileMe;
use App\Filament\Widgets\CalendarWidget;
use App\Http\Middleware\EnsureProfileComplete;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use Filament\Navigation\MenuItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('web')
            ->registration()
            ->userMenuItems([
                MenuItem::make()
                    ->label('Profile')
                    ->url(fn(): string => ProfileMe::getUrl())
                    ->icon('heroicon-o-user-circle'),
                // Menambahkan kembali item menu Logout
                'logout' => MenuItem::make()->label('Log out'),
            ])
            ->colors([
                'primary' => Color::hex('#005596'), // BPS Blue
                'gray' => Color::Slate,
                'info' => Color::hex('#03A9F4'),    // Light Blue
                'success' => Color::hex('#6CBE45'), // BPS Green
                'warning' => Color::hex('#F2911B'), // BPS Orange
                'danger' => Color::Red,
            ])
            ->font('Inter')
            ->brandName('DINAMIT')
            ->brandLogoHeight('3rem')
            ->favicon(asset('images/logo_bps.png'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                ProfileMe::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
                CalendarWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                //EnsureProfileComplete::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook('panels::body.end', fn() => view('filament.fallback-scripts'))
            ->plugins([
                FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3,
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
                FilamentFullCalendarPlugin::make(),
            ])
            ->renderHook('panels::auth.login.form.after', fn() => view('filament.auth.login-google'));
    }
}
