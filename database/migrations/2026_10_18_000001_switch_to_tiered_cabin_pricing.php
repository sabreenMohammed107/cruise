<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tiered occupancy pricing (PROJECT_STEPS.md, step 21).
 *
 * Each Sailing × Cabin now stores three cabin prices for the whole cruise:
 *   price_single  cabin with 1 paying guest
 *   price_double  cabin with 2 paying guests
 *   price_extra   the 3rd guest (adult or child)
 * Infants are free. The rate card that produced the old per-person prices
 * (cabin rate × nights × cruise factor × sailing factor) is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sailing_cabin_prices', function (Blueprint $table) {
            $table->decimal('price_single', 10, 2)->default(0)->after('cabin_category_id');
            $table->decimal('price_double', 10, 2)->default(0)->after('price_single');
            $table->decimal('price_extra', 10, 2)->default(0)->after('price_double');
        });

        // Keep what guests paid before: 1 adult = one adult fare, 2 adults = two adult fares, 3rd guest = child fare.
        DB::table('sailing_cabin_prices')->update([
            'price_single' => DB::raw('price_adult'),
            'price_double' => DB::raw('price_adult * 2'),
            'price_extra' => DB::raw('price_child'),
        ]);

        Schema::table('sailing_cabin_prices', function (Blueprint $table) {
            $table->dropColumn(['price_adult', 'price_child', 'price_infant']);
        });

        Schema::table('sailings', fn (Blueprint $table) => $table->dropColumn('price_factor'));
        Schema::table('cruises', fn (Blueprint $table) => $table->dropColumn('price_factor'));
        Schema::table('cabin_categories', fn (Blueprint $table) => $table->dropColumn(['base_rate_adult', 'base_rate_child', 'base_rate_infant']));

        // A cabin holds at most 3 guests, infants included.
        DB::table('cabin_categories')->where('max_guests', '>', 3)->update(['max_guests' => 3]);
    }

    public function down(): void
    {
        Schema::table('cabin_categories', function (Blueprint $table) {
            $table->decimal('base_rate_adult', 10, 2)->default(0);
            $table->decimal('base_rate_child', 10, 2)->default(0);
            $table->decimal('base_rate_infant', 10, 2)->default(0);
        });
        Schema::table('cruises', fn (Blueprint $table) => $table->decimal('price_factor', 4, 2)->default(1));
        Schema::table('sailings', fn (Blueprint $table) => $table->decimal('price_factor', 4, 2)->default(1));

        Schema::table('sailing_cabin_prices', function (Blueprint $table) {
            $table->decimal('price_adult', 10, 2)->default(0);
            $table->decimal('price_child', 10, 2)->default(0);
            $table->decimal('price_infant', 10, 2)->default(0);
        });

        DB::table('sailing_cabin_prices')->update([
            'price_adult' => DB::raw('price_single'),
            'price_child' => DB::raw('price_extra'),
        ]);

        Schema::table('sailing_cabin_prices', function (Blueprint $table) {
            $table->dropColumn(['price_single', 'price_double', 'price_extra']);
        });
    }
};
