<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The currency the guest saw when booking (header USD / EGP switch) and the rate of that moment, so the voucher,
 * the guest email and "My bookings" keep showing the same EGP amounts later. The amounts and "currency" stay USD:
 * that is what payments are recorded in. Older bookings: USD at 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->char('display_currency', 3)->default('USD')->after('currency');
            $table->decimal('exchange_rate', 12, 4)->default(1)->after('display_currency');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['display_currency', 'exchange_rate']);
        });
    }
};
