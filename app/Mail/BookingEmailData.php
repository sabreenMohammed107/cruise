<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\BookingCabin;
use App\Models\BookingTraveller;
use App\Support\Bi;

/**
 * The booking as both booking emails show it (resources/views/mail/partials/booking-details.blade.php),
 * in the current language: the Mailable has already switched to the guest's language (bookings.locale).
 */
final class BookingEmailData
{
    public const RELATIONS = ['sailing.cruise.ship', 'sailing.cruise.ports', 'cabinCategory', 'cabins.cabinCategory', 'cabins.travellers', 'travellers', 'promoCode'];

    public static function money(float|string|null $amount): string
    {
        return config('cruise.currency_symbol').number_format((float) $amount);
    }

    /**
     * A sailing code as guests read it. Codes made of the date and a number ("03112026-4" = 3 Nov 2026, sailing 4)
     * show as the date in the current language ("3 November 2026"); other codes ("RSD-270214") as they are.
     */
    public static function sailingLabel(string $code): string
    {
        if (preg_match('/^(\d{2})(\d{2})(\d{4})(?:-\d+)?$/', $code, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return \Illuminate\Support\Carbon::create((int) $m[3], (int) $m[2], (int) $m[1])->translatedFormat('j F Y');
        }

        return $code;
    }

    /** "3 adults + 1 child" in the current language. */
    public static function guests(array $counts): string
    {
        return collect($counts)->filter()
            ->map(fn ($n, $band) => trans_choice("site.guests.$band", $n, ['count' => $n]))
            ->join(__('site.guests.join'));
    }

    public static function make(Booking $booking): array
    {
        $sailing = $booking->sailing;
        $cruise = $sailing->cruise;
        $date = fn ($d) => $d->translatedFormat('l j F Y');
        $comma = Bi::isArabic() ? '، ' : ', ';
        $route = $cruise->ports->isNotEmpty()
            ? $cruise->ports->map->label()
            : collect($cruise->route ?? [])->map(fn ($p) => Bi::port($p));

        return [
            'reference' => $booking->reference,
            'cruise' => $cruise->tr('name'),
            'trip' => [
                __('emails.ship') => $cruise->ship->tr('name'),
                __('emails.sailing_code') => static::sailingLabel($sailing->code),
                __('emails.departs') => $date($sailing->departure_date).$comma.Bi::port($cruise->departure_port),
                __('emails.returns') => $date($sailing->return_date).$comma.Bi::port($cruise->arrival_port),
                __('emails.duration') => trans_choice('site.nights', $cruise->nights, ['count' => $cruise->nights]),
                __('emails.route') => $route->join(Bi::isArabic() ? ' ← ' : ' → '),
            ],
            'cabins' => static::cabins($booking),
            'prices' => array_filter([
                __('emails.fare') => static::money($booking->fare_total),
                __('emails.fees') => static::money($booking->fees_total),
                ($booking->promoCode ? __('emails.discount_code', ['code' => $booking->promoCode->code]) : __('emails.discount'))
                    => (float) $booking->discount_total > 0 ? '−'.static::money($booking->discount_total) : null,
            ]),
            'total' => static::money($booking->grand_total),
            'paid' => static::money($booking->amount_paid),
            'due' => static::money($booking->amountDue()),
            'guests' => static::guests($booking->guestCounts()),
            'contact' => ['email' => $booking->contact_email, 'phone' => trim($booking->contact_dial.' '.$booking->contact_phone)],
        ];
    }

    /**
     * Each cabin with its guests, its own fare and its travellers. Bookings saved before step 15 (no cabin rows)
     * show as one cabin holding every traveller.
     *
     * @return list<array{title: string, guests: string, fare: ?string, travellers: list<array>}>
     */
    private static function cabins(Booking $booking): array
    {
        $traveller = fn (BookingTraveller $t) => [
            'name' => trim("{$t->first_name} {$t->last_name}"),
            'band' => __("site.bands.{$t->band->value}.label"),
            'nationality' => Bi::value('nationalities', $t->nationality),
            'dob' => $t->date_of_birth?->translatedFormat('j M Y'),
            'gender' => Bi::value('genders', ucfirst($t->gender->value)),
            'primary' => (bool) $t->is_primary,
        ];

        if ($booking->cabins->isEmpty()) {
            $cabin = $booking->cabinCategory;

            return [[
                'title' => Bi::isArabic() ? ($cabin->name_ar ?: $cabin->name_en) : $cabin->name_en,
                'guests' => static::guests($booking->guestCounts()),
                'fare' => null,
                'travellers' => $booking->travellers->map($traveller)->all(),
            ]];
        }

        $several = $booking->cabins->count() > 1;

        return $booking->cabins->map(fn (BookingCabin $c) => [
            'title' => ($several ? __('emails.cabin_n', ['n' => $c->position]).' · ' : '').$c->name(),
            'guests' => static::guests($c->guestCounts()),
            'fare' => static::money((float) $c->fare_total + (float) $c->fees_total),
            'travellers' => $c->travellers->map($traveller)->all(),
        ])->all();
    }
}
