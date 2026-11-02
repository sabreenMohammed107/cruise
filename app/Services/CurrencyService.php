<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Display currency of the website: prices are stored, quoted and booked in USD; a visitor can choose to see them in
 * EGP (header switcher, kept in the session). SC.money() in core.js multiplies by SC_DATA.exchangeRate.
 *
 * The EGP rate comes from Admin → Pricing & offers → Currency (site_settings rows, defaults in config/cruise.php):
 *   manual  the admin's rate
 *   api     the live rate from ExchangeRate-API (config/services.php exchange_rate), cached for cache_hours.
 *           If a fetch fails: the last rate fetched, or else the admin's rate; retried after retry_minutes.
 * The admin's markup (EGP per USD) is added on top of rates from the API only (automatic mode): rate() is then
 * live rate + markup, and that is what visitors see and bookings keep. The admin's own rate (manual mode, or the
 * fallback when the API never worked) is used as entered. The cache holds the API rate only, so a new markup
 * applies at once.
 */
class CurrencyService
{
    public const BASE = 'USD';

    public const MODE_API = 'api';

    public const MODE_MANUAL = 'manual';

    public const SESSION = 'currency';

    private const CACHE = 'currency.rate.usd_egp';

    /** @return list<string> e.g. ["USD", "EGP"] */
    public function currencies(): array
    {
        return array_keys(config('cruise.display_currencies'));
    }

    /** The visitor's currency (session), USD when never chosen. */
    public function active(): string
    {
        $code = session(self::SESSION);

        return in_array($code, $this->currencies(), true) ? $code : self::BASE;
    }

    public function setActive(string $code): void
    {
        session([self::SESSION => $code]);
    }

    public function symbol(?string $code = null): string
    {
        return config('cruise.display_currencies.'.($code ?? $this->active()).'.symbol', '');
    }

    public function mode(): string
    {
        return SiteSetting::value('currency_mode') === self::MODE_MANUAL ? self::MODE_MANUAL : self::MODE_API;
    }

    /** EGP per 1 USD set by the admin (config default until saved). */
    public function manualRate(): float
    {
        $rate = (float) SiteSetting::value('manual_exchange_rate');

        return $rate > 0 ? $rate : (float) config('cruise.default_exchange_rate');
    }

    /** EGP the admin adds to every USD of the live rate (0 when not set); saved in both modes, used in automatic. */
    public function markup(): float
    {
        return max(0.0, (float) SiteSetting::value('exchange_rate_markup'));
    }

    /** EGP per 1 USD before the markup: the admin's rate or the live rate, by mode. */
    public function baseRate(): float
    {
        return $this->mode() === self::MODE_MANUAL ? $this->manualRate() : $this->liveRate()['rate'];
    }

    /** The markup added right now: only to a rate from the API, never to the admin's own rate. */
    public function appliedMarkup(): float
    {
        return $this->mode() === self::MODE_API && $this->liveRate()['source'] !== 'manual' ? $this->markup() : 0.0;
    }

    /** How many units of $code one USD buys: 1 for USD; for EGP the base rate of the current mode + any markup. */
    public function rate(?string $code = null): float
    {
        if (($code ?? $this->active()) === self::BASE) {
            return 1.0;
        }

        return $this->baseRate() + $this->appliedMarkup();
    }

    /** A USD amount in $code (the visitor's currency by default). Display only: nothing is saved converted. */
    public function convert(float|int|string|null $usd, ?string $code = null): float
    {
        return (float) $usd * $this->rate($code);
    }

    /** "$2,310" / "EGP 129,563": a USD amount at $rate, rounded like SC.money() in core.js. */
    public function format(float|int|string|null $usd, string $code = self::BASE, float $rate = 1.0): string
    {
        $value = round((float) $usd * ($code === self::BASE ? 1.0 : $rate));

        return ($value < 0 ? '−' : '').$this->symbol($code).number_format(abs($value));
    }

    /**
     * The live USD → EGP rate, from the cache when fresh.
     *
     * @return array{rate: float, source: 'api'|'last_known'|'manual', fetched_at: ?string, error: ?string}
     *   source: "api" just fetched / cached, "last_known" the last good fetch (the API failed), "manual" the admin's
     *   rate (the API failed and never worked)
     */
    public function liveRate(bool $refresh = false): array
    {
        if (! $refresh && is_array($cached = Cache::get(self::CACHE))) {
            return $cached;
        }

        try {
            $result = ['rate' => $this->fetch(), 'source' => 'api', 'fetched_at' => now()->toIso8601String(), 'error' => null];
            // Kept in the database too, so a cache clear followed by an API outage still has a recent rate
            SiteSetting::updateOrCreate(['key' => 'api_exchange_rate'], ['value' => (string) $result['rate']]);
            SiteSetting::updateOrCreate(['key' => 'api_exchange_rate_at'], ['value' => $result['fetched_at']]);
            Cache::put(self::CACHE, $result, now()->addHours(max(1, (int) config('services.exchange_rate.cache_hours'))));
        } catch (Throwable $e) {
            // Connection errors quote the URL, which holds the API key
            $error = str_replace((string) config('services.exchange_rate.key') ?: "\0", '***', $e->getMessage());
            Log::warning('Exchange rate fetch failed: '.$error);
            $last = (float) SiteSetting::value('api_exchange_rate');
            $result = $last > 0
                ? ['rate' => $last, 'source' => 'last_known', 'fetched_at' => SiteSetting::value('api_exchange_rate_at') ?: null]
                : ['rate' => $this->manualRate(), 'source' => 'manual', 'fetched_at' => null];
            $result['error'] = $error;
            Cache::put(self::CACHE, $result, now()->addMinutes((int) config('services.exchange_rate.retry_minutes')));
        }

        return $result;
    }

    /** Forget the cached live rate (next request fetches it again). */
    public function forget(): void
    {
        Cache::forget(self::CACHE);
    }

    /** USD → EGP from ExchangeRate-API: the v6 API with a key, the free open endpoint without. */
    private function fetch(): float
    {
        $key = config('services.exchange_rate.key');
        $url = $key ? "https://v6.exchangerate-api.com/v6/{$key}/latest/USD" : 'https://open.er-api.com/v6/latest/USD';

        $json = Http::timeout((int) config('services.exchange_rate.timeout'))->acceptJson()->get($url)->throw()->json();
        // v6 answers "conversion_rates", the open endpoint "rates"
        $rate = $json['conversion_rates']['EGP'] ?? $json['rates']['EGP'] ?? null;

        if (($json['result'] ?? null) !== 'success' || ! is_numeric($rate) || $rate <= 0) {
            throw new RuntimeException('Unexpected answer from the exchange rate API ('.($json['error-type'] ?? 'no EGP rate').').');
        }

        return (float) $rate;
    }
}
