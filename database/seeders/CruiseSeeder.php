<?php

namespace Database\Seeders;

use App\Enums\SailingStatus;
use App\Models\Cruise;
use App\Models\Port;
use App\Models\Sailing;
use App\Models\Ship;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * SC_DATA.cruises: cruises, day-by-day itineraries and sailings (departures).
 * Images are stock photo names (see StockPhotos). Each sailing gets a price row per cabin category with the
 * cruise's tiered prices ([single, double, 3rd guest] for the whole cabin and cruise; infants are free);
 * `soldOut` in the template becomes is_sold_out on that row.
 */
class CruiseSeeder extends Seeder
{
    public function run(): void
    {
        $ship = Ship::where('slug', 'aroya')->firstOrFail();
        $cabinIds = $ship->cabinCategories()->pluck('id', 'slug');

        foreach ($this->cruises() as $data) {
            $cruise = Cruise::updateOrCreate(['slug' => $data['slug']], [
                'ship_id' => $ship->id,
                'name' => $data['name'],
                'name_ar' => $data['name_ar'],
                'nights' => $data['nights'],
                'departure_port' => $data['departure_port'],
                'arrival_port' => $data['arrival_port'],
                'route' => $data['route'],
                'tags' => $data['tags'],
                'visa_free' => $data['visa_free'],
                'rating' => $data['rating'],
                'reviews_count' => $data['reviews_count'],
                'popularity' => $data['popularity'],
                'image' => StockPhotos::url($data['image']),
                'gallery' => StockPhotos::urls($data['gallery']),
                'summary' => $data['summary'],
                'is_active' => true,
            ]);

            // Route stops from the route ports (PortSeeder), in order
            $cruise->syncRoute(Port::idsFor($data['route']));

            foreach ($data['itinerary'] as $i => [$port, $time, $text]) {
                $cruise->itinerary()->updateOrCreate(['day_number' => $i + 1], [
                    'port' => $port, 'time_label' => $time, 'description' => $text,
                ]);
            }

            foreach ($data['sailings'] as [$code, $departure, $soldOut]) {
                $sailing = Sailing::updateOrCreate(['code' => $code], [
                    'cruise_id' => $cruise->id,
                    'departure_date' => $departure,
                    'return_date' => Carbon::parse($departure)->addDays($cruise->nights),
                    'status' => SailingStatus::Open,
                ]);

                foreach ($data['prices'] as $slug => [$single, $double, $extra]) {
                    $sailing->cabinPrices()->updateOrCreate(['cabin_category_id' => $cabinIds[$slug]], [
                        'price_single' => $single,
                        'price_double' => $double,
                        'price_extra' => $extra,
                        'is_sold_out' => in_array($slug, $soldOut, true),
                    ]);
                }
            }
        }
    }

