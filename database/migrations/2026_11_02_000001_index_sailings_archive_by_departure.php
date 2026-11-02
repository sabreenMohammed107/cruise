<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sailings go to the archive 24 hours before departure now, not after they return (Sailing::finished(),
 * Sailing::current()), so the archive indexes move from return_date to departure_date. The archive list
 * itself uses the departure_date index from create_sailings_table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sailings', function (Blueprint $table) {
            // "Has a sailing not archived yet" per cruise (Cruise::current(), Cruise::finished())
            $table->index(['cruise_id', 'departure_date']);
        });

        Schema::table('sailings', function (Blueprint $table) {
            $table->dropIndex(['cruise_id', 'return_date']);
            $table->dropIndex(['return_date', 'departure_date']);
        });
    }

    public function down(): void
    {
        Schema::table('sailings', function (Blueprint $table) {
            $table->index(['cruise_id', 'return_date']);
            $table->index(['return_date', 'departure_date']);
        });

        Schema::table('sailings', function (Blueprint $table) {
            $table->dropIndex(['cruise_id', 'departure_date']);
        });
    }
};
