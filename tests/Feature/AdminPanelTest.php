<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Cruise;
use App\Models\Destination;
use App\Models\ImageSlot;
use App\Models\Sailing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@safercruise.test')->firstOrFail();
    }

    public function test_admin_panel_has_its_own_sign_in(): void
    {
        $reem = User::where('email', 'reem@example.com')->firstOrFail();

        $this->get('/admin')->assertRedirect('/admin/login');
        // Signed in on the website as a customer is not signed in to the panel
        $this->actingAs($reem)->get('/admin')->assertRedirect('/admin/login');
        // Customers can't use the admin sign-in
        $this->post('/admin/login', ['email' => 'reem@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');

        $this->post('/admin/login', ['email' => 'admin@safercruise.test', 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($this->admin(), 'admin');
        $this->get('/admin')->assertOk();
    }

    public function test_admin_and_customer_can_be_signed_in_at_the_same_time(): void
    {
        // Same browser session: customer on the site, admin in the panel
        $this->post('/login', ['email' => 'reem@example.com', 'password' => 'password'])->assertRedirect('/account');
        $this->post('/admin/login', ['email' => 'admin@safercruise.test', 'password' => 'password'])->assertRedirect('/admin');

        $this->get('/account')->assertOk()->assertSee('reem@example.com');
        $this->get('/admin')->assertOk()->assertSee('Safer Admin');
        $this->get('/')->assertOk()->assertSee('Reem'); // site header shows the customer, not the admin

        // Signing out of the panel keeps the customer signed in on the site
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->get('/account')->assertOk();
        $this->get('/admin')->assertRedirect('/admin/login');

        // …and signing out of the site keeps the admin signed in to the panel
        $this->post('/admin/login', ['email' => 'admin@safercruise.test', 'password' => 'password']);
        $this->post('/logout')->assertRedirect('/');
        $this->get('/account')->assertRedirect('/login');
        $this->get('/admin')->assertOk();
    }

    public function test_admin_who_lost_the_role_gets_a_way_out(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');
        $admin->forceFill(['role' => \App\Enums\UserRole::Customer])->save();

        $this->get('/admin')->assertForbidden()->assertSee('Sign out of the admin panel');
    }
    public function test_make_admin_command(): void
    {
        $this->artisan('user:make-admin', ['email' => 'Reem@Example.com'])->assertSuccessful();
        $this->assertTrue(User::where('email', 'reem@example.com')->firstOrFail()->isAdmin());

        $this->artisan('user:make-admin', ['email' => 'reem@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse(User::where('email', 'reem@example.com')->firstOrFail()->isAdmin());

        $this->artisan('user:make-admin', ['email' => 'nobody@example.com'])->assertFailed();
    }

    public function test_every_admin_screen_loads(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $cruise = Cruise::where('slug', 'red-sea-discovery')->firstOrFail();

        $urls = ['/admin', '/admin/bookings', '/admin/bookings/SC2611-K4P2Q', '/admin/image-slots',
            "/admin/cruises/{$cruise->slug}/sailings", "/admin/cruises/{$cruise->slug}/sailings/create",
            '/admin/sailings/'.$cruise->sailings()->first()->id.'/edit'];
        foreach (collect(config('admin.resources'))->flatten() as $resource) {
            $urls[] = route($resource::routeName('index'));
            if ($resource::canCreate()) {
                $urls[] = route($resource::routeName('create'));
            }
            if ($first = $resource::$model::query()->first()) {
                $urls[] = route($resource::routeName('edit'), $first->getKey());
            }
        }

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_recording_payments_updates_the_booking(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $booking = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail(); // $4,155 due

        $this->post("/admin/bookings/{$booking->reference}/payments", ['type' => 'payment', 'method' => 'bank_transfer', 'amount' => 1000, 'received_at' => today()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Partial, $booking->fresh()->payment_status);

        $this->post("/admin/bookings/{$booking->reference}/payments", ['type' => 'payment', 'method' => 'cash', 'amount' => 3155, 'received_at' => today()->toDateString()]);
        $booking->refresh();
        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
        $this->assertSame(0.0, $booking->amountDue());
        $this->assertSame($this->admin()->id, $booking->payments()->first()->recorded_by);

        // Removing an entry recalculates
        $this->delete("/admin/bookings/{$booking->reference}/payments/{$booking->payments()->latest('id')->first()->id}");
        $this->assertSame(PaymentStatus::Partial, $booking->fresh()->payment_status);
    }

    public function test_cancelling_a_booking(): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/admin/bookings/SC2611-K4P2Q/cancel')->assertRedirect();

        $booking = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_generic_resource_create_update_delete(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/destinations', [
            'name' => 'Duba', 'slug' => 'duba', 'country' => 'Saudi Arabia', 'image_slot' => 'dest-al-wajh',
            'highlights' => "Port town walk\nRed Sea reefs", 'is_active' => '1', 'sort_order' => '',
        ])->assertRedirect('/admin/destinations')->assertSessionHasNoErrors();

        $duba = Destination::where('slug', 'duba')->firstOrFail();
        $this->assertSame(['Port town walk', 'Red Sea reefs'], $duba->highlights);
        $this->assertSame(0, $duba->sort_order);

        $this->put("/admin/destinations/{$duba->id}", ['name' => 'Duba', 'slug' => 'jeddah', 'country' => 'Saudi Arabia'])
            ->assertSessionHasErrors('slug'); // unique
        $this->put("/admin/destinations/{$duba->id}", ['name' => 'Duba', 'slug' => 'duba', 'country' => 'Saudi Arabia', 'is_active' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($duba->fresh()->is_active);

        $this->delete("/admin/destinations/{$duba->id}")->assertRedirect();
        $this->assertModelMissing($duba);
    }

    public function test_cruise_save_rewrites_the_itinerary(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $cruise = Cruise::where('slug', 'saudi-coast-weekend')->firstOrFail();

        $this->put("/admin/cruises/{$cruise->id}", [
            'name' => $cruise->name, 'slug' => $cruise->slug, 'ship_id' => $cruise->ship_id, 'nights' => 3,
            'departure_port' => 'Jeddah', 'arrival_port' => 'Jeddah', 'route' => "Jeddah\nYanbu\nJeddah",
            'price_factor' => '1.00', 'tags' => ['weekend'], 'is_active' => '1',
            'itinerary' => [
                ['port' => 'Jeddah', 'time' => 'Departs 20:00', 'text' => 'Embark.'],
                ['port' => '', 'time' => '', 'text' => ''],
                ['port' => 'Yanbu', 'time' => '09:00 – 18:00', 'text' => 'Beach day.'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Jeddah', 'Yanbu'], $cruise->itinerary()->pluck('port')->all());
        $this->assertSame([1, 2], $cruise->itinerary()->pluck('day_number')->all());
        $this->assertSame(['weekend'], $cruise->fresh()->tags);
    }

    public function test_admins_cannot_demote_themselves_and_roles_are_set_by_admins(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->put("/admin/users/{$admin->id}", [
            'first_name' => 'Safer', 'last_name' => 'Admin', 'email' => $admin->email, 'role' => 'customer',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($admin->fresh()->isAdmin());

        $reem = User::where('email', 'reem@example.com')->firstOrFail();
        $this->put("/admin/users/{$reem->id}", ['first_name' => 'Reem', 'last_name' => 'Alharbi', 'email' => $reem->email, 'role' => 'admin']);
        $this->assertTrue($reem->fresh()->isAdmin());
    }

    public function test_sailing_create_fills_prices_and_grid_overrides_them(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $cruise = Cruise::where('slug', 'saudi-coast-weekend')->firstOrFail();

        $this->post("/admin/cruises/{$cruise->slug}/sailings", [
            'code' => 'scw-270311', 'departure_date' => '2027-03-11', 'price_factor' => '1.10', 'status' => 'open',
        ])->assertSessionHasNoErrors();

        $sailing = Sailing::where('code', 'SCW-270311')->firstOrFail();
        $this->assertSame('2027-03-14', $sailing->return_date->toDateString());
        $this->assertSame(3, $sailing->cabinPrices()->count());

        $interior = $sailing->cabinPrices()->whereHas('cabinCategory', fn ($q) => $q->where('slug', 'interior'))->firstOrFail();
        $this->assertSame('1090.00', $interior->price_adult); // round5(330 × 3 × 1.1 = 1089)

        $this->put("/admin/sailings/{$sailing->id}", [
            'code' => 'SCW-270311', 'departure_date' => '2027-03-11', 'price_factor' => '1.10', 'status' => 'open',
            'prices' => [$interior->cabin_category_id => ['adult' => 999, 'child' => 500, 'infant' => 100, 'capacity' => 5, 'sold_out' => '1']],
        ])->assertSessionHasNoErrors();
        $interior->refresh();
        $this->assertSame(['999.00', 5, true], [$interior->price_adult, $interior->capacity, $interior->is_sold_out]);

        $this->post("/admin/sailings/{$sailing->id}/recalculate");
        $this->assertSame('1090.00', $interior->fresh()->price_adult);
    }

    public function test_sailings_with_bookings_cannot_be_deleted(): void
    {
        $sailing = Sailing::where('code', 'SCW-261105')->firstOrFail();

        $this->actingAs($this->admin(), 'admin')->delete("/admin/sailings/{$sailing->id}")->assertSessionHas('error');
        $this->assertModelExists($sailing);
    }

    public function test_photo_slot_upload_and_clear(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $slot = ImageSlot::where('key', 'hero')->firstOrFail();

        $this->post('/admin/image-slots/hero', ['photo' => UploadedFile::fake()->image('hero.jpg', 1200, 700)])->assertSessionHasNoErrors();
        $slot->refresh();
        $this->assertStringStartsWith('uploads/image-slots/hero-', $slot->path);
        $this->assertFileExists(public_path($slot->path));
        $this->assertTrue($slot->isFilled());

        $file = public_path($slot->path);
        $this->delete('/admin/image-slots/hero');
        $this->assertNull($slot->fresh()->path);
        $this->assertFileDoesNotExist($file);
    }
}
