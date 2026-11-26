<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin → Bookings in tabs by where the booking stands (Booking::stage()), as the client asked: New (nothing paid,
 * the page opens on it), Confirmed (a payment recorded, part or in full), Cancelled, All. The seed has one of each:
 * SC2611-K4P2Q unpaid, SC2603-H7M1Z paid, SC2605-T9R3B cancelled.
 */
class AdminBookingTabsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const NEW = 'SC2611-K4P2Q';

    private const PAID = 'SC2603-H7M1Z';

    private const CANCELLED = 'SC2605-T9R3B';

    private function staff(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** The tab's link with its count, e.g. "New 1" */
    private function tab(string $label, int $count): string
    {
        return $label.' '.$count;
    }

    public function test_the_page_opens_on_new_and_each_tab_lists_its_bookings(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');

        $this->get(route('admin.bookings.index'))->assertOk()
            ->assertSeeInOrder(['Nothing paid yet', 'aria-current="page"', 'A payment is recorded'], false)
            ->assertSeeText($this->tab('New', 1))->assertSeeText($this->tab('Confirmed', 1))
            ->assertSeeText($this->tab('Cancelled', 1))->assertSeeText($this->tab('All', 3))
            ->assertSee(self::NEW)->assertDontSee(self::PAID)->assertDontSee(self::CANCELLED)
            // The badge says where it stands; the payment badge stays next to it
            ->assertSeeInOrder([self::NEW, '<span class="tag tag-sun">New</span>', '<span class="tag tag-sun">Unpaid</span>'], false)
            ->assertDontSee('Booking</label>', false);

        $this->get(route('admin.bookings.index', ['stage' => 'confirmed']))->assertOk()
            ->assertSee(self::PAID)->assertDontSee(self::NEW)->assertDontSee(self::CANCELLED)
            ->assertSeeInOrder([self::PAID, '<span class="tag tag-lagoon">Confirmed</span>', '<span class="tag tag-lagoon">Paid</span>'], false);
        $this->get(route('admin.bookings.index', ['stage' => 'cancelled']))->assertOk()
            ->assertSee(self::CANCELLED)->assertDontSee(self::NEW)->assertDontSee(self::PAID);
        $this->get(route('admin.bookings.index', ['stage' => 'all']))->assertOk()
            ->assertSee(self::NEW)->assertSee(self::PAID)->assertSee(self::CANCELLED);
        $this->get(route('admin.bookings.index', ['stage' => 'held']))->assertSessionHasErrors('stage');
    }

    public function test_a_part_payment_moves_a_booking_to_confirmed(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $booking = Booking::where('reference', self::NEW)->sole();
        $this->assertSame('new', $booking->stage());

        Payment::create(['booking_id' => $booking->id, 'type' => 'payment', 'method' => 'bank_transfer', 'amount' => 100, 'currency' => 'USD', 'received_at' => now()]);
        $booking->syncPaymentTotals();
        $this->assertSame('confirmed', $booking->fresh()->stage());

        $this->get(route('admin.bookings.index'))->assertOk()
            ->assertSeeText($this->tab('New', 0))->assertSeeText($this->tab('Confirmed', 2))
            ->assertDontSee(self::NEW)->assertSeeText('No new bookings match.');
        $this->get(route('admin.bookings.index', ['stage' => 'confirmed']))->assertOk()
            ->assertSeeInOrder([self::NEW, '<span class="tag tag-lagoon">Confirmed</span>', '<span class="tag tag-sun">Part paid</span>'], false);
        $this->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('<span class="tag tag-lagoon">Confirmed</span>', false);
    }

    public function test_the_counts_follow_the_search_and_the_channel(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');

        // Searching one reference: every tab counts within the search
        $this->get(route('admin.bookings.index', ['q' => self::PAID]))->assertOk()
            ->assertSeeText($this->tab('New', 0))->assertSeeText($this->tab('Confirmed', 1))->assertSeeText($this->tab('All', 1));

        // The seeded bookings are B2C: none under Agents, in any tab; the channel counts follow the tab
        $this->get(route('admin.bookings.index', ['channel' => 'agent']))->assertOk()
            ->assertSeeText($this->tab('New', 0))->assertSeeText($this->tab('All', 0))->assertDontSee(self::NEW);
        $this->get(route('admin.bookings.index', ['stage' => 'all']))->assertOk()
            ->assertSeeText('All bookings (3)')->assertSeeText('B2C customers (3)')->assertSeeText('Agents (0)');
        $this->get(route('admin.bookings.index'))->assertOk()->assertSeeText('All bookings (1)');

        // A tab link keeps the other filters
        $this->get(route('admin.bookings.index', ['channel' => 'b2c']))->assertOk()
            ->assertSee(route('admin.bookings.index', ['stage' => 'cancelled', 'channel' => 'b2c']));
    }

    public function test_an_old_status_link_still_works(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');

        $this->get(route('admin.bookings.index', ['status' => 'cancelled']))->assertOk()
            ->assertSee(self::CANCELLED)->assertDontSee(self::NEW)->assertDontSee(self::PAID);
    }

    public function test_sellers_see_their_own_bookings_in_each_tab_and_the_menu_counts_new_ones(): void
    {
        $seller = $this->staff('seller@safercruise.test');
        Booking::whereIn('reference', [self::NEW, self::PAID])->update(['seller_id' => $seller->id]);
        Booking::where('reference', self::CANCELLED)->update(['seller_id' => null]);
        $this->actingAs($seller, 'admin');

        $this->get(route('admin.bookings.index'))->assertOk()
            ->assertSee(self::NEW)->assertDontSee(self::PAID)
            ->assertSeeText($this->tab('New', 1))->assertSeeText($this->tab('Cancelled', 0))->assertSeeText($this->tab('All', 2));
        $this->get(route('admin.bookings.index', ['stage' => 'confirmed']))->assertOk()->assertSee(self::PAID)->assertDontSee(self::NEW);
        $this->get(route('admin.bookings.index', ['stage' => 'cancelled']))->assertOk()->assertDontSee(self::CANCELLED);

        // The number on "My bookings" in the sidebar is the New count
        $this->assertSame(1, Booking::atStage('new')->where('seller_id', $seller->id)->count());
    }
}
