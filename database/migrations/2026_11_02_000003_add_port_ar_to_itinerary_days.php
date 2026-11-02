<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arabic name of an itinerary day ("Arrival at Alexandria" → "الوصول إلى الإسكندرية"). Empty: the English name is
 * shown in Arabic through the route ports / destinations when it is a port name, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itinerary_days', function (Blueprint $table) {
            $table->string('port_ar')->nullable()->after('port');
        });
    }

    public function down(): void
    {
        Schema::table('itinerary_days', function (Blueprint $table) {
            $table->dropColumn('port_ar');
        });
    }
};
