<?php

namespace App\Http\Controllers;

use App\Exceptions\EasyKashException;
use App\Http\Requests\EasyKashWebhookRequest;
use App\Http\Requests\InitiatePaymentRequest;
use App\Models\Booking;
use App\Models\OnlinePayment;
use App\Services\EasyKashPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Online payment of a booking through EasyKash (see EasyKashPaymentService).
 */
class PaymentController extends Controller
{
    public function __construct(private EasyKashPaymentService $easyKash) {}

    /** "Pay online": to the EasyKash checkout page. */
    public function initiate(InitiatePaymentRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $attempt = $this->easyKash->initiate($booking);
        } catch (EasyKashException) {
            return back()->withErrors(['payment' => __('site.payment.errors.gateway')]);
        }

        return redirect()->away($attempt->checkout_url);
    }

    /**
     * Where EasyKash sends the guest's browser back. Only shows the result: the booking is marked paid by the
     * webhook, never from this address (anyone can open it).
     */
    public function callback(OnlinePayment $onlinePayment): RedirectResponse
    {
        return redirect()
            ->route('booking.confirmation', $onlinePayment->booking)
            ->with('payment_status', $onlinePayment->status->value);
    }

    /** EasyKash → us, server to server. Signature checked by the request. */
    public function webhook(EasyKashWebhookRequest $request): JsonResponse
    {
        $attempt = $this->easyKash->handleWebhook($request->all());

        return response()->json(['received' => true, 'status' => $attempt?->status->value ?? 'ignored']);
    }
}
