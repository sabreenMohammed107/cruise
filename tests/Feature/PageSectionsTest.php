<?php

namespace Tests\Feature;

use App\Models\HomeSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Admin → Cruise pages: section copy of the ship page, cruises list, cruise details and destinations. */
class PageSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function asAdmin(): static
    {
        return $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin');
    }

    private function section(string $page, string $key): HomeSection
    {
        return HomeSection::where('page', $page)->where('key', $key)->firstOrFail();
    }

    private function form(HomeSection $section, array $changes = []): array
    {
        $text = ['eyebrow', 'title', 'body', 'primary_label', 'secondary_label', 'image_alt', 'caption_label', 'caption_value'];

        return array_merge($section->only([...$text, ...array_map(fn ($f) => $f.'_ar', $text), 'primary_url', 'secondary_url']), ['is_visible' => 1], $changes);
    }

    public function test_seeder_writes_every_page_in_both_languages(): void
    {
        foreach (['ship', 'catalog', 'cruise', 'destinations'] as $page) {
            $this->assertSame(array_keys(config("pages.$page.sections")), HomeSection::where('page', $page)->orderBy('id')->pluck('key')->all());
        }
        $this->assertSame('صُممت للبحر الأحمر', $this->section('ship', 'about')->title_ar);
        $this->assertCount(5, $this->section('cruise', 'safety')->items);
        $this->assertCount(4, $this->section('cruise', 'included')->items);
    }

    public function test_cruise_pages_screen_lists_every_page_in_order(): void
    {
        $this->get('/admin/pages')->assertRedirect('/admin/login');

        $this->asAdmin()->get('/admin')->assertOk()->assertSee('Cruise pages');
        $this->get('/admin/pages')->assertOk()
            ->assertSee('Text, buttons and photos of each ship section, category section, cruise section and destination section, in page order.')
            ->assertSeeInOrder(['Ship page', 'Hero', 'About the ship and facts', 'Life on board', 'Cruises list', 'Page heading and search', 'No results',
                'Cruise details', 'Departure dates', 'Health &amp; safety', 'Destinations', 'Page heading', 'Closing call to action'], false)
            ->assertSee('صُممت للبحر الأحمر');

        foreach (['ship', 'catalog', 'cruise', 'destinations'] as $page) {
            foreach (config("pages.$page.sections") as $key => $config) {
                $edit = $this->get("/admin/pages/$page/$key/edit")->assertOk();
                ($config['can_hide'] ?? true) ? $edit->assertSee('name="is_visible"', false) : $edit->assertDontSee('name="is_visible"', false);
            }
        }
        $this->get('/admin/pages/ship/nope/edit')->assertNotFound();
        $this->get('/admin/pages/nope/hero/edit')->assertNotFound();

        // Per-section labels instead of the generic ones
        $this->get('/admin/pages/ship/about/edit')->assertSee('Facts heading (English)')->assertSee('Note under the facts (عربي)');
        $this->get('/admin/pages/catalog/head/edit')->assertSee('Search box placeholder (English)');
    }

    public function test_ship_page_sections_are_edited_in_both_languages(): void
    {
        $cta = $this->section('ship', 'cta');
        $this->asAdmin()->put('/admin/pages/ship/cta', $this->form($cta, [
            'title' => 'Your Red Sea holiday starts here', 'title_ar' => 'عطلتك في البحر الأحمر تبدأ هنا',
            'primary_label' => 'Book now', 'primary_label_ar' => 'احجز الآن', 'primary_url' => '/cruises?port=Jeddah',
        ]))->assertRedirect('/admin/pages/ship/cta/edit')->assertSessionHasNoErrors();

        $this->get('/ship')->assertOk()->assertSee('Your Red Sea holiday starts here')->assertSee('href="'.url('/cruises?port=Jeddah').'"', false)
            ->assertSee('Ship facts')->assertSee('Everything on deck')->assertDontSee('Ready to sail on AROYA?');

        $this->withUnencryptedCookie('sc_lang', 'ar')->get('/ship')->assertOk()
            ->assertSee('عطلتك في البحر الأحمر تبدأ هنا')->assertSee('احجز الآن')
            ->assertSee('حقائق عن السفينة')->assertSee('كل ما على السطح')->assertSee('اعرض الكبائن')
            ->assertDontSee('Your Red Sea holiday starts here');
    }

    public function test_ship_hero_photo_and_hidden_section(): void
    {
        $hero = $this->section('ship', 'hero');
        $this->asAdmin()->put('/admin/pages/ship/hero', $this->form($hero, ['image' => UploadedFile::fake()->image('hero.jpg', 1600, 900)]))
            ->assertSessionHasNoErrors();
        $path = $hero->fresh()->image_path;
        $this->assertStringStartsWith('uploads/home/ship-hero-', $path);
        $this->get('/ship')->assertSee($path);

        $this->put('/admin/pages/ship/hero', $this->form($hero->fresh(), ['remove_image' => 1]))->assertSessionHasNoErrors();
        $this->assertFileDoesNotExist(public_path($path));

        $faq = $this->section('ship', 'faq');
        $this->put('/admin/pages/ship/faq', $this->form($faq, ['is_visible' => 0]))->assertSessionHasNoErrors();
        $this->assertMatchesRegularExpression('/<section id="faq"[^>]*\shidden\s*>/', $this->get('/ship')->getContent());
    }

    public function test_cruise_details_lists_and_locked_sections(): void
    {
        $included = $this->section('cruise', 'included');
        $first = $included->items->first();

        $this->asAdmin()->put('/admin/pages/cruise/included', $this->form($included, [
            'items' => [$first->id => ['title' => 'All meals in the main restaurants', 'title_ar' => 'كل الوجبات في المطاعم الرئيسية', 'icon' => 'check', 'tone' => 'lagoon', 'delete' => 0]],
        ]))->assertSessionHasNoErrors();

        $this->get('/cruises/red-sea-discovery')->assertOk()->assertSee('All meals in the main restaurants')->assertSee('Medical centre on board')
            ->assertSee('No online payment. Our team contacts you to arrange payment.');
        $this->withUnencryptedCookie('sc_lang', 'ar')->get('/cruises/red-sea-discovery')->assertOk()
            ->assertSee('كل الوجبات في المطاعم الرئيسية')->assertSee('اختر موعد المغادرة')->assertSee('مركز طبي على متن السفينة');

        // Sections needed to book can't be hidden
        $dates = $this->section('cruise', 'dates');
        $this->get('/admin/pages/cruise/dates/edit')->assertDontSee('name="is_visible"', false);
        $this->put('/admin/pages/cruise/dates', $this->form($dates, ['is_visible' => 0]))->assertSessionHasNoErrors();
        $this->assertTrue($dates->fresh()->is_visible);
    }

    public function test_catalog_and_destinations_pages(): void
    {
        $this->withUnencryptedCookie('sc_lang', 'ar');
        $this->get('/cruises')->assertOk()->assertSee('اختر رحلتك')->assertSee('placeholder="ابحث باسم الرحلة، مثلًا: البحر الأحمر"', false)->assertSee('لا توجد رحلات تطابق هذه الفلاتر');
        $this->get('/destinations')->assertOk()->assertSee('أين ترسو أرويا')->assertSee('وجدت مينائك؟');

        $this->withUnencryptedCookie('sc_lang', 'en');
        $this->get('/cruises')->assertOk()->assertSee('Choose your sailing')->assertSee('placeholder="Search by cruise name, e.g. Red Sea"', false);
        $this->get('/destinations')->assertOk()->assertSee('Where AROYA calls')->assertSee('Found your port?');
    }

    public function test_pages_render_without_rows(): void
    {
        HomeSection::where('page', '!=', 'home')->delete();

        $this->get('/ship')->assertOk()->assertSee('Built for the Red Sea');
        $this->get('/cruises')->assertOk()->assertSee('Choose your sailing');
        $this->get('/destinations')->assertOk()->assertSee('Where AROYA calls');
        $this->get('/cruises/red-sea-discovery')->assertOk()->assertSee('Travelling with infants');
    }
}
