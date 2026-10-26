<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 22: cruise highlights (Admin → Cruises → Highlights) instead of four fixed choices in the code.
 * cruises.tags keeps a list of highlight keys, so existing cruises are unchanged. The four highlights the site
 * had are added with the names and places they were shown in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('highlights', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique(); // stored in cruises.tags and in the cruises list address (?hl=)
            $table->string('name_en', 60);
            $table->string('name_ar', 60)->nullable();
            $table->boolean('show_on_cards')->default(false);       // tag on the sailing card photo
            $table->boolean('show_on_cruise_page')->default(true);  // tag above the cruise page title
            $table->string('tone', 10)->default('ocean');            // colour on the cruise page: ocean | lagoon | sun
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);             // shown on the site and in the cruise form
            $table->timestamps();
        });

        $now = now();
        DB::table('highlights')->insert(array_map(fn ($h) => $h + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now], [
            ['key' => 'family', 'name_en' => 'Family friendly', 'name_ar' => 'مناسبة للعائلات', 'show_on_cards' => false, 'show_on_cruise_page' => true, 'tone' => 'ocean', 'sort_order' => 1],
            ['key' => 'excursions', 'name_en' => 'Shore excursions', 'name_ar' => 'رحلات برية', 'show_on_cards' => false, 'show_on_cruise_page' => false, 'tone' => 'ocean', 'sort_order' => 2],
            ['key' => 'weekend', 'name_en' => 'Weekend', 'name_ar' => 'عطلة نهاية الأسبوع', 'show_on_cards' => true, 'show_on_cruise_page' => false, 'tone' => 'ocean', 'sort_order' => 3],
            ['key' => 'festive', 'name_en' => 'Festive sailing', 'name_ar' => 'رحلة احتفالية', 'show_on_cards' => true, 'show_on_cruise_page' => true, 'tone' => 'sun', 'sort_order' => 4],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('highlights');
    }
};
