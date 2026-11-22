<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offers by cruise length (step 39): a third condition next to cabin types and nationalities.
 *
 *   cruise_nights  the cruise lengths (nights) the offer is for, e.g. [4] or [4, 7]. One offer per length gives each
 *                  length its own price: children pay $100 on 4 nights (one offer) and $70 on 7 nights (another).
 *                  Empty = every length.
 *
 * Existing offers keep it empty, so they still apply to cruises of every length.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->json('cruise_nights')->nullable()->after('nationalities');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('cruise_nights');
        });
    }
};