    private function cruises(): array
    {
        return [
            [
                'slug' => 'red-sea-discovery', 'name' => 'Red Sea Discovery', 'name_ar' => 'اكتشاف البحر الأحمر',
                'nights' => 7, 'departure_port' => 'Jeddah', 'arrival_port' => 'Jeddah',
                'route' => ['Jeddah', 'Safaga', 'Sokhna', 'Al Wajh', 'Yanbu', 'Jeddah'],
                'tags' => ['family', 'excursions'], 'visa_free' => false, 'rating' => 4.8, 'reviews_count' => 412, 'popularity' => 96,
                'image' => 'hero', 'gallery' => ['hero', 'pool', 'dest-sokhna', 'sundeck', 'sunset'],
                // Cabin prices for the whole cruise: [single, double, 3rd guest]; infants free
                'prices' => ['interior' => [3465, 4620, 1190], 'balcony' => [5460, 7280, 1680], 'suite' => [12075, 16100, 2660]],
                'summary' => 'A full week across both shores of the Red Sea, with day trips to Luxor and Cairo from the Egyptian ports and two Saudi coastal stops on the way home.',
                'itinerary' => [
                    ['Jeddah', 'Departs 20:00', 'Check in at Jeddah Islamic Port from 14:00. Settle into your cabin and join the sail-away on the pool deck.'],
                    ['At sea', 'Full day', 'Spa, water park, kids club and the evening show in the main theatre.'],
                    ['Safaga, Egypt', '07:00 – 19:00', "Optional excursion to Luxor's Karnak Temple and the Valley of the Kings, or snorkel the house reef."],
                    ['Sokhna, Egypt', '06:00 – 21:00', 'Gateway to Cairo. Optional full-day tour to the Giza Pyramids and the Grand Egyptian Museum.'],
                    ['Al Wajh, Saudi Arabia', '10:00 – 18:00', 'Turquoise lagoons and quiet beaches on the northern Saudi coast.'],
                    ['Yanbu, Saudi Arabia', '08:00 – 17:00', "Walk Yanbu's historic old town or spend the day at a beach club."],
                    ['At sea', 'Full day', "Captain's farewell dinner and the closing night show."],
                    ['Jeddah', 'Arrives 07:00', 'Disembarkation from 08:00, by deck in a scheduled order.'],
                ],
                'sailings' => [
                    ['RSD-261115', '2026-11-15', []],
                    ['RSD-261213', '2026-12-13', []],
                    ['RSD-270117', '2027-01-17', ['suite']],
                    ['RSD-270214', '2027-02-14', []],
                ],
            ],
            [
                'slug' => 'saudi-coast-weekend', 'name' => 'Saudi Coast Weekend', 'name_ar' => 'عطلة الساحل السعودي',
                'nights' => 3, 'departure_port' => 'Jeddah', 'arrival_port' => 'Jeddah',
                'route' => ['Jeddah', 'Yanbu', 'Jeddah'],
                'tags' => ['weekend', 'family'], 'visa_free' => true, 'rating' => 4.6, 'reviews_count' => 288, 'popularity' => 92,
                'image' => 'aerial', 'gallery' => ['aerial', 'sundeck', 'lounge', 'exterior'],
                'prices' => ['interior' => [1485, 1980, 510], 'balcony' => [2340, 3120, 720], 'suite' => [5175, 6900, 1140]],
                'summary' => 'A short Thursday-to-Sunday escape with one port day in Yanbu. No visa needed for Saudi residents and GCC citizens.',
                'itinerary' => [
                    ['Jeddah', 'Departs 20:00', 'Embark from 14:00 and welcome dinner on board.'],
                    ['Yanbu, Saudi Arabia', '09:00 – 18:00', 'Beach day, diving or a walk through the old town.'],
                    ['At sea', 'Full day', 'Pools, water park and a family show in the evening.'],
                    ['Jeddah', 'Arrives 07:00', 'Disembarkation from 08:00.'],
                ],
                'sailings' => [
                    ['SCW-261029', '2026-10-29', ['suite']],
                    ['SCW-261105', '2026-11-05', []],
                    ['SCW-261126', '2026-11-26', []],
                    ['SCW-261203', '2026-12-03', []],
                    ['SCW-270107', '2027-01-07', []],
                ],
            ],
            [
                'slug' => 'egypt-jordan-voyage', 'name' => 'Egypt & Jordan Voyage', 'name_ar' => 'رحلة مصر والأردن',
                'nights' => 5, 'departure_port' => 'Jeddah', 'arrival_port' => 'Jeddah',
                'route' => ['Jeddah', 'Aqaba', 'Safaga', 'Jeddah'],
                'tags' => ['excursions'], 'visa_free' => false, 'rating' => 4.7, 'reviews_count' => 196, 'popularity' => 84,
                'image' => 'dest-aqaba', 'gallery' => ['dest-aqaba', 'dest-sokhna', 'sunset', 'pool'],
                'prices' => ['interior' => [2675, 3565, 920], 'balcony' => [4210, 5615, 1295], 'suite' => [9315, 12420, 2050]],
                'summary' => 'Petra and Wadi Rum from Aqaba, then Luxor or the reefs from Safaga. Two countries in five nights.',
                'itinerary' => [
                    ['Jeddah', 'Departs 20:00', 'Embark from 14:00.'],
                    ['At sea', 'Full day', 'Relax on board as the ship heads north through the Red Sea.'],
                    ['Aqaba, Jordan', '07:00 – 20:00', 'Optional excursion to Petra or a jeep tour in Wadi Rum.'],
                    ['Safaga, Egypt', '08:00 – 19:00', 'Optional Luxor day tour or a glass-bottom boat over the reef.'],
                    ['At sea', 'Full day', 'Farewell gala dinner.'],
                    ['Jeddah', 'Arrives 07:00', 'Disembarkation from 08:00.'],
                ],
                'sailings' => [
                    ['EJV-261120', '2026-11-20', []],
                    ['EJV-261218', '2026-12-18', []],
                    ['EJV-270129', '2027-01-29', []],
                ],
            ],
            [
                'slug' => 'aroya-repositioning', 'name' => 'AROYA – Repositioning', 'name_ar' => 'أرويا – رحلة إعادة التموضع',
                'nights' => 5, 'departure_port' => 'Jeddah', 'arrival_port' => 'Sokhna',
                'route' => ['Jeddah', 'Safaga', 'Sokhna'],
                'tags' => ['excursions'], 'visa_free' => false, 'rating' => 4.5, 'reviews_count' => 74, 'popularity' => 61,
                'image' => 'exterior', 'gallery' => ['exterior', 'night', 'dest-sokhna'],
                'prices' => ['interior' => [2030, 2705, 695], 'balcony' => [3200, 4265, 985], 'suite' => [7075, 9430, 1560]],
                'summary' => 'A one-way sailing as AROYA moves north for the season. Lower fares and quieter decks; ends in Sokhna, 90 minutes from Cairo.',
                'itinerary' => [
                    ['Jeddah', 'Departs 19:00', 'Embark from 13:00.'],
                    ['At sea', 'Full day', 'Sea day with full entertainment programme.'],
                    ['Safaga, Egypt', '08:00 – 18:00', 'Optional Luxor excursion.'],
                    ['At sea', 'Full day', 'Cruise the Gulf of Suez.'],
                    ['At sea', 'Full day', 'Farewell evening on deck.'],
                    ['Sokhna, Egypt', 'Arrives 07:00', 'Disembarkation from 08:00. Transfers to Cairo can be arranged.'],
                ],
                'sailings' => [
                    ['ARP-270404', '2027-04-04', []],
                ],
            ],
            [
                'slug' => 'yanbu-winter-escape', 'name' => 'Winter Escape from Yanbu', 'name_ar' => 'هروب الشتاء من ينبع',
                'nights' => 4, 'departure_port' => 'Yanbu', 'arrival_port' => 'Yanbu',
                'route' => ['Yanbu', 'Al Wajh', 'Jeddah', 'Yanbu'],
                'tags' => ['family'], 'visa_free' => true, 'rating' => 4.6, 'reviews_count' => 131, 'popularity' => 74,
                'image' => 'dest-yanbu', 'gallery' => ['dest-yanbu', 'dest-al-wajh', 'pool', 'lounge'],
                'prices' => ['interior' => [1880, 2510, 645], 'balcony' => [2965, 3950, 910], 'suite' => [6555, 8740, 1445]],
                'summary' => "Start from Yanbu instead of Jeddah, with a lagoon day in Al Wajh and an evening in Jeddah's Al-Balad.",
                'itinerary' => [
                    ['Yanbu', 'Departs 19:00', 'Embark from 13:00.'],
                    ['Al Wajh, Saudi Arabia', '09:00 – 18:00', 'Lagoon swimming and beach clubs.'],
                    ['At sea', 'Full day', 'Spa day and family entertainment.'],
                    ['Jeddah, Saudi Arabia', '12:00 – 23:00', 'Evening in historic Al-Balad.'],
                    ['Yanbu', 'Arrives 08:00', 'Disembarkation from 09:00.'],
                ],
                'sailings' => [
                    ['YWE-261210', '2026-12-10', []],
                    ['YWE-270121', '2027-01-21', []],
                    ['YWE-270225', '2027-02-25', []],
                ],
            ],
            [
                'slug' => 'new-year-at-sea', 'name' => 'New Year at Sea', 'name_ar' => 'رأس السنة في البحر',
                'nights' => 4, 'departure_port' => 'Jeddah', 'arrival_port' => 'Jeddah',
                'route' => ['Jeddah', 'Yanbu', 'Al Wajh', 'Jeddah'],
                'tags' => ['festive', 'family'], 'visa_free' => true, 'rating' => 4.9, 'reviews_count' => 158, 'popularity' => 99,
                'image' => 'night', 'gallery' => ['night', 'sunset', 'pool', 'sundeck'],
                'prices' => ['interior' => [2675, 3565, 920], 'balcony' => [4210, 5615, 1295], 'suite' => [9315, 12420, 2050]],
                'summary' => 'Count down to 2027 on the open deck, with a gala dinner, fireworks and a late-night show.',
                'itinerary' => [
                    ['Jeddah', 'Departs 20:00', 'Embark from 14:00.'],
                    ['Yanbu, Saudi Arabia', '09:00 – 18:00', 'Beach day ashore.'],
                    ['At sea', 'Full day', "New Year's Eve gala dinner and midnight fireworks."],
                    ['Al Wajh, Saudi Arabia', '11:00 – 18:00', 'A slow start to the new year in the lagoons.'],
                    ['Jeddah', 'Arrives 07:00', 'Disembarkation from 08:00.'],
                ],
                'sailings' => [
                    ['NYS-261229', '2026-12-29', ['balcony']],
                ],
            ],
        ];
    }
}
