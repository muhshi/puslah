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
        \Illuminate\Support\Facades\Gate::define('viewPulse', function (User $user) {
            // Pulse bisa diakses jika user punya role super_admin, Kepala, Kasubag, admin, Operator ATAU punya permission page_PulseAnalytics
            return $user->hasAnyRole(['super_admin', 'Kepala', 'Kasubag', 'admin', 'Operator']) || $user->can('page_PulseAnalytics');
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
