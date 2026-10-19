<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Cruise;
use App\Models\Destination;
use App\Models\Port;
use App\Models\Sailing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_website_sign_in_is_for_customers_only(): void
    {
        foreach (['admin@safercruise.test', 'seller@safercruise.test'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'password'])->assertSessionHasErrors('email');
            $this->assertGuest('web');
        }

        // Staff sign in to the dashboard directly; the website shows no link to it
        $this->post('/admin/login', ['email' => 'seller@safercruise.test', 'password' => 'password'])->assertRedirect('/admin');
        $this->get('/')->assertOk()->assertDontSee(url('/admin'));
        $this->get('/admin/login')->assertRedirect('/admin');
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

        $urls = ['/admin', '/admin/bookings', '/admin/bookings/SC2611-K4P2Q',
            "/admin/cruises/{$cruise->slug}/sailings", "/admin/cruises/{$cruise->slug}/sailings/create",
            '/admin/sailings/'.$cruise->sailings()->first()->id.'/edit'];
        foreach (collect(config('admin.resources'))->flatten() as $resource) {
            $urls[] = route($resource::routeName('index'));
            if ($resource::canCreate()) {
                $urls[] = route($resource::routeName('create'));
            }
            if ($first = $resource::scope()->first()) {
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
            'name' => 'Duba', 'slug' => 'duba', 'country' => 'Saudi Arabia', 'image' => Destination::where('slug', 'al-wajh')->value('image'),
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
            'departure_port' => 'Jeddah', 'arrival_port' => 'Jeddah', 'route_ports' => Port::idsFor(['Jeddah', 'Yanbu', 'Jeddah']),
            'tags' => ['weekend'], 'is_active' => '1',
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
            'first_name' => 'Safer', 'last_name' => 'Admin', 'email' => $admin->email, 'role' => 'seller',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($admin->fresh()->isAdmin());
        // "Customer" isn't a choice on Admins & staff at all
        $this->put("/admin/users/{$admin->id}", ['first_name' => 'Safer', 'last_name' => 'Admin', 'email' => $admin->email, 'role' => 'customer'])
            ->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->isAdmin());

        $reem = User::where('email', 'reem@example.com')->firstOrFail();
        // A customer is promoted from Customers, and then shows under Admins & staff
        $this->put("/admin/customers/{$reem->id}", ['first_name' => 'Reem', 'last_name' => 'Alharbi', 'email' => $reem->email, 'role' => 'admin']);
        $this->assertTrue($reem->fresh()->isAdmin());
        $this->get("/admin/customers/{$reem->id}/edit")->assertNotFound();
        $this->get("/admin/users/{$reem->id}/edit")->assertOk();
    }

    public function test_sailing_prices_are_explicit_single_double_and_extra_tiers(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $cruise = Cruise::where('slug', 'saudi-coast-weekend')->firstOrFail();
        $cabins = $cruise->ship->cabinCategories()->pluck('id', 'slug');

        // The new-sailing form starts from the cruise's latest prices and has no factor or recalculation
        $html = $this->get("/admin/cruises/{$cruise->slug}/sailings/create")->assertOk()->getContent();
        $this->assertStringContainsString('name="prices['.$cabins['interior'].'][single]"', $html);
        $this->assertStringContainsString('value="1485"', $html);
        $this->assertStringNotContainsString('price_factor', $html);
        $this->assertStringNotContainsString('Recalculate', $html);

        // Every tier is required
        $this->post("/admin/cruises/{$cruise->slug}/sailings", ['code' => 'scw-270311', 'departure_date' => '2027-03-11', 'status' => 'open'])
            ->assertSessionHasErrors('prices');

        $prices = $cabins->mapWithKeys(fn ($id, $slug) => [$id => ['single' => 1000, 'double' => 1600, 'extra' => 400]])->all();
        $this->post("/admin/cruises/{$cruise->slug}/sailings", [
            'code' => 'scw-270311', 'departure_date' => '2027-03-11', 'status' => 'open', 'prices' => $prices,
        ])->assertSessionHasNoErrors();

        $sailing = Sailing::where('code', 'SCW-270311')->firstOrFail();
        $this->assertSame('2027-03-14', $sailing->return_date->toDateString());
        $this->assertSame(3, $sailing->cabinPrices()->count());

        $interior = $sailing->cabinPrices()->where('cabin_category_id', $cabins['interior'])->firstOrFail();
        $this->assertSame(['1000.00', '1600.00', '400.00'], [$interior->price_single, $interior->price_double, $interior->price_extra]);

        $prices[$cabins['interior']] = ['single' => 999, 'double' => 1500, 'extra' => 350, 'capacity' => 5, 'sold_out' => '1'];
        $this->put("/admin/sailings/{$sailing->id}", [
            'code' => 'SCW-270311', 'departure_date' => '2027-03-11', 'status' => 'open', 'prices' => $prices,
        ])->assertSessionHasNoErrors();
        $interior->refresh();
        $this->assertSame(['999.00', '1500.00', '350.00', 5, true], [$interior->price_single, $interior->price_double, $interior->price_extra, $interior->capacity, $interior->is_sold_out]);

        // Prices for a cabin of another ship are rejected
        $this->put("/admin/sailings/{$sailing->id}", [
            'code' => 'SCW-270311', 'departure_date' => '2027-03-11', 'status' => 'open', 'prices' => $prices + [999999 => ['single' => 1, 'double' => 1, 'extra' => 1]],
        ])->assertSessionHasErrors('prices.999999');

        // The rate-card recalculation is gone
        $this->post("/admin/sailings/{$sailing->id}/recalculate")->assertNotFound();
    }

    public function test_cruises_list_shows_the_next_sailing_and_no_price_factor(): void
    {
        $this->travelTo('2026-10-11');
        $this->actingAs($this->admin(), 'admin');

        $html = $this->get('/admin/cruises')->assertOk()->getContent();
        $this->assertStringContainsString('Next sailing', $html);
        $this->assertStringContainsString('Thu 29 Oct 2026', $html); // SCW-261029, the closest departure of Saudi Coast Weekend
        $this->assertStringContainsString('In 18 days', $html);

        // Cancelled and past departures are skipped
        Sailing::where('code', 'SCW-261029')->update(['status' => 'cancelled']);
        $html = $this->get('/admin/cruises')->getContent();
        $this->assertStringNotContainsString('Thu 29 Oct 2026', $html);
        $this->assertStringContainsString('Thu 5 Nov 2026', $html);

        $cruise = Cruise::firstOrFail();
        $this->get("/admin/cruises/{$cruise->id}/edit")->assertOk()->assertDontSee('price_factor');
    }

    public function test_cabins_hold_at_most_three_guests(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $cabin = \App\Models\CabinCategory::where('slug', 'suite')->firstOrFail();
        $form = $cabin->only(['name_en', 'ship_id', 'slug', 'sort_order']);

        // the limit is config('cruise.max_cabin_guests')
        $max = (int) config('cruise.max_cabin_guests');
        $this->put("/admin/cabin-categories/{$cabin->id}", $form + ['max_guests' => $max + 1])->assertSessionHasErrors('max_guests');
        $this->put("/admin/cabin-categories/{$cabin->id}", $form + ['max_guests' => $max])->assertSessionHasNoErrors();
        $this->get("/admin/cabin-categories/{$cabin->id}/edit")->assertOk()->assertDontSee('base_rate');
    }

    public function test_sailings_with_bookings_cannot_be_deleted(): void
    {
        $sailing = Sailing::where('code', 'SCW-261105')->firstOrFail();

        $this->actingAs($this->admin(), 'admin')->delete("/admin/sailings/{$sailing->id}")->assertSessionHas('error');
        $this->assertModelExists($sailing);
    }

    public function test_sidebar_groups_like_the_safeer_dashboard(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $cruise = Cruise::firstOrFail();

        $html = $this->get("/admin/cruises/{$cruise->slug}/sailings")->assertOk()->getContent();
        foreach (['Dashboard', 'Bookings', 'Cruises', 'Ship', 'Website content', 'Pricing &amp; offers', 'Customers', 'Admins &amp; staff', 'Newsletter', 'Add cruise', 'Add staff member'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        // Only the group of the current page is open, with its link marked
        $groups = preg_split('#<details #', $html);
        $open = array_values(array_filter($groups, fn ($g) => preg_match('#^class="admin-group[^"]*"\s+open\s*>#', $g)));
        $this->assertCount(2, $open, 'one open group in the sidebar and one in the mobile drawer');
        $this->assertStringContainsString('<span class="flex-1">Cruises</span>', $open[0]);
        $this->assertMatchesRegularExpression('#href="[^"]+/admin/cruises"\s+aria-current="page"#', $html);
        $this->assertStringNotContainsString('Photo slots', $html);
    }

    public function test_arabic_lines_are_saved_intact(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $destination = Destination::where('slug', 'yanbu')->firstOrFail();
        // "م" is D9 85 in UTF-8; 0x85 used to be taken for a line break and cut the letter in half
        $lines = ['مدينة ينبع التاريخية', 'شعاب مرجانية وغوص'];

        $this->put("/admin/destinations/{$destination->id}", $destination->only(['name', 'name_ar', 'slug', 'country', 'country_ar', 'blurb', 'description', 'visa_note', 'sort_order', 'image'])
            + ['highlights' => implode("\n", $destination->highlights), 'highlights_ar' => implode("\r\n", $lines), 'is_active' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame($lines, $destination->fresh()->highlights_ar);
    }
}
