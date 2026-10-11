<?php

namespace Database\Seeders;

use App\Enums\PromoType;
use App\Models\PromoCode;
use Illuminate\Database\Seeder;

/**
 * SC_DATA.promos.
 */
class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        PromoCode::updateOrCreate(['code' => 'SAFER10'], [
            'type' => PromoType::Percent,
            'value' => 10,
            'label' => '10% off cruise fare',
            'is_active' => true,
        ]);
    }
}
