<?php

namespace Tests\Feature;

use App\Models\Cruise;
use App\Models\Sailing;
use App\Support\DestinationsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Step 21: the destinations page is rendered on the server with each port's nearest sailings, and stays short
 * however many sailings call at a port.
 */
class DestinationsPageTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
    }

    public function test_ports_and_their_nearest_sailings_are_in_the_html(): void
    {
        $response = $this->get('/destinations')->assertOk();

        // No page-script data: only the small SC_DATA core.js needs
        $this->assertArrayNotHasKey('cruises', $response->viewData('scData'));

        $response->assertSee('id="jeddah"', false)->assertSee('id="aqaba"', false)
            ->assertSee('Cruises that call here')
            ->assertSee('/cruises/saudi-coast-weekend?sailing=SCW-261029', false)  // nearest Jeddah sailing first
            ->assertSee('/cruises?port=Al%20Wajh', false)
            ->assertSee('data-country="Egypt"', false)
            ->assertSee('Starting from per person');

        $jeddah = DestinationsPage::ports()->firstWhere('destination.slug', 'jeddah');
        $this->assertSame(17, $jeddah['total']);                       // every seeded sailing calls at Jeddah
        $this->assertCount(DestinationsPage::PREVIEW, $jeddah['sailings']);
        $this->assertSame('29 Oct 2026', $jeddah['sailings'][0]['date']->format('j M Y'));
        $response->assertSee('The next 6 of 17 sailings')->assertSee('See all 17 sailings to Jeddah');
    }

    public function test_many_sailings_at_one_port_still_show_only_the_nearest(): void
    {
        $cruise = Cruise::where('slug', 'egypt-jordan-voyage')->firstOrFail();
        foreach (range(1, 40) as $i) {
            $date = Carbon::parse('2027-03-01')->addDays($i);
            $cruise->sailings()->create(['code' => "EJV-X$i", 'departure_date' => $date, 'return_date' => $date->copy()->addDays(5), 'status' => 'open']);
        }

        $html = $this->get('/destinations')->assertOk()->getContent();
        $aqaba = DestinationsPage::ports()->firstWhere('destination.slug', 'aqaba');
        $this->assertSame(43, $aqaba['total']);
        $this->assertCount(6, $aqaba['sailings']);
        // Aqaba's 3 seeded sailings come first, then the earliest 3 of the 40 new ones; the other 37 are not listed
        $this->assertStringContainsString('sailing=EJV-X3"', $html);
        $this->assertStringNotContainsString('sailing=EJV-X4"', $html);
        $this->assertStringContainsString('See all 43 sailings to Aqaba', $html);
    }

    public function test_sailing_without_prices_shows_no_price_and_arabic_page(): void
    {
        $sailing = Sailing::where('code', 'EJV-261120')->firstOrFail();
        $sailing->cabinPrices()->update(['price_single' => 0, 'price_double' => 0, 'price_extra' => 0]);

        $aqaba = DestinationsPage::ports()->firstWhere('destination.slug', 'aqaba');
        $this->assertNull(collect($aqaba['sailings'])->firstWhere('url', fn ($u) => str_contains($u, 'EJV-261120'))['from'] ?? null);
        $this->get('/destinations')->assertOk()->assertDontSee('$0<', false);

        $this->withUnencryptedCookie('sc_lang', 'ar')->get('/destinations')->assertOk()
            ->assertSee('رحلات ترسو هنا')->assertSee('العقبة')->assertSee('أقرب 6 من 17 رحلة');
    }
}
