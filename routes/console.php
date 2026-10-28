<?php

use App\Services\CurrencyService;
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

// Live USD → EGP rate (Admin → Currency): fetched ahead of its 12-hour cache running out, so no visitor waits
// for the API. If a fetch fails the website keeps the last rate (App\Services\CurrencyService).
Artisan::command('currency:refresh', function (CurrencyService $currency) {
    $live = $currency->liveRate(refresh: true);
    if ($live['source'] !== 'api') {
        $this->error("Live rate unavailable ({$live['error']}). The website uses 1 USD = {$live['rate']} EGP meanwhile.");

        return 1;
    }
    $this->info("1 USD = {$live['rate']} EGP");
})->purpose('Fetch the live USD to EGP rate now');

Schedule::command('currency:refresh')->twiceDaily(6, 18)->withoutOverlapping();
