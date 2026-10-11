<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
    }

    public function test_every_template_page_loads(): void
    {
        foreach (['/', '/ship', '/destinations', '/cruises', '/cruises/red-sea-discovery', '/booking', '/login', '/register'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_unknown_or_hidden_cruise_is_404(): void
    {
        $this->get('/cruises/no-such-cruise')->assertNotFound();

        \App\Models\Cruise::where('slug', 'red-sea-discovery')->update(['is_active' => false]);
        $this->get('/cruises/red-sea-discovery')->assertNotFound();
    }

    public function test_pages_get_sc_data_from_the_database(): void
    {
        $data = $this->get('/cruises/red-sea-discovery')->viewData('scData');

        $this->assertSame('USD', $data['currency']);
        $this->assertSame('red-sea-discovery', $data['page']->cruise);
        $this->assertCount(6, $data['cruises']);
        $this->assertCount(6, $data['destinations']);
        $this->assertSame("Saudi Arabia's first cruise ship", $data['ships'][0]['tagline']);
        // Stored prices are sent per sailing, so admin overrides reach the page
        $rsd = collect($data['cruises'])->firstWhere('id', 'red-sea-discovery');
        $this->assertSame(['adult' => 4005.0, 'child' => 1850.0, 'infant' => 315.0], collect($rsd['sailings'])->firstWhere('id', 'RSD-261213')['prices']['balcony']);
        // Promo codes are never published
        $this->assertEquals((object) [], $data['promos']);
    }

    public function test_only_upcoming_open_sailings_are_published(): void
    {
        $cruises = collect($this->get('/')->viewData('scData')['cruises']);
        $codes = $cruises->flatMap(fn ($c) => collect($c['sailings'])->pluck('id'));

        $this->assertCount(17, $codes);
        $this->assertNotContains('RSD-260315', $codes); // demo past sailing
    }

    public function test_newsletter_signup(): void
    {
        $this->postJson('/newsletter', ['email' => 'Guest@Example.com'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'not-an-email'])->assertUnprocessable();

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'guest@example.com']);
    }
}
