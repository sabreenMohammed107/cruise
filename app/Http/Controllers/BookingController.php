<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmBookingRequest;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Services\BookingService;
use App\Support\ScDataPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * booking.html (#guests → #review → Confirm) and confirmation.html.
 * The in-progress booking stays in the browser (sessionStorage, as in the template) until the
 * guest confirms; the server then re-validates everything and saves it.
 */
class BookingController extends Controller
{
    private const VIEWABLE = 'bookings.viewable';

    public function show(Request $request, ScDataPresenter $presenter): View
    {
        return view('booking.show', ['scData' => $presenter->make($request->user('web'))]);
    }

    /**
     * Checks a discount code. Codes are not published in SC_DATA, so booking.js asks here.
     */
    public function promo(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:40']]);
        $promo = PromoCode::findRedeemable($request->input('code'));

        if (! $promo) {
            return response()->json(['message' => "That code isn't valid for this sailing."], 422);
        }

        return response()->json([
            'code' => $promo->code,
            'type' => $promo->type->value,
            'value' => (float) $promo->value,
            'label' => $promo->tr('label'),
        ]);
    }

    public function confirm(ConfirmBookingRequest $request, BookingService $bookings): JsonResponse
    {
        $booking = $bookings->confirm(
            price: $request->price,
            guests: $request->guests(),
            travellers: $request->input('travellers'),
            contact: [
                'email' => $request->input('contact.email'),
                'dial' => $request->input('contact.dial'),
                'phone' => $request->normalisedPhone(),
            ],
            promo: $request->promo,
            user: $request->user('web'),
        );

        // Lets this browser open the confirmation page without signing in.
        $request->session()->push(self::VIEWABLE, $booking->reference);

        return response()->json([
            'reference' => $booking->reference,
            'redirect' => route('booking.confirmation', $booking),
        ], 201);
    }

    public function confirmation(Request $request, Booking $booking): View
    {
        abort_unless($this->canView($request, $booking), 404);

        return view('booking.confirmation', [
            'booking' => $booking->load(['sailing.cruise.ship', 'cabinCategory', 'travellers']),
        ]);
    }

    /**
     * Link for emails: works in any browser for 60 days.
     */
    public static function signedConfirmationUrl(Booking $booking): string
    {
        return URL::temporarySignedRoute('booking.confirmation', now()->addDays(60), $booking);
    }

    private function canView(Request $request, Booking $booking): bool
    {
        $user = $request->user('web');

        return in_array($booking->reference, $request->session()->get(self::VIEWABLE, []), true)
            || $request->hasValidSignature()
            || ($user && ($user->id === $booking->user_id || $user->isAdmin()))
            // Staff signed in to the panel (guard "admin") opening the guest voucher from a booking
            || (bool) Auth::guard('admin')->user()?->can('view-booking', $booking);
    }
}
