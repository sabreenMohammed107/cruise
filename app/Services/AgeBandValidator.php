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
        // Messages in the visitor's language: lang/{en,ar}/site.php → booking.errors (same words as SC.checkBand)
        if (! $dob) {
            return __('site.booking.errors.dob_required');
        }

        $birth = Carbon::parse($dob)->startOfDay();
        if ($birth->isFuture()) {
            return __('site.booking.errors.dob_future');
        }

        $rule = AgeBand::where('key', $band->value)->firstOrFail();
        $months = $this->monthsAt($birth, $departure);
        $max = $rule->max_months_exclusive;

        if ($months >= $rule->min_months && ($max === null || $months < $max)) {
            return null;
        }

        return match ($band) {
            TravellerBand::Adult => __('site.booking.errors.dob_adult', ['min' => intdiv($rule->min_months, 12)]),
            TravellerBand::Child => __('site.booking.errors.dob_child', ['min' => intdiv($rule->min_months, 12), 'max' => intdiv($max, 12) - 1]),
            // At least min_months (6) and under max_months_exclusive (24) whole months on the departure date
            TravellerBand::Infant => trans_choice('site.booking.errors.dob_infant', intdiv($max, 12), ['min' => $rule->min_months, 'max' => intdiv($max, 12)]),
        };
    }
}
