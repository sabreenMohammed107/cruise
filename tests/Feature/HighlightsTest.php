<?php

namespace Tests\Feature;

use App\Models\Cruise;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Step 22: cruise highlights are managed in Admin → Cruises → Highlights instead of four fixed choices.
 */
class HighlightsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): static
    {
        return $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin');
    }

    private function cruiseForm(Cruise $cruise, array $tags): array
    {
        return $cruise->only(['name', 'name_ar', 'slug', 'ship_id', 'nights', 'departure_port', 'arrival_port'])
            + ['route_ports' => $cruise->ports->pluck('id')->all(), 'tags' => $tags, 'is_active' => 1];
    }

    public function test_the_four_highlights_the_site_had_are_there(): void
    {
        $this->assertSame(['family', 'excursions', 'weekend', 'festive'], Highlight::ordered()->pluck('key')->all());
        $this->assertSame(['weekend', 'festive'], Highlight::where('show_on_cards', true)->ordered()->pluck('key')->all());
        $this->assertSame(['family', 'festive'], Highlight::where('show_on_cruise_page', true)->ordered()->pluck('key')->all());
        $this->assertSame('رحلة احتفالية', Highlight::where('key', 'festive')->value('name_ar'));
    }

    public function test_no_visa_needed_is_gone(): void
    {
        // It was a cruise setting with fixed words; the filter now lists only Admin → Cruises → Highlights
        $this->assertFalse(Schema::hasColumn('cruises', 'visa_free'));
        $this->assertSame(['family', 'excursions', 'weekend', 'festive'], array_column($this->get('/cruises')->assertOk()->viewData('scData')['highlights'], 'key'));
        $this->assertArrayNotHasKey('visaFree', $this->get('/cruises')->viewData('scData')['cruises'][0]);
    }

    public function test_admin_adds_a_highlight_and_ticks_it_on_a_cruise(): void
    {
        $this->admin()->get('/admin/highlights')->assertOk()->assertSee('Family friendly')->assertSee('مناسبة للعائلات');

        // Both names required; the code is unique
        $this->post('/admin/highlights', ['name_en' => 'Halal dining', 'key' => 'halal'])->assertSessionHasErrors('name_ar');
        $this->post('/admin/highlights', ['name_en' => 'X', 'name_ar' => 'س', 'key' => 'family', 'tone' => 'ocean'])->assertSessionHasErrors('key');
        $this->post('/admin/highlights', [
            'name_en' => 'Halal dining', 'name_ar' => 'طعام حلال', 'key' => 'halal', 'tone' => 'lagoon',
            'show_on_cards' => '1', 'show_on_cruise_page' => '1', 'is_active' => '1', 'sort_order' => 5,
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/highlights');

        // The cruise form offers it, and the cruise saves it
        $cruise = Cruise::where('slug', 'saudi-coast-weekend')->firstOrFail();
        $this->get("/admin/cruises/{$cruise->id}/edit")->assertOk()->assertSee('value="halal"', false)->assertSee('Halal dining');
        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise, ['weekend', 'halal']))->assertSessionHasNoErrors();
        $this->assertSame(['weekend', 'halal'], $cruise->fresh()->tags);
        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise, ['nope']))->assertSessionHasErrors('tags.0');

        // The website gets it in the page language
        $data = $this->get('/cruises')->assertOk()->viewData('scData');
        $this->assertSame(['key' => 'halal', 'label' => 'Halal dining', 'onCards' => true, 'onPage' => true, 'tone' => 'lagoon'], collect($data['highlights'])->firstWhere('key', 'halal'));
        $ar = $this->withUnencryptedCookie('sc_lang', 'ar')->get('/cruises')->viewData('scData');
        $this->assertSame('طعام حلال', collect($ar['highlights'])->firstWhere('key', 'halal')['label']);
    }

    public function test_renaming_and_hiding_a_highlight(): void
    {
        $festive = Highlight::where('key', 'festive')->firstOrFail();

        // The code can't change after creation (cruises and links use it)
        $this->admin()->put("/admin/highlights/{$festive->id}", [
            'name_en' => 'Holiday sailing', 'name_ar' => 'رحلة العطلات', 'key' => 'changed', 'tone' => 'sun', 'is_active' => '1', 'show_on_cards' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['festive', 'Holiday sailing'], [$festive->fresh()->key, $festive->fresh()->name_en]);
        $this->assertSame('Holiday sailing', collect($this->get('/cruises')->viewData('scData')['highlights'])->firstWhere('key', 'festive')['label']);

        // Hidden: off the website, still offered (marked) in the cruise form, and cruises keep it
        $festive->update(['is_active' => false]);
        $this->assertNull(collect($this->get('/cruises')->viewData('scData')['highlights'])->firstWhere('key', 'festive'));
        $cruise = Cruise::where('slug', 'new-year-at-sea')->firstOrFail();
        $this->get("/admin/cruises/{$cruise->id}/edit")->assertSee('Holiday sailing (hidden)');
        $this->assertContains('festive', $cruise->fresh()->tags);
    }

    public function test_a_highlight_in_use_cannot_be_deleted(): void
    {
        $this->admin();
        $family = Highlight::where('key', 'family')->firstOrFail();
        $this->delete("/admin/highlights/{$family->id}")->assertSessionHas('error');
        $this->assertModelExists($family);

        $unused = Highlight::create(['key' => 'spa', 'name_en' => 'Spa', 'name_ar' => 'سبا', 'tone' => 'ocean']);
        $this->delete("/admin/highlights/{$unused->id}")->assertRedirect('/admin/highlights');
        $this->assertModelMissing($unused);
    }

    public function test_highlights_screen_is_for_admins_only(): void
    {
        $this->get('/admin/highlights')->assertRedirect('/admin/login');
        $this->actingAs(User::where('email', 'seller@safercruise.test')->firstOrFail(), 'admin')->get('/admin/highlights')->assertForbidden();
    }
}
