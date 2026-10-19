<?php

namespace Tests\Feature;

use App\Models\CabinCategory;
use App\Models\PromoCode;
use App\Models\Sailing;
use App\Models\SailingCabinPrice;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tiered occupancy pricing. The same rules run in the browser (SC.fareLines / SC.quote in core.js).
 * Seeded prices (CruiseSeeder), whole cabin for the whole cruise, [single, double, 3rd guest]:
 *   Saudi Coast Weekend, interior: 1485 / 1980 / 510
 *   Red Sea Discovery, balcony:    5460 / 7280 / 1680
 */
class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function price(string $sailingCode, string $cabinSlug): SailingCabinPrice
    {
        return SailingCabinPrice::whereBelongsTo(Sailing::where('code', $sailingCode)->firstOrFail())
            ->whereBelongsTo(CabinCategory::where('slug', $cabinSlug)->firstOrFail())
            ->firstOrFail();
    }

    private function quote(array $guests, string $sailing = 'SCW-261105', string $cabin = 'interior', ?PromoCode $promo = null): array
    {
        return app(PricingService::class)->quote($this->price($sailing, $cabin), $guests, $promo);
    }

    public function test_one_adult_pays_the_single_price(): void
    {
        $quote = $this->quote(['adult' => 1]);

        $this->assertSame([['band' => 'single', 'label' => 'Single occupancy (1 guest)', 'unit' => 1485.0, 'qty' => 1, 'total' => 1485.0]], $quote['lines']);
        $this->assertSame(1485.0, $quote['fare']);
        $this->assertSame(105.0, $quote['fees']);   // 1 × 35 × 3 nights
        $this->assertSame(1590.0, $quote['total']);
    }

    public function test_two_adults_pay_the_double_price_not_twice_a_rate(): void
    {
        $quote = $this->quote(['adult' => 2]);

        $this->assertSame(['double'], array_column($quote['lines'], 'band'));
        $this->assertSame(1980.0, $quote['fare']);
        $this->assertSame(210.0, $quote['fees']);
        $this->assertSame(0.0, $quote['discount']);
        $this->assertSame(2190.0, $quote['total']);
    }

    public function test_a_child_as_second_guest_shares_the_double_price(): void
    {
        $this->assertSame(1980.0, $this->quote(['adult' => 1, 'child' => 1])['fare']);
    }

    public function test_third_guest_adds_the_extra_price(): void
    {
        $this->assertSame(1980.0 + 510.0, $this->quote(['adult' => 3])['fare']);
        $this->assertSame(1980.0 + 510.0, $this->quote(['adult' => 2, 'child' => 1])['fare']);
        $this->assertSame(1980.0 + 510.0, $this->quote(['adult' => 1, 'child' => 2])['fare']);
    }

    public function test_infants_are_free(): void
    {
        $quote = $this->quote(['adult' => 1, 'infant' => 1]);

        $this->assertSame(['single', 'infant'], array_column($quote['lines'], 'band'));
        $this->assertSame(0.0, $quote['lines'][1]['total']);
        $this->assertSame(1485.0, $quote['fare']);
        $this->assertSame(105.0, $quote['fees']);   // infants pay no fees (Age bands)
        $this->assertSame(2, $quote['count']);

        $this->assertSame(1980.0, $this->quote(['adult' => 2, 'infant' => 1])['fare']);
    }

    public function test_quote_for_a_family_with_promo(): void
    {
        $quote = $this->quote(['adult' => 2, 'child' => 1], 'RSD-261213', 'balcony', PromoCode::findRedeemable('safer10'));

        $this->assertSame(8960.0, $quote['fare']);      // double 7280 + 3rd guest 1680
        $this->assertSame(735.0, $quote['fees']);       // 3 guests × 35 × 7
        $this->assertSame(895.0, $quote['discount']);   // round5(10% of 8960 = 896)
        $this->assertSame(8800.0, $quote['total']);
        $this->assertSame(3, $quote['count']);
        $this->assertSame('SAFER10', $quote['promo']['code']);
        $this->assertSame(['single' => 5460.0, 'double' => 7280.0, 'extra' => 1680.0, 'infant' => 0.0], $quote['prices']);
    }

    public function test_inactive_or_unknown_promo_codes_are_not_redeemable(): void
    {
        $this->assertNull(PromoCode::findRedeemable('NOPE'));

        PromoCode::where('code', 'SAFER10')->update(['is_active' => false]);
        $this->assertNull(PromoCode::findRedeemable('SAFER10'));
    }

    public function test_sold_out_cabins_are_flagged_from_the_template(): void
    {
        $this->assertTrue($this->price('RSD-270117', 'suite')->is_sold_out);
        $this->assertTrue($this->price('SCW-261029', 'suite')->is_sold_out);
        $this->assertTrue($this->price('NYS-261229', 'balcony')->is_sold_out);
        $this->assertFalse($this->price('NYS-261229', 'suite')->is_sold_out);
    }

    public function test_from_price_is_per_person_sharing_and_skips_sold_out_cabins(): void
    {
        $sailing = Sailing::where('code', 'NYS-261229')->with('cabinPrices')->firstOrFail();

        // Interior double 3565 for two guests (balcony is sold out)
        $this->assertSame(1782.5, $sailing->fromPrice());
    }

    public function test_from_price_is_the_lowest_double_halved_never_the_single(): void
    {
        $sailing = Sailing::where('code', 'SCW-261105')->firstOrFail();
        // The admin price grid [single, double, 3rd guest] of a 7-night sailing
        foreach (['interior' => [820, 1200, 532], 'balcony' => [820, 1500, 600], 'suite' => [900, 1700, 650]] as $slug => [$single, $double, $extra]) {
            $this->price('SCW-261105', $slug)->update(['price_single' => $single, 'price_double' => $double, 'price_extra' => $extra]);
        }

        $this->assertSame(600.0, $sailing->load('cabinPrices')->fromPrice()); // 1200 / 2, not the single 820

        // A cabin without a Double price (0) isn't the "from" price
        $this->price('SCW-261105', 'interior')->update(['price_double' => 0]);
        $this->assertSame(750.0, $sailing->load('cabinPrices')->fromPrice());

        // Every cabin sold out: no price
        $sailing->cabinPrices()->update(['is_sold_out' => true]);
        $this->assertNull($sailing->load('cabinPrices')->fromPrice());
    }

    public function test_new_sailings_start_from_the_latest_prices_of_the_cruise(): void
    {
        $sailing = Sailing::where('code', 'SCW-270107')->firstOrFail();
        $sailing->cabinPrices()->update(['price_double' => 2222]);

        $prices = app(PricingService::class)->latestPrices($sailing->cruise);

        $this->assertCount(3, $prices);
        $this->assertSame(['2222.00'], $prices->pluck('price_double')->unique()->values()->all());
    }
}
