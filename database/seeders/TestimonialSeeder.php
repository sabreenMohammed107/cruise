<?php

namespace Database\Seeders;

use App\Models\Cruise;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * SC_DATA.testimonials, linked to cruises by name.
 */
class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $cruiseIds = Cruise::pluck('id', 'name');

        $testimonials = [
            ['Reem A.', 'Riyadh', 'Red Sea Discovery', 5, 'Booking took ten minutes and the kids club kept our two busy all week. The Luxor day from Safaga was the highlight.'],
            ['Mahmoud K.', 'Cairo', 'Egypt & Jordan Voyage', 5, 'Clear prices for each of us, no surprises at check-in. Petra from Aqaba was worth the early start.'],
            ['Sara & Omar', 'Jeddah', 'Saudi Coast Weekend', 4, 'A perfect long weekend without flights. We upgraded to a balcony cabin and would do it again.'],
        ];

        foreach ($testimonials as $i => [$name, $from, $cruise, $rating, $text]) {
            Testimonial::updateOrCreate(['name' => $name], [
                'from_city' => $from, 'cruise_id' => $cruiseIds[$cruise] ?? null, 'rating' => $rating,
                'text' => $text, 'is_published' => true, 'sort_order' => $i + 1,
            ]);
        }
    }
}
