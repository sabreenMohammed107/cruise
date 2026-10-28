<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmed;
use App\Mail\NewBookingForTeam;
use App\Models\Booking;
use App\Models\BookingCabin;
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
    public function __construct(private PricingService $pricing, private CurrencyService $currency) {}

    /**
     * A booking of one or more cabins on the same sailing (step 15).
     *
     * @param  list<array{price: SailingCabinPrice, guests: array{adult: int, child: int, infant: int}, travellers: array<string, array>}>  $cabins
     *         travellers of each cabin keyed "adult-1", "child-2", … within that cabin
     * @param  array{email: string, dial: string, phone: string}  $contact
     */
    public function confirm(array $cabins, array $contact, ?PromoCode $promo = null, ?User $user = null): Booking
    {
        $booking = DB::transaction(function () use ($cabins, $contact, $promo, $user) {
            // Lock the sailing × cabin rows so two guests can't take the last cabin at the same time.
            $ids = array_values(array_unique(array_map(fn ($c) => $c['price']->id, $cabins)));
            $locked = SailingCabinPrice::with(['sailing.cruise', 'cabinCategory'])->lockForUpdate()->findMany($ids)->keyBy('id');
            $cabins = array_map(fn ($c) => ['price' => $locked[$c['price']->id]] + $c, array_values($cabins));

            // The same cabin type may be booked more than once in one go
            foreach (array_count_values(array_map(fn ($c) => $c['price']->id, $cabins)) as $priceId => $wanted) {
                $this->ensureAvailable($locked[$priceId], $wanted);
            }

            $quote = $this->pricing->quoteBooking($cabins, $promo);
            $first = $cabins[0]['price'];
            $sum = fn (string $band) => array_sum(array_map(fn ($c) => $c['guests'][$band], $cabins));

            $booking = Booking::create([
                'reference' => Booking::generateReference($first->sailing->departure_date),
                'user_id' => $user?->id,
                'sailing_id' => $first->sailing_id,
                // first cabin and guest totals, for lists and exports
                'cabin_category_id' => $first->cabin_category_id,
                'promo_code_id' => $promo?->id,
                'status' => BookingStatus::Confirmed,
                'payment_status' => PaymentStatus::Unpaid,
                'adults' => $sum('adult'),
                'children' => $sum('child'),
                'infants' => $sum('infant'),
                'contact_email' => strtolower($contact['email']),
                'contact_dial' => $contact['dial'],
                'contact_phone' => $contact['phone'],
                'fare_total' => $quote['fare'],
                'fees_total' => $quote['fees'],
                'discount_total' => $quote['discount'],
                'grand_total' => $quote['total'],
                'currency' => config('cruise.currency'),
                // What the guest saw (header USD / EGP switch), at today's rate: the voucher and guest email keep it
                'display_currency' => $this->currency->active(),
                'exchange_rate' => $this->currency->rate(),
                // Website language at booking time (sc_lang cookie): the booking emails are written in it
                'locale' => app()->getLocale(),
                'price_snapshot' => $quote,
                'confirmed_at' => now(),
            ]);

            // Traveller slots run on across cabins (adult-1, adult-2 in cabin 1, adult-3 in cabin 2, …)
            $next = ['adult' => 0, 'child' => 0, 'infant' => 0];
            foreach ($cabins as $i => $cabin) {
                $part = $quote['cabins'][$i];
                $row = $booking->cabins()->create([
                    'position' => $i + 1,
                    'cabin_category_id' => $cabin['price']->cabin_category_id,
                    'adults' => $cabin['guests']['adult'],
                    'children' => $cabin['guests']['child'],
                    'infants' => $cabin['guests']['infant'],
                    'fare_total' => $part['fare'],
                    'fees_total' => $part['fees'],
                    'price_snapshot' => $part,
                ]);

                foreach ($this->inSlotOrder($cabin['travellers']) as $key => $t) {
                    [$band] = explode('-', $key);
                    $booking->travellers()->create([
                        'booking_cabin_id' => $row->id,
                        'band' => $band,
                        'slot' => ++$next[$band],
                        'is_primary' => $i === 0 && $key === 'adult-1',
                        'first_name' => trim($t['first']),
                        'last_name' => trim($t['last']),
                        'date_of_birth' => $t['dob'],
                        'gender' => strtolower($t['gender']),
                        'nationality' => $t['nationality'],
                    ]);
                }
            }

            $promo?->increment('used_count');

            return $booking;
        });

        $this->sendEmails($booking);

        return $booking;
    }

    /** adult-1, adult-2, child-1, infant-1 … whatever order the browser sent them in. */
    private function inSlotOrder(array $travellers): array
    {
        $order = ['adult' => 1, 'child' => 2, 'infant' => 3];
        uksort($travellers, function ($a, $b) use ($order) {
            [$ba, $na] = explode('-', $a);
            [$bb, $nb] = explode('-', $b);

            return [$order[$ba] ?? 9, (int) $na] <=> [$order[$bb] ?? 9, (int) $nb];
        });

        return $travellers;
    }

    /**
     * Sold-out flag and, when the admin set one, the number of cabins left for this sailing:
     * cabins of this type already booked (confirmed) + the ones wanted now must fit.
     */
    private function ensureAvailable(SailingCabinPrice $price, int $wanted = 1): void
    {
        $taken = $price->capacity === null ? 0 : BookingCabin::where('cabin_category_id', $price->cabin_category_id)
            ->whereHas('booking', fn ($q) => $q->where('sailing_id', $price->sailing_id)->where('status', BookingStatus::Confirmed))
            ->count();

        if ($price->is_sold_out || ($price->capacity !== null && $taken + $wanted > $price->capacity)) {
            throw ValidationException::withMessages([
                'cabins' => __('site.booking.errors.cabin_just_sold_out'),
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
