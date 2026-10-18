<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several cabins in one booking (PROJECT_STEPS.md step 15).
 *
 *   booking_cabins            one row per cabin of a booking: type, guests and its own tiered price
 *   booking_travellers        + booking_cabin_id: the cabin the traveller sleeps in
 *
 * bookings keeps cabin_category_id (= the first cabin) and adults / children / infants (= totals of all
 * cabins), so lists, exports and the dashboard keep working. Every existing booking becomes a booking
 * with one cabin. Traveller slots stay numbered per band across the whole booking (adult-1, adult-2, …),
 * so the existing unique key (booking_id, band, slot) still holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_cabins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            // 1, 2, 3 … in the order the guest added them ("Cabin 1", "Cabin 2")
            $table->unsignedTinyInteger('position');
            $table->foreignId('cabin_category_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);
            // This cabin's fare (tiered price) and fees; the discount applies to the booking as a whole
            $table->decimal('fare_total', 10, 2);
            $table->decimal('fees_total', 10, 2);
            $table->json('price_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'position']);
            $table->index('cabin_category_id');
        });

        Schema::table('booking_travellers', function (Blueprint $table) {
            $table->foreignId('booking_cabin_id')->nullable()->after('booking_id')->constrained('booking_cabins')->cascadeOnDelete();
        });

        // Existing bookings: one cabin each, with their totals and quote; their travellers sleep in it
        DB::table('bookings')->orderBy('id')->each(function ($b) {
            $cabinId = DB::table('booking_cabins')->insertGetId([
                'booking_id' => $b->id,
                'position' => 1,
                'cabin_category_id' => $b->cabin_category_id,
                'adults' => $b->adults,
                'children' => $b->children,
                'infants' => $b->infants,
                'fare_total' => $b->fare_total,
                'fees_total' => $b->fees_total,
                'price_snapshot' => $b->price_snapshot,
                'created_at' => $b->created_at,
                'updated_at' => $b->updated_at,
            ]);
            DB::table('booking_travellers')->where('booking_id', $b->id)->update(['booking_cabin_id' => $cabinId]);
        });
    }

    public function down(): void
    {
        Schema::table('booking_travellers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_cabin_id');
        });
        Schema::dropIfExists('booking_cabins');
    }
};
