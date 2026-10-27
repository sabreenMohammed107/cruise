<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the admin's cruises list and archive (App\Admin\CruiseFilters). The dates are on sailings
 * (a cruise runs on many dates), so that is where the date indexes go; departure_date is already indexed
 * with status (create_sailings_table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sailings', function (Blueprint $table) {
            // "Has a sailing not back yet" per cruise (Cruise::current(), Cruise::finished())
            $table->index(['cruise_id', 'return_date']);
            // Archive: sailings back before today (Sailing::finished()), and the year range read from them
            $table->index(['return_date', 'departure_date']);
        });

        Schema::table('cruises', function (Blueprint $table) {
            // Departure port / nights filters, and the DISTINCT lists behind their dropdowns
            $table->index('departure_port');
            $table->index('nights');
        });
    }

    public function down(): void
    {
        Schema::table('sailings', function (Blueprint $table) {
            $table->dropIndex(['cruise_id', 'return_date']);
            $table->dropIndex(['return_date', 'departure_date']);
        });

        Schema::table('cruises', function (Blueprint $table) {
            $table->dropIndex(['departure_port']);
            $table->dropIndex(['nights']);
        });
    }
};
