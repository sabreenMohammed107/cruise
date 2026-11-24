<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Reports\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Admin → Reports → Sales summary (report 1, docs/reports-plan.pdf). */
class SalesSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** January 2030: away from the demo bookings, compared with December 2029. */
    private const JANUARY = '/admin/reports/sales-summary?preset=custom&from=2030-01-01&to=2030-01-31';

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** A booking sold at $at, copied from a demo booking with simple figures. */
    private function sale(string $at, array $attributes = []): Booking
    {
        $booking = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail()->replicate();
        $booking->forceFill($attributes + [
            'reference' => 'TS'.Str::upper(Str::random(8)),
            'confirmed_at' => $at,
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'adults' => 2, 'children' => 0, 'infants' => 0,
            'fare_total' => 1000, 'fees_total' => 100, 'discount_total' => 0, 'offer_discount_total' => 0,
            'agent_discount_total' => 0, 'grand_total' => 1100, 'amount_paid' => 0,
            'agent_id' => null, 'seller_id' => null, 'display_currency' => 'USD', 'exchange_rate' => 1,
        ])->save();

        return $booking;
    }

    private function agent(): User
    {
        return User::factory()->create(['role' => UserRole::Agent, 'company_name' => 'Blue Waves Travel']);
    }

    /** Two sales in January (one by an agent), one cancelled, one in December, one just after January. */
    private function sales(): array
    {
        return [
            'web' => $this->sale('2030-01-05 10:00:00', ['children' => 1, 'amount_paid' => 500, 'payment_status' => PaymentStatus::Partial]),
            // Last minute of the period still counts; an agent booking with every kind of discount
            'agent' => $this->sale('2030-01-31 23:30:00', [
                'agent_id' => $this->agent()->id, 'fare_total' => 900, 'agent_discount_total' => 100, 'fees_total' => 100,
                'discount_total' => 50, 'offer_discount_total' => 50, 'grand_total' => 900, 'amount_paid' => 900,
                'payment_status' => PaymentStatus::Paid, 'display_currency' => 'EGP', 'exchange_rate' => 50,
            ]),
            'cancelled' => $this->sale('2030-01-10 09:00:00', ['status' => BookingStatus::Cancelled, 'grand_total' => 2000]),
            'december' => $this->sale('2029-12-15 12:00:00', ['adults' => 1, 'grand_total' => 1000]),
            'february' => $this->sale('2030-02-01 00:00:01'),
        ];
    }

    public function test_figures_for_the_period_and_the_comparison(): void
    {
        $this->sales();

        $response = $this->actingAs($this->user('admin@safercruise.test'), 'admin')->get(self::JANUARY)->assertOk();

        $c = $response->viewData('current');
        $this->assertSame(2, $c['bookings']);
        $this->assertSame(5, $c['guests']);
        $this->assertEqualsWithDelta(2000, $c['revenue'], 0.001);
        $this->assertEqualsWithDelta(1000, $c['average'], 0.001);
        $this->assertEqualsWithDelta(1400, $c['collected'], 0.001);
        $this->assertEqualsWithDelta(600, $c['outstanding'], 0.001);
        $this->assertEqualsWithDelta(70, $c['collection_rate'], 0.001);
        $this->assertSame(1, $c['cancelled']);
        $this->assertEqualsWithDelta(2000, $c['cancelled_value'], 0.001);

        // The bridge adds up to the revenue: public fare − agent discount + fees − promo − offers
        $this->assertEqualsWithDelta(2000, $c['public_fare'], 0.001);
        $this->assertEqualsWithDelta($c['revenue'], $c['public_fare'] - $c['agent_discount'] + $c['fees'] - $c['promo'] - $c['offers'], 0.001);

        // December 2029: the 31 days before
        $this->assertSame('2029-12-01', $response->viewData('comparison')[0]->toDateString());
        $this->assertSame('2029-12-31', $response->viewData('comparison')[1]->toDateString());
        $p = $response->viewData('previous');
        $this->assertSame(1, $p['bookings']);
        $this->assertEqualsWithDelta(1000, $p['revenue'], 0.001);

        // Channels, currencies, the daily chart and the list (cancelled listed, February not)
        $this->assertEqualsWithDelta(900, $response->viewData('channels')['agent']->revenue, 0.001);
        $this->assertEqualsWithDelta(45000, $response->viewData('currencies')->firstWhere('currency', 'EGP')->local, 0.001);
        $trend = $response->viewData('trend');
        $this->assertSame('day', $trend['unit']);
        $this->assertCount(31, $trend['revenue']);
        $this->assertEqualsWithDelta(1100, $trend['revenue'][4], 0.001);
        $this->assertSame(3, $response->viewData('bookings')->total());

        $response->assertSee('Sales summary')->assertSee('$2,000')->assertSee('▲ 100.0%', false)->assertSee('sales-trend');
    }

    public function test_filters_narrow_the_figures(): void
    {
        $sales = $this->sales();
        $this->actingAs($this->user('admin@safercruise.test'), 'admin');

        $this->assertSame(1, $this->get(self::JANUARY.'&channel=agent')->viewData('current')['bookings']);
        $this->assertSame(1, $this->get(self::JANUARY.'&channel=b2c')->viewData('current')['bookings']);
        $this->assertSame(1, $this->get(self::JANUARY.'&agent='.$sales['agent']->agent_id)->viewData('current')['bookings']);
        $this->assertSame(0, $this->get(self::JANUARY.'&sailing=999999')->viewData('current')['bookings']);
        $this->assertSame(2, $this->get(self::JANUARY.'&cruise='.$sales['web']->sailing->cruise_id)->viewData('current')['bookings']);
        $this->assertNull($this->get(self::JANUARY.'&compare=none')->viewData('previous'));
    }

    public function test_presets_and_their_comparison_periods(): void
    {
        $this->travelTo('2030-01-20 15:00:00');
        $viewer = $this->user('admin@safercruise.test');

        // This month to date, against the same days of last month
        [$from, $to] = ReportFilters::range('month');
        $f = new ReportFilters($viewer, 'month', $from, $to);
        $this->assertSame(['2030-01-01', '2030-01-20'], [$from->toDateString(), $to->toDateString()]);
        $this->assertSame('23:59:59', $to->format('H:i:s'));
        $this->assertSame(['2029-12-01', '2029-12-20'], array_map(fn ($d) => $d->toDateString(), $f->comparison()));

        // Last month, against the whole month before
        [$from, $to] = ReportFilters::range('last_month');
        $f = new ReportFilters($viewer, 'last_month', $from, $to);
        $this->assertSame(['2029-12-01', '2029-12-31'], [$from->toDateString(), $to->toDateString()]);
        $this->assertSame(['2029-11-01', '2029-11-30'], array_map(fn ($d) => $d->toDateString(), $f->comparison()));

        // Same period last year
        $f = new ReportFilters($viewer, 'month', ...[...ReportFilters::range('month'), 'year']);
        $this->assertSame('2029-01-01', $f->comparison()[0]->toDateString());

        // Buckets follow the length: this year so far (20 days) by day, a whole year by month
        $this->actingAs($viewer, 'admin')->get('/admin/reports/sales-summary?preset=year')->assertOk()
            ->assertViewHas('trend', fn ($t) => $t['unit'] === 'day');
        $this->get('/admin/reports/sales-summary?preset=custom&from=2029-01-01&to=2029-12-31')->assertOk()
            ->assertViewHas('trend', fn ($t) => $t['unit'] === 'month' && count($t['labels']) === 12);

        // Dates changed by hand make it a custom period
        $this->get('/admin/reports/sales-summary?preset=month&from=2030-01-02&to=2030-01-20')
            ->assertViewHas('filters', fn ($f) => $f->preset === 'custom' && $f->from->toDateString() === '2030-01-02');
    }

    public function test_a_seller_only_sees_the_bookings_assigned_to_them(): void
    {
        $sales = $this->sales();
        $seller = $this->user('seller@safercruise.test');
        $sales['web']->forceFill(['seller_id' => $seller->id])->save();
        $admin = $this->user('admin@safercruise.test');
        $sales['agent']->forceFill(['seller_id' => $admin->id])->save();

        $this->actingAs($seller, 'admin');
        $this->get('/admin')->assertSee('Reports');
        $this->get('/admin/reports')->assertOk()->assertSee('Sales summary');

        // Asking for someone else's bookings doesn't widen the report
        $response = $this->get(self::JANUARY.'&seller='.$admin->id)->assertOk();
        $this->assertSame(1, $response->viewData('current')['bookings']);
        $this->assertSame([$sales['web']->reference], collect($response->viewData('bookings')->items())->pluck('reference')->all());
        $response->assertDontSee('id="report-seller"', false);

        $sheet = $this->workbook($this->get(str_replace('/sales-summary?', '/sales-summary/export?', self::JANUARY).'&seller='.$admin->id))['bookings'];
        $this->assertStringContainsString($sales['web']->reference, $sheet);
        $this->assertStringNotContainsString($sales['agent']->reference, $sheet);
    }

    public function test_who_can_open_reports(): void
    {
        $this->actingAs($this->user('supervisor@safercruise.test'), 'admin')->get(self::JANUARY)->assertOk();
        $this->actingAs($this->user('admin@safercruise.test'), 'admin')->get('/admin/reports/no-such-report')->assertNotFound();
        $this->actingAs($this->user('reem@example.com'), 'admin')->get('/admin/reports')->assertForbidden();
        $this->actingAs($this->agent(), 'admin')->get(self::JANUARY)->assertForbidden();
    }

    public function test_excel_export(): void
    {
        $sales = $this->sales();
        $sales['web']->forceFill(['contact_email' => '=HYPERLINK("http://x")'])->save();

        $response = $this->actingAs($this->user('admin@safercruise.test'), 'admin')
            ->get('/admin/reports/sales-summary/export?preset=custom&from=2030-01-01&to=2030-01-31&channel=b2c&compare=year');
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('sales-summary-2030-01-01_2030-01-31.xlsx', $response->headers->get('content-disposition'));

        $book = $this->workbook($response);
        foreach ($book as $part => $xml) {
            $this->assertNotFalse(simplexml_load_string($xml), "$part is valid XML");
        }
        $this->assertStringContainsString('<sheet name="Summary"', $book['workbook']);
        $this->assertStringContainsString('<sheet name="Bookings"', $book['workbook']);

        // Summary: title, period, the filters in words, the key figures
        $this->assertStringContainsString('Sales summary', $book['summary']);
        $this->assertStringContainsString('1 Jan – 31 Jan 2030 · compared with 1 Jan – 31 Jan 2029', $book['summary']);
        $this->assertStringContainsString('Filters: Channel: Website (B2C)', $book['summary']);
        $this->assertStringContainsString('Key figures', $book['summary']);

        // Bookings: header kept in view with a filter over header + 2 rows (the b2c sale and the cancelled one),
        // then a totals row outside the filter
        $sheet = $book['bookings'];
        $this->assertStringContainsString('state="frozen"', $sheet);
        $this->assertStringContainsString('<autoFilter ref="A1:Z3"/>', $sheet);
        $this->assertStringContainsString($sales['web']->reference, $sheet);
        $this->assertStringContainsString($sales['cancelled']->reference, $sheet);
        $this->assertStringNotContainsString($sales['agent']->reference, $sheet);
        $this->assertStringNotContainsString($sales['february']->reference, $sheet);
        // Dates are real Excel dates (5 Jan 2030 10:00), amounts real numbers in the money format
        $this->assertStringContainsString('<c r="B2" s="2"><v>47488.4166666667</v></c>', $sheet);
        $this->assertStringContainsString('<c r="S2" s="5"><v>1100</v></c>', $sheet);
        // Text is never a formula in the file
        $this->assertStringContainsString('=HYPERLINK(&quot;http://x&quot;)', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);
        // Totals: 1 booking (the cancelled one left out), $1,100 revenue, $600 due
        $this->assertStringContainsString('Total: 1 booking, 1 cancelled not included', $sheet);
        $this->assertStringContainsString('<c r="S4" s="10"><v>1100</v></c>', $sheet);
        $this->assertStringContainsString('<c r="U4" s="10"><v>600</v></c>', $sheet);
    }

    /** The parts of an exported .xlsx (the temporary file is removed afterwards). */
    private function workbook(\Illuminate\Testing\TestResponse $response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $parts = [
            'workbook' => $zip->getFromName('xl/workbook.xml'),
            'styles' => $zip->getFromName('xl/styles.xml'),
            'summary' => $zip->getFromName('xl/worksheets/sheet1.xml'),
            'bookings' => $zip->getFromName('xl/worksheets/sheet2.xml'),
        ];
        $zip->close();
        @unlink($path);

        return $parts;
    }
}
