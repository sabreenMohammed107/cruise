<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offers (step 26c): "free nights" and "cabin upgrade" targets, and longer target / type values
 * (third_guest, second_guest, free_nights, fixed_price are over the first 10 characters).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('target', 20)->default('fare')->change();
            $table->string('type', 20)->change();
            $table->unsignedTinyInteger('free_nights')->nullable()->after('max_age');   // target free_nights
            $table->string('upgrade_from', 60)->nullable()->after('free_nights');     // target upgrade: cabin slug whose price is paid
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['free_nights', 'upgrade_from']);
        });
    }
};
