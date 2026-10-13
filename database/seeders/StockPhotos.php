<?php

namespace Database\Seeders;

/**
 * The template's stock photos (legacy-html/assets/js/data.js), by the names the seed data uses.
 * Pexels and Pixabay licences: free for commercial use, no attribution required.
 * Records store the photo URL itself; admins replace it from the media library.
 */
final class StockPhotos
{
    public static function url(string $name): string
    {
        $pexels = fn (int $id, int $w = 1600) => "https://images.pexels.com/photos/{$id}/pexels-photo-{$id}.jpeg?auto=compress&cs=tinysrgb&w={$w}";

        return match ($name) {
            'hero' => $pexels(32609062, 2200),
            'exterior' => $pexels(35192391),
            'sunset' => $pexels(5769604),
            'night' => $pexels(24244288),
            'aerial' => $pexels(2852124),
            'pool' => $pexels(12530456),
            'sundeck' => $pexels(32207991),
            'lounge' => $pexels(39954521),
            'dest-jeddah' => $pexels(6746785),
            'dest-yanbu' => $pexels(813036),
            'dest-al-wajh' => $pexels(34014112),
            'dest-safaga' => $pexels(6746784),
            'dest-sokhna' => $pexels(32636854),
            'dest-aqaba' => $pexels(28617385),
            'cabin-interior-1' => 'https://cdn.pixabay.com/photo/2017/08/16/18/50/cruise-2648839_1280.jpg',
            'cabin-interior-2', 'cabin-suite-1' => 'https://cdn.pixabay.com/photo/2019/10/24/22/25/cruise-4575486_1280.jpg',
            'cabin-balcony-1', 'cabin-suite-2' => 'https://cdn.pixabay.com/photo/2019/10/23/21/03/cruise-4572829_1280.jpg',
            'cabin-balcony-2', 'cabin-suite-3' => 'https://cdn.pixabay.com/photo/2018/07/09/16/56/balcony-3526535_1280.jpg',
            'cabin-balcony-3' => $pexels(12260932),
        };
    }

    /** @param  list<string>  $names */
    public static function urls(array $names): array
    {
        return array_map(static::url(...), $names);
    }
}
