<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/** Breadcrumbs and fixed words of the page templates come from lang/{en,ar}/pages.php. */
class PageTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_language_files_have_the_same_keys(): void
    {
        foreach (['pages', 'site', 'ui'] as $group) {
            $keys = fn (string $locale) => array_keys(Arr::dot(trans($group, [], $locale)));
            $this->assertSame($keys('en'), $keys('ar'), "lang/en/$group.php and lang/ar/$group.php differ");
        }
    }

    public function test_page_scripts_get_their_words_and_terms_in_both_languages(): void
    {
        $data = $this->get('/cruises')->assertOk()->viewData('scData');
        $this->assertSame('Select sailing', $data['i18n']['en']['ui']['card']['select']);
        $this->assertSame('اختر الرحلة', $data['i18n']['ar']['ui']['card']['select']);
        $this->assertSame('{1} :count night|[2,*] :count nights', $data['i18n']['en']['nights']);
        $this->assertSame('No penalty', $data['terms']['cancellation'][0][1]);

        $ar = $this->withUnencryptedCookie('sc_lang', 'ar')->get('/cruises')->assertOk()->viewData('scData');
        $this->assertSame('بدون غرامة', $ar['terms']['cancellation'][0][1]);
        $this->assertStringStartsWith('بإتمام هذا الحجز', $ar['terms']['text'][0]);
    }

    public function test_breadcrumbs_in_both_languages(): void
    {
        $pages = [
            '/ship' => [['Home', 'The ship'], ['الرئيسية', 'السفينة']],
            '/cruises' => [['Home', 'Cruises'], ['الرئيسية', 'الرحلات']],
            '/destinations' => [['Home', 'Destinations'], ['الرئيسية', 'الوجهات']],
            '/cruises/red-sea-discovery' => [['Home', 'Cruises', 'Red Sea Discovery'], ['الرئيسية', 'الرحلات', 'اكتشاف البحر الأحمر']],
        ];

        foreach ($pages as $url => [$en, $ar]) {
            $html = $this->withUnencryptedCookie('sc_lang', 'en')->get($url)->assertOk()
                ->assertSee('aria-label="Breadcrumb"', false)
                ->assertSee('aria-current="page">'.e(end($en)).'</span>', false)
                ->assertSeeInOrder($en)
                ->getContent();
            // schema.org trail for search engines, one entry per crumb
            $this->assertMatchesRegularExpression('/"@type":"BreadcrumbList".*"position":'.count($en).',"name":"'.preg_quote(end($en), '/').'"/', $html);

            $this->withUnencryptedCookie('sc_lang', 'ar')->get($url)->assertOk()
                ->assertSee('aria-label="مسار التنقل"', false)
                ->assertSee('aria-current="page">'.e(end($ar)).'</span>', false)
                ->assertSeeInOrder($ar)
                ->assertSee('"name":"'.end($ar).'"', false);
        }
        // details.js still finds the last crumb
        $this->withUnencryptedCookie('sc_lang', 'en')->get('/cruises/red-sea-discovery')->assertSee('id="crumb-name" aria-current="page">Red Sea Discovery</span>', false);
    }

    public function test_titles_and_fixed_words_follow_the_language(): void
    {
        $checks = [
            '/' => [
                'en' => ['<title>Safer Cruise | Red Sea cruises aboard AROYA</title>', 'aria-label="Previous photo"', '>Email address</label>', '2 adults'],
                'ar' => ['<title>Safer Cruise | رحلات البحر الأحمر على متن أرويا</title>', 'aria-label="الصورة السابقة"', '>البريد الإلكتروني</label>', 'بالغان'],
            ],
            '/ship' => [
                'en' => ['<title>The ship: AROYA | Safer Cruise</title>', '>About the ship</a>', '>Questions</a>', 'mt-2">AROYA</h1>'],
                'ar' => ['<title>السفينة: أرويا | Safer Cruise</title>', '>عن السفينة</a>', '>الأسئلة</a>', 'mt-2">أرويا</h1>'],
            ],
            '/cruises' => [
                'en' => ['<title>All cruises | Safer Cruise</title>', 'Departure port', 'Price per person', '>Most popular</option>', '>Show results</button>'],
                'ar' => ['<title>كل الرحلات | Safer Cruise</title>', 'ميناء المغادرة', 'السعر للشخص', '>الأكثر شعبية</option>', '>عرض النتائج</button>'],
            ],
            '/cruises/red-sea-discovery' => [
                'en' => ['<title>Red Sea Discovery | Safer Cruise</title>', '>Cabins &amp; prices</a>', 'View all photos', '>Expand all</button>', '>Continue</button>'],
                'ar' => ['<title>اكتشاف البحر الأحمر | Safer Cruise</title>', '>الكبائن والأسعار</a>', 'عرض كل الصور', '>توسيع الكل</button>', '>متابعة</button>'],
            ],
            '/destinations' => [
                'en' => ['<title>Destinations | Safer Cruise</title>', 'aria-label="Filter by country"'],
                'ar' => ['<title>الوجهات | Safer Cruise</title>', 'aria-label="تصفية حسب الدولة"'],
            ],
        ];

        foreach ($checks as $url => $byLang) {
            foreach ($byLang as $lang => $expected) {
                $response = $this->withUnencryptedCookie('sc_lang', $lang)->get($url)->assertOk();
                foreach ($expected as $text) {
                    $response->assertSee($text, false);
                }
                if ($lang === 'ar') {
                    // none of the English fixed words are left on the Arabic page
                    foreach ($byLang['en'] as $english) {
                        $response->assertDontSee($english, false);
                    }
                }
            }
        }
    }
}
