<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Mail\BookingCancelled;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Bookings held without payment (bookings.hold_until, config cruise.hold_hours): when the hold is over and still
 * nothing is paid, the booking is cancelled (its cabins go back on sale), a line is added to its admin notes, and
 * the guest gets an email saying so (App\Mail\BookingCancelled). Run every 5 minutes by bookings:cancel-unpaid.
 *
 * Any payment recorded before the hold is over keeps the booking (part payments too): only bookings with nothing
 * paid are cancelled. Bookings from before the hold have no hold_until and are never cancelled this way.
 */
class UnpaidBookings
{
    /** @return list<string> the references cancelled */
    public function cancelExpired(): array
    {
        $cancelled = [];
        foreach (Booking::query()->holdExpired()->pluck('id') as $id) {
            $booking = DB::transaction(function () use ($id) {
                // Again under a lock: a payment may have been recorded since the list was read
                $booking = Booking::query()->holdExpired()->lockForUpdate()->find($id);
                if (! $booking) {
                    return null;
                }
                $note = '['.now()->format('j M Y, H:i').'] Cancelled automatically: nothing paid within '
                    .$booking->confirmed_at?->diffInHours($booking->hold_until).' hours of booking. The guest was emailed.';
                $booking->forceFill([
                    'status' => BookingStatus::Cancelled,
                    'cancelled_at' => now(),
                    'admin_notes' => trim(($booking->admin_notes ? $booking->admin_notes."\n" : '').$note),
                ])->save();
                $booking->syncPaymentTotals();

                return $booking;
            });
            if (! $booking) {
                continue;
            }
            $cancelled[] = $booking->reference;

            // The booking stays cancelled whatever happens to the email; a failure is reported
            try {
                Mail::to($booking->contact_email)->send(new BookingCancelled($booking));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $cancelled;
    }
}
