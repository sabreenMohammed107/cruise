<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Customers vs admins & staff, the customer export, the Seller role and assigning bookings to sellers. */
class StaffCustomersSellersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@safercruise.test')->firstOrFail();
    }

    private function seller(): User
    {
        return User::where('email', 'seller@safercruise.test')->firstOrFail();
    }

    private function customer(array $attributes = []): User
    {
        return User::create($attributes + ['first_name' => 'Test', 'last_name' => 'Customer', 'email' => uniqid('c').'@example.com', 'password' => 'password1']);
    }

    /* ---------- 1. Customers and staff are separate ---------- */

    public function test_customers_and_staff_are_listed_separately(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/customers')->assertOk()
            ->assertSee('reem@example.com')
            ->assertDontSee('admin@safercruise.test')->assertDontSee('seller@safercruise.test')
            ->assertSee('Export to Excel');

        $this->get('/admin/users')->assertOk()
            ->assertSee('Admins &amp; staff', false)
            ->assertSee('admin@safercruise.test')->assertSee('seller@safercruise.test')
            ->assertDontSee('reem@example.com');

        // Each screen only opens its own people
        $reem = User::where('email', 'reem@example.com')->firstOrFail();
        $this->get("/admin/users/{$reem->id}/edit")->assertNotFound();
        $this->get("/admin/customers/{$this->seller()->id}/edit")->assertNotFound();
        $this->get("/admin/customers/{$this->seller()->id}")->assertNotFound();
        $this->delete("/admin/users/{$reem->id}")->assertNotFound();
        $this->assertModelExists($reem);
    }

    public function test_customer_filters_search_and_details(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $reem = User::where('email', 'reem@example.com')->firstOrFail();
        $new = $this->customer(['email' => 'new.customer@example.com', 'nationality' => 'Egypt']);

        $this->get('/admin/customers?q=new.customer')->assertSee('new.customer@example.com')->assertDontSee('reem@example.com');
        $this->get('/admin/customers?nationality=Egypt')->assertSee('new.customer@example.com')->assertDontSee('reem@example.com');
        $this->get('/admin/customers?bookings=with')->assertSee('reem@example.com')->assertDontSee('new.customer@example.com');
        $this->get('/admin/customers?bookings=without')->assertSee('new.customer@example.com')->assertDontSee('reem@example.com');
        $this->get('/admin/customers?from='.today()->addDay()->toDateString())->assertDontSee('new.customer@example.com');

        $this->get("/admin/customers/{$reem->id}")->assertOk()
            ->assertSee('Reem Alharbi')->assertSee('+966 501234567')
            ->assertSee($reem->bookings()->first()->reference);

        // A customer with bookings can't be deleted; one without can
        $this->delete("/admin/customers/{$reem->id}")->assertSessionHas('error');
        $this->assertModelExists($reem);
        $this->delete("/admin/customers/{$new->id}")->assertRedirect();
        $this->assertModelMissing($new);
    }

    public function test_customers_screens_are_admin_only(): void
    {
        $this->actingAs($this->seller(), 'admin');

        foreach (['/admin/customers', '/admin/customers/export', '/admin/users', '/admin/cruises', '/admin/home-sections'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    /* ---------- 2. Export ---------- */

    public function test_customer_export_is_an_excel_ready_csv_of_the_filtered_list(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->customer(['first_name' => 'منى', 'last_name' => '=HYPERLINK("x")', 'email' => 'mona@example.com', 'nationality' => 'Egypt', 'phone_dial' => '+20', 'phone' => '1012345678']);

        $response = $this->get('/admin/customers/export')->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('customers-'.now()->format('Y-m-d').'.csv', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv); // BOM: Excel reads the file as UTF-8
        $rows = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
        $this->assertSame(['ID', 'First name', 'Last name', 'Email', 'Phone', 'Nationality', 'Registered', 'Email verified', 'Bookings', 'Confirmed bookings', 'Last booking', 'Status'], $rows[0]);

        $emails = array_column(array_slice($rows, 1), 3);
        $this->assertContains('reem@example.com', $emails);
        $this->assertContains('mona@example.com', $emails);
        $this->assertNotContains('admin@safercruise.test', $emails);
        $this->assertNotContains('seller@safercruise.test', $emails);

        $mona = collect($rows)->firstWhere(3, 'mona@example.com');
        $this->assertSame('منى', $mona[1]);
        $this->assertSame("'=HYPERLINK(\"x\")", $mona[2]); // formulas are neutralised
        $this->assertSame('(+20) 1012345678', $mona[4]);
        $this->assertSame(['0', 'No bookings yet'], [$mona[8], $mona[11]]);

        $reem = collect($rows)->firstWhere(3, 'reem@example.com');
        $this->assertSame((string) User::where('email', 'reem@example.com')->first()->bookings()->count(), $reem[8]);

        // Same filters as the list
        $filtered = $this->get('/admin/customers/export?nationality=Egypt')->streamedContent();
        $this->assertStringContainsString('mona@example.com', $filtered);
        $this->assertStringNotContainsString('reem@example.com', $filtered);
    }

    /* ---------- 3. Seller role ---------- */

    public function test_admins_create_sellers_and_sellers_sign_in(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->get('/admin/users/create')->assertOk()->assertSee('<option value="seller"', false)
            ->assertSee('<option value="admin"', false)->assertDontSee('<option value="customer"', false);
        // Customers are only made / managed under Customers
        $this->post('/admin/users', ['first_name' => 'C', 'last_name' => 'D', 'email' => 'cd@example.com', 'role' => 'customer', 'password' => 'secret123'])
            ->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'cd@example.com']);
        $this->get('/admin/customers/create')->assertOk()->assertSee('<option value="customer"', false);

        $this->post('/admin/users', [
            'first_name' => 'Omar', 'last_name' => 'Sales', 'email' => 'Omar.Sales@example.com',
            'role' => 'seller', 'password' => 'secret123',
        ])->assertRedirect('/admin/users')->assertSessionHasNoErrors();

        $omar = User::where('email', 'omar.sales@example.com')->firstOrFail();
        $this->assertSame(UserRole::Seller, $omar->role);
        $this->assertTrue($omar->isStaff());

        $this->post('/admin/users', ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@example.com', 'role' => 'owner', 'password' => 'secret123'])
            ->assertSessionHasErrors('role');

        // A seller signs in to the panel; the website sign-up never makes staff
        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => 'omar.sales@example.com', 'password' => 'secret123'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('My bookings')->assertDontSee('Admins &amp; staff', false);

        $this->artisan('user:make-admin', ['email' => 'reem@example.com', '--seller' => true])->assertSuccessful();
        $this->assertTrue(User::where('email', 'reem@example.com')->firstOrFail()->isSeller());
    }

    /* ---------- 4. Bookings assigned to sellers ---------- */

    public function test_admin_assigns_and_reassigns_a_booking(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $booking = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail();
        $seller = $this->seller();
        $other = User::create(['first_name' => 'Second', 'last_name' => 'Seller', 'email' => 'second@example.com', 'password' => 'password1']);
        $other->forceFill(['role' => UserRole::Seller])->save();

        $this->get('/admin/bookings')->assertSee('Unassigned')->assertSee('name="seller_id"', false);

        $this->patch("/admin/bookings/{$booking->reference}/seller", ['seller_id' => $seller->id])
            ->assertSessionHas('status', 'Booking SC2611-K4P2Q assigned to Sara Seller.');
        $this->assertSame($seller->id, $booking->fresh()->seller_id);
        $this->assertTrue($seller->assignedBookings()->whereKey($booking->id)->exists());
        $this->assertSame('Sara Seller', $booking->fresh()->seller->name);

        $this->patch("/admin/bookings/{$booking->reference}/seller", ['seller_id' => $other->id]);
        $this->assertSame($other->id, $booking->fresh()->seller_id);

        // Only sellers can be assigned
        $this->patch("/admin/bookings/{$booking->reference}/seller", ['seller_id' => $this->admin()->id])->assertSessionHasErrors('seller_id');
        $reem = User::where('email', 'reem@example.com')->firstOrFail();
        $this->patch("/admin/bookings/{$booking->reference}/seller", ['seller_id' => $reem->id])->assertSessionHasErrors('seller_id');
        $this->assertSame($other->id, $booking->fresh()->seller_id);

        // Filter by seller, and unassign
        $this->get("/admin/bookings?seller={$other->id}")->assertSee('SC2611-K4P2Q');
        $this->get("/admin/bookings?seller={$seller->id}")->assertDontSee('SC2611-K4P2Q');
        $this->get('/admin/bookings?seller=none')->assertDontSee('SC2611-K4P2Q');
        $this->get("/admin/bookings/{$booking->reference}")->assertSee('Assigned seller');

        $this->patch("/admin/bookings/{$booking->reference}/seller", ['seller_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($booking->fresh()->seller_id);

        // Deleting a seller leaves their bookings unassigned
        $this->patch("/admin/bookings/{$booking->reference}/seller", ['seller_id' => $other->id]);
        $this->delete("/admin/users/{$other->id}")->assertRedirect();
        $this->assertNull($booking->fresh()->seller_id);
    }

    public function test_seller_only_works_on_their_own_bookings(): void
    {
        $seller = $this->seller();
        $mine = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail();
        $mine->update(['seller_id' => $seller->id]);
        $other = Booking::where('id', '!=', $mine->id)->firstOrFail();

        $this->actingAs($seller, 'admin');

        $this->get('/admin/bookings')->assertOk()->assertSee($mine->reference)->assertDontSee($other->reference)
            ->assertDontSee('name="seller_id"', false);
        $this->get('/admin')->assertOk()->assertSee($mine->reference)->assertDontSee($other->reference)->assertDontSee('Next departures');

        $this->get("/admin/bookings/{$mine->reference}")->assertOk()->assertSee('Sara Seller')->assertDontSee('Cancel booking');
        $this->get("/admin/bookings/{$other->reference}")->assertForbidden();
        $this->get(route('booking.confirmation', $mine))->assertOk(); // the guest voucher
        $this->get(route('booking.confirmation', $other))->assertNotFound(); // the voucher never says whether a booking exists

        // Notes and payments on their booking; nothing on others; no admin actions
        $this->patch("/admin/bookings/{$mine->reference}", ['admin_notes' => 'Called the guest.'])->assertSessionHasNoErrors();
        $this->assertSame('Called the guest.', $mine->fresh()->admin_notes);
        $this->post("/admin/bookings/{$mine->reference}/payments", ['type' => 'payment', 'method' => 'cash', 'amount' => 100, 'received_at' => today()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame($seller->id, $mine->payments()->first()->recorded_by);

        $this->patch("/admin/bookings/{$other->reference}", ['admin_notes' => 'x'])->assertForbidden();
        $this->post("/admin/bookings/{$other->reference}/payments", ['type' => 'payment', 'method' => 'cash', 'amount' => 1, 'received_at' => today()->toDateString()])->assertForbidden();
        $this->patch("/admin/bookings/{$mine->reference}/seller", ['seller_id' => ''])->assertForbidden();
        $this->post("/admin/bookings/{$mine->reference}/cancel")->assertForbidden();
        $this->delete("/admin/bookings/{$mine->reference}/payments/{$mine->payments()->first()->id}")->assertForbidden();
        $this->assertSame($seller->id, $mine->fresh()->seller_id);
    }
}
