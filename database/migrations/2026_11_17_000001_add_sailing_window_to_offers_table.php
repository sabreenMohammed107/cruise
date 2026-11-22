<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offers (step 35): a sailing window next to the booking window.
 *
 *   starts_on / ends_on                  booking window: the days a booking can be made with the offer (unchanged)
 *   sailing_starts_on / sailing_ends_on  sailing window: only sailings departing on these days, both included.
 *                                        Either or both may be empty: no limit on that side (every departure).
 *
 * Existing offers keep both empty, so they still cover every sailing of their cruise(s).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->date('sailing_starts_on')->nullable()->after('ends_on');
            $table->date('sailing_ends_on')->nullable()->after('sailing_starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['sailing_starts_on', 'sailing_ends_on']);
        });
    }
};
