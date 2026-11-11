<?php

namespace Tests\Feature;

use App\Enums\OnlinePaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\OnlinePayment;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Online payment through EasyKash: checkout for the amount due (USD → EGP at the site's rate), and the signed
 * webhook that records the payment on the booking.
 */
class EasyKashPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const SECRET = 'test-hmac-secret';

    private const CHECKOUT = 'https://www.easykash.net/DirectPayV1/abc123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Mail::fake();
        // Manual rate, so no exchange-rate API call: USD × 50 = EGP
        SiteSetting::create(['key' => 'currency_mode', 'value' => 'manual']);
        SiteSetting::create(['key' => 'manual_exchange_rate', 'value' => '50']);
        config([
            'services.easykash.api_key' => 'test-api-key',
            'services.easykash.hmac_secret' => self::SECRET,
            'services.easykash.base_url' => 'https://easykash.test',
        ]);
        // What EasyKash answers; a test can replace it (a later Http::fake would not take precedence)
        $this->gateway = fn () => Http::response(['redirectUrl' => self::CHECKOUT]);
        Http::fake(['easykash.test/*' => fn () => ($this->gateway)()]);
    }

    private \Closure $gateway;

    private function book(): Booking
    {
        $ref = $this->postJson('/booking/confirm', [
            'sailing' => 'RSD-261213',
            'cabins' => [
                ['cabin' => 'balcony', 'guests' => ['adult' => 1, 'child' => 0, 'infant' => 0], 'travellers' => [
                    'adult-1' => ['first' => 'Noura', 'last' => 'Alqahtani', 'dob' => '1991-03-02', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
                ]],
            ],
            'contact' => ['email' => 'noura@example.com', 'dial' => '+20', 'phone' => '100 123 4567'],
            'agreed' => true,
        ])->assertCreated()->json('reference');

        return Booking::where('reference', $ref)->firstOrFail();
    }

    private function pay(Booking $booking): OnlinePayment
    {
        $this->post("/booking/{$booking->reference}/pay")->assertRedirect(self::CHECKOUT);

        return $booking->onlinePayments()->firstOrFail();
    }

    private function webhook(OnlinePayment $attempt, array $overrides = [], ?string $secret = self::SECRET)
    {
        $data = array_merge([
            'ProductCode' => 'EDV4471',
            'Amount' => (string) $attempt->amount,
            'ProductType' => 'Direct Pay',
            'PaymentMethod' => 'Credit & Debit Card',
            'BuyerName' => 'Noura Alqahtani',
            'BuyerEmail' => 'noura@example.com',
            'BuyerMobile' => '+201001234567',
            'status' => 'PAID',
            'easykashRef' => '2911105009',
            'customerReference' => $attempt->customer_reference,
            'Timestamp' => '1700000000',
        ], $overrides);

        $signed = implode('', array_map(fn ($f) => $data[$f], ['ProductCode', 'Amount', 'ProductType', 'PaymentMethod', 'status', 'easykashRef', 'customerReference']));
        $data['signatureHash'] = hash_hmac('sha512', $signed, $secret);

        return $this->postJson('/payment/easykash/webhook', $data);
    }

    public function test_pay_now_opens_an_easykash_checkout_for_the_amount_due_in_egp(): void
    {
        $booking = $this->book();
        $this->get("/booking/{$booking->reference}/confirmation")->assertSee(route('payment.initiate', $booking));

        $attempt = $this->pay($booking);

        $this->assertSame(OnlinePaymentStatus::Pending, $attempt->status);
        $this->assertEquals($booking->amountDue(), (float) $attempt->amount_usd);
        $this->assertEquals(round($booking->amountDue() * 50, 2), (float) $attempt->amount);
        $this->assertSame('EGP', $attempt->currency);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://easykash.test/api/directpayv1/pay'
            && $r->hasHeader('authorization', 'test-api-key')
            && $r['amount'] == $attempt->amount
            && $r['currency'] === 'EGP'
            && $r['customerReference'] === $attempt->customer_reference
            && $r['email'] === 'noura@example.com'
            && $r['redirectUrl'] === route('payment.easykash.callback', $attempt->customer_reference));
    }

    public function test_paying_twice_reuses_the_open_checkout(): void
    {
        $booking = $this->book();
        $this->pay($booking);
        $this->pay($booking);

        $this->assertSame(1, $booking->onlinePayments()->count());
        Http::assertSentCount(1);
    }

    public function test_a_stranger_cannot_pay_for_someone_elses_booking(): void
    {
        $booking = $this->book();
        $this->flushSession();

        $this->post("/booking/{$booking->reference}/pay")->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_a_gateway_error_returns_to_the_booking_with_a_message(): void
    {
        $this->gateway = fn () => Http::response(['message' => 'Invalid token'], 401);
        $booking = $this->book();

        $this->from("/booking/{$booking->reference}/confirmation")
            ->post("/booking/{$booking->reference}/pay")
            ->assertRedirect("/booking/{$booking->reference}/confirmation")
            ->assertSessionHasErrors('payment');

        $this->assertSame(OnlinePaymentStatus::Failed, $booking->onlinePayments()->first()->status);
    }

    public function test_pay_online_is_hidden_and_refused_when_not_configured(): void
    {
        config(['services.easykash.api_key' => null]);
        $booking = $this->book();

        $this->get("/booking/{$booking->reference}/confirmation")->assertDontSee(route('payment.initiate', $booking));
        $this->post("/booking/{$booking->reference}/pay")->assertSessionHasErrors('payment');
        Http::assertNothingSent();
    }

    public function test_a_signed_paid_webhook_records_the_payment_and_marks_the_booking_paid(): void
    {
        $booking = $this->book();
        $attempt = $this->pay($booking);

        $this->webhook($attempt)->assertOk()->assertJsonPath('status', 'paid');

        $booking->refresh();
        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
        $this->assertEquals((float) $booking->grand_total, (float) $booking->amount_paid);
        $this->assertNotNull($booking->paid_at);

        $payment = $booking->payments()->sole();
        $this->assertSame(PaymentMethod::EasyKash, $payment->method);
        $this->assertSame('2911105009', $payment->reference);
        $this->assertEquals((float) $attempt->amount_usd, (float) $payment->amount);

        $attempt->refresh();
        $this->assertSame(OnlinePaymentStatus::Paid, $attempt->status);
        $this->assertSame($payment->id, $attempt->payment_id);
        $this->assertSame('PAID', $attempt->gateway_response['webhooks'][0]['status']);

        // Guest's browser comes back: the voucher says so, and no longer offers to pay
        $this->get(route('payment.easykash.callback', $attempt->customer_reference))
            ->assertRedirect(route('booking.confirmation', $booking));
        $this->get("/booking/{$booking->reference}/confirmation")
            ->assertSee(__('site.payment.status.paid'))
            ->assertDontSee(route('payment.initiate', $booking));
    }

    public function test_a_repeated_webhook_records_the_payment_once(): void
    {
        $booking = $this->book();
        $attempt = $this->pay($booking);

        $this->webhook($attempt)->assertOk();
        $this->webhook($attempt)->assertOk();

        $this->assertSame(1, $booking->payments()->count());
        $this->assertCount(2, $attempt->fresh()->gateway_response['webhooks']);
    }

    public function test_a_webhook_with_a_wrong_signature_is_rejected(): void
    {
        $booking = $this->book();
        $attempt = $this->pay($booking);

        $this->webhook($attempt, secret: 'not-the-secret')->assertForbidden();

        $this->assertSame(0, $booking->payments()->count());
        $this->assertSame(OnlinePaymentStatus::Pending, $attempt->fresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $booking->fresh()->payment_status);
    }

    public function test_a_tampered_amount_breaks_the_signature(): void
    {
        $booking = $this->book();
        $attempt = $this->pay($booking);

        // Signed for the real amount, then the amount is changed in transit
        $signed = implode('', ['EDV4471', (string) $attempt->amount, 'Direct Pay', 'Card', 'PAID', 'X1', $attempt->customer_reference]);
        $this->postJson('/payment/easykash/webhook', [
            'ProductCode' => 'EDV4471', 'Amount' => '1.00', 'ProductType' => 'Direct Pay', 'PaymentMethod' => 'Card',
            'status' => 'PAID', 'easykashRef' => 'X1', 'customerReference' => $attempt->customer_reference,
            'signatureHash' => hash_hmac('sha512', $signed, self::SECRET),
        ])->assertForbidden();

        $this->assertSame(0, $booking->payments()->count());
    }

    public function test_a_signed_payment_for_another_amount_is_held_for_review(): void
    {
        $booking = $this->book();
        $attempt = $this->pay($booking);

        $this->webhook($attempt, ['Amount' => '10.00'])->assertOk()->assertJsonPath('status', 'amount_mismatch');

        $this->assertSame(0, $booking->payments()->count());
        $this->assertSame(PaymentStatus::Unpaid, $booking->fresh()->payment_status);
    }

    public function test_a_failed_payment_is_recorded_and_can_be_retried(): void
    {
        $booking = $this->book();
        $attempt = $this->pay($booking);

        $this->webhook($attempt, ['status' => 'FAILED'])->assertOk()->assertJsonPath('status', 'failed');
        $this->assertSame(0, $booking->payments()->count());

        // A new checkout, not the failed one
        $this->post("/booking/{$booking->reference}/pay")->assertRedirect(self::CHECKOUT);
        $this->assertSame(2, $booking->onlinePayments()->count());
    }

    public function test_the_webhook_needs_no_csrf_token_but_ignores_unknown_references(): void
    {
        $fake = new OnlinePayment(['customer_reference' => '999', 'amount' => 100]);

        $this->webhook($fake)->assertOk()->assertJsonPath('status', 'ignored');
    }
}
