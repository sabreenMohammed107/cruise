<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgentRate;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Sailing;
use App\Models\User;
use App\Support\ScDataPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Time-limited offers (Admin → Offers, step 26): by cruise or sailing, on the cabin price or by age, with a cap per
 * booking and a number of bookings, for customers / agents, with or without promo codes.
 *
 * RSD-261213 (departs 13 Dec 2026), balcony: double 7280 + 3rd guest 1680 = 8960 for 2 adults + 1 child (8 years old).
 */
class OffersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
    }

    private function sailing(): Sailing
    {
        return Sailing::where('code', 'RSD-261213')->sole();
    }

    private function offer(array $attributes = []): Offer
    {
        return Offer::create($attributes + [
            'name' => 'Summer deal', 'name_ar' => 'عرض الصيف', 'cruise_id' => $this->sailing()->cruise_id,
            'target' => 'fare', 'type' => 'percent', 'value' => 10, 'ends_on' => '2026-10-31',
        ]);
    }

    /** The RSD-261213 booking; a traveller set to null in $override is left out. */
    private function payload(array $override = []): array
    {
        $payload = array_replace_recursive([
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
        $payload['travellers'] = array_filter($payload['travellers']);

        return $payload;
    }

    private function book(array $override = [], ?User $as = null): Booking
    {
        app()->forgetScopedInstances(); // each booking is a new request
        $request = $as ? $this->actingAs($as, 'web') : $this;
        $ref = $request->postJson('/booking/confirm', $this->payload($override))->assertCreated()->json('reference');

        return Booking::where('reference', $ref)->sole();
    }

    private function agent(): User
    {
        $agent = User::factory()->create(['email' => 'agent@example.com']);
        $agent->forceFill(['role' => UserRole::Agent])->save();

        return $agent;
    }

    /** @return list<array> the offers SC_DATA publishes on RSD-261213 for this visitor */
    private function publishedOffers(?User $user = null): array
    {
        // A new page load: offers are read once per request (OfferService is scoped)
        app()->forgetScopedInstances();
        $cruises = app(ScDataPresenter::class)->make($user)['cruises'];
        foreach ($cruises as $cruise) {
            foreach ($cruise['sailings'] as $sailing) {
                if ($sailing['id'] === 'RSD-261213') {
                    return $sailing['offers'];
                }
            }
        }
        $this->fail('RSD-261213 is not on the website.');
    }

    public function test_a_cruise_offer_comes_off_every_sailing_of_the_cruise_and_counts_the_booking(): void
    {
        $offer = $this->offer();

        $booking = $this->book();

        // 10% of 8960
        $this->assertSame(['8960.00', '896.00', '0.00', '8064.00'], [$booking->fare_total, $booking->offer_discount_total, $booking->discount_total, $booking->grand_total]);
        $this->assertSame([['id' => $offer->id, 'name' => 'Summer deal', 'nameAr' => 'عرض الصيف', 'target' => 'fare', 'discount' => 896]], $booking->price_snapshot['offers']);
        $this->assertSame(1, $offer->fresh()->used_count);

        // Published on every sailing of that cruise, and on no other cruise
        $cruises = collect(app(ScDataPresenter::class)->make()['cruises']);
        $own = $cruises->firstWhere('id', $this->sailing()->cruise->slug);
        $this->assertTrue(collect($own['sailings'])->every(fn ($s) => array_column($s['offers'], 'id') === [$offer->id]));
        $this->assertTrue($cruises->where('id', '!=', $own['id'])->flatMap(fn ($c) => $c['sailings'])->every(fn ($s) => $s['offers'] === []));

        // Shown to the team
        $this->actingAs(User::where('email', 'admin@safercruise.test')->sole(), 'admin')
            ->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('Offer: Summer deal');
    }

    public function test_a_sailing_offer_wins_over_the_cruise_offer(): void
    {
        $this->offer(['value' => 10]);
        $this->offer(['name' => 'Last seats', 'sailing_id' => $this->sailing()->id, 'type' => 'fixed', 'value' => 500]);

        $booking = $this->book();

        // 500 off the cabin, the cruise's 10% left out
        $this->assertSame(['500.00', '8460.00'], [$booking->offer_discount_total, $booking->grand_total]);
        $this->assertSame(['Last seats'], array_column($booking->price_snapshot['offers'], 'name'));
    }

    public function test_offers_outside_their_dates_switched_off_or_used_up_are_not_applied(): void
    {
        $this->offer(['starts_on' => '2026-10-12']);            // starts tomorrow
        $this->offer(['ends_on' => '2026-10-10']);              // ended yesterday
        $this->offer(['is_active' => false]);
        $used = $this->offer(['max_uses' => 1]);
        $used->forceFill(['used_count' => 1])->save();

        $this->assertSame([], $this->publishedOffers());
        $this->assertSame('8960.00', $this->book()->grand_total);
        $this->assertSame('Scheduled', Offer::whereDate('starts_on', '2026-10-12')->sole()->status_label);
        $this->assertSame('Used up', $used->fresh()->status_label);
    }

    public function test_the_last_day_is_included_and_max_bookings_stops_the_offer(): void
    {
        $offer = $this->offer(['ends_on' => '2026-10-11', 'max_uses' => 1]);

        $this->assertSame('8064.00', $this->book()->grand_total);
        // Used up: the next booking pays the full price
        $this->assertSame('8960.00', $this->book(['contact' => ['email' => 'second@example.com']])->grand_total);
        $this->assertSame(1, $offer->fresh()->used_count);
    }

    public function test_an_age_offer_takes_its_share_and_stops_at_its_maximum(): void
    {
        // Children up to 11: half their share, at most 1000 per booking; adults 60+: nobody qualifies
        $kids = $this->offer(['name' => 'Kids half price', 'target' => 'age', 'min_age' => 0, 'max_age' => 11, 'value' => 50, 'max_discount' => 1000]);
        $this->offer(['name' => 'Seniors', 'target' => 'age', 'min_age' => 60, 'value' => 30]);

        // Share 8960 ÷ 3 = 2986.67, half = 1493.33, capped at 1000
        $booking = $this->book();
        $this->assertSame(['1000.00', '7960.00'], [$booking->offer_discount_total, $booking->grand_total]);
        $this->assertSame([$kids->id], array_column($booking->price_snapshot['offers'], 'id'));

        // With a cruise offer too: share (8960 − 896) ÷ 3 = 2688, half = 1344, capped at 1000 → 8960 − 896 − 1000
        $this->offer();
        $this->assertSame(['1896.00', '7064.00'], [($b = $this->book(['contact' => ['email' => 'b@example.com']]))->offer_discount_total, $b->grand_total]);

        // A 12-year-old is outside the range
        $this->assertSame('8064.00', $this->book(['contact' => ['email' => 'c@example.com'], 'travellers' => ['child-1' => ['dob' => '2014-01-01']]])->grand_total);
    }

    public function test_offers_for_customers_and_for_agents(): void
    {
        AgentRate::create(['percent' => 10]); // agent fare 8064 (public 8960)
        $agent = $this->agent();
        $b2c = $this->offer(['name' => 'Web deal', 'audience' => 'b2c', 'value' => 20]);

        // Customers get the B2C offer, agents don't
        $this->assertSame('7168.00', $this->book()->grand_total);
        $this->assertSame([$b2c->id], array_column($this->publishedOffers(), 'id'));
        $this->assertSame([], $this->publishedOffers($agent));
        $this->assertSame('8064.00', $this->book(['contact' => ['email' => 'a1@example.com']], $agent)->grand_total);

        // B2B on top of the commission: 5% of the agent fare 8064 = 403.20
        $b2c->update(['is_active' => false]);
        $stack = $this->offer(['name' => 'Agents week', 'audience' => 'b2b', 'value' => 5]);
        $booking = $this->book(['contact' => ['email' => 'a2@example.com']], $agent);
        $this->assertSame(['8064.00', '896.00', '403.20', '7660.80'], [$booking->fare_total, $booking->agent_discount_total, $booking->offer_discount_total, $booking->grand_total]);
        $this->assertSame([], $this->publishedOffers());

        // B2B instead of the commission: public 8960 − 15% = 7616, no commission
        $stack->update(['is_active' => false]);
        $this->offer(['name' => 'Agents special', 'audience' => 'b2b', 'value' => 15, 'agent_mode' => 'replace']);
        $booking = $this->book(['contact' => ['email' => 'a3@example.com']], $agent);
        $this->assertSame(['8960.00', '0.00', '1344.00', '7616.00'], [$booking->fare_total, $booking->agent_discount_total, $booking->offer_discount_total, $booking->grand_total]);
        $this->assertNull($booking->price_snapshot['agent']);
        $this->assertTrue($this->publishedOffers($agent)[0]['replaceAgent']);
    }

    public function test_promo_codes_only_with_combinable_offers(): void
    {
        $offer = $this->offer();
        $before = Booking::count();

        $this->postJson('/booking/confirm', $this->payload(['promoCode' => 'safer10']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['promoCode' => __('site.booking.errors.promo_not_combinable')]);
        $this->assertSame($before, Booking::count());

        // Combinable: the code is on what the offer left, 10% of 8064 rounded to 5 = 805
        $offer->update(['combinable' => true]);
        $booking = $this->book(['promoCode' => 'safer10']);
        $this->assertSame(['896.00', '805.00', '7259.00'], [$booking->offer_discount_total, $booking->discount_total, $booking->grand_total]);

        // An age offer that isn't combinable only blocks codes when someone in the booking has that age
        $offer->update(['is_active' => false]);
        $this->offer(['name' => 'Seniors', 'target' => 'age', 'min_age' => 60, 'value' => 30]);
        $this->assertSame('8065.00', $this->book(['promoCode' => 'safer10', 'contact' => ['email' => 'x@example.com']])->grand_total);
    }

    public function test_the_published_offer_has_what_the_page_scripts_need(): void
    {
        $offer = $this->offer(['target' => 'age', 'min_age' => 0, 'max_age' => 11, 'type' => 'fixed', 'value' => 200, 'max_discount' => 400, 'combinable' => true]);

        $this->assertSame([[
            'id' => $offer->id, 'name' => 'Summer deal', 'level' => 1, 'target' => 'age', 'minAge' => 0, 'maxAge' => 11,
            'freeNights' => null, 'upgradeFrom' => null,
            'type' => 'fixed', 'value' => 200.0, 'maxDiscount' => 400.0, 'cabins' => null,
            'nationalities' => null, 'combinable' => true, 'replaceAgent' => false, 'endsOn' => '2026-10-31',
        ]], $this->publishedOffers());

        // Arabic name in Arabic
        app()->setLocale('ar');
        $this->assertSame('عرض الصيف', $this->publishedOffers()[0]['name']);
    }

    public function test_the_third_guest_and_single_traveller_offers(): void
    {
        // 3rd guest free (1680), after a cruise offer of 10%: 1680 × 0.9 = 1512 → 8960 − 896 − 1512
        $third = $this->offer(['name' => '3rd guest free', 'target' => 'third_guest', 'type' => 'free', 'value' => 100]);
        $this->offer();
        $booking = $this->book();
        $this->assertSame(['2408.00', '6552.00'], [$booking->offer_discount_total, $booking->grand_total]);
        $this->assertEqualsCanonicalizing(['3rd guest free', 'Summer deal'], array_column($booking->price_snapshot['offers'], 'name'));

        // Two guests: no 3rd guest, so only the cruise offer (7280 − 728)
        $two = $this->book(['guests' => ['adult' => 2, 'child' => 0], 'travellers' => ['child-1' => null], 'contact' => ['email' => 'two@example.com']]);
        $this->assertSame(['Summer deal'], array_column($two->price_snapshot['offers'], 'name'));
        $this->assertSame('6552.00', $two->grand_total);

        // Single traveller: the supplement (single − double ÷ 2) is free, so one guest pays half the double
        Offer::query()->update(['is_active' => false]);
        $this->offer(['name' => 'No single supplement', 'target' => 'single', 'type' => 'free', 'value' => 100]);
        $price = $this->sailing()->cabinPrices()->whereHas('cabinCategory', fn ($q) => $q->where('slug', 'balcony'))->sole();
        $solo = $this->book(['guests' => ['adult' => 1, 'child' => 0], 'travellers' => ['adult-2' => null, 'child-1' => null], 'contact' => ['email' => 'solo@example.com']]);
        $this->assertEquals((float) $price->price_double / 2, (float) $solo->grand_total);
        $this->assertEquals((float) $price->price_single - (float) $price->price_double / 2, (float) $solo->offer_discount_total);
        $this->assertSame(1, $third->fresh()->used_count);
    }

    /** RSD-261213's price row of a cabin type. */
    private function cabinPrice(string $slug): \App\Models\SailingCabinPrice
    {
        return $this->sailing()->cabinPrices()->whereHas('cabinCategory', fn ($q) => $q->where('slug', $slug))->sole();
    }

    public function test_second_guest_free_nights_upgrade_and_fixed_price(): void
    {
        $balcony = $this->cabinPrice('balcony');
        $nights = $this->sailing()->cruise->nights;
        $cents = fn (float $n) => floor($n * 100 + 0.5) / 100;
        $email = fn (string $e) => ['contact' => ['email' => "$e@example.com"]];
        $only = function (array $attributes) {
            Offer::query()->update(['is_active' => false]);

            return $this->offer($attributes);
        };

        // 2nd guest at half: half of what they add (double − single)
        $only(['name' => '2nd guest half', 'target' => 'second_guest', 'value' => 50]);
        $second = ((float) $balcony->price_double - (float) $balcony->price_single) / 2;
        $this->assertEquals($cents($second), (float) $this->book($email('second'))->offer_discount_total);

        // 1 free night: 8960 × 1 ÷ nights
        $only(['name' => 'Free night', 'target' => 'free_nights', 'free_nights' => 1, 'type' => 'free', 'value' => 100]);
        $this->assertEquals($cents(8960 / $nights), (float) $this->book($email('night'))->offer_discount_total);

        // Balcony at the interior price: the same guests pay the interior fare (double + 3rd guest)
        $interior = $this->cabinPrice('interior');
        $only(['name' => 'Upgrade', 'target' => 'upgrade', 'upgrade_from' => 'interior', 'type' => 'free', 'value' => 100, 'cabin_categories' => ['balcony']]);
        $upgraded = $this->book($email('upgrade'));
        $this->assertEquals((float) $interior->price_double + (float) $interior->price_extra, (float) $upgraded->grand_total);
        // Booking the interior cabin itself: nothing to upgrade
        $this->assertSame([], $this->book($email('same') + ['cabin' => 'interior', 'guests' => ['child' => 0], 'travellers' => ['child-1' => null]])->price_snapshot['offers']);

        // Fixed final price: the cabin at 5000, and with an age offer each child pays 100 of their share
        $only(['name' => 'Cabin 5000', 'type' => 'fixed_price', 'value' => 5000]);
        $this->assertSame('5000.00', $this->book($email('fixed'))->grand_total);
        $only(['name' => 'Kids 100', 'target' => 'age', 'max_age' => 11, 'type' => 'fixed_price', 'value' => 100]);
        $this->assertEquals($cents(8960 / 3 - 100), (float) $this->book($email('kids'))->offer_discount_total);
    }

    public function test_an_offer_that_takes_nothing_off_never_blocks_a_promo_or_drops_the_commission(): void
    {
        // "At the price of the suite" on a balcony: the suite costs more, so there is nothing to take off
        AgentRate::create(['percent' => 10]);
        $agent = $this->agent();
        $this->offer(['name' => 'Suite price', 'target' => 'upgrade', 'upgrade_from' => 'suite', 'type' => 'free', 'value' => 100,
            'agent_mode' => 'replace', 'combinable' => false]);

        // The promo code still works (10% of 8960 → 895)
        $booking = $this->book(['promoCode' => 'safer10']);
        $this->assertSame(['0.00', '895.00', '8065.00'], [$booking->offer_discount_total, $booking->discount_total, $booking->grand_total]);
        $this->assertSame([], $booking->price_snapshot['offers']);

        // The agent keeps the commission (agent fare 8064)
        $agentBooking = $this->book(['contact' => ['email' => 'agent-booking@example.com']], $agent);
        $this->assertSame(['8064.00', '896.00', '8064.00'], [$agentBooking->fare_total, $agentBooking->agent_discount_total, $agentBooking->grand_total]);
        $this->assertSame(0, Offer::sole()->used_count);
    }

    public function test_the_cabin_types_condition_is_checked_cabin_by_cabin(): void
    {
        // Balcony is on the list: applies; suites only: not
        $this->offer(['cabin_categories' => ['balcony', 'suite']]);
        $this->assertSame('8064.00', $this->book()->grand_total);
        Offer::query()->update(['is_active' => false]);
        $this->offer(['name' => 'Suites', 'cabin_categories' => ['suite']]);
        $this->assertSame('8960.00', $this->book(['contact' => ['email' => 'c2@example.com']])->grand_total);
        // Published either way: the page decides with the cabin chosen
        $this->assertCount(1, $this->publishedOffers());

        // Two cabins in one booking: only the balcony gets the balcony offer (7280 × 10% = 728)
        Offer::query()->update(['is_active' => false]);
        $this->offer(['name' => 'Balcony deal', 'cabin_categories' => ['balcony']]);
        $cabin = fn (string $slug, string $first) => ['cabin' => $slug, 'guests' => ['adult' => 2, 'child' => 0, 'infant' => 0], 'travellers' => [
            'adult-1' => ['first' => $first, 'last' => 'Saleh', 'dob' => '1990-01-01', 'gender' => 'Male', 'nationality' => 'Saudi Arabia'],
            'adult-2' => ['first' => $first, 'last' => 'Omar', 'dob' => '1990-01-01', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
        ]];
        $payload = array_diff_key($this->payload(), array_flip(['cabin', 'guests', 'travellers'])) + ['cabins' => [$cabin('balcony', 'Ali'), $cabin('interior', 'Sami')]];
        app()->forgetScopedInstances();
        $ref = $this->postJson('/booking/confirm', $payload)->assertCreated()->json('reference');
        $this->assertSame('728.00', Booking::where('reference', $ref)->sole()->offer_discount_total);
    }

    public function test_the_nationalities_condition(): void
    {
        // Nationalities: every traveller of the cabin for a cabin offer, the guest's own for an age offer
        $this->offer(['name' => 'Saudi residents', 'nationalities' => ['Saudi Arabia']]);
        $this->offer(['name' => 'Kids', 'target' => 'age', 'max_age' => 11, 'value' => 50, 'nationalities' => ['Saudi Arabia']]);
        $other = collect(config('cruise.nationalities'))->first(fn ($n) => $n !== 'Saudi Arabia');

        $all = $this->book(['contact' => ['email' => 'n1@example.com']]);
        $this->assertEqualsCanonicalizing(['Saudi residents', 'Kids'], array_column($all->price_snapshot['offers'], 'name'));
        // An adult of another nationality: not the cabin offer, the Saudi child still gets the age offer
        $mixed = $this->book(['travellers' => ['adult-2' => ['nationality' => $other]], 'contact' => ['email' => 'n2@example.com']]);
        $this->assertSame(['Kids'], array_column($mixed->price_snapshot['offers'], 'name'));
    }

    public function test_admin_manages_offers(): void
    {
        $this->actingAs(User::where('email', 'admin@safercruise.test')->sole(), 'admin');
        $sailing = $this->sailing();
        $otherCruise = \App\Models\Cruise::whereKeyNot($sailing->cruise_id)->value('id');
        $form = ['name' => 'Kids sail free', 'cruise_id' => '', 'sailing_id' => $sailing->id, 'target' => 'age', 'min_age' => '', 'max_age' => 11,
            'type' => 'percent', 'value' => 100, 'max_discount' => '', 'max_uses' => 50, 'starts_on' => '', 'ends_on' => '2026-11-30',
            'audience' => 'b2c', 'agent_mode' => 'stack', 'is_active' => 1];

        $this->get(route('admin.offers.create'))->assertOk()->assertSee('RSD-261213');
        $this->post(route('admin.offers.store'), $form)->assertRedirect()->assertSessionHasNoErrors();

        // The sailing brings its cruise
        $offer = Offer::sole();
        $this->assertSame([$sailing->cruise_id, $sailing->id, 'age', null, 11, false], [$offer->cruise_id, $offer->sailing_id, $offer->target->value, $offer->min_age, $offer->max_age, $offer->combinable]);
        $this->get(route('admin.offers.index'))->assertOk()->assertSee('Kids sail free')->assertSee('Live');

        // A sailing of another cruise, an age offer without ages, over 100%, ends before it starts
        $this->post(route('admin.offers.store'), ['cruise_id' => $otherCruise] + $form)->assertSessionHasErrors('sailing_id');
        $this->post(route('admin.offers.store'), ['max_age' => ''] + $form)->assertSessionHasErrors('target');
        $this->post(route('admin.offers.store'), ['value' => 101] + $form)->assertSessionHasErrors('value');
        $this->post(route('admin.offers.store'), ['starts_on' => '2026-12-01'] + $form)->assertSessionHasErrors('ends_on');
        $this->assertSame(1, Offer::count());

        // The form shows each choice's fields only (data-show-when), with its conditions under their switches
        $this->get(route('admin.offers.create'))->assertOk()
            ->assertSee('data-show-when="target" data-show-values="age"', false)
            ->assertSee('data-show-when="if_cabins" data-show-values="1"', false)
            ->assertSee('Only some cabin types')->assertSee('Some nationalities')
            // Only these two conditions are left
            ->assertDontSee('Early booking')->assertDontSee('Departure season')->assertDontSee('Last minute')
            ->assertDontSee('Groups and families')->assertDontSee('Longer cruises')->assertDontSee('Returning customers only');

        // A ticked condition needs its values
        $fare = ['target' => 'fare', 'max_age' => '', 'sailing_id' => '', 'cruise_id' => $sailing->cruise_id] + $form;
        $this->post(route('admin.offers.store'), ['if_cabins' => 1] + $fare)->assertSessionHasErrors('if_cabins');
        $this->post(route('admin.offers.store'), ['if_nationality' => 1] + $fare)->assertSessionHasErrors('if_nationality');

        // Unticked conditions and a fare offer's ages are cleared; "Free" saves 100%
        $this->post(route('admin.offers.store'), ['name' => 'Suites', 'type' => 'free', 'value' => '', 'min_age' => 3,
            'if_cabins' => 1, 'cabin_categories' => ['suite'],
            'if_nationality' => 0, 'nationalities' => ['Egypt']] + $fare)->assertSessionHasNoErrors();
        $offer = Offer::where('name', 'Suites')->sole();
        $this->assertSame([['suite'], null, null, 'free', '100.00'], [$offer->cabin_categories, $offer->nationalities, $offer->min_age, $offer->type->value, $offer->value]);

        // Free nights need their number and save as free; an upgrade needs its cabin
        $this->post(route('admin.offers.store'), ['target' => 'free_nights'] + $fare)->assertSessionHasErrors('free_nights');
        $this->post(route('admin.offers.store'), ['target' => 'upgrade'] + $fare)->assertSessionHasErrors('upgrade_from');
        $this->post(route('admin.offers.store'), ['name' => '7 for 6', 'target' => 'free_nights', 'free_nights' => 1, 'type' => '', 'value' => '', 'upgrade_from' => 'interior'] + $fare)->assertSessionHasNoErrors();
        $nightsOffer = Offer::where('name', '7 for 6')->sole();
        $this->assertSame([1, 'free', null], [$nightsOffer->free_nights, $nightsOffer->type->value, $nightsOffer->upgrade_from]);
        $this->assertSame('1 free night', $nightsOffer->discount_label);

        // Editing shows the ticked switches
        $this->get(route('admin.offers.edit', $offer))->assertOk()->assertSee('name="if_cabins" value="1" class="check" checked', false);
    }
}
