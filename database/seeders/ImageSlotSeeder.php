<?php

namespace Database\Seeders;

use App\Models\ImageSlot;
use Illuminate\Database\Seeder;

/**
 * The 22 image slots from legacy-html/assets/js/data.js (IMG / slot()).
 * Stock photos: Pexels and Pixabay licences, free for commercial use, no attribution required.
 */
class ImageSlotSeeder extends Seeder
{
    public function run(): void
    {
        $pexels = fn (int $id, int $w = 1600) => "https://images.pexels.com/photos/{$id}/pexels-photo-{$id}.jpeg?auto=compress&cs=tinysrgb&w={$w}";

        $stock = [
            'shipSunset' => $pexels(32609062, 2200),
            'shipBlueSky' => $pexels(35192391),
            'shipDusk' => $pexels(24244288),
            'shipAerial' => $pexels(2852124),
            'shipDrone' => $pexels(6746785),
            'shipDroneDusk' => $pexels(6746784),
            'shipIsland' => $pexels(813036),
            'deckSunset' => $pexels(5769604),
            'deckPool' => $pexels(32207991),
            'poolsAbove' => $pexels(12530456),
            'deckCouple' => $pexels(39954521),
            'balconies' => $pexels(12260932),
            'blueWater' => $pexels(34014112),
            'pyramids' => $pexels(32636854),
            'petra' => $pexels(28617385),
            'stateroomBed' => 'https://cdn.pixabay.com/photo/2019/10/23/21/03/cruise-4572829_1280.jpg',
            'stateroomSofa' => 'https://cdn.pixabay.com/photo/2019/10/24/22/25/cruise-4575486_1280.jpg',
            'cabinRoom' => 'https://cdn.pixabay.com/photo/2017/08/16/18/50/cruise-2648839_1280.jpg',
            'cabinBalcony' => 'https://cdn.pixabay.com/photo/2018/07/09/16/56/balcony-3526535_1280.jpg',
        ];

        // [slot key, stock photo, local SVG, label, recommended size]
        $slots = [
            ['hero', 'shipSunset', 'ship-hero', 'Homepage and ship page hero: AROYA at sea, wide', '2400 × 1350'],
            ['exterior', 'shipBlueSky', 'ship-sea', 'AROYA side view, daytime', '1600 × 1000'],
            ['sunset', 'deckSunset', 'ship-deck', 'Sunset from the deck', '1600 × 1000'],
            ['night', 'shipDusk', 'ship-night', 'AROYA lit up at night', '1600 × 1000'],
            ['aerial', 'shipAerial', 'ship-sea', 'AROYA from above', '1600 × 1000'],
            ['pool', 'poolsAbove', 'pool', 'Pools and water park', '1600 × 1000'],
            ['sundeck', 'deckPool', 'pool', 'Sun deck by the pool', '1600 × 1000'],
            ['lounge', 'deckCouple', 'ship-deck', 'Relaxing on deck', '1600 × 1000'],
            ['dest-jeddah', 'shipDrone', 'city', 'Destination: Jeddah', '1600 × 1200'],
            ['dest-yanbu', 'shipIsland', 'coast', 'Destination: Yanbu', '1600 × 1200'],
            ['dest-al-wajh', 'blueWater', 'beach', 'Destination: Al Wajh', '1600 × 1200'],
            ['dest-safaga', 'shipDroneDusk', 'reef', 'Destination: Safaga', '1600 × 1200'],
            ['dest-sokhna', 'pyramids', 'desert', 'Destination: Sokhna (Pyramids)', '1600 × 1200'],
            ['dest-aqaba', 'petra', 'petra', 'Destination: Aqaba (Petra)', '1600 × 1200'],
            ['cabin-interior-1', 'cabinRoom', 'cabin-interior-1', 'Interior Cabin: main photo', '1600 × 1000'],
            ['cabin-interior-2', 'stateroomSofa', 'cabin-interior-2', 'Interior Cabin: second photo', '1600 × 1000'],
            ['cabin-balcony-1', 'stateroomBed', 'cabin-balcony-1', 'Balcony Cabin: main photo', '1600 × 1000'],
            ['cabin-balcony-2', 'cabinBalcony', 'cabin-balcony-2', 'Balcony Cabin: the balcony', '1600 × 1000'],
            ['cabin-balcony-3', 'balconies', 'cabin-balcony-2', 'Balcony Cabin: balconies from outside', '1600 × 1000'],
            ['cabin-suite-1', 'stateroomSofa', 'cabin-suite-1', 'Superior Suite Khuzama: lounge', '1600 × 1000'],
            ['cabin-suite-2', 'stateroomBed', 'cabin-suite-2', 'Superior Suite Khuzama: bedroom', '1600 × 1000'],
            ['cabin-suite-3', 'cabinBalcony', 'cabin-suite-3', 'Superior Suite Khuzama: balcony', '1600 × 1000'],
        ];

        foreach ($slots as $i => [$key, $photo, $svg, $label, $size]) {
            ImageSlot::updateOrCreate(['key' => $key], [
                'label' => $label,
                'recommended_size' => $size,
                'stock_url' => $stock[$photo],
                'fallback_path' => "assets/img/{$svg}.svg",
                'sort_order' => $i + 1,
            ]);
        }
    }
}
