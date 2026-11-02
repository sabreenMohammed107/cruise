<?php

namespace Tests\Feature;

use App\Models\Cruise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin → Cruises: the list keeps cruises still running, sailings go to the archive (and close) on the day they depart
 * (a year at a time), and both filter by year, month, departure port and nights.
 */
class CruiseArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-27');
        $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin');
    }

    /** A copy of a seeded cruise with its own sailings: [departure, nights] each. */
    private function cruise(string $name, array $sailings, array $attributes = []): Cruise
    {
        $cruise = Cruise::firstOrFail()->replicate()->fill(['slug' => str($name)->slug(), 'name' => $name] + $attributes);
        $cruise->save();
        foreach ($sailings as $i => [$departure, $nights]) {
            $cruise->sailings()->create([
                'code' => strtoupper(substr(md5($name), 0, 6))."-$i",
                'departure_date' => $departure,
                'return_date' => now()->parse($departure)->addDays($nights)->toDateString(),
                'status' => 'open',
            ]);
        }

        return $cruise;
    }

    public function test_the_list_keeps_running_cruises_and_the_archive_takes_sailings_on_departure_day(): void
    {
        $this->cruise('Zeta Finished Cruise', [['2025-06-01', 3], ['2026-10-20', 4]]);   // last one back on 24 Oct
        $this->cruise('Zeta At Sea Now', [['2026-10-25', 4]]);                           // left, back on 29 Oct
        $this->cruise('Zeta Departs Today', [['2026-10-27', 3]]);                        // closed from 00:00
        $this->cruise('Zeta Departs Tomorrow', [['2026-10-28', 3]]);                     // still bookable today
        $this->cruise('Zeta Not Scheduled', []);                                          // new, no dates yet
        $this->cruise('Zeta Mixed', [['2026-01-10', 3], ['2027-02-01', 3]]);              // one over, one to come

        $this->get(route('admin.cruises.index', ['q' => 'Zeta']))->assertOk()
            ->assertSee('Zeta Departs Tomorrow')->assertSee('Zeta Not Scheduled')->assertSee('Zeta Mixed')
            ->assertDontSee('Zeta Finished Cruise')->assertDontSee('Zeta At Sea Now')->assertDontSee('Zeta Departs Today')
            ->assertSee('See the archive');

        // Latest year with an archived sailing by default; sailings from the day they depart on
        $this->get(route('admin.cruises.archive'))->assertOk()
            ->assertSee('Zeta Finished Cruise')->assertSee('Zeta Mixed')->assertSee('Zeta At Sea Now')
            ->assertSee('Zeta Departs Today')->assertDontSee('Zeta Departs Tomorrow')
            ->assertSee('<option value="2026" selected', false);

        // Another year: the 2025 sailing of the finished cruise, not the 2026 ones
        $this->get(route('admin.cruises.archive', ['year' => 2025]))->assertOk()
            ->assertSee('Zeta Finished Cruise')->assertDontSee('Zeta Mixed');

        // A finished cruise is still editable, so it can be scheduled again
        $finished = Cruise::where('name', 'Zeta Finished Cruise')->sole();
        $this->get(route('admin.cruises.edit', $finished->id))->assertOk();
    }

    public function test_sailings_close_command_closes_sailings_departing_today_or_earlier(): void
    {
        $this->cruise('Zeta Close', [['2026-10-26', 3], ['2026-10-27', 3], ['2026-10-28', 3]]);
        $sailings = Cruise::where('name', 'Zeta Close')->sole()->sailings()->orderBy('departure_date');

        $this->artisan('sailings:close')->assertSuccessful();

        $this->assertSame(['closed', 'closed', 'open'], $sailings->pluck('status')->map->value->all());
    }

    public function test_archive_filters_by_month_port_and_nights(): void
    {
        $this->cruise('Zeta March Jeddah', [['2026-03-05', 3]], ['departure_port' => 'Jeddah', 'nights' => 3]);
        $this->cruise('Zeta March Yanbu', [['2026-03-12', 4]], ['departure_port' => 'Yanbu', 'nights' => 4]);
        $this->cruise('Zeta June Jeddah', [['2026-06-05', 3]], ['departure_port' => 'Jeddah', 'nights' => 3]);

        $this->get(route('admin.cruises.archive', ['year' => 2026, 'month' => 3]))
            ->assertSee('Zeta March Jeddah')->assertSee('Zeta March Yanbu')->assertDontSee('Zeta June Jeddah');
        $this->get(route('admin.cruises.archive', ['year' => 2026, 'port' => 'Yanbu']))
            ->assertSee('Zeta March Yanbu')->assertDontSee('Zeta March Jeddah')->assertDontSee('Zeta June Jeddah');
        $this->get(route('admin.cruises.archive', ['year' => 2026, 'month' => 3, 'nights' => 3]))
            ->assertSee('Zeta March Jeddah')->assertDontSee('Zeta March Yanbu');
    }

    public function test_list_filters_by_year_month_port_and_nights(): void
    {
        $this->cruise('Zeta Dec 2026', [['2026-12-05', 3]], ['departure_port' => 'Jeddah', 'nights' => 3]);
        $this->cruise('Zeta Dec 2027', [['2027-12-05', 7]], ['departure_port' => 'Yanbu', 'nights' => 7]);
        // December 2025 is over: it must not make this cruise match "December"
        $this->cruise('Zeta Old Dec', [['2025-12-05', 3], ['2027-05-01', 3]], ['departure_port' => 'Jeddah', 'nights' => 3]);

        $list = fn (array $f) => $this->get(route('admin.cruises.index', ['q' => 'Zeta'] + $f))->assertOk();

        $list(['month' => 12])->assertSee('Zeta Dec 2026')->assertSee('Zeta Dec 2027')->assertDontSee('Zeta Old Dec');
        $list(['year' => 2027, 'month' => 12])->assertSee('Zeta Dec 2027')->assertDontSee('Zeta Dec 2026');
        $list(['port' => 'Yanbu'])->assertSee('Zeta Dec 2027')->assertDontSee('Zeta Dec 2026');
        $list(['nights' => 3])->assertSee('Zeta Dec 2026')->assertSee('Zeta Old Dec')->assertDontSee('Zeta Dec 2027');
    }

    public function test_unknown_filter_values_are_rejected_not_queried(): void
    {
        $this->get(route('admin.cruises.archive', ['year' => 1999]))->assertSessionHasErrors('year');
        $this->get(route('admin.cruises.archive', ['month' => 13]))->assertSessionHasErrors('month');
        $this->get(route('admin.cruises.index', ['port' => "Jeddah' OR 1=1 --"]))->assertSessionHasErrors('port');
    }

    public function test_sellers_cannot_open_the_archive(): void
    {
        $this->actingAs(User::where('email', 'seller@safercruise.test')->firstOrFail(), 'admin')
            ->get(route('admin.cruises.archive'))->assertForbidden();
    }
}
