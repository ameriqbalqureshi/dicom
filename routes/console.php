<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune')->daily();
Schedule::command('studies:delete-expired')->hourly();
Schedule::command('uploads:prune-incoming')->hourly();
Schedule::command('packages:send-expiry-reminders')->dailyAt('09:00');

/*
 * Shared hosting cannot keep a queue worker running, so the scheduler sends queued emails each minute. A worker started
 * this way stops once the queue is empty, and before the next run begins.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
