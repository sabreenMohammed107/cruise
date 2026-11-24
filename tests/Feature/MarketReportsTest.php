<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\AgentApplication;
use App\Models\Booking;
use App\Models\BookingTraveller;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin → Reports 11 – 14 (docs/reports-plan.pdf): Customers, Cancellations & refunds, Seasonality & lead time,
 * Interest & potential customers; and the printed (PDF) template every report shares.
 */
class MarketReportsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const JANUARY = '?preset=custom&from=2030-01-01&to=2030-01-31';

    private function admin(): User
    {
        return User::where('email', 'admin@safercruise.test')->firstOrFail();
    }

    /** The demo booking's sailing, departing 15 Mar 2030 for these tests. */
    private function sailing()
    {
        $sailing = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail()->sailing;
        $sailing->forceFill(['departure_date' => '2030-03-15'])->save();

        return $sailing;
    }

    /** A booking sold at $at on that sailing, copied from a demo booking with simple figures. */
    private function sale(string $at, array $attributes = []): Booking
    {
        $booking = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail()->replicate();
        $booking->forceFill($attributes + [
            'reference' => 'TS'.Str::upper(Str::random(8)),
            'confirmed_at' => $at,
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'adults' => 2, 'children' => 0, 'infants' => 0,
            'fare_total' => 1000, 'fees_total' => 0, 'discount_total' => 0, 'offer_discount_total' => 0,
            'agent_discount_total' => 0, 'grand_total' => 1000, 'amount_paid' => 0,
            'agent_id' => null, 'seller_id' => null, 'display_currency' => 'USD', 'exchange_rate' => 1, 'locale' => 'en',
        ])->save();

        return $booking;
    }

    private function customer(string $name): User
    {
        return User::factory()->create(['name' => $name, 'role' => UserRole::Customer, 'created_at' => '2030-01-03 10:00:00']);
    }

    private function travellers(Booking $booking, string $nationality, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            BookingTraveller::create(['booking_id' => $booking->id, 'band' => 'adult', 'slot' => $i, 'first_name' => 'T'.$i, 'last_name' => 'Test',
                'date_of_birth' => '1990-01-01', 'gender' => 'female', 'nationality' => $nationality]);
        }
    }

    public function test_customers_report(): void
    {
        $this->sailing();
        $mona = $this->customer('Mona New');
        $omar = $this->customer('Omar Returning');
        $this->sale('2029-06-01 10:00:00', ['user_id' => $omar->id]);
        $family = $this->sale('2030-01-05 10:00:00', ['user_id' => $mona->id, 'children' => 1, 'locale' => 'ar', 'display_currency' => 'EGP', 'exchange_rate' => 50]);
        $solo = $this->sale('2030-01-10 10:00:00', ['user_id' => $omar->id, 'adults' => 1, 'grand_total' => 500]);
        $this->sale('2030-01-12 10:00:00', ['user_id' => null, 'contact_email' => 'Guest@Example.test', 'grand_total' => 800]);
        $this->sale('2030-01-15 10:00:00', ['user_id' => $mona->id, 'status' => BookingStatus::Cancelled]);
        $this->travellers($family, 'Egypt', 3);
        $this->travellers($solo, 'Saudi Arabia', 1);

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/reports/customers'.self::JANUARY)->assertOk();

        $c = $response->viewData('current');
        $this->assertSame([3, 2, 1, 1], [$c['customers'], $c['new'], $c['returning'], $c['repeat']]);
        $this->assertEqualsWithDelta(2300, $c['revenue'], 0.001);
        $this->assertSame([5, 1, 0], [$c['adults'], $c['children'], $c['infants']]);
        $this->assertSame(2, $c['accounts']);

        $nationalities = $response->viewData('nationalities');
        $this->assertSame(['Egypt', 3, 1], [$nationalities[0]['name'], $nationalities[0]['travellers'], $nationalities[0]['bookings']]);
        $parties = $response->viewData('parties')->keyBy('name');
        $this->assertSame(1, $parties['Families (with children or infants)']['bookings']);
        $this->assertSame(1, $parties['Solo travellers']['bookings']);
        $this->assertSame(1, $parties['Couples and pairs (2 adults)']['bookings']);
        $this->assertSame(1, $response->viewData('currencies')->firstWhere('name', 'EGP')['bookings']);
        $this->assertSame(1, $response->viewData('languages')->firstWhere('name', 'Arabic')['bookings']);

        $top = $response->viewData('top');
        $this->assertSame(['Mona New', true], [$top[0]['name'], $top[0]['new']]);
        $omarRow = $top->firstWhere('name', 'Omar Returning');
        $this->assertSame([false, 2], [$omarRow['new'], $omarRow['lifetime_bookings']]);
        // Mona's cancelled booking doesn't make her a repeat customer
        $this->assertSame(1, $top[0]['lifetime_bookings']);
        // A booking without an account is a customer by its email, whatever its case
        $this->assertSame('eguest@example.test', $top->firstWhere('name', null)['key']);
    }

    public function test_cancellations_report(): void
    {
        $this->sailing();
        $admin = $this->admin();
        $early = $this->sale('2029-12-01 10:00:00', ['status' => BookingStatus::Cancelled, 'cancelled_at' => '2030-01-10 09:00:00', 'amount_paid' => 300]);
        $late = $this->sale('2030-01-05 10:00:00', ['status' => BookingStatus::Cancelled, 'cancelled_at' => '2030-01-20 09:00:00', 'grand_total' => 500]);
        $active = $this->sale('2030-01-08 10:00:00', ['amount_paid' => 950]);
        $this->sale('2029-11-01 10:00:00', ['status' => BookingStatus::Cancelled, 'cancelled_at' => '2029-12-20 09:00:00']);
        foreach ([[$early, 'payment', 400, '2029-12-02'], [$early, 'refund', 100, '2030-01-12'], [$active, 'payment', 1000, '2030-01-09'], [$active, 'refund', 50, '2030-01-25']] as [$b, $type, $amount, $at]) {
            Payment::create(['booking_id' => $b->id, 'type' => $type, 'method' => 'bank_transfer', 'amount' => $amount, 'received_at' => $at.' 10:00:00', 'recorded_by' => $admin->id]);
        }

        $response = $this->actingAs($admin, 'admin')->get('/admin/reports/cancellations'.self::JANUARY)->assertOk();

        $c = $response->viewData('current');
        $this->assertSame(2, $c['cancelled']);
        $this->assertEqualsWithDelta(1500, $c['value'], 0.001);
        $this->assertEqualsWithDelta(400, $c['paid'], 0.001);
        $this->assertEqualsWithDelta(100, $c['refunded'], 0.001);
        $this->assertEqualsWithDelta(300, $c['kept'], 0.001);
        // Refunds paid in January on any booking: $100 + $50
        $this->assertEqualsWithDelta(150, $c['refunds_paid'], 0.001);
        $this->assertSame(2, $c['refunds_count']);
        // Sold in January: the late one (cancelled) and the active one
        $this->assertSame([2, 1], [$c['sold'], $c['sold_cancelled']]);
        $this->assertEqualsWithDelta(50, $c['rate'], 0.001);
        $this->assertSame(1, $response->viewData('previous')['cancelled']);

        // 10 Jan → 15 Mar: 64 days; 20 Jan → 15 Mar: 54 days
        $notice = $response->viewData('notice')->keyBy('name');
        $this->assertSame(1, $notice['61 – 90 days']['cancelled']);
        $this->assertSame(1, $notice['31 – 60 days']['cancelled']);
        $this->assertSame(2, $response->viewData('cruises')->sole()['cancelled']);
        $this->assertSame([$late->reference, $early->reference], $response->viewData('cancelled')->map(fn ($r) => $r['booking']->reference)->all());
        $response->assertSee('Refunds paid out in this period');
    }

    public function test_seasonality_report(): void
    {
        $this->sailing();
        $this->sale('2030-01-14 10:00:00'); // 60 days ahead
        $this->sale('2030-03-10 10:00:00'); // 5 days ahead
        $this->sale('2030-02-20 10:00:00', ['status' => BookingStatus::Cancelled]);

        $this->actingAs($this->admin(), 'admin');
        $response = $this->get('/admin/reports/seasonality?preset=custom&from=2030-01-01&to=2030-03-31')->assertOk();

        $c = $response->viewData('current');
        $this->assertSame(2, $c['bookings']);
        $this->assertEqualsWithDelta(32.5, $c['average_lead'], 0.001);
        $this->assertEqualsWithDelta(32.5, $c['median_lead'], 0.001);
        $this->assertEqualsWithDelta(50, $c['last_minute'], 0.001);
        $this->assertEqualsWithDelta(0, $c['early'], 0.001);
        $bands = $response->viewData('bands')->keyBy('name');
        $this->assertSame(1, $bands['Last week (0 – 7 days)']['bookings']);
        $this->assertSame(1, $bands['1 – 2 months (31 – 60 days)']['bookings']);
        $this->assertSame([1, 0, 1], $response->viewData('soldByMonth')->pluck('bookings')->all());
        $sailing = $response->viewData('sailingByMonth');
        $this->assertSame(['Mar 2030', 2], [$sailing->sole()['name'], $sailing->sole()['bookings']]);
        $response->assertSee('lead-chart');

        // Opens on this year so far
        $this->get('/admin/reports/seasonality')->assertOk()->assertViewHas('filters', fn ($f) => $f->preset === 'year');
    }

    public function test_interest_report(): void
    {
        $sailing = $this->sailing();
        $cruise = $sailing->cruise_id;
        $browser = $this->customer('Browsing Only');
        $buyer = $this->customer('Bought Later');
        DB::table('favorites')->insert([
            ['user_id' => $browser->id, 'cruise_id' => $cruise, 'created_at' => '2030-01-04 10:00:00', 'updated_at' => '2030-01-04 10:00:00'],
            ['user_id' => $buyer->id, 'cruise_id' => $cruise, 'created_at' => '2030-01-06 10:00:00', 'updated_at' => '2030-01-06 10:00:00'],
        ]);
        $this->sale('2030-02-02 10:00:00', ['user_id' => $buyer->id]);
        ContactMessage::create(['name' => 'Lina Lead', 'email' => 'lina@example.test', 'message' => 'Do you have family cabins in March?'])
            ->forceFill(['created_at' => '2030-01-08 10:00:00'])->save();
        NewsletterSubscriber::create(['email' => 'news@example.test', 'subscribed_at' => '2030-01-09 10:00:00']);
        $pending = AgentApplication::create(['company_name' => 'Nile Trips', 'contact_name' => 'Hany', 'email' => 'nile@example.test', 'phone' => '1000']);
        $pending->forceFill(['created_at' => '2030-01-10 10:00:00'])->save();
        $rejected = AgentApplication::create(['company_name' => 'No Thanks', 'contact_name' => 'Ola', 'email' => 'no@example.test', 'phone' => '1001']);
        $rejected->forceFill(['status' => 'rejected', 'created_at' => '2030-01-11 10:00:00'])->save();

        $this->actingAs($this->admin(), 'admin');
        $response = $this->get('/admin/reports/interest'.self::JANUARY.'&channel=agent')->assertOk();

        $c = $response->viewData('current');
        $this->assertSame([2, 1, 1, 2], [$c['favourites'], $c['messages'], $c['subscribers'], $c['applications']]);
        $this->assertSame(1, $c['converted']);
        $this->assertSame(
            collect([$browser->email, 'lina@example.test', 'news@example.test', 'nile@example.test'])->sort()->values()->all(),
            $response->viewData('leads')->pluck('email')->sort()->values()->all()
        );
        $this->assertSame([$sailing->cruise->name], $response->viewData('leads')->firstWhere('email', $browser->email)['interests']);
        $row = $response->viewData('cruises')->firstWhere('name', $sailing->cruise->name);
        $this->assertSame([2, 2, 1, 1], [$row['total'], $row['added'], $row['booked'], $row['not_booked']]);
        // Only the cruise filter is used: the channel in the URL is ignored, the form has no channel
        $response->assertViewHas('filters', fn ($f) => $f->channel === null)->assertDontSee('id="report-channel"', false);

        // The cruise filter keeps the favourites only
        $this->assertSame(0, $this->get('/admin/reports/interest'.self::JANUARY.'&cruise='.$cruise)->viewData('current')['messages']);

        // Contact messages and the newsletter are for admins and supervisors: not sellers
        $this->actingAs(User::where('email', 'seller@safercruise.test')->firstOrFail(), 'admin');
        $this->get('/admin/reports/interest')->assertForbidden();
        $this->get('/admin/reports')->assertOk()->assertDontSee('Interest &amp; potential customers', false)->assertSee('Seasonality');
    }

    public function test_every_report_prints_on_the_branded_template_and_exports(): void
    {
        $this->sailing();
        $this->sale('2030-01-14 10:00:00');
        $this->actingAs($this->admin(), 'admin');

        $sheets = [
            'customers' => ['Summary', 'Customers'],
            'cancellations' => ['Summary', 'Cancelled bookings', 'Refunds paid'],
            'seasonality' => ['Summary', 'By month', 'Bookings'],
            'interest' => ['Summary', 'Potential customers', 'Agent applications'],
        ];
        foreach ($sheets as $key => $names) {
            $this->get("/admin/reports/$key".self::JANUARY)->assertOk()
                ->assertSee('id="report-print"', false)->assertSee('images/voucher/voucher-header.jpg', false)->assertSee('images/voucher/voucher-footer.jpg', false);

            $response = $this->get("/admin/reports/$key/export".self::JANUARY)->assertOk();
            $this->assertStringContainsString("$key-2030-01-01_2030-01-31.xlsx", $response->headers->get('content-disposition'));
            $path = $response->baseResponse->getFile()->getPathname();
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $workbook = $zip->getFromName('xl/workbook.xml');
            foreach ($names as $i => $name) {
                $this->assertStringContainsString('<sheet name="'.$name.'"', $workbook, "$key: $name");
                $this->assertNotFalse(simplexml_load_string($zip->getFromName('xl/worksheets/sheet'.($i + 1).'.xml')), "$key: $name is valid XML");
            }
            $zip->close();
            @unlink($path);
        }
    }
}
