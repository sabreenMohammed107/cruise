<?php

namespace Tests\Feature;

use App\Models\Cruise;
use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Step 16: route ports managed in Admin → Route ports, cruise routes picked from them in order.
 */
class RoutePortsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@safercruise.test')->firstOrFail();
    }

    private function cruiseForm(Cruise $cruise, array $changes = []): array
    {
        return array_merge($cruise->only(['name', 'name_ar', 'slug', 'ship_id', 'nights', 'departure_port', 'arrival_port']), [
            'route_ports' => $cruise->ports->pluck('id')->all(), 'tags' => $cruise->tags, 'is_active' => 1,
        ], $changes);
    }

    public function test_seeded_cruises_have_their_route_as_ordered_ports(): void
    {
        $cruise = Cruise::where('slug', 'red-sea-discovery')->firstOrFail();

        $this->assertSame(['Jeddah', 'Safaga', 'Sokhna', 'Al Wajh', 'Yanbu', 'Jeddah'], $cruise->ports->pluck('name_en')->all());
        $this->assertSame(['جدة', 'سفاجا', 'السخنة', 'الوجه', 'ينبع', 'جدة'], $cruise->ports->pluck('name_ar')->all());
        $this->assertSame($cruise->route, $cruise->routeNames());
        $this->assertSame(6, Port::count());
    }

    public function test_admin_manages_route_ports(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/routes')->assertOk()->assertSee('Route ports')->assertSee('Al Wajh')->assertSee('الوجه');
        $this->get('/admin/routes/create')->assertOk()->assertSee('name="name_ar"', false);

        // Both names are required, the English one unique
        $this->post('/admin/routes', ['name_en' => 'Duba'])->assertSessionHasErrors('name_ar');
        $this->post('/admin/routes', ['name_en' => 'Jeddah', 'name_ar' => 'جدة'])->assertSessionHasErrors('name_en');
        $this->post('/admin/routes', ['name_en' => 'Duba', 'name_ar' => 'ضباء', 'is_active' => '1'])->assertSessionHasNoErrors()->assertRedirect('/admin/routes');

        $duba = Port::where('name_en', 'Duba')->firstOrFail();
        $this->assertSame('ضباء', $duba->name_ar);
        $this->put("/admin/routes/{$duba->id}", ['name_en' => 'Duba', 'name_ar' => 'ميناء ضباء', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($duba->fresh()->is_active);

        // A port no cruise uses can be deleted; one in a route can't
        $this->delete("/admin/routes/{$duba->id}")->assertRedirect();
        $this->assertModelMissing($duba);
        $jeddah = Port::where('name_en', 'Jeddah')->firstOrFail();
        $this->delete("/admin/routes/{$jeddah->id}")->assertSessionHas('error');
        $this->assertModelExists($jeddah);
    }

    public function test_cruise_form_saves_the_route_in_order(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $cruise = Cruise::where('slug', 'saudi-coast-weekend')->firstOrFail();

        // The textarea is gone; the route builder lists the stops with both names
        $this->get("/admin/cruises/{$cruise->id}/edit")->assertOk()
            ->assertDontSee('name="route"', false)->assertSee('name="route_ports[]"', false)->assertSee('ينبع');

        $ids = Port::idsFor(['Yanbu', 'Al Wajh', 'Aqaba', 'Yanbu']);
        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise, ['route_ports' => $ids]))->assertSessionHasNoErrors();

        $cruise->refresh();
        $this->assertSame(['Yanbu', 'Al Wajh', 'Aqaba', 'Yanbu'], $cruise->ports->pluck('name_en')->all());
        $this->assertSame([1, 2, 3, 4], $cruise->ports->pluck('pivot.position')->all());
        $this->assertSame(['Yanbu', 'Al Wajh', 'Aqaba', 'Yanbu'], $cruise->route); // kept for core.js and destinations
        $this->assertTrue($cruise->callsAt('Aqaba'));

        // At least two stops, and only existing ports
        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise, ['route_ports' => [$ids[0]]]))->assertSessionHasErrors('route_ports');
        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise, ['route_ports' => [$ids[0], 99999]]))->assertSessionHasErrors('route_ports.1');
        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise, ['route_ports' => null]))->assertSessionHasErrors('route_ports');
    }

    public function test_new_cruise_gets_its_route(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $template = Cruise::where('slug', 'saudi-coast-weekend')->firstOrFail();

        $this->post('/admin/cruises', $this->cruiseForm($template, [
            'name' => 'Aqaba Express', 'slug' => 'aqaba-express', 'route_ports' => Port::idsFor(['Jeddah', 'Aqaba']),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['Jeddah', 'Aqaba'], Cruise::where('slug', 'aqaba-express')->firstOrFail()->route);
    }

    public function test_renaming_a_port_updates_the_routes(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $port = Port::where('name_en', 'Sokhna')->firstOrFail();

        $this->put("/admin/routes/{$port->id}", ['name_en' => 'Ain Sokhna', 'name_ar' => 'العين السخنة', 'is_active' => '1'])->assertSessionHasNoErrors();

        $this->assertSame(['Jeddah', 'Safaga', 'Ain Sokhna'], Cruise::where('slug', 'aroya-repositioning')->firstOrFail()->route);
    }

    public function test_website_shows_route_names_in_the_page_language(): void
    {
        Port::where('name_en', 'Yanbu')->update(['name_ar' => 'ميناء ينبع']);

        $en = $this->get('/cruises')->assertOk()->viewData('scData');
        $this->assertSame(['Jeddah', 'Yanbu', 'Jeddah'], collect($en['cruises'])->firstWhere('id', 'saudi-coast-weekend')['route']);
        $this->assertSame([], (array) $en['portNames']);

        $ar = $this->withUnencryptedCookie('sc_lang', 'ar')->get('/cruises')->assertOk()->viewData('scData');
        $this->assertSame(['Jeddah', 'Yanbu', 'Jeddah'], collect($ar['cruises'])->firstWhere('id', 'saudi-coast-weekend')['route']); // keys stay English
        $this->assertSame('ميناء ينبع', ((array) $ar['portNames'])['Yanbu']); // route ports win over destinations
        $this->assertSame('جدة', ((array) $ar['portNames'])['Jeddah']);
    }

    public function test_migration_converts_existing_routes(): void
    {
        $migration = ['--path' => 'database/migrations/2026_10_19_000001_create_ports_and_cruise_port_tables.php'];
        Artisan::call('migrate:rollback', $migration);
        Cruise::where('slug', 'egypt-jordan-voyage')->update(['route' => json_encode(['Jeddah, Saudi Arabia', 'Aqaba', 'Duba', 'Jeddah'])]);
        Artisan::call('migrate', $migration);

        $cruise = Cruise::where('slug', 'egypt-jordan-voyage')->firstOrFail();
        $this->assertSame(['Jeddah', 'Aqaba', 'Duba', 'Jeddah'], $cruise->ports->pluck('name_en')->all());
        $this->assertSame(['Jeddah', 'Aqaba', 'Duba', 'Jeddah'], $cruise->route);
        $this->assertSame('العقبة', Port::where('name_en', 'Aqaba')->value('name_ar')); // from the destination
        $this->assertNull(Port::where('name_en', 'Duba')->value('name_ar'));
        $this->assertSame(['Jeddah', 'Yanbu', 'Al Wajh', 'Jeddah'], Cruise::where('slug', 'new-year-at-sea')->firstOrFail()->ports->pluck('name_en')->all());
    }
}
