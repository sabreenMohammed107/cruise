<?php

namespace Database\Seeders;

use App\Models\Port;
use Illuminate\Database\Seeder;

/**
 * Route ports (step 16): the ports cruise routes are built from, in English and Arabic.
 * The English names match the destinations (DestinationSeeder), which is how "Cruises that call here" works.
 */
class PortSeeder extends Seeder
{
    public function run(): void
    {
        $ports = [
            ['Jeddah', 'جدة'],
            ['Yanbu', 'ينبع'],
            ['Al Wajh', 'الوجه'],
            ['Safaga', 'سفاجا'],
            ['Sokhna', 'السخنة'],
            ['Aqaba', 'العقبة'],
        ];

        foreach ($ports as $i => [$en, $ar]) {
            Port::updateOrCreate(['name_en' => $en], ['name_ar' => $ar, 'sort_order' => $i + 1, 'is_active' => true]);
        }
    }
}
