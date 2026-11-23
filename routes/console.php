<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mofang:sync')->hourly()->withoutOverlapping();
Schedule::command('mofang:fulfill')->everyMinute()->withoutOverlapping();
Schedule::command('mofang:sync-services')->everyTenMinutes()->withoutOverlapping();
Schedule::command('services:bill')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('services:lifecycle')->hourly()->withoutOverlapping();
Schedule::command('services:notify')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('notifications:retry')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('finance:reconcile')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('orders:release-stock')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('tickets:prune-attachments --days=90')->dailyAt('04:30')->withoutOverlapping();
Schedule::command('settings:expire-changes --days=7')->dailyAt('05:00')->withoutOverlapping();
Schedule::command('ops:scan')->everyTenMinutes()->withoutOverlapping();
Schedule::command('ops:escalate')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('payments:sync')->everyMinute()->withoutOverlapping();
Schedule::command('backup:database')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('backup:verify')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('provision:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('nodes:sync')->everyMinute()->withoutOverlapping();
Schedule::command('services:metrics')->everyMinute()->withoutOverlapping();
Schedule::command('services:guest-passwords')->everyMinute()->withoutOverlapping();
