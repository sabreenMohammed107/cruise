<?php

namespace App\Support;

/**
 * Mobile numbers as the website's phone widget sends them: the country code ("+966") apart from the number.
 * Same rules as phoneError() in booking.js; used by the booking, "Contact us" and "Become an agent" forms.
 */
final class PhoneNumber
{
    /** @return list<string> every code of the phone widget (config/dial_codes.php) */
    public static function dialCodes(): array
    {
        return array_column(config('cruise.dial_codes'), 0);
    }

    /** The number as the template stores it: no spaces, dashes, brackets or leading zeros. */
    public static function normalise(?string $raw): string
    {
        return ltrim(preg_replace('/[\s\-()]/', '', (string) $raw), '0');
    }

    /** The visitor-language message for a number that doesn't fit its country, or null. */
    public static function error(?string $dial, ?string $raw): ?string
    {
        $n = self::normalise($raw);

        $key = match (true) {
            $n === '' => 'phone_required',
            ! ctype_digit($n) => 'phone_digits',
            $dial === '+966' && ! preg_match('/^5\d{8}$/', $n) => 'phone_sa',
            $dial === '+20' && ! preg_match('/^1\d{9}$/', $n) => 'phone_eg',
            strlen($n) < 7 || strlen($n) > 12 => 'phone_length',
            default => null,
        };

        return $key ? __("site.booking.errors.$key") : null;
    }
}
