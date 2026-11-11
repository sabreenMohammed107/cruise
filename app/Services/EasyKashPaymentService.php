<?php

namespace App\Services;

use App\Enums\OnlinePaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Exceptions\EasyKashException;
use App\Models\Booking;
use App\Models\OnlinePayment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * EasyKash Direct Pay.
 *
 * 1. initiate(): saves a pending OnlinePayment and asks EasyKash for a checkout page; the guest is sent there.
 * 2. EasyKash calls the webhook (server to server) with the result, signed with HMAC-SHA512 (verifySignature()).
 * 3. handleWebhook(): a signed "PAID" for the right amount adds a Payment (USD) and updates the booking's totals,
 *    exactly as a payment recorded by the team. Repeated webhooks are ignored.
 *
 * Bookings are kept in USD; EasyKash charges EGP, converted at the site's current rate when the checkout opens.
 */
class EasyKashPaymentService
{
    /** Webhook fields covered by the signature, in the order EasyKash concatenates them. */
    private const SIGNED_FIELDS = ['ProductCode', 'Amount', 'ProductType', 'PaymentMethod', 'status', 'easykashRef', 'customerReference'];

    public function __construct(private CurrencyService $currency) {}

    public function enabled(): bool
    {
        return filled(config('services.easykash.api_key')) && filled(config('services.easykash.hmac_secret'));
    }

    /**
     * A checkout for what the booking still owes. Reuses a recent pending one for the same amount, so a guest who
     * clicks twice or comes back gets the same page.
     *
     * @throws EasyKashException
     */
    public function initiate(Booking $booking): OnlinePayment
    {
        if (! $this->enabled()) {
            throw new EasyKashException('EasyKash is not configured.');
        }

        $usd = round($booking->amountDue(), 2);

        $recent = $booking->onlinePayments()
            ->where('status', OnlinePaymentStatus::Pending)
            ->where('amount_usd', $usd)
            ->whereNotNull('checkout_url')
            ->where('created_at', '>=', now()->subMinutes((int) config('services.easykash.reuse_minutes')))
            ->first();

        if ($recent) {
            return $recent;
        }

        $currency = config('services.easykash.currency');
        $rate = $this->currency->rate($currency);

        $attempt = $booking->onlinePayments()->create([
            'gateway' => 'easykash',
            'customer_reference' => OnlinePayment::generateReference(),
            'status' => OnlinePaymentStatus::Pending,
            'amount' => round($usd * $rate, 2),
            'currency' => $currency,
            'amount_usd' => $usd,
            'exchange_rate' => $rate,
        ]);

        $traveller = $booking->primaryTraveller();

        $payload = [
            'amount' => (float) $attempt->amount,
            'currency' => $currency,
            'paymentOptions' => config('services.easykash.payment_options'),
            'cashExpiry' => (int) config('services.easykash.cash_expiry_hours'),
            'name' => trim(($traveller?->first_name ?? '').' '.($traveller?->last_name ?? '')) ?: $booking->contact_email,
            'email' => $booking->contact_email,
            'mobile' => preg_replace('/[^\d+]/', '', $booking->contact_dial.$booking->contact_phone),
            'redirectUrl' => route('payment.easykash.callback', $attempt->customer_reference),
            'customerReference' => $attempt->customer_reference,
        ];

        try {
            $response = Http::baseUrl(config('services.easykash.base_url'))
                ->withHeaders(['authorization' => config('services.easykash.api_key')])
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('services.easykash.timeout'))
                ->post('/api/directpayv1/pay', $payload);
        } catch (ConnectionException $e) {
            $this->fail($attempt, ['error' => $e->getMessage()]);

            throw new EasyKashException('EasyKash is unreachable.', previous: $e);
        }

        $url = $response->json('redirectUrl');

