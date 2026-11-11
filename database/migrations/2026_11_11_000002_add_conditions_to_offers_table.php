<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offer conditions (step 26b, App\Models\Offer), both optional: the offer applies only to these cabin types, and / or
 * only to travellers of these nationalities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->json('cabin_categories')->nullable()->after('max_age'); // cabin slugs
            $table->json('nationalities')->nullable()->after('cabin_categories');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['cabin_categories', 'nationalities']);
        });
    }
};
