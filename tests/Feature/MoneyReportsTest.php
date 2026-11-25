<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Sailing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin → Reports 3 and 4 (docs/reports-plan.pdf): Receivables (balances as at the end of the period, by days to
 * departure and age) and the Payments ledger (money in and out day by day, by method and by who recorded it).
 *
 * January 2030, with these bookings (sailing departs 15 Mar 2030, another one departed 20 Jan 2030):
 *   A sold 1 Dec, $1,000: paid $400 on 5 Dec, $200 on 20 Jan, $300 on 5 Feb (after the period)
 *   B sold 25 Jan, $500, nothing paid
 *   C sold 10 Jan, $800, paid in full on 11 Jan (cash, by the seller), $50 refunded on 25 Jan (card)
 *   D sold 10 Dec, $700, nothing paid, cancelled on 5 Feb (still live on 31 Jan)
 *   E sold 1 Nov, $1,000, nothing paid, cancelled on 15 Jan
 *   F sold 2 Jan, $300, nothing paid, sailed on 20 Jan
 */
class MoneyReportsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const JANUARY = '?preset=custom&from=2030-01-01&to=2030-01-31';

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function sale(Sailing $sailing, string $at, float $total, array $attributes = []): Booking
    {
        $booking = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail()->replicate();
        $booking->forceFill($attributes + [
            'reference' => 'TS'.Str::upper(Str::random(8)),
            'sailing_id' => $sailing->id,
            'confirmed_at' => $at,
            'status' => BookingStatus::Confirmed,
            'cancelled_at' => null,
            'payment_status' => PaymentStatus::Unpaid,
            'adults' => 2, 'children' => 0, 'infants' => 0,
            'fare_total' => $total, 'fees_total' => 0, 'discount_total' => 0, 'offer_discount_total' => 0,
            'agent_discount_total' => 0, 'grand_total' => $total, 'amount_paid' => 0,
            'agent_id' => null, 'seller_id' => null, 'display_currency' => 'USD', 'exchange_rate' => 1, 'locale' => 'en',
        ])->save();

        return $booking;
    }

    private function pay(Booking $booking, string $type, float $amount, string $at, string $method, User $by): Payment
    {
        $payment = Payment::create(['booking_id' => $booking->id, 'type' => $type, 'method' => $method, 'amount' => $amount, 'currency' => 'USD',
            'received_at' => $at, 'recorded_by' => $by->id]);
        $booking->syncPaymentTotals();

        return $payment;
    }

    /** @return array<string, Booking> */
    private function scenario(): array
    {
        $admin = $this->user('admin@safercruise.test');
        $seller = $this->user('seller@safercruise.test');
        $sailing = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail()->sailing;
        $sailing->forceFill(['departure_date' => '2030-03-15'])->save();
        $departed = $sailing->replicate();
        $departed->forceFill(['code' => 'TS-DEPARTED', 'departure_date' => '2030-01-20', 'return_date' => '2030-01-27'])->save();
        // The demo bookings out of the way
        Booking::query()->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => '2027-01-01 00:00:00']);

        $s = [
            'A' => $this->sale($sailing, '2029-12-01 10:00:00', 1000, ['seller_id' => $seller->id]),
            'B' => $this->sale($sailing, '2030-01-25 10:00:00', 500),
            'C' => $this->sale($sailing, '2030-01-10 10:00:00', 800, ['seller_id' => $seller->id]),
            'D' => $this->sale($sailing, '2029-12-10 10:00:00', 700, ['status' => BookingStatus::Cancelled, 'cancelled_at' => '2030-02-05 10:00:00']),
            'E' => $this->sale($sailing, '2029-11-01 10:00:00', 1000, ['status' => BookingStatus::Cancelled, 'cancelled_at' => '2030-01-15 10:00:00']),
            'F' => $this->sale($departed, '2030-01-02 10:00:00', 300),
        ];
        $this->pay($s['A'], 'payment', 400, '2029-12-05 10:00:00', 'bank_transfer', $admin);
        $this->pay($s['A'], 'payment', 200, '2030-01-20 10:00:00', 'bank_transfer', $admin)
            ->receipts()->create(['path' => 'payment-receipts/test.pdf', 'name' => 'transfer-slip.pdf', 'size' => 1000]);
        $this->pay($s['A'], 'payment', 300, '2030-02-05 10:00:00', 'bank_transfer', $admin);
        $this->pay($s['C'], 'payment', 800, '2030-01-11 10:00:00', 'cash', $seller);
        $this->pay($s['C'], 'refund', 50, '2030-01-25 10:00:00', 'card', $admin);

        return $s;
    }

    public function test_receivables_are_the_balances_at_the_end_of_the_period(): void
    {
        $s = $this->scenario();
        $this->travelTo('2030-02-10 12:00:00');

        $response = $this->actingAs($this->user('admin@safercruise.test'), 'admin')->get('/admin/reports/receivables'.self::JANUARY)->assertOk();

        // On 31 Jan: A owes 400 (the 5 Feb payment comes later), B 500, D 700 (cancelled later), F 300; C is paid (the
        // $50 refund leaves $50 owed); E was cancelled before
        $open = $response->viewData('open')->keyBy(fn ($r) => $r['booking']->reference);
        $this->assertEqualsCanonicalizing([$s['A']->reference, $s['B']->reference, $s['C']->reference, $s['D']->reference, $s['F']->reference], $open->keys()->all());
        $this->assertEqualsWithDelta(400, $open[$s['A']->reference]['due'], 0.001);
        $this->assertEqualsWithDelta(50, $open[$s['C']->reference]['due'], 0.001);
        $this->assertSame(43, $open[$s['A']->reference]['days_to_departure']);
        $this->assertSame(61, $open[$s['A']->reference]['age_days']);
        $this->assertSame(-11, $open[$s['F']->reference]['days_to_departure']);
        // Soonest departure first: F (departed) leads
        $this->assertSame($s['F']->reference, $response->viewData('open')->first()['booking']->reference);

        $c = $response->viewData('current');
        $this->assertSame([5, 3, 2], [$c['bookings'], $c['unpaid'], $c['partial']]);
        $this->assertEqualsWithDelta(1950, $c['due'], 0.001);
        $this->assertEqualsWithDelta(300, $c['departed_due'], 0.001);
        $this->assertSame(1, $c['departed']);
        $this->assertSame(0, $c['soon']);
        // Sold in January and still owing: B, C, F
        $this->assertEqualsWithDelta(850, $c['new_due'], 0.001);
        // Received in January less refunded: 200 + 800 − 50
        $this->assertEqualsWithDelta(950, $c['collected'], 0.001);

        // On 31 Dec: A owed 600, D 700, E 1,000; collected in December: 400
        $p = $response->viewData('previous');
        $this->assertSame(3, $p['bookings']);
        $this->assertEqualsWithDelta(2300, $p['due'], 0.001);
        $this->assertEqualsWithDelta(400, $p['collected'], 0.001);

        $departure = $response->viewData('departure')->keyBy('name');
        $this->assertSame(1, $departure['Already departed']['bookings']);
        $this->assertSame(4, $departure['31 – 60 days']['bookings']);
        $age = $response->viewData('age')->keyBy('name');
        $this->assertSame([1, 2, 1, 1], [$age['7 days or less']['bookings'], $age['8 – 30 days']['bookings'], $age['31 – 60 days']['bookings'], $age['More than 60 days']['bookings']]);
        $response->assertSee('Balances as at 31 Jan 2030');
    }

    public function test_a_seller_sees_the_balances_of_their_own_bookings(): void
    {
        $s = $this->scenario();
        $this->travelTo('2030-02-10 12:00:00');

        $response = $this->actingAs($this->user('seller@safercruise.test'), 'admin')->get('/admin/reports/receivables'.self::JANUARY.'&seller=none')->assertOk();

        $this->assertEqualsCanonicalizing([$s['A']->reference, $s['C']->reference], $response->viewData('open')->map(fn ($r) => $r['booking']->reference)->all());
        // Only the money on their bookings: 200 on A, 800 − 50 on C
        $this->assertEqualsWithDelta(950, $response->viewData('current')['collected'], 0.001);
    }

    public function test_payments_ledger(): void
    {
        $s = $this->scenario();
        $admin = $this->user('admin@safercruise.test');

        $response = $this->actingAs($admin, 'admin')->get('/admin/reports/payments'.self::JANUARY)->assertOk();

        $c = $response->viewData('current');
        $this->assertEqualsWithDelta(1000, $c['received'], 0.001);
        $this->assertEqualsWithDelta(50, $c['refunded'], 0.001);
        $this->assertEqualsWithDelta(950, $c['net'], 0.001);
        $this->assertSame([2, 1, 2, 3, 3], [$c['payments'], $c['refunds'], $c['bookings'], $c['entries'], $c['days']]);
        $this->assertEqualsWithDelta(500, $c['average'], 0.001);
        $this->assertEqualsWithDelta(800, $c['largest'], 0.001);
        // Only the 20 Jan transfer has a receipt
        $this->assertSame(2, $c['without_receipt']);
        $this->assertEqualsWithDelta(400, $response->viewData('previous')['received'], 0.001);

        $days = $response->viewData('days');
        $this->assertSame(['2030-01-11', '2030-01-20', '2030-01-25'], $days->map(fn ($d) => $d['day']->toDateString())->all());
        $this->assertSame([800.0, 1000.0, 950.0], $days->pluck('running')->all());

        $methods = $response->viewData('methods')->keyBy('name');
        $this->assertEqualsWithDelta(80, $methods['Cash']['share'], 0.001);
        $this->assertEqualsWithDelta(200, $methods['Bank transfer']['received'], 0.001);
        $this->assertEqualsWithDelta(-50, $methods['Card']['net'], 0.001);
        $this->assertSame(0, $methods['Payment link']['entries']);

        $staff = $response->viewData('staff')->keyBy('name');
        $this->assertEqualsWithDelta(800, $staff[$this->user('seller@safercruise.test')->name]['received'], 0.001);
        $this->assertSame(2, $staff[$admin->name]['entries']);

        $chart = $response->viewData('chart');
        $this->assertSame(['day', 31], [$chart['unit'], count($chart['buckets'])]);
        $this->assertEqualsWithDelta(800, $chart['buckets'][10]['in'], 0.001);
        $response->assertSee('transfer-slip.pdf')->assertSee($s['A']->reference);

        // Every booking here is a website one: the agent channel leaves nothing
        $this->get('/admin/reports/payments'.self::JANUARY.'&channel=agent')->assertOk()->assertViewHas('current', fn ($c) => $c['entries'] === 0);
    }

    public function test_both_print_on_the_branded_template_and_export(): void
    {
        $this->scenario();
        $this->travelTo('2030-02-10 12:00:00');
        $this->actingAs($this->user('admin@safercruise.test'), 'admin');

        foreach (['receivables' => ['Summary', 'Balances'], 'payments' => ['Summary', 'By day', 'Ledger']] as $key => $names) {
            $this->get("/admin/reports/$key".self::JANUARY)->assertOk()
                ->assertSee('id="report-print"', false)->assertSee('images/voucher/voucher-header.jpg', false);

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
