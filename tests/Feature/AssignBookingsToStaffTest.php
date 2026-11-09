<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bookings can be assigned to any staff member (admins, supervisors and sellers), not only sellers. Admins and
 * supervisors assign; the "Assigned to" filter covers every staff member. Sellers still see only their own bookings.
 */
class AssignBookingsToStaffTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->booking = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail();
    }

    private function staff(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function make(string $email, UserRole $role, string $first = 'Test'): User
    {
        $user = User::create(['first_name' => $first, 'last_name' => $role->label(), 'email' => $email, 'password' => 'password1']);
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function assign(?User $to)
    {
        return $this->patch(route('admin.bookings.seller', $this->booking), ['seller_id' => $to?->id ?? '']);
    }

    public function test_admin_assigns_to_an_admin_a_supervisor_or_a_seller(): void
    {
        $admin = $this->staff('admin@safercruise.test');
        $this->actingAs($admin, 'admin');

        foreach ([$admin, $this->staff('supervisor@safercruise.test'), $this->staff('seller@safercruise.test')] as $person) {
            $this->assign($person)->assertSessionHasNoErrors()
                ->assertSessionHas('status', "Booking SC2611-K4P2Q assigned to {$person->name}.");
            $this->assertSame($person->id, $this->booking->fresh()->seller_id);
            $this->assertTrue($person->assignedBookings()->whereKey($this->booking->id)->exists());
        }

        // Unassign still works
        $this->assign(null)->assertSessionHasNoErrors();
        $this->assertNull($this->booking->fresh()->seller_id);
    }

    public function test_only_staff_can_be_assigned(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $this->assign($this->staff('seller@safercruise.test'));
        $seller = $this->staff('seller@safercruise.test')->id;

        $agent = $this->make('agent@example.com', UserRole::Agent);
        $hidden = $this->make('dev@example.com', UserRole::SuperAdmin, 'Developer');
        foreach ([$this->staff('reem@example.com'), $agent, $hidden] as $notStaff) {
            $this->assign($notStaff)->assertSessionHasErrors(['seller_id' => 'Choose an admin, supervisor or seller.']);
        }
        $this->patch(route('admin.bookings.seller', $this->booking), ['seller_id' => 999999])->assertSessionHasErrors('seller_id');
        $this->assertSame($seller, $this->booking->fresh()->seller_id);

        // Nor listed: the hidden super admin, customers, agents
        $this->get(route('admin.bookings.show', $this->booking))
            ->assertDontSee("<option value=\"{$hidden->id}\"", false)
            ->assertDontSee("<option value=\"{$agent->id}\"", false);
    }

    public function test_pickers_and_filter_list_staff_grouped_by_role(): void
    {
        $admin = $this->staff('admin@safercruise.test');
        $this->actingAs($admin, 'admin');
        $ids = fn () => [$admin->id, $this->staff('supervisor@safercruise.test')->id, $this->staff('seller@safercruise.test')->id];

        // Booking page and list rows: admins, then supervisors, then sellers
        foreach ([route('admin.bookings.show', $this->booking), route('admin.bookings.index')] as $url) {
            [$a, $su, $se] = $ids();
            $this->get($url)->assertOk()
                ->assertSee('Assigned to')
                ->assertSeeInOrder(['<optgroup label="Admins">', "value=\"{$a}\"", '<optgroup label="Supervisors">', "value=\"{$su}\"", '<optgroup label="Sellers">', "value=\"{$se}\""], false);
        }
        // The list's filter is "Assigned to" (no "Seller" column or filter any more), with a shortcut to my own
        $this->get(route('admin.bookings.index'))
            ->assertSee('<label for="seller" class="label">Assigned to</label>', false)
            ->assertSee('>Assigned to me</option>', false)
            ->assertDontSee('>Seller</th>', false);
    }

    public function test_filter_by_any_staff_member(): void
    {
        $admin = $this->staff('admin@safercruise.test');
        $supervisor = $this->staff('supervisor@safercruise.test');
        $this->actingAs($admin, 'admin');
        $this->assign($supervisor);

        $this->get(route('admin.bookings.index', ['seller' => $supervisor->id]))->assertSessionHasNoErrors()->assertSee('SC2611-K4P2Q');
        $this->get(route('admin.bookings.index', ['seller' => $admin->id]))->assertDontSee('SC2611-K4P2Q');
        $this->get(route('admin.bookings.index', ['seller' => 'none']))->assertDontSee('SC2611-K4P2Q');

        // Assigned to me
        $this->assign($admin);
        $this->get(route('admin.bookings.index', ['seller' => $admin->id]))->assertSee('SC2611-K4P2Q')
            ->assertSee('<option value="'.$admin->id.'" selected>Assigned to me</option>', false);

        // Admins & staff → "Bookings (n)" links to this filter for an admin too (it was refused when only sellers counted)
        $this->get(route('admin.users.index'))->assertSee(e(route('admin.bookings.index', ['seller' => $admin->id])), false);

        // A customer's id is not a filter
        $this->get(route('admin.bookings.index', ['seller' => $this->staff('reem@example.com')->id]))->assertSessionHasErrors('seller');
    }

    public function test_sellers_still_see_only_their_own_bookings(): void
    {
        $seller = $this->staff('seller@safercruise.test');
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $this->assign($this->staff('supervisor@safercruise.test'));

        // Assigned to a supervisor: not the seller's
        $this->actingAs($seller, 'admin');
        // (the link, not the reference: the "assigned to …" message of the last request names it)
        $this->get(route('admin.bookings.index'))->assertOk()
            ->assertDontSee(route('admin.bookings.show', $this->booking), false)->assertDontSee('name="seller_id"', false);
        $this->get(route('admin.bookings.show', $this->booking))->assertForbidden();
        $this->assign($seller)->assertForbidden();

        // Assigning still opens it to the seller it goes to
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $this->assign($seller);
        $this->actingAs($seller, 'admin');
        $this->get(route('admin.bookings.show', $this->booking))->assertOk()->assertSee('Sara Seller');
    }

    public function test_someone_who_is_no_longer_staff_still_shows_on_the_booking(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $former = $this->make('former@example.com', UserRole::Supervisor, 'Former');
        $this->assign($former);

        // Back to customer: kept on the booking and shown as such, not as "Unassigned"; no longer offered or filterable
        $former->forceFill(['role' => UserRole::Customer])->save();
        $this->get(route('admin.bookings.show', $this->booking))
            ->assertSee("<option value=\"{$former->id}\" selected disabled>Former Supervisor (no longer staff)</option>", false);
        $this->get(route('admin.bookings.index', ['seller' => $former->id]))->assertSessionHasErrors('seller');
        $this->assertSame($former->id, $this->booking->fresh()->seller_id);
    }
}
