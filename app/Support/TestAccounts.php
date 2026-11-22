<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\User;
use App\Services\Assistant\Assistant;

/**
 * The super admin's test accounts: the agents chosen in Admin → Assistant chats (Assistant::testers()). Everything
 * about them (the accounts, their bookings, the figures those bookings add up to) is for the super admin only, and
 * their bookings' team email goes to config assistant.test_email instead of the site's team address.
 *
 * Their own pages on the website (their bookings, the chat) work as for any account.
 */
final class TestAccounts
{
    /** @return list<int> */
    public static function ids(): array
    {
        return Assistant::testers();
    }

    /** @return list<int> the accounts this panel user must not see: none for the super admin */
    public static function hiddenFrom(?User $viewer): array
    {
        return $viewer?->isSuperAdmin() ? [] : self::ids();
    }

    /** The booking was made by, or for, a test account. */
    public static function owns(Booking $booking): bool
    {
        $ids = self::ids();
        $in = fn ($id) => $id !== null && in_array((int) $id, $ids, true);

        return $in($booking->user_id) || $in($booking->agent_id);
    }

    /** 404 for a panel user who isn't the super admin, as if the booking didn't exist. */
    public static function guard(Booking $booking, ?User $viewer): void
    {
        abort_if(! $viewer?->isSuperAdmin() && self::owns($booking), 404);
    }

    /** Where the "new booking" email for the team goes. */
    public static function teamEmail(Booking $booking): ?string
    {
        return self::owns($booking) ? config('assistant.test_email') : config('cruise.team_email');
    }
}
