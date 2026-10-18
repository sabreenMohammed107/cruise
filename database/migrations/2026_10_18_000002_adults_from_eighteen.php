<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Age bands (PROJECT_STEPS.md step 14): adults are 18 and over, children 2 to 17. Infants unchanged.
 *
 *   adult   min_months 144 → 216                 (12+ → 18+)
 *   child   max_months_exclusive 144 → 216       (2–11 → 2–17)
 *
 * Data only (the columns already fit). Hints are replaced only where they still have the old seeded
 * text, so wording an admin wrote is kept. Existing bookings are not touched: their travellers keep
 * the band they were booked in.
 */
return new class extends Migration
{
    private const HINTS = [
        // [band key, column, old seeded text, new text]
        ['adult', 'hint', '12 years and over', '18 years and over'],
        ['adult', 'hint_ar', '12 سنة فأكثر', '18 سنة فأكثر'],
        ['child', 'hint', '2 to 11 years', '2 to 17 years'],
        ['child', 'hint_ar', 'من 2 إلى 11 سنة', 'من 2 إلى 17 سنة'],
    ];

    public function up(): void
    {
        DB::table('age_bands')->where('key', 'adult')->update(['min_months' => 216]);
        DB::table('age_bands')->where('key', 'child')->update(['max_months_exclusive' => 216]);

        foreach (self::HINTS as [$key, $column, $old, $new]) {
            DB::table('age_bands')->where('key', $key)->where($column, $old)->update([$column => $new]);
        }
    }

    public function down(): void
    {
        DB::table('age_bands')->where('key', 'adult')->update(['min_months' => 144]);
        DB::table('age_bands')->where('key', 'child')->update(['max_months_exclusive' => 144]);

        foreach (self::HINTS as [$key, $column, $old, $new]) {
            DB::table('age_bands')->where('key', $key)->where($column, $new)->update([$column => $old]);
        }
    }
};
