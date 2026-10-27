<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queued emails (password reset, contact messages): the server's cron runs `schedule:run` every minute, which
// works through the queue and stops, so no long-running `queue:work` process is needed on shared hosting.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')
    ->everyMinute()
    // The lock expires after 10 minutes (default 24 hours), so a run that was cut off can't block the queue for a day
    ->withoutOverlapping(10);
