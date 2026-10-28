<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Cruise;
use App\Models\HomeSection;
use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Supervisor role: adds and edits everything and assigns bookings, but can't delete or manage people. */
class SupervisorRoleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@safercruise.test')->firstOrFail();
    }

    private function supervisor(): User
    {
        return User::where('email', 'supervisor@safercruise.test')->firstOrFail();
    }

    private function seller(): User
    {
        return User::where('email', 'seller@safercruise.test')->firstOrFail();
    }

    private function newUser(string $email, string $role): array
    {
        return ['first_name' => 'New', 'last_name' => 'Person', 'email' => $email, 'role' => $role, 'password' => 'secret123'];
    }

    /* ---------- 1. Creating people ---------- */

    public function test_admin_creates_a_supervisor_who_can_sign_in(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->get('/admin/users/create')->assertOk()->assertSee('<option value="supervisor"', false);

        $this->post('/admin/users', $this->newUser('new.supervisor@example.com', 'supervisor'))
            ->assertRedirect('/admin/users')->assertSessionHasNoErrors();

        $user = User::where('email', 'new.supervisor@example.com')->firstOrFail();
        $this->assertSame(UserRole::Supervisor, $user->role);
        $this->assertTrue($user->isSupervisor());
        $this->assertTrue($user->isStaff());

        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => 'new.supervisor@example.com', 'password' => 'secret123'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Next departures');
    }

    public function test_supervisor_cannot_create_or_edit_people(): void
    {
        $this->actingAs($this->supervisor(), 'admin');
        $reem = User::where('email', 'reem@example.com')->firstOrFail();

        $this->get('/admin/users/create')->assertForbidden();
        $this->get('/admin/customers/create')->assertForbidden();
        foreach (['admin', 'supervisor', 'seller'] as $role) {
            $this->post('/admin/users', $this->newUser("{$role}.x@example.com", $role))->assertForbidden();
            $this->assertDatabaseMissing('users', ['email' => "{$role}.x@example.com"]);
        }
        $this->post('/admin/customers', $this->newUser('customer.x@example.com', 'customer'))->assertForbidden();

        // Nor promote or change anyone (a customer to admin, a seller's details)
        $this->get("/admin/customers/{$reem->id}/edit")->assertForbidden();
        $this->put("/admin/customers/{$reem->id}", ['role' => 'admin'] + $this->newUser($reem->email, 'admin'))->assertForbidden();
        $this->assertSame(UserRole::Customer, $reem->fresh()->role);
        $this->get("/admin/users/{$this->seller()->id}/edit")->assertForbidden();
        $this->put("/admin/users/{$this->seller()->id}", $this->newUser('changed@example.com', 'admin'))->assertForbidden();
        $this->assertSame(UserRole::Seller, $this->seller()->role);

        // The lists stay readable, without the add / edit / delete buttons
        $this->get('/admin/users')->assertOk()->assertSee('seller@safercruise.test')
            ->assertDontSee('New staff member')->assertDontSee('/admin/users/create')->assertDontSee("/admin/users/{$this->seller()->id}/edit");
        $this->get('/admin/customers')->assertOk()->assertSee('reem@example.com')
            ->assertDontSee('/admin/customers/create')->assertDontSee("/admin/customers/{$reem->id}/edit");
        $this->get("/admin/customers/{$reem->id}")->assertOk()->assertDontSee("/admin/customers/{$reem->id}/edit");
    }

    /* ---------- 2. Adding and editing content, but no deleting ---------- */

    public function test_supervisor_adds_and_edits_content(): void
    {
        $this->actingAs($this->supervisor(), 'admin');

        $this->post('/admin/routes', ['name_en' => 'Test Harbour', 'name_ar' => 'ينبع', 'sort_order' => 0, 'is_active' => 1])
            ->assertRedirect('/admin/routes')->assertSessionHasNoErrors();
        $port = Port::where('name_en', 'Test Harbour')->firstOrFail();

        $this->get("/admin/routes/{$port->id}/edit")->assertOk();
        $this->put("/admin/routes/{$port->id}", ['name_en' => 'Test Harbour East', 'name_ar' => 'ينبع', 'sort_order' => 1, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('Test Harbour East', $port->fresh()->name_en);

        $this->get('/admin/cruises')->assertOk()->assertSee('/admin/cruises/create')->assertDontSee('Delete');
        $this->get('/admin/home-sections')->assertOk();
    }

    public function test_supervisor_cannot_delete_anything(): void
    {
        $this->actingAs($this->supervisor(), 'admin');
        $cruise = Cruise::firstOrFail();
        $port = Port::create(['name_en' => 'Unused', 'name_ar' => 'غير مستخدم']);
        $message = ContactMessage::create(['name' => 'Reem', 'email' => 'reem@example.com', 'phone_dial' => '+966', 'phone' => '501234567', 'message' => 'Hello there, a question.']);
        $booking = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail();
        $payment = $booking->payments()->create(['type' => 'payment', 'method' => 'cash', 'amount' => 100, 'currency' => $booking->currency, 'received_at' => now()]);
        $customer = User::create(['first_name' => 'No', 'last_name' => 'Bookings', 'email' => 'nobookings@example.com', 'password' => 'password1']);

        $this->delete("/admin/cruises/{$cruise->id}")->assertForbidden();
        $this->delete("/admin/routes/{$port->id}")->assertForbidden();
        $this->delete("/admin/sailings/{$cruise->sailings()->firstOrFail()->id}")->assertForbidden();
        $this->delete(route('admin.contact-messages.destroy', $message))->assertForbidden();
        $this->delete("/admin/bookings/{$booking->reference}/payments/{$payment->id}")->assertForbidden();
        $this->delete("/admin/customers/{$customer->id}")->assertForbidden();
        $this->delete("/admin/users/{$this->seller()->id}")->assertForbidden();

        $this->assertNotSoftDeleted($cruise);
        $this->assertModelExists($port);
        $this->assertModelExists($message);
        $this->assertModelExists($payment);
        $this->assertModelExists($customer);
        $this->assertModelExists($this->seller());

        // Homepage cards: the delete checkbox is hidden, and sending it anyway changes nothing
        $why = HomeSection::where('page', 'home')->where('key', 'why')->firstOrFail();
        $card = $why->items()->firstOrFail();
        $this->get('/admin/home-sections/why/edit')->assertOk()->assertDontSee("items[{$card->id}][delete]", false);
        $this->put('/admin/home-sections/why', $why->only(['title', 'title_ar']) + ['is_visible' => 1, 'items' => [$card->id => ['title' => $card->title, 'delete' => 1]]])
            ->assertForbidden();
        $this->assertModelExists($card);

        // No delete buttons on the screens
        $this->get('/admin/routes')->assertOk()->assertDontSee("/admin/routes/{$port->id}\"", false);
        $this->get(route('admin.contact-messages.show', $message))->assertOk()->assertDontSee('Delete message');
        $this->get("/admin/sailings/{$cruise->sailings()->firstOrFail()->id}/edit")->assertOk()->assertDontSee('Delete sailing');
        $this->get("/admin/bookings/{$booking->reference}")->assertOk()->assertDontSee("/payments/{$payment->id}", false);
    }

    public function test_admin_can_still_delete(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $cruise = Cruise::firstOrFail();
        $port = Port::create(['name_en' => 'Unused', 'name_ar' => 'غير مستخدم']);
        $booking = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail();
        $payment = $booking->payments()->create(['type' => 'payment', 'method' => 'cash', 'amount' => 100, 'currency' => $booking->currency, 'received_at' => now()]);

        $this->get('/admin/routes')->assertSee("/admin/routes/{$port->id}\"", false);
        $this->get("/admin/bookings/{$booking->reference}")->assertSee("/payments/{$payment->id}", false);

        $this->delete("/admin/routes/{$port->id}")->assertRedirect('/admin/routes');
        $this->assertModelMissing($port);
        $this->delete("/admin/bookings/{$booking->reference}/payments/{$payment->id}")->assertRedirect();
        $this->assertModelMissing($payment);
        $this->delete("/admin/cruises/{$cruise->id}")->assertRedirect('/admin/cruises');
        $this->assertSoftDeleted($cruise);
    }

    /* ---------- 3. Assigning bookings ---------- */

    public function test_supervisor_assigns_a_booking_to_a_seller_only(): void
    {
        $this->actingAs($this->supervisor(), 'admin');
        $booking = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail();
        $seller = $this->seller();

        // The picker lists sellers only
        $this->get("/admin/bookings/{$booking->reference}")->assertOk()
            ->assertSee("<option value=\"{$seller->id}\"", false)
            ->assertDontSee("<option value=\"{$this->admin()->id}\"", false)
            ->assertDontSee("<option value=\"{$this->supervisor()->id}\"", false);

        $this->patch("/admin/bookings/{$booking->reference}/seller", ['seller_id' => $seller->id])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Booking SC2611-K4P2Q assigned to Sara Seller.');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'seller_id' => $seller->id]);
        $this->get("/admin/bookings?seller={$seller->id}")->assertSee('SC2611-K4P2Q');

        // Admins, supervisors and customers can't be assigned
        $reem = User::where('email', 'reem@example.com')->firstOrFail();
        foreach ([$this->admin(), $this->supervisor(), $reem] as $notASeller) {
            $this->patch("/admin/bookings/{$booking->reference}/seller", ['seller_id' => $notASeller->id])->assertSessionHasErrors('seller_id');
        }
        $this->assertSame($seller->id, $booking->fresh()->seller_id);
    }

    /* ---------- Menu and command line ---------- */

    public function test_supervisor_menu_has_no_links_for_adding_people(): void
    {
        $this->actingAs($this->supervisor(), 'admin')->get('/admin')->assertOk()
            ->assertSee('Staff list')->assertSee('Customers list')->assertSee('Add cruise')
            ->assertDontSee('Add staff member')->assertDontSee('Add customer');

        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk()
            ->assertSee('Add staff member')->assertSee('Add customer');
    }

    public function test_make_admin_command_makes_a_supervisor(): void
    {
        $this->artisan('user:make-admin', ['email' => 'reem@example.com', '--supervisor' => true])->assertSuccessful();
        $this->assertTrue(User::where('email', 'reem@example.com')->firstOrFail()->isSupervisor());
    }
}
