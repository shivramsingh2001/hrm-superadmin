<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// --- Tenant lifecycle (Phase 3) ---------------------------------------------
// Requires the platform cron: `* * * * * php artisan schedule:run`

Schedule::command('tenants:trial-expiry')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('tenants:auto-suspend')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('tenants:execute-deletions')->dailyAt('03:30')->withoutOverlapping();

// --- Metrics & ops (Phase 8) ----------------------------------------------
Schedule::command('metrics:snapshot')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('metrics:digest')->weeklyOn(1, '07:30');           // Monday
Schedule::command('audit:verify')->dailyAt('04:00');                 // fails loudly if tampered
