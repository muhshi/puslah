<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

\Illuminate\Support\Facades\Schedule::command('backup:clean')->weekly()->at('01:00');
\Illuminate\Support\Facades\Schedule::command('backup:run')->weekly()->at('01:30');
\Illuminate\Support\Facades\Schedule::command('surveys:deactivate-expired')->daily();
