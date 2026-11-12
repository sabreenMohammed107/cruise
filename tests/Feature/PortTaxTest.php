<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\CabinCategory;
use App\Models\Sailing;
use App\Models\SailingCabinPrice;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Port & departure taxes: USD per paying guest (adults and children; infants free), added to the fare, never
 * discounted. Amount from Admin → Pricing & offers → Port taxes (site_settings "port_tax_per_guest"), default
 * config cruise.port_tax_per_guest ($50).
 */
class PortTaxTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Mail::fake();
        config(['cruise.port_tax_per_guest' => 50]); // the shipped default (TestCase turns it off)
        SiteSetting::create(['key' => 'currency_mode', 'value' => 'manual']);
        SiteSetting::create(['key' => 'manual_exchange_rate', 'value' => '50']);
    }

    private function asAdmin(): static
    {
        return $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin');
    }

    private function price(string $sailing, string $cabin): SailingCabinPrice
    {
        return SailingCabinPrice::whereBelongsTo(Sailing::where('code', $sailing)->firstOrFail())
            ->whereBelongsTo(CabinCategory::where('slug', $cabin)->firstOrFail())->firstOrFail();
    }

    /** RSD-261213: balcony 2 adults + 1 child, interior 1 adult + 1 infant, SAFER10 (fare 12,425, promo −1,245). */
    private function book(?string $currency = null): Booking
    {
        $t = fn ($first, $dob, $g = 'Female') => ['first' => $first, 'last' => 'Test', 'dob' => $dob, 'gender' => $g, 'nationality' => 'Egypt'];
        $payload = [
            'sailing' => 'RSD-261213',
            'cabins' => [
                ['cabin' => 'balcony', 'guests' => ['adult' => 2, 'child' => 1, 'infant' => 0], 'travellers' => [
                    'adult-1' => $t('Noura', '1991-03-02'), 'adult-2' => $t('Khalid', '1988-07-19', 'Male'), 'child-1' => $t('Sara', '2018-05-01'),
                ]],
                ['cabin' => 'interior', 'guests' => ['adult' => 1, 'child' => 0, 'infant' => 1], 'travellers' => [
                    'adult-1' => $t('Huda', '1960-01-15'), 'infant-1' => $t('Omar', '2025-12-01', 'Male'),
                ]],
            ],
            'contact' => ['email' => 'noura@example.com', 'dial' => '+20', 'phone' => '100 123 4567'],
            'promoCode' => 'safer10',
            'agreed' => true,
        ];
        $request = $currency ? $this->withSession(['currency' => $currency]) : $this;

        return Booking::where('reference', $request->postJson('/booking/confirm', $payload)->assertCreated()->json('reference'))->firstOrFail();
    }

    public function test_default_is_50_dollars_per_guest(): void
    {
        $this->assertSame(50.0, PricingService::portTaxPerGuest());
    }

    public function test_adults_and_children_pay_it_and_infants_do_not(): void
    {
        $quote = app(PricingService::class)->quote($this->price('RSD-261213', 'balcony'), ['adult' => 2, 'child' => 1, 'infant' => 0]);
        $this->assertSame([150.0, 50.0, $quote['fare'] + 150.0], [$quote['fees'], $quote['portTax'], $quote['total']]);

        $quote = app(PricingService::class)->quote($this->price('RSD-261213', 'interior'), ['adult' => 1, 'infant' => 1]);
        $this->assertSame(50.0, $quote['fees']);
    }

    public function test_the_booking_is_charged_the_tax_and_the_promo_only_touches_the_fare(): void
    {
        $booking = $this->book();

        // 4 paying guests × $50; 12,425 + 200 − 1,245
        $this->assertSame(['12425.00', '200.00', '1245.00', '11380.00'], [$booking->fare_total, $booking->fees_total, $booking->discount_total, $booking->grand_total]);
        $this->assertSame(['150.00', '50.00'], $booking->cabins()->orderBy('position')->pluck('fees_total')->all());
    }

    public function test_an_egp_booking_shows_the_tax_at_the_booking_day_rate(): void
    {
        $booking = $this->book('EGP');

        $this->assertSame('EGP 10,000', $booking->money($booking->fees_total));
        $this->get(BookingController::signedConfirmationUrl($booking))->assertOk()->assertSee('EGP 569,000');
    }

    public function test_the_guest_email_and_admin_booking_show_the_tax_line(): void
    {
        $booking = $this->book();

        Mail::assertSent(BookingConfirmed::class, fn ($m) => str_contains($m->render(), 'Port &amp; departure taxes'));
        $this->asAdmin()->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee('Port &amp; departure taxes', false)->assertSee('$50 × 4');
    }

    public function test_the_admin_changes_the_amount_and_new_quotes_use_it(): void
    {
        $this->asAdmin()->get(route('admin.port-tax.edit'))->assertOk()->assertSee('Port &amp; departure taxes', false);
        $this->asAdmin()->put(route('admin.port-tax.update'), ['port_tax_per_guest' => '75.5'])->assertRedirect(route('admin.port-tax.edit'));

        $this->assertSame(75.5, PricingService::portTaxPerGuest());
        $this->assertSame(75.5, $this->get('/cruises')->viewData('scData')['portTax']);
        $quote = app(PricingService::class)->quote($this->price('RSD-261213', 'balcony'), ['adult' => 2]);
        $this->assertSame(151.0, $quote['fees']);
    }

    public function test_zero_turns_it_off_and_bad_amounts_are_refused(): void
    {
        $this->asAdmin()->put(route('admin.port-tax.update'), ['port_tax_per_guest' => '0'])->assertSessionHasNoErrors();
        $this->assertSame(0.0, app(PricingService::class)->quote($this->price('RSD-261213', 'balcony'), ['adult' => 2])['fees']);

        foreach (['-5', 'abc', '', '1.234'] as $bad) {
            $this->asAdmin()->put(route('admin.port-tax.update'), ['port_tax_per_guest' => $bad])->assertSessionHasErrors('port_tax_per_guest');
        }
        $this->assertSame('0', SiteSetting::where('key', 'port_tax_per_guest')->value('value'));
    }

    public function test_sellers_cannot_change_it(): void
    {
        $seller = User::where('role', 'seller')->first();
        if (! $seller) {
            $this->markTestSkipped('No seller in the seed data.');
        }
        $this->actingAs($seller, 'admin')->put(route('admin.port-tax.update'), ['port_tax_per_guest' => '10'])->assertForbidden();
    }
}
