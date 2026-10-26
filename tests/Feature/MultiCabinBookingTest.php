<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CabinCategory;
use App\Models\Sailing;
use App\Models\User;
use App\Support\ScDataPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Step 15: several cabins in one booking, each with its own guests and tiered price, on one sailing.
 * RSD-261213 (7 nights, departs 13 Dec 2026). Seeded prices [single, double, extra]:
 * balcony 5460 / 7280 / 1680, interior 3465 / 4620 / 1190. Fees $35 per adult or child per night.
 */
class MultiCabinBookingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Mail::fake();
    }

    private function person(string $first, string $dob): array
    {
        return ['first' => $first, 'last' => 'Alqahtani', 'dob' => $dob, 'gender' => 'Female', 'nationality' => 'Saudi Arabia'];
    }

    /** A cabin entry with a traveller for every guest slot. */
    private function cabin(string $slug, int $adults, int $children = 0, int $infants = 0): array
    {
        $travellers = [];
        foreach (['adult' => [$adults, '1990-01-01'], 'child' => [$children, '2016-05-01'], 'infant' => [$infants, '2026-01-01']] as $band => [$n, $dob]) {
            for ($i = 1; $i <= $n; $i++) {
                $travellers["$band-$i"] = $this->person(ucfirst($band).chr(64 + $i), $dob);
            }
        }

        return ['cabin' => $slug, 'guests' => ['adult' => $adults, 'child' => $children, 'infant' => $infants], 'travellers' => $travellers];
    }

    private function payload(array $cabins, array $override = []): array
    {
        return array_merge([
            'sailing' => 'RSD-261213',
            'cabins' => $cabins,
            'contact' => ['email' => 'noura@example.com', 'dial' => '+966', 'phone' => '551234567'],
            'promoCode' => null,
            'agreed' => true,
        ], $override);
    }

    public function test_two_cabins_are_priced_one_by_one_with_the_promo_on_the_total(): void
    {
        $response = $this->postJson('/booking/confirm', $this->payload(
            [$this->cabin('balcony', 2, 1), $this->cabin('interior', 1, 0, 1)],
            ['promoCode' => 'SAFER10'],
        ))->assertCreated();

        $booking = Booking::where('reference', $response->json('reference'))->with(['cabins.cabinCategory', 'cabins.travellers', 'travellers'])->firstOrFail();

        // balcony: double 7280 + 3rd guest 1680; interior: single 3465; no fees (tax included, step 21)
        // fare 12425; discount round5(1242.5) = 1245; total 12425 − 1245 = 11180
        $this->assertSame(['12425.00', '0.00', '1245.00', '11180.00'], [$booking->fare_total, $booking->fees_total, $booking->discount_total, $booking->grand_total]);

        $this->assertSame([1, 2], $booking->cabins->pluck('position')->all());
        $this->assertSame(['balcony', 'interior'], $booking->cabins->map(fn ($c) => $c->cabinCategory->slug)->all());
        $this->assertSame(['8960.00', '3465.00'], $booking->cabins->pluck('fare_total')->all());
        $this->assertSame([3, 1, 1], [$booking->adults, $booking->children, $booking->infants]); // totals
        $this->assertSame('balcony', $booking->cabinCategory->slug);                            // first cabin

        // travellers sleep in their own cabin; slots run on across cabins; one primary guest
        $this->assertSame(['adult-1', 'adult-2', 'child-1'], $booking->cabins[0]->travellers->map(fn ($t) => "{$t->band->value}-{$t->slot}")->all());
        $this->assertSame(['adult-3', 'infant-1'], $booking->cabins[1]->travellers->map(fn ($t) => "{$t->band->value}-{$t->slot}")->all());
        $this->assertSame(1, $booking->travellers->where('is_primary', true)->count());
        $this->assertTrue($booking->cabins[0]->travellers->first()->is_primary);

        // the saved quote keeps each cabin
        $this->assertCount(2, $booking->price_snapshot['cabins']);
        $this->assertSame(['double', 'extra', 'single', 'infant'], array_column($booking->price_snapshot['lines'], 'band'));
        $this->assertSame([1, 1, 2, 2], array_column($booking->price_snapshot['lines'], 'cabin'));
        $this->assertSame('2 × Balcony Cabin', Booking::make()->setRelation('cabins', collect([$booking->cabins[0], $booking->cabins[0]]))->cabinSummary());
        $this->assertSame('Balcony Cabin, Interior Cabin', $booking->cabinSummary());
    }

    public function test_the_same_cabin_type_twice_counts_against_the_cabins_left(): void
    {
        $sailing = Sailing::where('code', 'RSD-261213')->firstOrFail();
        $balcony = $sailing->cabinPrices()->where('cabin_category_id', CabinCategory::where('slug', 'balcony')->value('id'));

        $balcony->update(['capacity' => 1]);
        $this->postJson('/booking/confirm', $this->payload([$this->cabin('balcony', 2), $this->cabin('balcony', 2)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['cabins']);
        $this->assertSame(3, Booking::count()); // demo bookings only

        $balcony->update(['capacity' => 2]);
        $ref = $this->postJson('/booking/confirm', $this->payload([$this->cabin('balcony', 2), $this->cabin('balcony', 2)]))->assertCreated()->json('reference');
        $this->assertSame(2, Booking::where('reference', $ref)->first()->cabins()->count());

        // both balcony cabins are now taken
        $this->postJson('/booking/confirm', $this->payload([$this->cabin('balcony', 1)]))->assertUnprocessable()->assertJsonValidationErrors(['cabins']);
    }

    public function test_every_cabin_is_checked_on_its_own(): void
    {
        // cabin 2 without an adult
        $this->postJson('/booking/confirm', $this->payload([$this->cabin('balcony', 2), $this->cabin('interior', 0, 2)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cabins.1.guests' => 'Cabin 2: Every cabin needs at least one adult.']);

        // cabin 2 over its limit, infants included
        $this->postJson('/booking/confirm', $this->payload([$this->cabin('balcony', 1), $this->cabin('interior', 2, 1, 1)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cabins.1.guests' => "Cabin 2: Interior Cabin fits up to 3 guests including infants, and you've added 4."]);

        // a traveller's age in cabin 2, in Arabic
        $cabins = [$this->cabin('balcony', 1), $this->cabin('interior', 1, 1)];
        $cabins[1]['travellers']['child-1']['dob'] = '2005-01-01';
        $this->withCredentials()->withUnencryptedCookie('sc_lang', 'ar')->postJson('/booking/confirm', $this->payload($cabins))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cabins.1.travellers.child-1.dob' => 'الكابينة 2: طفل 1: يجب أن يكون عمر الطفل بين 2 و17 سنة في تاريخ المغادرة.']);

        // a sold-out cabin type anywhere in the booking
        $this->postJson('/booking/confirm', $this->payload([$this->cabin('interior', 1), $this->cabin('suite', 1)], ['sailing' => 'RSD-270117']))
            ->assertUnprocessable()->assertJsonValidationErrors(['cabins.1.cabin']);
    }

    public function test_at_most_five_cabins_per_booking(): void
    {
        $this->postJson('/booking/confirm', $this->payload(array_fill(0, 6, $this->cabin('interior', 1))))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cabins' => 'You can book up to 5 cabins at once. For more, contact our team.']);

        $this->postJson('/booking/confirm', $this->payload(array_fill(0, 5, $this->cabin('interior', 1))))->assertCreated();
    }

    public function test_voucher_account_and_admin_show_every_cabin(): void
    {
        $reem = User::where('email', 'reem@example.com')->firstOrFail();
        $url = $this->actingAs($reem)->postJson('/booking/confirm', $this->payload([$this->cabin('balcony', 2), $this->cabin('interior', 1, 1)]))
            ->assertCreated()->json('redirect');
        $booking = Booking::latest('id')->firstOrFail();

        $this->get($url)->assertOk()
            ->assertSee('>Cabins</dt>', false)->assertSee('2 cabins')
            ->assertSee('Cabin 1 · Balcony Cabin (2 adults)')->assertSee('Cabin 2 · Interior Cabin (1 adult + 1 child)');

        $account = app(ScDataPresenter::class)->booking($booking->fresh());
        $this->assertSame([['position' => 1, 'cabinId' => 'balcony'], ['position' => 2, 'cabinId' => 'interior']], array_map(fn ($c) => array_intersect_key($c, ['position' => 1, 'cabinId' => 1]), $account['cabins']));
        $this->assertSame([1, 1, 2, 2], array_column($account['travellers'], 'cabin'));

        $admin = User::where('email', 'admin@safercruise.test')->firstOrFail();
        $this->actingAs($admin, 'admin')->get("/admin/bookings/{$booking->reference}")->assertOk()
            ->assertSee('Cabins (2)')->assertSee('Balcony Cabin, Interior Cabin')
            ->assertSee('Cabin 2 · Interior Cabin (1 adult + 1 child)');
        $this->get('/admin/bookings')->assertOk()->assertSee('Balcony Cabin, Interior Cabin');
    }

    public function test_bookings_made_before_have_one_cabin(): void
    {
        // the demo bookings (and, on a live database, the migration) give every booking one cabin row
        foreach (Booking::with(['cabins', 'travellers'])->get() as $booking) {
            $this->assertCount(1, $booking->cabins);
            $this->assertSame($booking->cabin_category_id, $booking->cabins[0]->cabin_category_id);
            $this->assertSame([$booking->adults, $booking->children, $booking->infants], [$booking->cabins[0]->adults, $booking->cabins[0]->children, $booking->cabins[0]->infants]);
            $this->assertTrue($booking->travellers->every(fn ($t) => $t->booking_cabin_id === $booking->cabins[0]->id));
        }
    }
}
