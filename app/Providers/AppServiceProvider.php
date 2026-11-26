<?php

namespace App\Providers;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use App\Http\Responses\SsoLogoutResponse;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Passport\Passport;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (interface_exists(LogoutResponse::class)) {
            $this->app->singleton(LogoutResponse::class, SsoLogoutResponse::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        User::observe(UserObserver::class);

        $socialite = $this->app->make(Factory::class);
        $socialite->extend('sipetra', function ($app) use ($socialite) {
            $config = $app['config']['services.sipetra'];

            return $socialite->buildProvider(SipetraSocialiteProvider::class, $config);
        });

        Passport::viewPrefix('auth.oauth');

        // Macro: Tombol Proper Case seragam untuk semua input TextInput & Textarea (Filament v5)
        $registerProperCaseAction = function (string $componentClass) {
            $componentClass::macro('withProperCase', function (?string $tooltip = 'Ubah ke format Proper Case') {
                return $this->suffixAction(
                    \Filament\Actions\Action::make('properCase')
                        ->label('Proper Case')
                        ->icon('heroicon-m-sparkles')
                        ->color('gray')
                        ->tooltip($tooltip)
                        ->action(function (\Filament\Schemas\Components\Utilities\Set $set, $component) {
                            $val = $component->getState();
                            if (is_string($val) && filled($val)) {
                                $cleaned = trim(preg_replace('/\s+/', ' ', $val));
                                $proper = mb_convert_case($cleaned, MB_CASE_TITLE, 'UTF-8');
                                $set($component->getStatePath(), $proper);
                                $set($component->getName(), $proper);
                            }
                        })
                );
            });
        };

        $registerProperCaseAction(\Filament\Forms\Components\TextInput::class);
                // $registerProperCaseAction(\Filament\Forms\Components\Textarea::class); // Textarea lacks suffixAction
    }
}
