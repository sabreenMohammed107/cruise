<?php

namespace Tests\Feature;

use App\Models\HomeSection;
use App\Models\ShipFaq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** /pre-boarding-guide, its sections in Admin → Cruise pages, the booking page notice and the pre-boarding FAQs. */
class PreBoardingGuideTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function asAdmin(): static
    {
        return $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin');
    }

    private function section(string $key): HomeSection
    {
        return HomeSection::where('page', 'guide')->where('key', $key)->firstOrFail();
    }

    public function test_guide_page_shows_every_part_of_the_guide_in_both_languages(): void
    {
        $this->get('/pre-boarding-guide')->assertOk()
            ->assertSeeInOrder(['Pre-embarkation guide', 'Physical passport with 6 months validity', 'Türkiye visa', 'Egypt visa', 'Greece visa (Schengen)',
                'Pre-boarding steps', 'Check-in opens', '1:00 PM', 'Check-in closes', '7:00 PM', 'Galataport Istanbul terminal',
                'Prohibited items on board', 'Hair dryers', 'Travelling with kids &amp; teens', 'Guardian Consent Form', 'Frequently asked questions'], false)
            ->assertSee('href="https://online.flippingbook.com/view/1033697715/"', false)
            ->assertSee('href="https://www.mfa.gov.tr/visa-information-for-foreigners.en.mfa"', false)
            ->assertSee('Do I need a visa for Egypt?')
            ->assertSee('data-faq-search="#guide-faq"', false)
            ->assertDontSee('Important before you confirm'); // the booking page box is not on this page

        $this->withUnencryptedCookie('sc_lang', 'ar')->get('/pre-boarding-guide')->assertOk()
            ->assertSee('دليل ما قبل الصعود إلى السفينة')->assertSee('تأشيرة مصر')->assertSee('مجففات الشعر')
            ->assertSee('هل أحتاج إلى تأشيرة لدخول مصر؟')->assertSee('حمّل النموذج')
            ->assertDontSee('Egypt visa');
    }

    public function test_seeder_writes_the_guide_and_faqs(): void
    {
        $this->assertSame(array_keys(config('pages.guide.sections')), HomeSection::where('page', 'guide')->orderBy('id')->pluck('key')->all());
        $this->assertCount(18, $this->section('prohibited')->items);
        $this->assertCount(5, $this->section('steps')->items);
        $this->assertSame('https://online.flippingbook.com/view/613291161/', $this->section('kids')->items->last()->url);

        $this->assertSame(10, ShipFaq::where('question_ar', 'like', '%')->whereIn('question', [
            'Do I need a visa for Türkiye?', 'Do I need a visa for Egypt?', 'Do I need a Schengen visa for Greece?',
            'Can Safer Cruise or AROYA arrange my visa?', 'How long must my passport be valid?', 'What time is check-in at the port?',
            'What do I need to do before boarding?', 'How do I pay on board?', 'Which items are not allowed on board?',
            'My child is travelling without a parent. What do I need?',
        ])->count());

        // Re-running neither duplicates nor reorders them
        $before = ShipFaq::orderBy('sort_order')->pluck('question')->all();
        $this->seed(\Database\Seeders\PreBoardingFaqSeeder::class);
        $this->assertSame($before, ShipFaq::orderBy('sort_order')->pluck('question')->all());
    }

    public function test_admin_edits_cards_and_their_links(): void
    {
        $this->asAdmin()->get('/admin/pages')->assertOk()->assertSee('Pre-boarding guide')->assertSee('Booking page: before you confirm');
        foreach (config('pages.guide.sections') as $key => $config) {
            $edit = $this->get("/admin/pages/guide/$key/edit")->assertOk();
            ($config['item_links'] ?? false) ? $edit->assertSee('[url]', false) : $edit->assertDontSee('[url]', false);
        }

        $kids = $this->section('kids');
        $form = fn (array $item) => [
            'title' => $kids->title, 'title_ar' => $kids->title_ar, 'is_visible' => 1,
            'items' => [$kids->items->first()->id => $item + ['title' => 'Guardian form', 'title_ar' => 'نموذج الوصي', 'icon' => 'users', 'tone' => 'sun', 'delete' => 0]],
        ];

        // Links are checked like button links
        $this->put('/admin/pages/guide/kids', $form(['url' => 'javascript:alert(1)']))->assertSessionHasErrors('items.'.$kids->items->first()->id.'.url');

        $this->put('/admin/pages/guide/kids', $form(['url' => 'https://example.com/guardian.pdf']) + [
            'new_items' => [['title' => 'Teen club rules', 'title_ar' => 'قواعد نادي المراهقين', 'icon' => 'info', 'tone' => 'ocean', 'url' => '/contact']],
        ])->assertSessionHasNoErrors();

        $this->get('/pre-boarding-guide')->assertSee('Guardian form')->assertSee('href="https://example.com/guardian.pdf"', false)
            ->assertSee('Teen club rules')->assertSee('href="'.url('/contact').'"', false);

        // Hidden sections leave the page and its section nav
        $this->put('/admin/pages/guide/prohibited', ['title' => 'Prohibited', 'is_visible' => 0])->assertSessionHasNoErrors();
        $this->get('/pre-boarding-guide')->assertDontSee('id="prohibited"', false)->assertDontSee('href="#prohibited"', false);
    }

    public function test_booking_page_shows_the_important_information_box(): void
    {
        $this->get('/booking')->assertOk()->assertSee('id="booking-notice"', false)
            ->assertSee('Important before you confirm')->assertSee('No irons, hair dryers, kettles or heating appliances')
            ->assertSee('href="'.url('/pre-boarding-guide').'"', false);
        $this->withUnencryptedCookie('sc_lang', 'ar')->get('/booking')->assertSee('معلومات مهمة قبل التأكيد');

        $this->asAdmin()->put('/admin/pages/guide/booking_notice', [
            'title' => 'Before you confirm', 'is_visible' => 0, 'primary_label' => 'Guide', 'primary_url' => '/pre-boarding-guide',
        ])->assertSessionHasNoErrors();
        $this->get('/booking')->assertOk()->assertDontSee('id="booking-notice"', false);
    }

    public function test_guide_renders_without_rows_and_is_linked_from_the_site(): void
    {
        HomeSection::where('page', 'guide')->delete();

        $this->get('/pre-boarding-guide')->assertOk()->assertSee('Pre-embarkation guide')->assertSee('Guardian Consent Form');
        $this->get('/booking')->assertOk()->assertSee('Important before you confirm');
        $this->get('/')->assertSee('href="'.route('guide').'"', false);
        $this->get('/ship')->assertSee('data-faq-search="#ship-faq"', false);
    }
}
