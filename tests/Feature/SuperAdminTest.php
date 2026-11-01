<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgentApplication;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * The developer's super admin: made from .env by SuperAdminSeeder, can do everything in the panel, and the panel
 * never shows it (no list, form, role choice or name).
 */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function superAdmin(): User
    {
        config(['admin.super_admin' => ['email' => 'Dev@Example.com', 'password' => 'a-long-secret-123']]);
        $this->seed(SuperAdminSeeder::class);

        return User::where('email', 'dev@example.com')->sole();
    }

    public function test_the_seeder_makes_the_account_from_env_and_can_run_again(): void
    {
        $dev = $this->superAdmin();
        $this->assertSame(UserRole::SuperAdmin, $dev->role);
        $this->assertTrue(Hash::check('a-long-secret-123', $dev->password));

        // A new password in .env: same account, new password
        config(['admin.super_admin.password' => 'another-secret-456']);
        $this->seed(SuperAdminSeeder::class);
        $this->assertSame(1, User::where('role', UserRole::SuperAdmin)->count());
        $this->assertTrue(Hash::check('another-secret-456', $dev->fresh()->password));
    }

    public function test_the_seeder_refuses_missing_settings_and_other_accounts(): void
    {
        config(['admin.super_admin' => ['email' => null, 'password' => null]]);
        $this->assertThrows(fn () => $this->seed(SuperAdminSeeder::class), RuntimeException::class);

        // Never takes over an existing account
        config(['admin.super_admin' => ['email' => 'admin@safercruise.test', 'password' => 'a-long-secret-123']]);
        $this->assertThrows(fn () => $this->seed(SuperAdminSeeder::class), RuntimeException::class);
        $this->assertSame(UserRole::Admin, User::where('email', 'admin@safercruise.test')->sole()->role);
    }

    public function test_it_signs_in_to_the_panel_and_can_do_everything(): void
    {
        $dev = $this->superAdmin();

        $this->post('/admin/login', ['email' => 'dev@example.com', 'password' => 'a-long-secret-123'])->assertRedirect();
        $this->assertAuthenticatedAs($dev, 'admin');
        foreach (['admin-panel', 'manage-content', 'manage-users', 'delete-records', 'assign-bookings'] as $ability) {
            $this->assertTrue($dev->can($ability), $ability);
        }
        $this->assertTrue($dev->can('view-booking', Booking::first()));

        // e.g. approve an agent (admins only)
        Notification::fake();
        $application = AgentApplication::create(['company_name' => 'Red Sea Travel', 'contact_name' => 'Omar', 'email' => 'omar@redsea.example', 'phone_dial' => '+966', 'phone' => '501234567']);
        $this->post(route('admin.agent-requests.approve', $application))->assertSessionHas('status');

        // The website's sign-in is for customers and agents only
        auth('admin')->logout();
        $this->post('/login', ['email' => 'dev@example.com', 'password' => 'a-long-secret-123'])->assertSessionHasErrors('email');
    }

    public function test_the_panel_never_shows_it(): void
    {
        $dev = $this->superAdmin();
        $this->actingAs(User::where('email', 'admin@safercruise.test')->sole(), 'admin');

        // Not in any list, and its records can't be opened by address
        foreach (['admin.users.index', 'admin.customers.index', 'admin.agents.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('dev@example.com');
        }
        $this->get(route('admin.users.edit', $dev->id))->assertNotFound();
        $this->get(route('admin.customers.edit', $dev->id))->assertNotFound();
        $this->delete(route('admin.users.destroy', $dev->id))->assertNotFound();
        $this->assertTrue($dev->fresh()->exists);

        // Not a role anyone can give
        $this->get(route('admin.users.create'))->assertDontSee('super_admin');
        $reem = User::where('email', 'reem@example.com')->sole();
        $this->get(route('admin.customers.edit', $reem->id))->assertDontSee('super_admin');
        $this->post(route('admin.users.store'), ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@example.com', 'role' => 'super_admin', 'password' => 'secret123'])
            ->assertSessionHasErrors('role');
        $this->put(route('admin.customers.update', $reem->id), ['first_name' => 'Reem', 'last_name' => 'Alharbi', 'email' => $reem->email, 'role' => 'super_admin'])
            ->assertSessionHasErrors('role');
        $this->assertSame(UserRole::Customer, $reem->fresh()->role);

        // A payment it records is not signed with its name
        $booking = Booking::first();
        $this->actingAs($dev, 'admin')->post(route('admin.bookings.payments.store', $booking), [
            'type' => 'payment', 'method' => 'cash', 'amount' => 10, 'received_at' => now()->toDateString(),
        ]);
        $this->actingAs(User::where('email', 'admin@safercruise.test')->sole(), 'admin')
            ->get(route('admin.bookings.show', $booking))->assertOk()->assertDontSee('by Super');
    }
}
