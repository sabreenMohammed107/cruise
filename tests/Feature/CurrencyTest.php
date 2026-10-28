<?php

namespace Tests\Feature;

use App\Models\SailingCabinPrice;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\CurrencyService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * USD / EGP display currency: header switch (session), Admin → Currency (live API or manual rate), and the
 * fallback when the API fails. Prices in the database and in SC_DATA stay USD.
 */
class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const PAGE = '/cruises/red-sea-discovery';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Cache::flush();
        Http::preventStrayRequests();
    }

    private function asAdmin(): static
    {
        return $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin');
    }

    /** What the fake exchange rate API answers next: one Http::fake() per test, since later stubs never win. */
    private ?Closure $answer = null;

    private function api(callable $answer): void
    {
        if (! $this->answer) {
            Http::fake(fn ($request) => ($this->answer)($request));
        }
        $this->answer = Closure::fromCallable($answer);
    }

    private function fakeApi(float $rate = 49.25): void
    {
        $this->api(fn () => Http::response(['result' => 'success', 'base_code' => 'USD', 'rates' => ['USD' => 1, 'EGP' => $rate]]));
    }

    private function manual(float $rate): void
    {
        SiteSetting::create(['key' => 'currency_mode', 'value' => 'manual']);
        SiteSetting::create(['key' => 'manual_exchange_rate', 'value' => (string) $rate]);
    }

    public function test_prices_are_usd_until_the_visitor_picks_egp_and_the_api_is_not_called(): void
    {
        Http::fake();
        $response = $this->get(self::PAGE)->assertOk();
        $this->assertMatchesRegularExpression('#aria-pressed="true"[^>]*>USD</button>#', $response->getContent());
        $data = $response->viewData('scData');

        $this->assertSame(['USD', '$', 1.0], [$data['displayCurrency'], $data['displaySymbol'], $data['exchangeRate']]);
        Http::assertNothingSent();
    }

    public function test_visitor_switches_to_egp_with_the_manual_rate_and_stored_prices_stay_usd(): void
    {
        $this->manual(48.5);
        $before = SailingCabinPrice::orderBy('id')->first()->price_double;

        $this->from(self::PAGE)->post('/currency', ['currency' => 'EGP'])->assertRedirect(self::PAGE)->assertSessionHas('currency', 'EGP');

        $response = $this->get(self::PAGE)->assertOk()->assertSee('1 USD = 48.50 EGP. Bookings are charged in US dollars.');
        $this->assertMatchesRegularExpression('#aria-pressed="true"[^>]*>EGP</button>#', $response->getContent());
        $data = $response->viewData('scData');
        $this->assertSame(['EGP', 'EGP ', 48.5], [$data['displayCurrency'], $data['displaySymbol'], $data['exchangeRate']]);
        // SC_DATA keeps the USD prices (core.js converts them for display) and the database is untouched
        $this->assertSame('USD', $data['currency']);
        $this->assertSame((float) $before, (float) SailingCabinPrice::orderBy('id')->first()->price_double);

        $this->post('/currency', ['currency' => 'USD'])->assertSessionHas('currency', 'USD');
        $this->assertSame(1.0, $this->get(self::PAGE)->viewData('scData')['exchangeRate']);
    }

    public function test_an_unknown_currency_is_refused(): void
    {
        $this->post('/currency', ['currency' => 'EUR'])->assertSessionHasErrors('currency')->assertSessionMissing('currency');
    }

    public function test_api_mode_uses_the_live_rate_and_caches_it(): void
    {
        $this->fakeApi(49.25);

        $rate = fn () => $this->withSession(['currency' => 'EGP'])->get(self::PAGE)->viewData('scData')['exchangeRate'];
        $this->assertSame([49.25, 49.25], [$rate(), $rate()]);
        Http::assertSentCount(1);
        $this->assertSame('49.25', SiteSetting::value('api_exchange_rate'));
    }

    public function test_the_api_key_switches_to_the_v6_endpoint(): void
    {
        config(['services.exchange_rate.key' => 'secret-key']);
        Http::fake(['v6.exchangerate-api.com/v6/secret-key/latest/USD' => Http::response(['result' => 'success', 'conversion_rates' => ['EGP' => 47.1]])]);

        $this->assertSame(47.1, app(CurrencyService::class)->rate('EGP'));
        Http::assertSent(fn ($request) => $request->url() === 'https://v6.exchangerate-api.com/v6/secret-key/latest/USD');
    }

    public function test_api_failure_falls_back_to_the_last_rate_then_to_the_manual_rate(): void
    {
        $currency = app(CurrencyService::class);
        SiteSetting::create(['key' => 'manual_exchange_rate', 'value' => '51']);

        // Never fetched: the admin's rate, and the page still loads
        $this->api(fn () => Http::response('Server error', 500));
        $this->assertSame(['manual', 51.0], [$currency->liveRate()['source'], $currency->rate('EGP')]);
        $this->withSession(['currency' => 'EGP'])->get(self::PAGE)->assertOk();
        // The failure is cached for a while instead of calling the API on every page
        Http::assertSentCount(1);

        // Fetched once, then the API fails: the last rate fetched
        $this->fakeApi(49.9);
        $this->assertSame('api', $currency->liveRate(refresh: true)['source']);
        $this->api(fn () => Http::response('Unavailable', 503));
        $live = $currency->liveRate(refresh: true);
        $this->assertSame(['last_known', 49.9], [$live['source'], $live['rate']]);
        $this->assertNotNull($live['error']);
    }

    public function test_a_timeout_or_bad_answer_is_handled_like_a_failure(): void
    {
        $currency = app(CurrencyService::class);

        $this->api(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
        $this->assertSame(50.0, $currency->rate('EGP')); // config cruise.default_exchange_rate

        $this->api(fn () => Http::response(['result' => 'error', 'error-type' => 'invalid-key']));
        $this->assertSame('manual', $currency->liveRate(refresh: true)['source']);
    }

    public function test_the_api_key_never_shows_in_the_error(): void
    {
        config(['services.exchange_rate.key' => 'secret-key']);
        Http::fake(fn ($request) => throw new ConnectionException('cURL error 6: Could not resolve host for '.$request->url()));

        $this->assertStringNotContainsString('secret-key', app(CurrencyService::class)->liveRate()['error']);
    }

    public function test_admin_switches_between_api_and_manual_mode(): void
    {
        $this->fakeApi(49.25);

        $this->asAdmin()->get('/admin/currency')->assertOk()
            ->assertSee('Rate in use')->assertSee('1 USD = 49.25 EGP')->assertSee('Live from the API')
            ->assertSee('value="api" class="mt-1" checked', false);

        $this->asAdmin()->put('/admin/currency', ['currency_mode' => 'manual', 'manual_exchange_rate' => '47.75'])
            ->assertRedirect('/admin/currency')->assertSessionHas('status');
        $this->assertSame(['manual', '47.75'], [SiteSetting::value('currency_mode'), SiteSetting::value('manual_exchange_rate')]);
        $this->asAdmin()->get('/admin/currency')->assertSee('1 USD = 47.75 EGP');
        $this->assertSame(47.75, $this->withSession(['currency' => 'EGP'])->get(self::PAGE)->viewData('scData')['exchangeRate']);

        $this->asAdmin()->put('/admin/currency', ['currency_mode' => 'api', 'manual_exchange_rate' => '47.75']);
        $this->assertSame(49.25, app(CurrencyService::class)->rate('EGP'));
    }

    public function test_admin_rate_is_validated(): void
    {
        foreach (['', '0', '-3', 'abc', '12.123456'] as $bad) {
            $this->asAdmin()->put('/admin/currency', ['currency_mode' => 'manual', 'manual_exchange_rate' => $bad])
                ->assertSessionHasErrors('manual_exchange_rate');
        }
        $this->asAdmin()->put('/admin/currency', ['currency_mode' => 'other', 'manual_exchange_rate' => '50'])
            ->assertSessionHasErrors('currency_mode');
        $this->assertSame(0, SiteSetting::whereIn('key', ['currency_mode', 'manual_exchange_rate'])->count());
    }

    public function test_admin_fetches_the_live_rate_now(): void
    {
        $this->fakeApi(49.25);
        $this->asAdmin()->post('/admin/currency/refresh')->assertRedirect('/admin/currency')
            ->assertSessionHas('status', 'Live rate updated: 1 USD = 49.2500 EGP.');

        $this->api(fn () => Http::response('down', 500));
        $this->asAdmin()->post('/admin/currency/refresh')->assertSessionHas('error');
    }
}
