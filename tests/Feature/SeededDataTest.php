<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CabinCategory;
use App\Models\Cruise;
use App\Models\Destination;
use App\Models\Sailing;
use App\Models\Ship;
use App\Models\ShipZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeededDataTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
    }

    public function test_seed_matches_the_template_mock_data(): void
    {
        $this->assertSame(6, Cruise::count());
        $this->assertSame(17, Sailing::upcoming()->count());
        $this->assertSame(8, Cruise::where('slug', 'red-sea-discovery')->firstOrFail()->itinerary()->count());
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('image_slots'));

        $ship = Ship::where('slug', 'aroya')->firstOrFail();
        $this->assertSame("Saudi Arabia's first cruise ship", $ship->tagline);
        $this->assertCount(2, $ship->story);
        $this->assertSame([7, 4, 6, 14, 9], [ // 4 ship FAQs + 10 from the pre-boarding guide
            $ship->specs()->count(), $ship->zones()->count(), $ship->essentials()->count(),
            $ship->faqs()->count(), $ship->facilities()->count(),
        ]);
    }

    public function test_every_seeded_image_is_a_valid_photo(): void
    {
        $used = \App\Support\MediaLibrary::usedValues();

        $this->assertNotEmpty($used);
        $this->assertSame([], array_values(array_filter($used, fn ($v) => ! \App\Support\MediaLibrary::isValid($v))));
        $this->assertCount(3, CabinCategory::where('slug', 'balcony')->firstOrFail()->images);
        $this->assertCount(4, Ship::firstOrFail()->zones->pluck('image')->filter());
        $this->assertNotNull(Destination::firstOrFail()->image);
        $this->assertNotNull(ShipZone::firstOrFail()->image);
    }

    public function test_destinations_list_the_cruises_that_call_there(): void
    {
        $slugs = fn (string $port) => Destination::where('slug', $port)->firstOrFail()->cruises()->pluck('slug')->sort()->values()->all();

        $this->assertSame(['egypt-jordan-voyage'], $slugs('aqaba'));
        $this->assertSame(['aroya-repositioning', 'red-sea-discovery'], $slugs('sokhna'));
        // Route entry is "Al Wajh, Saudi Arabia": matched on the start of the entry.
        $this->assertSame(['new-year-at-sea', 'red-sea-discovery', 'yanbu-winter-escape'], $slugs('al-wajh'));
    }

    public function test_dashboard_tabs_split_bookings_like_the_template(): void
    {
        $user = User::where('email', 'reem@example.com')->firstOrFail();

        $this->assertSame(['SC2611-K4P2Q'], $user->bookings()->upcoming()->pluck('reference')->all());
        $this->assertSame(['SC2603-H7M1Z'], $user->bookings()->past()->pluck('reference')->all());
        $this->assertSame(['SC2605-T9R3B'], $user->bookings()->cancelled()->pluck('reference')->all());
    }

    public function test_demo_bookings_are_in_dollars_with_offline_payment_states(): void
    {
        $booking = fn (string $ref) => Booking::where('reference', $ref)->firstOrFail();

        $this->assertSame('USD', $booking('SC2611-K4P2Q')->currency);
        $this->assertSame(PaymentStatus::Unpaid, $booking('SC2611-K4P2Q')->payment_status);
        $this->assertSame(3840.0, $booking('SC2611-K4P2Q')->amountDue()); // tax included, no fees (step 21)

        $this->assertSame(PaymentStatus::Paid, $booking('SC2603-H7M1Z')->payment_status);
        $this->assertSame(0.0, $booking('SC2603-H7M1Z')->amountDue());

        // Cancelled 35 days out: half refunded, half kept as the cancellation penalty.
        $this->assertSame(PaymentStatus::Refunded, $booking('SC2605-T9R3B')->payment_status);
        $this->assertSame('8260.00', $booking('SC2605-T9R3B')->amount_paid); // half of 16,520 (tax included, step 21)

        $this->assertSame(['SC2611-K4P2Q'], Booking::awaitingPayment()->pluck('reference')->all());
    }

    public function test_travellers_are_ordered_by_band_then_slot(): void
    {
        $booking = Booking::where('reference', 'SC2605-T9R3B')->firstOrFail();

        $this->assertSame(
            ['adult-1', 'adult-2', 'child-1', 'child-2'],
            $booking->travellers->map(fn ($t) => $t->band->value.'-'.$t->slot)->all(),
        );
        $this->assertTrue($booking->travellers->first()->is_primary);
    }

    public function test_user_name_is_kept_in_step_with_first_and_last_name(): void
    {
        $user = User::where('email', 'reem@example.com')->firstOrFail();
        $user->update(['last_name' => 'Alotaibi']);

        $this->assertSame('Reem Alotaibi', $user->fresh()->name);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue(User::where('email', 'admin@safercruise.test')->firstOrFail()->isAdmin());
    }

    public function test_booking_reference_has_the_template_format(): void
    {
        $ref = Booking::generateReference(now()->setDate(2026, 11, 5));

        $this->assertMatchesRegularExpression('/^SC2611-[A-Z0-9]{5}$/', $ref);
    }
}
