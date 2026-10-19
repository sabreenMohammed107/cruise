<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // No WithoutModelEvents: User's saving hook fills the `name` column from first/last name.

    /**
     * Seed the application's database with the template's sample data (legacy-html/assets/js/data.js).
     * Order matters: ships → cabins → cruises/sailings/prices → people and bookings.
     */
    public function run(): void
    {
        $this->call([
            AgeBandSeeder::class,
            ShipSeeder::class,
            CabinCategorySeeder::class,
            PortSeeder::class, // before cruises: their routes are made of these ports
            CruiseSeeder::class,
            DestinationSeeder::class,
            TestimonialSeeder::class,
            PromoCodeSeeder::class,
            HomeSectionSeeder::class,
            ArabicContentSeeder::class, // Arabic {column}_ar of the content above (fills empty ones only)
        ]);

        if (app()->isLocal() || app()->runningUnitTests()) {
            $this->call([
                UserSeeder::class,
                DemoBookingSeeder::class,
            ]);
        }
    }
}
