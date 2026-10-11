<?php

namespace Tests\Feature;

use App\Models\HomeSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Homepage content: database → Admin → Homepage → public homepage. */
class HomeContentTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function asAdmin(): static
    {
        return $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin');
    }

    /** Form data for a section as it is now, for PUT with a few changes. */
    private function formData(HomeSection $section, array $changes = []): array
    {
        $text = ['eyebrow', 'title', 'body', 'primary_label', 'secondary_label', 'image_alt', 'caption_label', 'caption_value'];

        return array_merge(
            $section->only([...$text, ...array_map(fn ($f) => $f.'_ar', $text), 'primary_url', 'secondary_url']),
            ['is_visible' => 1],
            $changes,
        );
    }

    public function test_seeder_writes_every_section_from_the_template(): void
    {
        $this->assertSame(array_keys(config('home.sections')), HomeSection::where('page', 'home')->orderBy('id')->pluck('key')->all());
        $this->assertSame('Sail the Red Sea aboard AROYA', HomeSection::where('page', 'home')->where('key', 'hero')->value('title'));
        // Every text the template shows has an Arabic version
        foreach (config('home.sections') as $key => $config) {
            foreach (collect(config('home.text_fields'))->only($config['fields'])->flatten() as $column) {
                if (filled($config['content'][$column] ?? null)) {
                    $this->assertNotEmpty(HomeSection::where('page', 'home')->where('key', $key)->value($column.'_ar'), "$key.{$column}_ar");
                }
            }
        }
        $this->assertSame(4, HomeSection::where('page', 'home')->where('key', 'why')->first()->items->pluck('text_ar')->filter()->count());
        $this->assertCount(4, HomeSection::where('page', 'home')->where('key', 'why')->first()->items);
    }

    public function test_homepage_renders_the_seeded_content(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Sail the Red Sea aboard AROYA')
            ->assertSee('Sailing from Jeddah and Yanbu')
            ->assertSee('assets/img/hero-home.jpg')
            ->assertSee('href="'.url('/cruises').'"', false)
            ->assertSee('href="#ship"', false)
            ->assertSee('AROYA at a glance')
            ->assertSee('Flag and home port')
            ->assertSee('Licensed agency')
            ->assertSee('Hear about new sailings first');
    }

    public function test_homepage_still_renders_without_rows(): void
    {
        HomeSection::where('page', 'home')->delete();

        $this->get('/')->assertOk()->assertSee('Sail the Red Sea aboard AROYA')->assertSee('Help when you need it');
    }

    public function test_admin_screens_load_and_are_admin_only(): void
    {
        $this->get('/admin/home-sections')->assertRedirect('/admin/login');
        $this->actingAs(User::where('email', 'reem@example.com')->firstOrFail())
            ->get('/admin/home-sections')->assertRedirect('/admin/login');

        $this->asAdmin()->get('/admin/home-sections')->assertOk()->assertSee('Hero')->assertSee('Why Safer Cruise');
        foreach (array_keys(config('home.sections')) as $key) {
            $this->get("/admin/home-sections/{$key}/edit")->assertOk();
        }
        $this->get('/admin/home-sections/nope/edit')->assertNotFound();
    }

    public function test_admin_update_shows_on_the_homepage(): void
    {
        $hero = HomeSection::where('page', 'home')->where('key', 'hero')->firstOrFail();

        $this->asAdmin()->put('/admin/home-sections/hero', $this->formData($hero, [
            'title' => 'Winter sailings now open',
            'body' => 'New dates <b>from December</b>.',
            'primary_label' => 'Book now',
            'primary_url' => 'https://example.com/offer',
            'secondary_label' => '',
            'secondary_label_ar' => '',
            'secondary_url' => '',
        ]))->assertRedirect('/admin/home-sections/hero/edit')->assertSessionHasNoErrors();

        $this->get('/')->assertOk()
            ->assertSee('Winter sailings now open')
            ->assertDontSee('Sail the Red Sea aboard AROYA')
            ->assertSee('New dates &lt;b&gt;from December&lt;/b&gt;.', false) // escaped
            ->assertSee('href="https://example.com/offer"', false)
            ->assertDontSee('Meet the ship');
    }

    /** Requests from here on are made as a visitor who chose عربي (the sc_lang cookie set by core.js). */
    private function inArabic(): static
    {
        return $this->withUnencryptedCookie('sc_lang', 'ar');
    }

    public function test_page_language_follows_the_cookie(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Sail the Red Sea aboard AROYA')
            ->assertDontSee('أبحر في البحر الأحمر على متن أرويا');

        $this->inArabic()->get('/')->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('أبحر في البحر الأحمر على متن أرويا')
            ->assertSee('alt="سفينة سياحية راسية بجوار شاطئ فيروزي"', false)
            ->assertSee('وكالة مرخّصة')
            ->assertSee('اشترك')
            ->assertDontSee('Sail the Red Sea aboard AROYA');

        // The admin panel stays in English whatever the cookie says
        $this->asAdmin()->get('/admin/home-sections')->assertOk()->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_fixed_words_come_from_the_language_files(): void
    {
        // lang/en and lang/ar define exactly the same keys
        $keys = fn (string $locale) => array_keys(\Illuminate\Support\Arr::dot(trans('site', [], $locale)));
        $this->assertSame($keys('en'), $keys('ar'));

        $en = ['Destination', 'Departure month', 'Guests', 'sailings match', 'Cruises', 'My bookings', 'Contact us', '© '.now()->year.' Safer. All rights reserved.'];
        $ar = ['الوجهة', 'شهر المغادرة', 'الضيوف', 'رحلة مطابقة', 'الرحلات', 'حجوزاتي', 'تواصل معنا', '© '.now()->year.' Safer. جميع الحقوق محفوظة.'];
        $this->get('/')->assertOk()->assertSee($en)->assertSee('>عربي</button>', false);

        // Header and footer on every page; the logo stays as it is
        foreach (['/', '/cruises', '/ship', '/login'] as $url) {
            $this->inArabic()->get($url)->assertOk()
                ->assertSee(array_slice($ar, 4))
                ->assertSee('>English</button>', false)
                ->assertSee('>Safer</span><span', false)
                ->assertSee('by safer.travel');
        }
        $response = $this->inArabic()->get('/')->assertSee(array_slice($ar, 0, 4));

        // The page script gets both files for the words it builds (options, guest text, counters)
        $i18n = $response->viewData('scData')['i18n'];
        $this->assertSame('أي ميناء', $i18n['ar']['search']['any_port']);
        $this->assertSame('{1} :count night|[2,*] :count nights', $i18n['en']['nights']);
    }

    public function test_admin_edits_both_languages(): void
    {
        $ship = HomeSection::where('page', 'home')->where('key', 'ship')->firstOrFail();

        $this->asAdmin()->get('/admin/home-sections/ship/edit')->assertOk()
            ->assertSee('name="title_ar"', false)->assertSee('name="caption_value_ar"', false)->assertSee('أرويا في لمحة');

        $this->put('/admin/home-sections/ship', $this->formData($ship, [
            'title' => 'Meet AROYA',
            'title_ar' => 'تعرّف على أرويا',
            'body_ar' => '', // empty Arabic → English text in both modes
            'caption_value_ar' => 'جدة <script>',
        ]))->assertSessionHasNoErrors();

        $ship->refresh();
        $this->assertSame('تعرّف على أرويا', $ship->title_ar);
        $this->assertNull($ship->body_ar);

        $this->get('/')->assertOk()->assertSee('Meet AROYA')->assertDontSee('تعرّف على أرويا');
        $html = $this->inArabic()->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('تعرّف على أرويا', $html);
        $this->assertStringContainsString('leading-relaxed max-w-[56ch]">'.e($ship->body).'</p>', $html); // empty Arabic → English
        $this->assertStringContainsString('جدة &lt;script&gt;', $html);
        $this->withUnencryptedCookie('sc_lang', 'en');

        // Arabic has the same length limits
        $this->put('/admin/home-sections/ship', $this->formData($ship, ['title_ar' => str_repeat('ع', 256)]))
            ->assertSessionHasErrors('title_ar');
    }

    public function test_arabic_only_button_still_needs_a_link(): void
    {
        $hero = HomeSection::where('page', 'home')->where('key', 'hero')->firstOrFail();

        $this->asAdmin()->put('/admin/home-sections/hero', $this->formData($hero, [
            'secondary_label' => '', 'secondary_label_ar' => 'تعرّف على السفينة', 'secondary_url' => '',
        ]))->assertSessionHasErrors('secondary_url');
    }

    public function test_unsafe_links_and_missing_title_are_rejected(): void
    {
        $hero = HomeSection::where('page', 'home')->where('key', 'hero')->firstOrFail();

        $this->asAdmin()->put('/admin/home-sections/hero', $this->formData($hero, [
            'title' => '',
            'primary_url' => 'javascript:alert(1)',
            'secondary_url' => '//evil.example',
        ]))->assertSessionHasErrors(['title', 'primary_url', 'secondary_url']);

        $this->assertSame('/cruises', $hero->fresh()->primary_url);
    }

    public function test_hero_photo_upload_and_remove(): void
    {
        $hero = HomeSection::where('page', 'home')->where('key', 'hero')->firstOrFail();
        $this->asAdmin()->put('/admin/home-sections/hero', $this->formData($hero, [
            'image' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
        ]))->assertSessionHasNoErrors();

        $hero->refresh();
        $this->assertStringStartsWith('uploads/home/home-hero-', $hero->image_path);
        $file = public_path($hero->image_path);
        $this->assertFileExists($file);
        $this->get('/')->assertSee($hero->image_path)->assertDontSee('assets/img/hero-home.jpg');

        // Not an image
        $this->put('/admin/home-sections/hero', $this->formData($hero, [
            'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors('image');

        $this->put('/admin/home-sections/hero', $this->formData($hero, ['remove_image' => 1]))->assertSessionHasNoErrors();
        $this->assertNull($hero->fresh()->image_path);
        $this->assertFileDoesNotExist($file);
        $this->get('/')->assertSee('assets/img/hero-home.jpg');
    }

    public function test_cards_can_be_edited_added_and_deleted(): void
    {
        $why = HomeSection::where('page', 'home')->where('key', 'why')->firstOrFail();
        [$first, $second] = $why->items->all();

        $this->asAdmin()->put('/admin/home-sections/why', $this->formData($why, [
            'items' => [
                $first->id => ['title' => 'Fully licensed', 'title_ar' => 'مرخّصة بالكامل', 'text' => 'License 14079.', 'text_ar' => 'ترخيص 14079.', 'icon' => 'shield', 'tone' => 'sun', 'sort_order' => 1, 'delete' => 0],
                $second->id => ['title' => $second->title, 'delete' => 1],
            ],
            'new_items' => [
                ['title' => 'Family friendly', 'title_ar' => 'مناسبة للعائلات', 'text' => 'Halal dining.', 'icon' => 'smile', 'tone' => 'lagoon', 'sort_order' => ''],
                ['title' => '', 'text' => ''], // blank row is ignored
            ],
        ]))->assertSessionHasNoErrors();

        $items = $why->fresh()->items;
        $this->assertCount(4, $items);
        $this->assertSame('Fully licensed', $items->first()->title);
        $this->assertSame('Family friendly', $items->last()->title);
        $this->assertSame('مناسبة للعائلات', $items->last()->title_ar);
        $this->assertSame('ترخيص 14079.', $items->first()->text_ar);
        $this->assertModelMissing($second);

        $this->get('/')->assertSee('Fully licensed')->assertSee('Family friendly')->assertSee('bg-sun-50', false)
            ->assertDontSee($second->title);
        $this->inArabic()->get('/')->assertSee('مرخّصة بالكامل')->assertSee('مناسبة للعائلات');
        $this->withUnencryptedCookie('sc_lang', 'en');

        // Bad icon / colour are rejected
        $this->put('/admin/home-sections/why', $this->formData($why, [
            'items' => [$first->id => ['title' => 'x', 'icon' => 'not-an-icon', 'tone' => 'red']],
        ]))->assertSessionHasErrors(["items.{$first->id}.icon", "items.{$first->id}.tone"]);
    }

    public function test_hidden_section_keeps_its_markup_for_the_page_script(): void
    {
        $sailings = HomeSection::where('page', 'home')->where('key', 'sailings')->firstOrFail();
        $this->asAdmin()->put('/admin/home-sections/sailings', $this->formData($sailings, ['is_visible' => 0]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($sailings->fresh()->is_visible);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<section id="sailings"[^>]*\shidden\s*>/', $html);
        $this->assertStringContainsString('id="featured-grid"', $html); // home.js still finds its element
        $this->assertDoesNotMatchRegularExpression('/<section id="ship"[^>]*\shidden\s*>/', $html);
    }

    public function test_missing_row_is_created_from_defaults_when_edited(): void
    {
        HomeSection::where('page', 'home')->where('key', 'why')->delete();

        $this->asAdmin()->get('/admin/home-sections/why/edit')->assertOk()->assertSee('Licensed agency');
        $this->assertCount(4, HomeSection::where('page', 'home')->where('key', 'why')->first()->items);
    }
}
