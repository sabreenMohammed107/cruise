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
        // double 7280 + 3rd guest 1680 = 8960 fare; no fees (tax included, step 21); discount round5(896) = 895
        $this->assertSame(['8960.00', '0.00', '895.00', '8065.00'], [$booking->fare_total, $booking->fees_total, $booking->discount_total, $booking->grand_total]);
        $this->assertSame(['double', 'extra'], array_column($booking->price_snapshot['lines'], 'band'));
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

    public function test_children_are_two_to_seventeen_and_adults_eighteen_plus(): void
    {
        // RSD-261213 departs 13 Dec 2026. A 12-year-old is a child now (adults are 18+, step 14).
        $this->postJson('/booking/confirm', $this->payload(['travellers' => ['child-1' => ['dob' => '2014-12-13']]]))->assertCreated();

        // 18 on the departure day: must book as an adult
        $this->postJson('/booking/confirm', $this->payload(['travellers' => ['child-1' => ['dob' => '2008-12-13']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cabins.0.travellers.child-1.dob' => 'Child 1: Children must be 2–17 years old on the departure date.']);

        // 17 as an adult: rejected
        $this->postJson('/booking/confirm', $this->payload(['travellers' => ['adult-2' => ['dob' => '2009-01-01']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cabins.0.travellers.adult-2.dob' => 'Adult 2: Adults must be 18 or older on the departure date.']);
    }

    /** The payload for a party of the given size, with a traveller for every slot. */
    private function party(int $adults, int $children, int $infants, string $cabin = 'balcony'): array
    {
        $person = fn (string $first, string $dob) => ['first' => $first, 'last' => 'Alqahtani', 'dob' => $dob, 'gender' => 'Female', 'nationality' => 'Saudi Arabia'];
        $travellers = [];
        foreach (['adult' => [$adults, '1990-01-01'], 'child' => [$children, '2018-05-01'], 'infant' => [$infants, '2026-01-01']] as $band => [$n, $dob]) {
            for ($i = 1; $i <= $n; $i++) {
                $travellers["$band-$i"] = $person(ucfirst($band).chr(64 + $i), $dob);
            }
        }

        return array_merge($this->payload(), [
            'cabin' => $cabin,
            'guests' => ['adult' => $adults, 'child' => $children, 'infant' => $infants],
            'travellers' => $travellers,
        ]);
    }

    public function test_cabin_limit_counts_infants_and_infants_need_an_adult(): void
    {
        // 3 guests in total, infants included
        $this->postJson('/booking/confirm', $this->party(2, 1, 1))->assertUnprocessable()
            ->assertJsonValidationErrors(['cabins.0.guests' => "Balcony Cabin fits up to 3 guests including infants, and you've added 4."]);
        $this->postJson('/booking/confirm', $this->party(1, 0, 3))->assertUnprocessable()->assertJsonValidationErrors(['cabins.0.guests']);
        $this->postJson('/booking/confirm', $this->party(2, 2, 0, 'interior'))->assertUnprocessable()->assertJsonValidationErrors(['cabins.0.guests']);

        // no adult: infants can't travel alone
        $this->postJson('/booking/confirm', $this->party(0, 1, 1))->assertUnprocessable()
            ->assertJsonValidationErrors(['cabins.0.guests' => 'Infants must travel with at least one adult. Add an adult to the booking.']);
        $this->postJson('/booking/confirm', $this->party(0, 1, 0))->assertUnprocessable()
            ->assertJsonValidationErrors(['cabins.0.guests' => 'Every booking needs at least one adult.']);
    }

    public function test_infant_under_six_months_on_departure_blocks_the_booking(): void
    {
        // RSD-261213 departs 13 Dec 2026: born 14 Jun 2026 = 5 months; born 13 Jun 2026 = 6 months
        $payload = $this->party(1, 0, 1);
        $payload['travellers']['infant-1']['dob'] = '2026-06-14';

        $this->postJson('/booking/confirm', $payload)->assertUnprocessable()->assertJsonValidationErrors([
            'cabins.0.travellers.infant-1.dob' => 'Infant 1: Infants must be at least 6 months and under 2 years old on the departure date.',
        ]);
        $this->withCredentials()->withUnencryptedCookie('sc_lang', 'ar')->postJson('/booking/confirm', $payload)->assertUnprocessable()->assertJsonValidationErrors([
            'cabins.0.travellers.infant-1.dob' => 'رضيع 1: يجب أن يكون عمر الرضيع 6 أشهر على الأقل وأقل من سنتين في تاريخ المغادرة.',
        ]);
        $this->assertSame(0, Booking::count() - 3); // only the 3 demo bookings

        $payload['travellers']['infant-1']['dob'] = '2026-06-13';
        $this->postJson('/booking/confirm', $payload)->assertCreated();
    }

    public function test_infants_fill_a_place_but_are_free(): void
    {
        // 1 adult + 2 infants: full cabin, single price
        $ref = $this->postJson('/booking/confirm', $this->party(1, 0, 2))->assertCreated()->json('reference');
        $booking = Booking::where('reference', $ref)->firstOrFail();
        $this->assertSame(['5460.00', '0.00', '5460.00'], [$booking->fare_total, $booking->fees_total, $booking->grand_total]); // single, tax included
        $this->assertSame([1, 0, 2], [$booking->adults, $booking->children, $booking->infants]);

        // 2 adults + 1 infant: double price
        $ref = $this->postJson('/booking/confirm', $this->party(2, 0, 1))->assertCreated()->json('reference');
        $this->assertSame('7280.00', Booking::where('reference', $ref)->value('fare_total'));
    }

    public function test_travellers_must_match_the_guest_counts(): void
    {
        $payload = $this->payload();
        unset($payload['travellers']['child-1']);

        $this->postJson('/booking/confirm', $payload)->assertUnprocessable()->assertJsonValidationErrors(['cabins.0.travellers']);
    }

    public function test_sold_out_or_full_cabins_cannot_be_booked(): void
    {
        $this->postJson('/booking/confirm', $this->payload(['sailing' => 'RSD-270117', 'cabin' => 'suite']))
            ->assertUnprocessable()->assertJsonValidationErrors(['cabins.0.cabin']);

        // capacity set by the admin: 1 balcony cabin left
        $sailing = Sailing::where('code', 'RSD-261213')->firstOrFail();
        $sailing->cabinPrices()->where('cabin_category_id', CabinCategory::where('slug', 'balcony')->value('id'))->update(['capacity' => 1]);

        $this->postJson('/booking/confirm', $this->payload())->assertCreated();
        $this->postJson('/booking/confirm', $this->payload())->assertUnprocessable()->assertJsonValidationErrors(['cabins']);
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
