<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Mail\BookingConfirmed;
use App\Mail\NewBookingForTeam;
use App\Models\Booking;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ScDataPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A booking made with the website in EGP keeps that currency and the rate of the booking day
 * (bookings.display_currency / exchange_rate): voucher, guest email and "My bookings" show EGP at that rate. The
 * amounts, payments, admin panel and team email stay USD.
 */
class BookingCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Mail::fake();
        // Manual rate, so no API call: $11,180 × 50 = EGP 559,000
        SiteSetting::create(['key' => 'currency_mode', 'value' => 'manual']);
        SiteSetting::create(['key' => 'manual_exchange_rate', 'value' => '50']);
    }

    /** RSD-261213, two cabins with SAFER10: $11,180 after a $1,245 discount (same booking as BookingEmailsTest). */
    private function book(?string $currency = null): Booking
    {
        $payload = [
            'sailing' => 'RSD-261213',
            'cabins' => [
                ['cabin' => 'balcony', 'guests' => ['adult' => 2, 'child' => 1, 'infant' => 0], 'travellers' => [
                    'adult-1' => ['first' => 'Noura', 'last' => 'Alqahtani', 'dob' => '1991-03-02', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
                    'adult-2' => ['first' => 'Khalid', 'last' => 'Alqahtani', 'dob' => '1988-07-19', 'gender' => 'Male', 'nationality' => 'Saudi Arabia'],
                    'child-1' => ['first' => 'Sara', 'last' => 'Alqahtani', 'dob' => '2018-05-01', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
                ]],
                ['cabin' => 'interior', 'guests' => ['adult' => 1, 'child' => 0, 'infant' => 1], 'travellers' => [
                    'adult-1' => ['first' => 'Huda', 'last' => 'Saleh', 'dob' => '1960-01-15', 'gender' => 'Female', 'nationality' => 'Egypt'],
                    'infant-1' => ['first' => 'Omar', 'last' => 'Saleh', 'dob' => '2025-12-01', 'gender' => 'Male', 'nationality' => 'Egypt'],
                ]],
            ],
            'contact' => ['email' => 'noura@example.com', 'dial' => '+966', 'phone' => '055 123 4567'],
            'promoCode' => 'safer10',
            'agreed' => true,
        ];
        $request = $currency ? $this->withSession(['currency' => $currency]) : $this;
        $ref = $request->postJson('/booking/confirm', $payload)->assertCreated()->json('reference');

        return Booking::where('reference', $ref)->firstOrFail();
    }

    private function sent(string $mailable): string
    {
        $html = null;
        Mail::assertSent($mailable, function ($m) use (&$html) {
            $html = $m->render();

            return true;
        });

        return $html;
    }

    public function test_an_egp_booking_stores_the_currency_and_rate_but_keeps_usd_amounts(): void
    {
        $booking = $this->book('EGP');

        $this->assertSame(['USD', 'EGP', '50.0000', '11180.00'], [$booking->currency, $booking->display_currency, $booking->exchange_rate, $booking->grand_total]);
        $this->assertSame('EGP 559,000', $booking->money($booking->grand_total));
    }

    public function test_the_voucher_shows_egp_at_the_booking_day_rate(): void
    {
        $booking = $this->book('EGP');
        // A later rate change does not move the amounts of an existing booking
        SiteSetting::where('key', 'manual_exchange_rate')->update(['value' => '60']);

        $this->withSession(['currency' => 'USD'])->get(BookingController::signedConfirmationUrl($booking))->assertOk()
            ->assertSee('EGP 559,000')
            ->assertSee('Charged in US dollars: $11,180 (1 USD = 50.00 EGP on the booking day)');
    }

    public function test_a_usd_booking_is_unchanged(): void
    {
        $booking = $this->book();

        $this->assertSame(['USD', '1.0000'], [$booking->display_currency, $booking->exchange_rate]);
        $this->get(BookingController::signedConfirmationUrl($booking))->assertOk()
            ->assertSee('$11,180')->assertDontSee('EGP 559,000')->assertDontSee('Charged in US dollars: $11,180');
        $this->assertStringContainsString('Prices in US dollars, for the whole cruise.', $this->sent(BookingConfirmed::class));
    }

    public function test_the_guest_email_is_in_egp_and_the_team_email_in_usd(): void
    {
        $this->book('EGP');

        $guest = $this->sent(BookingConfirmed::class);
        foreach (['EGP 559,000', '−EGP 62,250', 'at 1 USD = 50.00 EGP on the booking day. Charged in US dollars: $11,180.'] as $text) {
            $this->assertStringContainsString(e($text), $guest, $text);
        }
        $this->assertStringNotContainsString('>$11,180<', $guest);

        $team = $this->sent(NewBookingForTeam::class);
        foreach (['$11,180', '−$1,245', 'The guest booked seeing EGP prices: EGP 559,000 at 1 USD = 50.00 EGP.'] as $text) {
            $this->assertStringContainsString(e($text), $team, $text);
        }
        $this->assertStringNotContainsString('−EGP', $team);
    }

    public function test_admin_sees_usd_and_what_the_guest_saw(): void
    {
        $booking = $this->book('EGP');

        $admin = $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin');
        $admin->get("/admin/bookings/{$booking->reference}")->assertOk()
            ->assertSee('Booked in EGP')->assertSee('The guest booked in Egyptian pounds (EGP)')
            ->assertSee('$11,180')->assertSee('Guest saw EGP 559,000 (1 USD = 50.00 EGP)');
        // Bookings list: the tag and the guest's amount under the USD total
        $admin->get('/admin/bookings')->assertOk()->assertSee('Booked in EGP')->assertSee('EGP 559,000');
    }

    public function test_usd_bookings_get_no_currency_tag(): void
    {
        $booking = $this->book();

        $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin')
            ->get("/admin/bookings/{$booking->reference}")->assertOk()
            ->assertDontSee('Booked in EGP')->assertDontSee('The guest booked in');
        $this->assertStringNotContainsString('The guest booked seeing', $this->sent(NewBookingForTeam::class));
    }

    public function test_my_bookings_get_the_rate_of_each_booking(): void
    {
        $data = app(ScDataPresenter::class)->booking($this->book('EGP')->load('sailing.cruise.ship', 'cabinCategory', 'cabins.cabinCategory', 'travellers.cabin'));

        $this->assertSame(['EGP ', 50.0, 11180.0], [$data['displaySymbol'], $data['exchangeRate'], $data['amountDue']]);
    }
}
