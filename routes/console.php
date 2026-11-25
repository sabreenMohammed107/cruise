<?php

use App\Enums\SailingStatus;
use App\Models\Sailing;
use App\Services\CurrencyService;
use App\Services\UnpaidBookings;
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
        $this->error("Live rate unavailable ({$live['error']}). The website uses 1 USD = {$currency->rate('EGP')} EGP meanwhile.");

        return 1;
    }
    $this->info("1 USD = {$live['rate']} EGP (the website uses {$currency->rate('EGP')} EGP)");
})->purpose('Fetch the live USD to EGP rate now');

Schedule::command('currency:refresh')->twiceDaily(6, 18)->withoutOverlapping();

// Sailings close at the start of the day they depart: from 00:00 the website (Sailing::upcoming()) stops
// offering them and the admin shows them in the archive (Sailing::finished()), both from the date alone.
// This marks them closed too, so their status says so wherever it's shown.
Artisan::command('sailings:close', function () {
    $closed = Sailing::where('status', SailingStatus::Open)
        ->whereDate('departure_date', '<=', Sailing::archiveCutoff())
        ->update(['status' => SailingStatus::Closed]);
    $this->info("Closed {$closed} sailing(s) departing today or earlier.");
})->purpose('Close bookings for sailings departing today or earlier');

Schedule::command('sailings:close')->dailyAt('00:02')->withoutOverlapping();

// New bookings are held for config cruise.hold_hours (24) hours: one with nothing paid when its hold is over is
// cancelled, its cabins go back on sale, and the guest is emailed (App\Services\UnpaidBookings).
Artisan::command('bookings:cancel-unpaid', function (UnpaidBookings $unpaid) {
    $cancelled = $unpaid->cancelExpired();
    $this->info(count($cancelled).' unpaid booking(s) cancelled'.($cancelled ? ': '.implode(', ', $cancelled) : '.'));
})->purpose('Cancel bookings held without payment past their hold');

Schedule::command('bookings:cancel-unpaid')->everyFiveMinutes()->withoutOverlapping(10);