        if ($response->failed() || ! is_string($url) || $url === '') {
            $this->fail($attempt, ['http_status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

            throw new EasyKashException('EasyKash refused the payment request (HTTP '.$response->status().').');
        }

        $attempt->update(['checkout_url' => $url, 'gateway_response' => ['initiate' => $response->json()]]);

        Log::info('EasyKash checkout opened', ['booking' => $booking->reference, 'customer_reference' => $attempt->customer_reference, 'amount' => $attempt->amount]);

        return $attempt;
    }

    /** The webhook really comes from EasyKash: HMAC-SHA512 of the signed fields with our secret. */
    public function verifySignature(array $data): bool
    {
        $secret = (string) config('services.easykash.hmac_secret');
        $signature = $data['signatureHash'] ?? null;

        if ($secret === '' || ! is_string($signature) || $signature === '') {
            return false;
        }

        $signed = implode('', array_map(fn (string $field) => (string) ($data[$field] ?? ''), self::SIGNED_FIELDS));

        return hash_equals(hash_hmac('sha512', $signed, $secret), strtolower($signature));
    }

    /**
     * Applies a verified webhook. Safe to call more than once for the same payment.
     * Returns null when the reference is unknown.
     */
    public function handleWebhook(array $data): ?OnlinePayment
    {
        return DB::transaction(function () use ($data) {
            $attempt = OnlinePayment::where('customer_reference', (string) ($data['customerReference'] ?? ''))
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                Log::warning('EasyKash webhook for an unknown reference', ['data' => $data]);

                return null;
            }

            $responses = $attempt->gateway_response ?? [];
            $responses['webhooks'][] = $data;
            $attempt->fill(['gateway_response' => $responses, 'gateway_reference' => $data['easykashRef'] ?? $attempt->gateway_reference]);

            // Already recorded: just keep the log of what arrived
            if ($attempt->status === OnlinePaymentStatus::Paid) {
                $attempt->save();

                return $attempt;
            }

            $status = strtoupper((string) ($data['status'] ?? ''));

            if ($status === 'PAID') {
                $this->markPaid($attempt, $data);
            } else {
                $attempt->status = match ($status) {
                    'EXPIRED' => OnlinePaymentStatus::Expired,
                    'FAILED', 'CANCELED', 'CANCELLED', 'REFUSED' => OnlinePaymentStatus::Failed,
                    default => $attempt->status, // NEW / PENDING: still waiting (e.g. an unpaid cash voucher)
                };
                $attempt->save();
            }

            Log::info('EasyKash webhook', ['customer_reference' => $attempt->customer_reference, 'status' => $status, 'result' => $attempt->status->value]);

            return $attempt;
        });
    }

    private function markPaid(OnlinePayment $attempt, array $data): void
    {
        if (abs((float) ($data['Amount'] ?? 0) - (float) $attempt->amount) >= 0.01) {
            $attempt->status = OnlinePaymentStatus::AmountMismatch;
            $attempt->save();

            Log::error('EasyKash paid amount does not match the checkout', [
                'customer_reference' => $attempt->customer_reference, 'expected' => $attempt->amount, 'received' => $data['Amount'] ?? null,
            ]);

            return;
        }

        $booking = $attempt->booking;

        $payment = $booking->payments()->create([
            'type' => PaymentType::Payment,
            'method' => PaymentMethod::EasyKash,
            'amount' => $attempt->amount_usd,
            'currency' => 'USD',
            'reference' => $data['easykashRef'] ?? $attempt->customer_reference,
            'notes' => sprintf('Online: %s %s at %s (%s)', $attempt->currency, number_format((float) $attempt->amount, 2),
                number_format((float) $attempt->exchange_rate, 4), $data['PaymentMethod'] ?? 'EasyKash'),
            'received_at' => now(),
        ]);

        $attempt->fill(['status' => OnlinePaymentStatus::Paid, 'payment_id' => $payment->id, 'paid_at' => now()])->save();

        $booking->syncPaymentTotals();
    }

    private function fail(OnlinePayment $attempt, array $response): void
    {
        $attempt->update(['status' => OnlinePaymentStatus::Failed, 'gateway_response' => ['initiate' => $response]]);

        Log::error('EasyKash checkout failed', ['customer_reference' => $attempt->customer_reference, 'response' => $response]);
    }
}
