<?php

namespace App\Providers;

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
        if (interface_exists(\Filament\Http\Responses\Auth\Contracts\LogoutResponse::class)) {
            $this->app->singleton(\Filament\Http\Responses\Auth\Contracts\LogoutResponse::class, \App\Http\Responses\SsoLogoutResponse::class);
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

        $socialite = $this->app->make(\Laravel\Socialite\Contracts\Factory::class);
        $socialite->extend('sipetra', function ($app) use ($socialite) {
            $config = $app['config']['services.sipetra'];

            return $socialite->buildProvider(\App\Providers\SipetraSocialiteProvider::class, $config);
        });

        \Laravel\Passport\Passport::viewPrefix('auth.oauth');
    }
}
