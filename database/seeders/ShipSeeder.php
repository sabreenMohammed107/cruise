<?php

namespace Database\Seeders;

use App\Models\Ship;
use Illuminate\Database\Seeder;

/**
 * SC_DATA.ships[0] (specs, story, zones, essentials, FAQs) and SC_DATA.facilities.
 * All ship facts are SAMPLE values from the template.
 */
class ShipSeeder extends Seeder
{
    public function run(): void
    {
        $ship = Ship::updateOrCreate(['slug' => 'aroya'], [
            'name' => 'AROYA',
            'tagline' => "Saudi Arabia's first cruise ship",
            'flag' => 'Saudi Arabia',
            'home_port' => 'Jeddah',
            'description' => "Saudi Arabia's first cruise ship, built for families in the region: halal dining, prayer areas, and separate pool decks.",
            'story' => [
                'AROYA is the first ship of Aroya Cruises, the Saudi cruise line launched to bring ocean holidays to families across the region. She sails from Jeddah and Yanbu on short breaks and week-long voyages around the Red Sea.',
                'Everything on board is designed for Arab guests: all food is halal, there are prayer rooms on several decks, family and adults-only pool areas are separate, and entertainment runs in Arabic and English.',
            ],
            'image_slot' => 'exterior',
            'hero_image_slot' => 'hero',
        ]);

        $specs = [
            ['tonnage', '151,300', 'GT', 'Gross tonnage'],
            ['cabins', '1,678', null, 'Cabins'],
            ['length', '335', 'm', 'Length'],
            ['speed', '23.8', 'knots', 'Top speed'],
            ['passengers', '3,352', null, 'Guest capacity'],
            ['crew', '2,000', null, 'Crew members'],
            ['decks', '18', null, 'Decks'],
        ];
        foreach ($specs as $i => [$key, $value, $unit, $label]) {
            $ship->specs()->updateOrCreate(['key' => $key], [
                'value' => $value, 'unit' => $unit, 'label' => $label, 'sort_order' => $i + 1,
            ]);
        }

        $zones = [
            ['waves', 'Pools & water park', 'pool', 'Family pools, a separate adults-only pool deck, and an aqua park with slides and a splash zone for younger children.'],
            ['sun', 'Sun decks', 'sundeck', 'Wide open decks with loungers, shaded cabanas and bars serving fresh juices and Saudi coffee.'],
            ['utensils', 'Dining with a view', 'sunset', 'Arabic, Asian, Italian and grill restaurants, all halal. Breakfast, lunch and dinner in the main restaurants are included in your fare.'],
            ['music', 'Evenings on board', 'night', 'Nightly shows in the main theatre, live music lounges and family performances in Arabic and English.'],
        ];
        foreach ($zones as $i => [$icon, $title, $slot, $text]) {
            $ship->zones()->updateOrCreate(['title' => $title], [
                'icon' => $icon, 'image_slot' => $slot, 'text' => $text, 'sort_order' => $i + 1,
            ]);
        }

        $essentials = [
            ['utensils', 'Halal dining', 'All food served on board is halal.'],
            ['home', 'Prayer rooms', 'Prayer rooms on several decks, with prayer times announced daily.'],
            ['smile', 'Family first', 'Kids clubs by age group and family cabins for up to four guests.'],
            ['card', 'Cashless on board', 'Link a card at check-in and pay with your cruise card.'],
            ['globe', 'Wi-Fi packages', 'Internet packages can be bought on board.'],
            ['pulse', 'Medical centre', 'Doctors and nurses on call 24 hours a day.'],
        ];
        foreach ($essentials as $i => [$icon, $title, $text]) {
            $ship->essentials()->updateOrCreate(['title' => $title], [
                'icon' => $icon, 'text' => $text, 'sort_order' => $i + 1,
            ]);
        }

        $faqs = [
            ['What should I bring to check-in?', 'A passport valid for at least six months, or a Saudi national ID for domestic-only sailings, plus your booking reference.'],
            ['Is there a dress code?', 'Smart-casual in the evenings. Modest swimwear is welcome in all pool areas.'],
            ['Can I bring my own food or drinks?', 'Sealed baby food and medical items are allowed. Other food and drinks are provided on board.'],
            ['Is the ship accessible?', 'Yes. Accessible cabins and lifts serve all public decks. Tell us about any needs when you book.'],
        ];
        foreach ($faqs as $i => [$question, $answer]) {
            $ship->faqs()->updateOrCreate(['question' => $question], [
                'answer' => $answer, 'sort_order' => $i + 1,
            ]);
        }

        $facilities = [
            ['utensils', '35+', 'Restaurants', 'Arabic, Asian, Italian and grill venues, all serving halal food.'],
            ['coffee', '20+', 'Cafés and lounges', 'Specialty coffee, tea lounges and dessert bars.'],
            ['music', '1,000', 'Theatre seats', 'Nightly shows, live music and family performances.'],
            ['smile', '1,200 m²', 'Kids zone', 'Supervised clubs by age group, from 3 to 17 years.'],
            ['droplet', '6', 'Water slides', 'An aqua park with splash zones for younger children.'],
            ['waves', '4', 'Pools', 'Family pools plus a separate adults-only pool deck.'],
            ['flower', '2,000 m²', 'Spa & wellness', 'Treatment rooms, hammam, sauna and a full gym.'],
            ['bag', '25', 'Shops', 'Duty-free fashion, perfume, jewellery and gifts.'],
            ['crown', 'VIP', 'Private areas', 'Suite-only lounges, restaurant and sun deck.'],
        ];
        foreach ($facilities as $i => [$icon, $value, $label, $description]) {
            $ship->facilities()->updateOrCreate(['label' => $label], [
                'icon' => $icon, 'value' => $value, 'description' => $description, 'sort_order' => $i + 1,
            ]);
        }
    }
}
