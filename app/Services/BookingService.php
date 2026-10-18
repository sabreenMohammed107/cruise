<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmed;
use App\Mail\NewBookingForTeam;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\SailingCabinPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates bookings. There is no online payment (template v2): a booking is confirmed
 * as soon as the guest submits it, and the team arranges payment offline.
 */
class BookingService
{
    public function __construct(private PricingService $pricing) {}

    /**
     * @param  array{adult: int, child: int, infant: int}  $guests
     * @param  array<string, array{first: string, last: string, dob: string, gender: string, nationality: string}>  $travellers  keyed "adult-1", "child-2", …
     * @param  array{email: string, dial: string, phone: string}  $contact
     */
    public function confirm(
        SailingCabinPrice $price,
        array $guests,
        array $travellers,
        array $contact,
        ?PromoCode $promo = null,
        ?User $user = null,
    ): Booking {
        $booking = DB::transaction(function () use ($price, $guests, $travellers, $contact, $promo, $user) {
            // Lock the sailing × cabin row so two guests can't take the last cabin at the same time.
            $price = SailingCabinPrice::with('sailing.cruise')->lockForUpdate()->findOrFail($price->id);
            $this->ensureAvailable($price);

            $quote = $this->pricing->quote($price, $guests, $promo);

            $booking = Booking::create([
                'reference' => Booking::generateReference($price->sailing->departure_date),
                'user_id' => $user?->id,
                'sailing_id' => $price->sailing_id,
                'cabin_category_id' => $price->cabin_category_id,
                'promo_code_id' => $promo?->id,
                'status' => BookingStatus::Confirmed,
                'payment_status' => PaymentStatus::Unpaid,
                'adults' => $guests['adult'],
                'children' => $guests['child'],
                'infants' => $guests['infant'],
                'contact_email' => strtolower($contact['email']),
                'contact_dial' => $contact['dial'],
                'contact_phone' => $contact['phone'],
                'fare_total' => $quote['fare'],
                'fees_total' => $quote['fees'],
                'discount_total' => $quote['discount'],
                'grand_total' => $quote['total'],
                'currency' => config('cruise.currency'),
                'price_snapshot' => $quote,
                'confirmed_at' => now(),
            ]);

            foreach ($travellers as $key => $t) {
                [$band, $slot] = explode('-', $key);
                $booking->travellers()->create([
                    'band' => $band,
                    'slot' => (int) $slot,
                    'is_primary' => $key === 'adult-1',
                    'first_name' => trim($t['first']),
                    'last_name' => trim($t['last']),
                    'date_of_birth' => $t['dob'],
                    'gender' => strtolower($t['gender']),
                    'nationality' => $t['nationality'],
                ]);
            }

            $promo?->increment('used_count');

            return $booking;
        });

        $this->sendEmails($booking);

        return $booking;
    }

    /**
     * Sold-out flag and, when the admin set one, the number of cabins left for this sailing.
     */
    private function ensureAvailable(SailingCabinPrice $price): void
    {
        $taken = $price->capacity === null ? 0 : Booking::where('sailing_id', $price->sailing_id)
            ->where('cabin_category_id', $price->cabin_category_id)
            ->where('status', BookingStatus::Confirmed)
            ->count();

        if ($price->is_sold_out || ($price->capacity !== null && $taken >= $price->capacity)) {
            throw ValidationException::withMessages([
                'cabin' => __('site.booking.errors.cabin_just_sold_out'),
            ]);
        }
    }

    /**
     * The booking is saved either way; a mail failure is reported, not shown to the guest.
     */
    private function sendEmails(Booking $booking): void
    {
        try {
            Mail::to($booking->contact_email)->send(new BookingConfirmed($booking));
            if ($team = config('cruise.team_email')) {
                Mail::to($team)->send(new NewBookingForTeam($booking));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
