<?php

namespace Database\Seeders;

use App\Models\AgeBand;
use Illuminate\Database\Seeder;

/**
 * SC_DATA.ageBands + SC_DATA.fees.perGuestPerNight.
 */
class AgeBandSeeder extends Seeder
{
    public function run(): void
    {
        $bands = [
            // 12 years and over
            ['key' => 'adult', 'label' => 'Adult', 'label_ar' => 'بالغ', 'hint' => '12 years and over', 'min_months' => 144, 'max_months_exclusive' => null, 'fee_per_night' => 35],
            // 2 to 11 years (24 ≤ months < 144)
            ['key' => 'child', 'label' => 'Child', 'label_ar' => 'طفل', 'hint' => '2 to 11 years', 'min_months' => 24, 'max_months_exclusive' => 144, 'fee_per_night' => 35],
            // 6 months to under 2 years
            ['key' => 'infant', 'label' => 'Infant', 'label_ar' => 'رضيع', 'hint' => '6 months to under 2 years', 'min_months' => 6, 'max_months_exclusive' => 24, 'fee_per_night' => 0],
        ];

        foreach ($bands as $order => $band) {
            AgeBand::updateOrCreate(['key' => $band['key']], $band + ['sort_order' => $order + 1]);
        }
    }
}
