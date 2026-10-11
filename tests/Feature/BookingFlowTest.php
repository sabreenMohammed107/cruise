<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\BookingController;
use App\Mail\BookingConfirmed;
use App\Mail\NewBookingForTeam;
use App\Models\Booking;
use App\Models\CabinCategory;
use App\Models\Sailing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * POST /booking/confirm: the server re-checks what booking.js and details.js check in the browser.
 */
class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Mail::fake();
    }

    /** RSD-261213 (departs 13 Dec 2026), balcony, 2 adults + 1 child, in booking.js's payload shape. */
    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'sailing' => 'RSD-261213',
            'cabin' => 'balcony',
            'guests' => ['adult' => 2, 'child' => 1, 'infant' => 0],
            'travellers' => [
                'adult-1' => ['first' => 'Noura', 'last' => 'Alqahtani', 'dob' => '1991-03-02', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
                'adult-2' => ['first' => 'Khalid', 'last' => 'Alqahtani', 'dob' => '1988-07-19', 'gender' => 'Male', 'nationality' => 'Saudi Arabia'],
                'child-1' => ['first' => 'Sara', 'last' => 'Alqahtani', 'dob' => '2018-05-01', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
            ],
            'contact' => ['email' => 'noura@example.com', 'dial' => '+966', 'phone' => '055 123 4567'],
            'promoCode' => null,
            'agreed' => true,
        ], $override);
    }

    public function test_confirming_creates_an_unpaid_booking_priced_by_the_server(): void
    {
        $response = $this->postJson('/booking/confirm', $this->payload(['promoCode' => 'safer10']))->assertCreated();

        $booking = Booking::where('reference', $response->json('reference'))->firstOrFail();
        $this->assertMatchesRegularExpression('/^SC2612-[A-Z0-9]{5}$/', $booking->reference);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Unpaid, $booking->payment_status);
        // 2×4005 + 1850 = 9860 fare; fees 3×35×7 = 735; discount round5(986) = 985
        $this->assertSame(['9860.00', '735.00', '985.00', '9610.00'], [$booking->fare_total, $booking->fees_total, $booking->discount_total, $booking->grand_total]);
        $this->assertSame('551234567', $booking->contact_phone);
        $this->assertCount(3, $booking->travellers);
        $this->assertTrue($booking->travellers->first()->is_primary);
        $this->assertSame(1, $booking->promoCode->used_count);
        $response->assertJsonPath('redirect', route('booking.confirmation', $booking));

        Mail::assertSent(BookingConfirmed::class, fn ($mail) => $mail->hasTo('noura@example.com'));
        Mail::assertSent(NewBookingForTeam::class);
    }

    public function test_signed_in_guest_owns_the_booking(): void
    {
        $user = User::where('email', 'reem@example.com')->firstOrFail();

        $ref = $this->actingAs($user)->postJson('/booking/confirm', $this->payload())->assertCreated()->json('reference');

        $this->assertSame($user->id, Booking::where('reference', $ref)->value('user_id'));
    }

    public function test_child_who_is_twelve_on_departure_is_rejected(): void
    {
        $this->postJson('/booking/confirm', $this->payload(['travellers' => ['child-1' => ['dob' => '2014-12-13']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['travellers.child-1.dob']);
    }

    public function test_guest_limits_follow_the_template(): void
    {
        // more infants than adults
        $this->postJson('/booking/confirm', $this->payload([
            'guests' => ['adult' => 1, 'child' => 0, 'infant' => 2],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['guests']);

        // interior cabin fits 3, party of 4
        $this->postJson('/booking/confirm', $this->payload([
            'cabin' => 'interior',
            'guests' => ['adult' => 2, 'child' => 2, 'infant' => 0],
            'travellers' => ['child-2' => ['first' => 'Ali', 'last' => 'Alqahtani', 'dob' => '2016-01-01', 'gender' => 'Male', 'nationality' => 'Saudi Arabia']],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['guests']);
    }

    public function test_travellers_must_match_the_guest_counts(): void
    {
        $payload = $this->payload();
        unset($payload['travellers']['child-1']);

        $this->postJson('/booking/confirm', $payload)->assertUnprocessable()->assertJsonValidationErrors(['travellers']);
    }

    public function test_sold_out_or_full_cabins_cannot_be_booked(): void
    {
        $this->postJson('/booking/confirm', $this->payload(['sailing' => 'RSD-270117', 'cabin' => 'suite']))
            ->assertUnprocessable()->assertJsonValidationErrors(['cabin']);

        // capacity set by the admin: 1 balcony cabin left
        $sailing = Sailing::where('code', 'RSD-261213')->firstOrFail();
        $sailing->cabinPrices()->where('cabin_category_id', CabinCategory::where('slug', 'balcony')->value('id'))->update(['capacity' => 1]);

        $this->postJson('/booking/confirm', $this->payload())->assertCreated();
        $this->postJson('/booking/confirm', $this->payload())->assertUnprocessable()->assertJsonValidationErrors(['cabin']);
    }

    public function test_past_or_closed_sailings_cannot_be_booked(): void
    {
        $this->postJson('/booking/confirm', $this->payload(['sailing' => 'RSD-260315', 'cabin' => 'interior']))
            ->assertUnprocessable()->assertJsonValidationErrors(['sailing']);
    }

    public function test_contact_and_terms_are_checked(): void
    {
        $this->postJson('/booking/confirm', $this->payload(['contact' => ['phone' => '12345'], 'agreed' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agreed']);

        $this->postJson('/booking/confirm', $this->payload(['contact' => ['phone' => '412345678']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact.phone' => 'Saudi mobile numbers have 9 digits and start with 5.']);
    }

    public function test_promo_endpoint(): void
    {
        $this->postJson('/booking/promo', ['code' => 'safer10'])->assertOk()->assertJson(['code' => 'SAFER10', 'type' => 'percent', 'value' => 10]);
        $this->postJson('/booking/promo', ['code' => 'NOPE'])->assertUnprocessable();
        $this->postJson('/booking/confirm', $this->payload(['promoCode' => 'NOPE']))->assertUnprocessable()->assertJsonValidationErrors(['promoCode']);
    }

    public function test_confirmation_page_is_private(): void
    {
        $ref = $this->postJson('/booking/confirm', $this->payload())->json('reference');
        $booking = Booking::where('reference', $ref)->firstOrFail();

        // same browser session
        $this->get("/booking/{$ref}/confirmation")->assertOk()->assertSee($ref)->assertSee('Noura Alqahtani');

        // another browser: not found, unless the link is signed (email) or the viewer is an admin
        $this->flushSession();
        $this->get("/booking/{$ref}/confirmation")->assertNotFound();
        $this->get(BookingController::signedConfirmationUrl($booking))->assertOk();
        // admin signed in to the panel opens the guest voucher from the booking page
        $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin')
            ->get("/booking/{$ref}/confirmation")->assertOk();
    }
}
