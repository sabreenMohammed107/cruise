<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A new booking is held for config cruise.hold_hours (24) hours: if nothing is paid by then, it is cancelled
 * automatically and the guest is told by email (command bookings:cancel-unpaid, App\Services\UnpaidBookings).
 * Set when the booking is made (App\Services\BookingService); null for the bookings from before, which are never
 * cancelled this way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('hold_until')->nullable()->after('cancelled_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['hold_until']);
            $table->dropColumn('hold_until');
        });
    }
};
