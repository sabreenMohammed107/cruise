<?php

namespace Database\Seeders;

use App\Models\HomeSection;
use Illuminate\Database\Seeder;

/**
 * Section copy of every page, in English and Arabic: the homepage (config/home.php) and the ship
 * page, cruises list, cruise details and destinations (config/pages.php), from the template HTML.
 * Re-running resets every section, and its cards, to the template text.
 */
class HomeSectionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (HomeSection::pages() as $page) {
            foreach (HomeSection::pageConfig($page)['sections'] as $key => $config) {
                $section = HomeSection::updateOrCreate(
                    ['page' => $page, 'key' => $key],
                    $config['content'] + ['image_path' => null, 'is_visible' => true],
                );

                $section->items()->delete();
                foreach ($config['items'] ?? [] as $i => $item) {
                    $section->items()->create($item + ['sort_order' => $i + 1]);
                }
            }
        }
    }
}
