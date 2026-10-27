<?php

namespace Tests\Feature;

use App\Models\AgeBand;
use App\Models\CabinCategory;
use App\Models\Cruise;
use App\Models\Destination;
use App\Models\Facility;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Models\ItineraryDay;
use App\Models\Port;
use App\Models\PromoCode;
use App\Models\Ship;
use App\Models\ShipEssential;
use App\Models\ShipFaq;
use App\Models\ShipSpec;
use App\Models\ShipZone;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Every visitor-facing database text exists in English and Arabic. */
class BilingualContentTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** Content models: each lists its translated columns in TRANSLATABLE. */
    private const CONTENT = [
        Ship::class, ShipSpec::class, ShipZone::class, ShipEssential::class, ShipFaq::class, Facility::class,
        CabinCategory::class, Cruise::class, ItineraryDay::class, Destination::class, Testimonial::class,
        AgeBand::class, PromoCode::class, HomeSection::class, HomeSectionItem::class, SiteSetting::class,
    ];

    /** Tables with no site copy: personal or operational data, links, framework tables. */
    private const NOT_CONTENT = [
        'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'migrations', 'bookings', 'booking_cabins', 'booking_travellers', 'payments', 'favorites', 'newsletter_subscribers',
        'contact_messages', 'agent_requests', 'sailings', 'sailing_cabin_prices', 'cruise_port',
    ];

    /** Content tables with name_en / name_ar instead of TRANSLATABLE columns. */
    private const NAME_EN_AR = ['cabin_categories', 'ports', 'highlights'];

    private function inArabic(): static
    {
        return $this->withUnencryptedCookie('sc_lang', 'ar');
    }

    public function test_every_table_is_bilingual_or_listed_as_not_content(): void
    {
        $contentTables = array_map(fn ($model) => (new $model)->getTable(), self::CONTENT);
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            $this->assertContains($table, [...$contentTables, ...self::NAME_EN_AR, ...self::NOT_CONTENT], "Table \"$table\" needs Arabic columns (TRANSLATABLE) or a NOT_CONTENT entry.");
        }

        foreach (self::CONTENT as $model) {
            $table = (new $model)->getTable();
            $this->assertNotEmpty($model::translatable(), "$model has no TRANSLATABLE columns");
            foreach ($model::translatable() as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "$table.$column");
                $this->assertTrue(Schema::hasColumn($table, $column.'_ar'), "$table.{$column}_ar is missing");
            }
        }
        foreach (self::NAME_EN_AR as $table) {
            $this->assertTrue(Schema::hasColumns($table, ['name_en', 'name_ar']), "$table.name_en / name_ar");
        }
    }

    public function test_all_seeded_content_has_its_arabic_version(): void
    {
        $checked = 0;
        foreach (self::CONTENT as $model) {
            foreach ($model::all() as $row) {
                foreach ($model::translatable() as $column) {
                    if (filled($row->getAttribute($column))) {
                        $this->assertNotEmpty($row->getAttribute($column.'_ar'), class_basename($model)." #{$row->id} {$column}_ar");
                        $checked++;
                    }
                }
            }
        }
        $this->assertGreaterThan(250, $checked); // 268 texts in the template data
        $this->assertSame(3, CabinCategory::whereNotNull('name_ar')->count());
    }

    public function test_site_data_is_sent_in_the_visitor_language(): void
    {
        $en = $this->get('/ship')->assertOk()->viewData('scData');
        $this->assertSame('AROYA', $en['ships'][0]['name']);
        $this->assertSame('Gross tonnage', $en['ships'][0]['specs'][0]['label']);
        $this->assertSame([], (array) $en['portNames']);

        $ar = $this->inArabic()->get('/ship')->assertOk()->viewData('scData');
        $ship = $ar['ships'][0];
        $this->assertSame('ar', $ar['lang']);
        $this->assertSame('أرويا', $ship['name']);
        $this->assertSame('أول سفينة سياحية سعودية', $ship['tagline']);
        $this->assertStringStartsWith('أرويا هي', $ship['story'][0]);
        $this->assertSame(['key' => 'tonnage', 'value' => '151,300', 'unit' => 'طن إجمالي', 'label' => 'الحمولة الإجمالية'], $ship['specs'][0]);
        $this->assertSame('المسابح والحديقة المائية', $ship['zones'][0]['title']);
        $this->assertSame('طعام حلال', $ship['essentials'][0][1]);
        $this->assertSame('ماذا أحضر لتسجيل الوصول؟', $ship['faqs'][0][0]);
        $this->assertSame(['icon' => 'utensils', 'value' => '+35', 'label' => 'مطاعم', 'text' => 'مطاعم عربية وآسيوية وإيطالية ومشويات، وكلها تقدّم طعامًا حلالًا.'], $ar['facilities'][0]);

        $cabin = collect($ar['cabins'])->firstWhere('id', 'balcony');
        $this->assertSame('كابينة بلكونة', $cabin['nameEn']); // main name in the page language
        $this->assertSame('Balcony Cabin', $cabin['nameAr']); // the other language underneath
        $this->assertSame('بلكونة خاصة', $cabin['features'][0]);

        $cruise = collect($ar['cruises'])->firstWhere('id', 'red-sea-discovery');
        $this->assertSame('اكتشاف البحر الأحمر', $cruise['name']);
        $this->assertStringStartsWith('أسبوع كامل', $cruise['summary']);
        $this->assertSame(['port' => 'Jeddah', 'time' => 'المغادرة 20:00', 'text' => 'سجّل وصولك في ميناء جدة الإسلامي من الساعة 14:00. استقر في كابينتك وانضم إلى احتفال الإبحار على سطح المسبح.'], $cruise['itinerary'][0]);
        $this->assertSame(['Jeddah', 'Safaga', 'Sokhna', 'Al Wajh', 'Yanbu', 'Jeddah'], $cruise['route']); // keys stay English

        $safaga = collect($ar['destinations'])->firstWhere('slug', 'safaga');
        $this->assertSame('Safaga', $safaga['name']);
        $this->assertSame('مصر', $safaga['country']);
        $this->assertSame('الأقصر: معبد الكرنك ووادي الملوك', $safaga['highlights'][0]);
        $this->assertSame(['At sea' => 'في البحر', 'Safaga' => 'سفاجا', 'Egypt' => 'مصر'], array_intersect_key((array) $ar['portNames'], array_flip(['At sea', 'Safaga', 'Egypt'])));

        $this->assertSame('ريم أ.', $ar['testimonials'][0]['name']);
        $this->assertSame('بالغ', $ar['ageBands']['adult']['label']);
        $this->assertSame('18 سنة فأكثر', $ar['ageBands']['adult']['hint']);
    }

    public function test_promo_label_follows_the_language(): void
    {
        // withCredentials: JSON test requests only send cookies when asked to
        $this->postJson('/booking/promo', ['code' => 'safer10'])->assertOk()->assertJsonPath('label', '10% off cruise fare');
        $this->inArabic()->withCredentials()->postJson('/booking/promo', ['code' => 'safer10'])->assertOk()->assertJsonPath('label', 'خصم 10% على سعر الرحلة');
    }

    public function test_admin_edits_arabic_next_to_english(): void
    {
        $admin = User::where('email', 'admin@safercruise.test')->firstOrFail();
        $spec = ShipSpec::where('key', 'decks')->firstOrFail();

        $this->actingAs($admin, 'admin')->get("/admin/ship-specs/{$spec->id}/edit")->assertOk()
            ->assertSee('name="label_ar"', false)->assertSee('name="unit_ar"', false)->assertSee('dir="rtl"', false)->assertSee('الطوابق');

        $form = ['ship_id' => $spec->ship_id, 'key' => 'decks', 'label' => 'Decks', 'label_ar' => 'عدد الطوابق', 'value' => '18', 'value_ar' => '18', 'unit' => '', 'unit_ar' => '', 'sort_order' => 7];
        $this->put("/admin/ship-specs/{$spec->id}", $form)->assertSessionHasNoErrors();
        $this->assertSame('عدد الطوابق', $spec->fresh()->label_ar);

        // English stays required, Arabic is optional but has the same length limit
        $this->put("/admin/ship-specs/{$spec->id}", ['label' => ''] + $form)->assertSessionHasErrors('label');
        $this->put("/admin/ship-specs/{$spec->id}", ['label_ar' => ''] + $form)->assertSessionHasNoErrors();
        $this->put("/admin/ship-specs/{$spec->id}", ['value_ar' => str_repeat('9', 41)] + $form)->assertSessionHasErrors('value_ar');

        // Every content screen shows its Arabic boxes
        foreach (['ships' => Ship::class, 'ship-zones' => ShipZone::class, 'ship-essentials' => ShipEssential::class, 'ship-faqs' => ShipFaq::class,
            'facilities' => Facility::class, 'cabin-categories' => CabinCategory::class, 'cruises' => Cruise::class, 'destinations' => Destination::class,
            'testimonials' => Testimonial::class, 'age-bands' => AgeBand::class, 'promo-codes' => PromoCode::class] as $slug => $model) {
            $page = $this->get("/admin/$slug/{$model::firstOrFail()->id}/edit")->assertOk();
            foreach ($model::translatable() as $column) {
                if ($model === Cruise::class && $column === 'name' || $model === Destination::class && $column === 'name' || $model === AgeBand::class && $column === 'label') {
                    continue; // these already had their own "(Arabic)" field
                }
                $page->assertSee('name="'.$column.'_ar"', false);
            }
        }
    }

    public function test_itinerary_editor_saves_arabic(): void
    {
        $admin = User::where('email', 'admin@safercruise.test')->firstOrFail();
        $cruise = Cruise::where('slug', 'saudi-coast-weekend')->firstOrFail();

        $this->actingAs($admin, 'admin')->get("/admin/cruises/{$cruise->id}/edit")->assertOk()
            ->assertSee('itinerary[0][time_ar]', false)->assertSee('المغادرة 20:00');

        $form = $cruise->only(['name', 'name_ar', 'slug', 'ship_id', 'nights', 'departure_port', 'arrival_port', 'summary', 'summary_ar'])
            + ['route_ports' => Port::idsFor(['Jeddah', 'Yanbu', 'Jeddah']), 'tags' => ['family'], 'is_active' => 1,
                'itinerary' => [['port' => 'Jeddah', 'time' => 'Departs 21:00', 'time_ar' => 'المغادرة 21:00', 'text' => 'Embark.', 'text_ar' => 'الصعود.']]];
        $this->put("/admin/cruises/{$cruise->id}", $form)->assertSessionHasNoErrors();

        $day = $cruise->itinerary()->first();
        $this->assertSame(['المغادرة 21:00', 'الصعود.'], [$day->time_label_ar, $day->description_ar]);
    }
}
