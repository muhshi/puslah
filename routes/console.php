<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

\Illuminate\Support\Facades\Schedule::command('backup:clean')->weekly()->at('01:00');
\Illuminate\Support\Facades\Schedule::command('backup:run')->weekly()->at('01:30');
\Illuminate\Support\Facades\Schedule::command('surveys:deactivate-expired')->daily();
\Illuminate\Support\Facades\Schedule::command('activitylog:clean')->daily();
\Illuminate\Support\Facades\Schedule::command('app:generate-certificates')->daily();
\Illuminate\Support\Facades\Schedule::command('recap:update')->dailyAt('23:00');

// Pengingat Laporan Kinerja Harian Telegram (Multi-Tier, Hari Kerja)
\Illuminate\Support\Facades\Schedule::command('daily-activity:send-reminders --tier=15:00')->weekdays()->at('15:00')->timezone('Asia/Jakarta');
\Illuminate\Support\Facades\Schedule::command('daily-activity:send-reminders --tier=16:00')->weekdays()->at('16:00')->timezone('Asia/Jakarta');
\Illuminate\Support\Facades\Schedule::command('daily-activity:send-reminders --tier=18:30')->weekdays()->at('18:30')->timezone('Asia/Jakarta');

