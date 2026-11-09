<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A guest who books without signing in gets a customer account made from the booking (BookingService::confirm()),
 * so they can sign in later and see their trips. The sign-in details go out in the booking email
 * (App\Mail\BookingConfirmed). Switch off with CRUISE_GUEST_ACCOUNTS=false.
 *
 * An email that already has an account (customer, agent, staff) is left alone: the booking is NOT added to that
 * account (D10: the address typed at checkout isn't verified, so a stranger could otherwise put bookings into someone
 * else's account). Only addresses with no account get one.
 */
class GuestAccounts
{
    /**
     * A new customer account for the booking's contact email, linked to the booking.
     *
     * @return ?array{0: User, 1: string} the account and its temporary password, or null when the email already has an account
     */
    public function createFor(Booking $booking): ?array
    {
        $email = strtolower(trim($booking->contact_email));
        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            return null;
        }

        // The contact is cabin 1's first adult (step 15)
        $guest = $booking->loadMissing('travellers')->primaryTraveller();
        $password = self::temporaryPassword();

        try {
            $user = DB::transaction(function () use ($booking, $email, $guest, $password) {
                // role is not mass assignable: the database default ("customer") applies, as on /register
                $user = User::create([
                    'first_name' => $guest?->first_name ?? Str::before($email, '@'),
                    'last_name' => $guest?->last_name,
                    'email' => $email,
                    'phone_dial' => $booking->contact_dial,
                    'phone' => $booking->contact_phone,
                    'date_of_birth' => $guest?->date_of_birth,
                    'nationality' => $guest?->nationality,
                    // Hashed by the model's "hashed" cast
                    'password' => $password,
                    // Same defaults as an account made on /register
                    'notification_prefs' => ['offers' => false, 'whatsapp' => true, 'reminders' => true],
                ]);

                $booking->forceFill(['user_id' => $user->id])->save();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Another booking with this email made the account a moment ago: same as "already has an account"
            return null;
        }

        $booking->setRelation('user', $user);

        return [$user, $password];
    }

    /** 12 letters and digits (at least one of each): passes the account password rules and is easy to type. Same as agents. */
    public static function temporaryPassword(): string
    {
        return Str::password(12, symbols: false);
    }
}
