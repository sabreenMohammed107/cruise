<?php

namespace Database\Seeders;

use App\Models\Ship;
use Illuminate\Database\Seeder;

/**
 * SC_DATA.cabins: the three bookable categories with their per-night rate card.
 */
class CabinCategorySeeder extends Seeder
{
    public function run(): void
    {
        $ship = Ship::where('slug', 'aroya')->firstOrFail();

        $cabins = [
            [
                'slug' => 'interior', 'badge' => 'Best value',
                'name_en' => 'Interior Cabin', 'name_ar' => 'كابينة داخلية',
                'short_description' => 'A quiet, well-planned cabin for guests who spend their days on deck.',
                'description' => "Interior cabins sit on the inside of the ship, so they stay dark and quiet for a good night's sleep. Twin beds convert to a queen, and a sofa bed sleeps a third guest.",
                'max_guests' => 3, 'size' => '14–17 m²', 'balcony_size' => null,
                'beds' => 'Twin beds convertible to a queen, plus sofa bed', 'bedroom' => null,
                'features' => ['Private bathroom with shower', 'Smart TV', 'In-room safe', 'Air conditioning', 'Daily housekeeping', 'Hairdryer'],
                'image_slots' => ['cabin-interior-1', 'cabin-interior-2'],
                'base_rate_adult' => 330, 'base_rate_child' => 170, 'base_rate_infant' => 45,
            ],
            [
                'slug' => 'balcony', 'badge' => null,
                'name_en' => 'Balcony Cabin', 'name_ar' => 'كابينة بلكونة',
                'short_description' => 'Your own private balcony for sunrise coffee over the Red Sea.',
                'description' => 'Floor-to-ceiling glass opens onto a private balcony with two chairs and a small table. Room for up to four guests with a queen bed and a sofa bed.',
                'max_guests' => 4, 'size' => '20 m²', 'balcony_size' => '5 m²',
                'beds' => 'Queen bed (or twins), plus double sofa bed', 'bedroom' => null,
                'features' => ['Private balcony', 'Sitting area', 'Smart TV', 'Mini fridge', 'In-room safe', 'Daily housekeeping'],
                'image_slots' => ['cabin-balcony-1', 'cabin-balcony-2', 'cabin-balcony-3'],
                'base_rate_adult' => 520, 'base_rate_child' => 240, 'base_rate_infant' => 45,
            ],
            [
                'slug' => 'suite', 'badge' => 'Most space',
                'name_en' => 'Superior Suite Khuzama', 'name_ar' => 'سوبيريور سويت خزامى',
                'short_description' => 'A separate bedroom, lounge and wide balcony with priority services.',
                'description' => 'The Khuzama suite has a separate bedroom and a lounge with a dining table, a full bathroom with bathtub and a wide private balcony. Suite guests get priority embarkation and a dedicated reception desk.',
                'max_guests' => 4, 'size' => '38 m²', 'balcony_size' => '10 m²',
                'beds' => 'King bed in bedroom, double sofa bed in lounge',
                'bedroom' => 'Separate bedroom with wardrobe; lounge with sofa bed and dining table for four.',
                'features' => ['Private balcony', 'Separate bedroom', 'Bathtub and rain shower', 'Priority embarkation', 'Suite reception', 'Coffee machine', 'Welcome fruit and drinks'],
                'image_slots' => ['cabin-suite-1', 'cabin-suite-2', 'cabin-suite-3'],
                'base_rate_adult' => 1150, 'base_rate_child' => 380, 'base_rate_infant' => 45,
            ],
        ];

        foreach ($cabins as $i => $cabin) {
            $ship->cabinCategories()->updateOrCreate(['slug' => $cabin['slug']], $cabin + ['sort_order' => $i + 1]);
        }
    }
}
