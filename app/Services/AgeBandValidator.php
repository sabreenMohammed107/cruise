<?php

namespace App\Services;

use App\Enums\TravellerBand;
use App\Models\AgeBand;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Server-side port of SC.ageAt / SC.checkBand (core.js).
 * Ages are counted on the departure date, not today.
 */
class AgeBandValidator
{
    /**
     * Whole months between date of birth and the reference date.
     */
    public function monthsAt(CarbonInterface $dob, CarbonInterface $ref): int
    {
        $months = ($ref->year - $dob->year) * 12 + ($ref->month - $dob->month);

        if ($ref->day < $dob->day) {
            $months--;
        }

        return $months;
    }

    /**
     * Null when the date of birth fits the band, otherwise a message explaining the fix.
     */
    public function check(TravellerBand $band, ?string $dob, CarbonInterface $departure): ?string
    {
        if (! $dob) {
            return 'Enter a date of birth.';
        }

        $birth = Carbon::parse($dob)->startOfDay();
        if ($birth->isFuture()) {
            return "Date of birth can't be in the future.";
        }

        $rule = AgeBand::where('key', $band->value)->firstOrFail();
        $months = $this->monthsAt($birth, $departure);
        $max = $rule->max_months_exclusive;

        if ($months >= $rule->min_months && ($max === null || $months < $max)) {
            return null;
        }

        return match ($band) {
            TravellerBand::Adult => sprintf(
                'Adults must be %d or older on the departure date. Change the date or go back and add this guest as a child.',
                intdiv($rule->min_months, 12),
            ),
            TravellerBand::Child => sprintf(
                'Children must be %d–%d years old on the departure date.',
                intdiv($rule->min_months, 12),
                intdiv($max, 12) - 1,
            ),
            TravellerBand::Infant => sprintf(
                'Infants must be at least %d months and under %d years old on the departure date.',
                $rule->min_months,
                intdiv($max, 12),
            ),
        };
    }
}
