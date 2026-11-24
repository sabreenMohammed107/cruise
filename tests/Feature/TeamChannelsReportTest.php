<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\AgentTier;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Admin → Reports → Sales team & channels (reports 8, 9 and 10, docs/reports-plan.pdf). */
class TeamChannelsReportTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** January 2030 (away from the demo bookings), compared with December 2029. */
    private const JANUARY = '/admin/reports/team-channels?preset=custom&from=2030-01-01&to=2030-01-31';

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
            'agent_id' => null, 'agent_tier_id' => null, 'seller_id' => null, 'promo_code_id' => null,
            'display_currency' => 'USD', 'exchange_rate' => 1,
        ])->save();

        return $booking;
    }

    private function pay(Booking $booking, float $amount, string $at, User $by, string $type = 'payment'): void
    {
        Payment::create(['booking_id' => $booking->id, 'type' => $type, 'method' => 'bank_transfer', 'amount' => $amount, 'received_at' => $at, 'recorded_by' => $by->id]);
    }

    /**
     * January: a website sale by the seller with a promo code, an agent sale (Gold type) by the admin with an offer,
     * an unassigned sale and a cancelled one; December: one sale by the seller. Payments: $400 in January on the
     * website sale (seller), a $100 refund in January on the December sale (admin), $300 in December.
     */
    private function sales(): array
    {
        $seller = $this->user('seller@safercruise.test');
        $admin = $this->user('admin@safercruise.test');
        $gold = AgentTier::firstOrCreate(['name' => 'Gold']);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'name' => 'Mona Adel', 'company_name' => 'Blue Waves Travel', 'agent_tier_id' => $gold->id]);
        $promo = PromoCode::create(['code' => 'SAVE10', 'type' => 'fixed', 'value' => 100, 'label' => '$100 off']);
        $offer = Offer::create(['name' => 'Winter deal', 'target' => 'fare', 'type' => 'percent', 'value' => 5, 'ends_on' => '2030-03-31']);

        $s = [
            'web' => $this->sale('2030-01-05 10:00:00', ['seller_id' => $seller->id, 'promo_code_id' => $promo->id, 'discount_total' => 100, 'grand_total' => 1000, 'amount_paid' => 400]),
            'agent' => $this->sale('2030-01-20 12:00:00', [
                'seller_id' => $admin->id, 'agent_id' => $agent->id, 'agent_tier_id' => $gold->id,
                'fare_total' => 900, 'agent_discount_total' => 100, 'offer_discount_total' => 50, 'grand_total' => 950, 'amount_paid' => 950,
                'price_snapshot' => ['offers' => [['id' => $offer->id, 'name' => 'Winter deal', 'discount' => 50]]],
            ]),
            'unassigned' => $this->sale('2030-01-25 09:00:00'),
            'cancelled' => $this->sale('2030-01-10 09:00:00', ['seller_id' => $seller->id, 'status' => BookingStatus::Cancelled, 'grand_total' => 2000]),
            'december' => $this->sale('2029-12-15 12:00:00', ['seller_id' => $seller->id, 'grand_total' => 1000]),
        ];
        $this->pay($s['web'], 400, '2030-01-06 11:00:00', $seller);
        $this->pay($s['december'], 300, '2029-12-20 11:00:00', $seller);
        $this->pay($s['december'], 100, '2030-01-15 11:00:00', $admin, 'refund');

        return $s + compact('seller', 'admin', 'agent', 'promo', 'offer');
    }

    public function test_headline_and_staff_figures(): void
    {
        $s = $this->sales();

        $response = $this->actingAs($s['admin'], 'admin')->get(self::JANUARY)->assertOk();

        $c = $response->viewData('current');
        $this->assertSame(3, $c['bookings']);
        $this->assertEqualsWithDelta(3050, $c['revenue'], 0.001);
        // Payments by the day received: $400 in, $100 refunded (on a December sale)
        $this->assertSame(2, $c['payments']);
        $this->assertEqualsWithDelta(300, $c['net_received'], 0.001);
        $this->assertEqualsWithDelta(950, $c['agent_revenue'], 0.001);
        $this->assertEqualsWithDelta(10, $c['agent_discount_rate'], 0.001);
        $this->assertEqualsWithDelta(150, $c['discounts'], 0.001);
        $this->assertSame(2, $c['discounted']);
        $this->assertEqualsWithDelta(300, $response->viewData('previous')['received'], 0.001);

        $staff = collect($response->viewData('staff')['rows'])->keyBy('name');
        $seller = $staff[$s['seller']->name];
        $this->assertSame(1, $seller['bookings']);
        $this->assertEqualsWithDelta(1000, $seller['revenue'], 0.001);
        $this->assertEqualsWithDelta(600, $seller['outstanding'], 0.001);
        $this->assertEqualsWithDelta(40, $seller['collection_rate'], 0.001);
        $this->assertSame(1, $seller['cancelled']);
        $this->assertEqualsWithDelta(400, $seller['net_received'], 0.001);
        $this->assertEqualsWithDelta(1000, $seller['previous'], 0.001);

        $admin = $staff[$s['admin']->name];
        $this->assertEqualsWithDelta(950, $admin['revenue'], 0.001);
        $this->assertEqualsWithDelta(-100, $admin['net_received'], 0.001);
        $this->assertEqualsWithDelta(1100, $staff['Unassigned']['revenue'], 0.001);
        // The whole team is listed, best revenue first, unassigned last
        $this->assertSame(0, $staff[$this->user('supervisor@safercruise.test')->name]['bookings']);
        $this->assertSame('Unassigned', collect($response->viewData('staff')['rows'])->last()['name']);
        $this->assertEqualsWithDelta(3050, $response->viewData('staff')['total']['revenue'], 0.001);

        $response->assertSee('Staff performance')->assertSee('Blue Waves Travel')->assertSee('Winter deal')->assertSee('SAVE10');
    }

    public function test_agents_offers_and_promo_codes(): void
    {
        $s = $this->sales();

        $response = $this->actingAs($s['admin'], 'admin')->get(self::JANUARY)->assertOk();

        $tier = $response->viewData('tiers')->sole();
        $this->assertSame(['Gold', 1, 1], [$tier['name'], $tier['agents'], $tier['bookings']]);
        $this->assertEqualsWithDelta(1000, $tier['public_fare'], 0.001);
        $this->assertEqualsWithDelta(10, $tier['discount_rate'], 0.001);

        $agent = $response->viewData('agents')->sole();
        $this->assertSame(['Blue Waves Travel', 'Mona Adel', 'Gold'], [$agent['name'], $agent['contact'], $agent['tier']]);
        $this->assertEqualsWithDelta(950, $agent['revenue'], 0.001);
        $this->assertSame('2030-01-20', $agent['last_sale']->toDateString());

        $promo = $response->viewData('promos')->sole();
        $this->assertSame(['SAVE10', 1], [$promo['name'], $promo['bookings']]);
        $this->assertEqualsWithDelta(100, $promo['discount'], 0.001);
        $this->assertEqualsWithDelta(10, $promo['per_dollar'], 0.001);
        $this->assertEqualsWithDelta(100 / 1100 * 100, $promo['share'], 0.001);

        $offer = $response->viewData('offers')->sole();
        $this->assertSame(['Winter deal', 1], [$offer['name'], $offer['bookings']]);
        $this->assertEqualsWithDelta(50, $offer['discount'], 0.001);
        $this->assertEqualsWithDelta(19, $offer['per_dollar'], 0.001);

        // Website only: no agents
        $b2c = $this->get(self::JANUARY.'&channel=b2c')->assertOk();
        $this->assertTrue($b2c->viewData('agents')->isEmpty());
        $this->assertTrue($b2c->viewData('offers')->isEmpty());
        $b2c->assertSee('No agent bookings in this period.');
    }

    public function test_a_seller_only_sees_their_own_row(): void
    {
        $s = $this->sales();

        $this->actingAs($s['seller'], 'admin');
        $this->get('/admin/reports')->assertOk()->assertSee('Sales team &amp; channels', false);

        $response = $this->get(self::JANUARY.'&seller='.$s['admin']->id)->assertOk();
        // Their sale, and the refund the admin entered on their December booking
        $this->assertSame([$s['seller']->name, 'Recorded by others'], collect($response->viewData('staff')['rows'])->pluck('name')->all());
        $this->assertSame(1, $response->viewData('current')['bookings']);
        $this->assertTrue($response->viewData('agents')->isEmpty());
        $response->assertDontSee('Blue Waves Travel');
    }

    public function test_excel_export(): void
    {
        $s = $this->sales();

        $response = $this->actingAs($s['admin'], 'admin')->get(str_replace('/team-channels?', '/team-channels/export?', self::JANUARY));
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('team-channels-2030-01-01_2030-01-31.xlsx', $response->headers->get('content-disposition'));

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $workbook = $zip->getFromName('xl/workbook.xml');
        $sheets = [];
        for ($i = 1; $i <= 6; $i++) {
            $sheets[$i] = $zip->getFromName("xl/worksheets/sheet$i.xml");
            $this->assertNotFalse(simplexml_load_string($sheets[$i]), "sheet $i is valid XML");
        }
        $zip->close();
        @unlink($path);

        foreach (['Summary', 'Staff', 'Agent types', 'Agents', 'Promo codes', 'Offers'] as $name) {
            $this->assertStringContainsString('<sheet name="'.$name.'"', $workbook);
        }
        $this->assertStringContainsString('Sales team &amp; channels', $sheets[1]);
        $this->assertStringContainsString('1 Jan – 31 Jan 2030 · compared with 1 Dec – 31 Dec 2029', $sheets[1]);
        $this->assertStringContainsString($s['seller']->name, $sheets[2]);
        $this->assertStringContainsString('state="frozen"', $sheets[2]);
        $this->assertStringContainsString('Blue Waves Travel', $sheets[4]);
        $this->assertStringContainsString('SAVE10', $sheets[5]);
        $this->assertStringContainsString('Winter deal', $sheets[6]);
        $this->assertStringNotContainsString('<f>', implode('', $sheets));
    }
}
