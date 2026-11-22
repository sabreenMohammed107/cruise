<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Age offers (step 38): only from a guest's place in the cabin.
 *
 *   min_guest_position  the guest's place among the paying guests of the cabin (adults first, then children; infants
 *                       are free and not counted). 3 = only the 3rd guest and later, e.g. a child pays $100 with
 *                       2 adults but not with 1 adult. Empty = any guest.
 *
 * Existing offers keep it empty, so they still apply to every guest of their age range.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_guest_position')->nullable()->after('max_age');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('min_guest_position');
        });
    }
};
