<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

/**
 * SC_DATA.destinations: the homepage slider and destinations.html.
 * `name` must match the port names used in cruise routes, which is how "Cruises that call here" works.
 */
class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = [
            [
                'slug' => 'jeddah', 'name' => 'Jeddah', 'name_ar' => 'جدة', 'country' => 'Saudi Arabia', 'image_slot' => 'dest-jeddah',
                'blurb' => 'Historic Al-Balad and the Corniche.',
                'description' => "AROYA's home port and the start of most sailings. Arrive a day early to walk the coral-stone houses of Al-Balad, a UNESCO World Heritage Site, and watch the King Fahd Fountain light up the Corniche at night.",
                'highlights' => ['Al-Balad historic district', 'Jeddah Corniche and King Fahd Fountain', 'Red Sea Mall and waterfront dining'],
                'visa_note' => 'Home port. Saudi residents and GCC citizens sail with a national ID.',
            ],
            [
                'slug' => 'yanbu', 'name' => 'Yanbu', 'name_ar' => 'ينبع', 'country' => 'Saudi Arabia', 'image_slot' => 'dest-yanbu',
                'blurb' => 'Old town lanes and reef diving.',
                'description' => 'A quiet coastal town with a restored old quarter and some of the clearest water on the Saudi coast. Most guests split the day between the souq and a beach club.',
                'highlights' => ['Yanbu Old Town and souq', 'Snorkelling and diving trips', 'Beach clubs on the Yanbu Corniche'],
                'visa_note' => 'Domestic port. No visa needed for Saudi residents and GCC citizens.',
            ],
            [
                'slug' => 'al-wajh', 'name' => 'Al Wajh', 'name_ar' => 'الوجه', 'country' => 'Saudi Arabia', 'image_slot' => 'dest-al-wajh',
                'blurb' => 'Turquoise lagoons and empty beaches.',
                'description' => 'Gateway to the northern Red Sea coast, with shallow turquoise lagoons, sandbanks and small islands. A slow, beach-first port day.',
                'highlights' => ['Lagoon swimming and sandbank trips', 'Boat tours to nearby islands', 'Al Wajh old town'],
                'visa_note' => 'Domestic port. No visa needed for Saudi residents and GCC citizens.',
            ],
            [
                'slug' => 'safaga', 'name' => 'Safaga', 'name_ar' => 'سفاجا', 'country' => 'Egypt', 'image_slot' => 'dest-safaga',
                'blurb' => 'Gateway to Luxor and the Valley of the Kings.',
                'description' => 'Safaga is the closest port to Luxor. Take the full-day excursion to Karnak Temple and the Valley of the Kings, or stay by the sea for some of the best reefs in the Red Sea.',
                'highlights' => ['Luxor: Karnak Temple and Valley of the Kings', 'Glass-bottom boat and snorkelling trips', 'Soma Bay beaches'],
                'visa_note' => 'Egypt visa rules apply. Many nationalities can get a visa on arrival; check before you book.',
            ],
            [
                'slug' => 'sokhna', 'name' => 'Sokhna', 'name_ar' => 'السخنة', 'country' => 'Egypt', 'image_slot' => 'dest-sokhna',
                'blurb' => 'Gateway to Cairo and the Pyramids.',
                'description' => 'Ain Sokhna sits about 90 minutes from Cairo. The full-day tour takes you to the Giza Pyramids, the Sphinx and the Grand Egyptian Museum.',
                'highlights' => ['Giza Pyramids and the Sphinx', 'Grand Egyptian Museum', 'Galala mountain viewpoints'],
                'visa_note' => 'Egypt visa rules apply. Many nationalities can get a visa on arrival; check before you book.',
            ],
            [
                'slug' => 'aqaba', 'name' => 'Aqaba', 'name_ar' => 'العقبة', 'country' => 'Jordan', 'image_slot' => 'dest-aqaba',
                'blurb' => 'Gateway to Petra and Wadi Rum.',
                'description' => "Jordan's only port, two hours from Petra. Choose the Petra excursion to see the Treasury and the Monastery, or a jeep tour through the red desert of Wadi Rum.",
                'highlights' => ['Petra: the Treasury and the Siq', 'Wadi Rum jeep safari', 'Aqaba Marine Park'],
                'visa_note' => 'Jordan visa rules apply. The Jordan Pass covers the visa and Petra entry for many nationalities.',
            ],
        ];

        foreach ($destinations as $i => $destination) {
            Destination::updateOrCreate(['slug' => $destination['slug']], $destination + [
                'sort_order' => $i + 1, 'is_active' => true,
            ]);
        }
    }
}
