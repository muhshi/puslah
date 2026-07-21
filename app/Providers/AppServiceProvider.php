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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::define('viewPulse', function (?User $user = null) {
            if (! $user) {
                return false;
            }
            return $user->hasAnyRole(['super_admin', 'Super Admin', 'Kepala', 'Kasubag', 'admin', 'Admin', 'Operator'])
                || $user->can('page_PulseAnalytics')
                || $user->roles()->exists();
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        User::observe(UserObserver::class);

        $socialite = $this->app->make(\Laravel\Socialite\Contracts\Factory::class);
        $socialite->extend('sipetra', function ($app) use ($socialite) {
            $config = $app['config']['services.sipetra'];
            return $socialite->buildProvider(\App\Providers\SipetraSocialiteProvider::class, $config);
        });
    }
}
