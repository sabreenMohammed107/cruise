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
 * Expected values were computed with SC.prices / SC.quote from legacy-html/assets/js/core.js.
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

    public function test_rate_card_applies_cruise_and_sailing_factors_but_not_to_infants(): void
    {
        $sailing = Sailing::where('code', 'RSD-261213')->firstOrFail(); // 7 nights, sailing factor 1.1
        $cabin = CabinCategory::where('slug', 'balcony')->firstOrFail();

        $prices = app(PricingService::class)->rateCardPrices($sailing, $cabin);

        // 520 × 7 × 1.0 × 1.1 = 4004 → 4005; 240 × 7.7 = 1848 → 1850; 45 × 7 = 315
        $this->assertSame(['adult' => 4005.0, 'child' => 1850.0, 'infant' => 315.0], $prices);
    }

    public function test_quote_for_a_family_with_promo_matches_the_template(): void
    {
        $quote = app(PricingService::class)->quote(
            $this->price('RSD-261213', 'balcony'),
            ['adult' => 2, 'child' => 1, 'infant' => 1],
            PromoCode::findRedeemable('safer10'),
        );

        $this->assertSame(10175.0, $quote['fare']);      // 2×4005 + 1850 + 315
        $this->assertSame(735.0, $quote['fees']);        // 3 guests × 35 × 7; infants exempt
        $this->assertSame(1020.0, $quote['discount']);   // round5(10% of 10175 = 1017.5)
        $this->assertSame(9890.0, $quote['total']);
        $this->assertSame(4, $quote['count']);
        $this->assertSame('SAFER10', $quote['promo']['code']);
    }

    public function test_quote_without_promo_has_no_discount(): void
    {
        $quote = app(PricingService::class)->quote($this->price('SCW-261105', 'interior'), ['adult' => 2]);

        // 330 × 3 = 990 per adult; fees 2 × 35 × 3
        $this->assertSame(1980.0, $quote['fare']);
        $this->assertSame(210.0, $quote['fees']);
        $this->assertSame(0.0, $quote['discount']);
        $this->assertSame(2190.0, $quote['total']);
        $this->assertCount(1, $quote['lines']);
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

    public function test_from_price_skips_sold_out_cabins(): void
    {
        $sailing = Sailing::where('code', 'NYS-261229')->with('cabinPrices')->firstOrFail();

        // Interior: 330 × 4 × 1.35 = 1782 → 1780 (balcony is sold out but would be higher anyway)
        $this->assertSame(1780.0, $sailing->fromPrice());
    }
}
