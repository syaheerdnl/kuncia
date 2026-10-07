<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Kuncia billing (server cron runs `php artisan schedule:run` every minute)
Schedule::command('invoices:generate')->monthlyOn(1, '00:10')->withoutOverlapping();
Schedule::command('invoices:mark-overdue')->dailyAt('00:30')->withoutOverlapping();

// Guest sandbox for recruiters (only when KUNCIA_GUEST=true)
Schedule::command('kuncia:guest-reset')->dailyAt('03:00')->when(fn () => (bool) config('kuncia.guest.enabled'))->withoutOverlapping();
