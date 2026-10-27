<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "No visa needed" is removed from the site: it was a cruise setting with fixed words in the code (the first
 * option of the cruises-list "Highlights" filter, a tag on cards and the cruise page). Every highlight now comes
 * from Admin → Cruises → Highlights only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cruises', function (Blueprint $table) {
            $table->dropColumn('visa_free');
        });
    }

    public function down(): void
    {
        // Which cruises had it ticked is not kept
        Schema::table('cruises', function (Blueprint $table) {
            $table->boolean('visa_free')->default(false)->after('tags');
        });
    }
};
