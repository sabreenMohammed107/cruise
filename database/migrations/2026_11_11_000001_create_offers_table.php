<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-limited offers (Admin → Pricing & offers → Offers, App\Models\Offer): a discount on every sailing of a cruise,
 * on one sailing, or on every cruise, either on the whole cabin price or on the guests of an age range, for a date
 * window, with an optional cap per booking and an optional number of bookings. Applied by App\Services\PricingService.
 *
 * bookings.offer_discount_total: what the offers took off a booking (the promo code stays in discount_total).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            // Neither: every cruise. Cruise only: all its sailings. Sailing: that one (its cruise is filled in too).
            $table->foreignId('cruise_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('sailing_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('target', 10)->default('fare');     // fare | age (App\Enums\OfferTarget)
            $table->unsignedTinyInteger('min_age')->nullable(); // whole years on the departure date, both ends included
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->string('type', 10);                          // percent | fixed (App\Enums\PromoType)
            $table->decimal('value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();  // cap per booking
            $table->unsignedInteger('max_uses')->nullable();     // number of bookings
            $table->unsignedInteger('used_count')->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on');
            $table->string('audience', 10)->default('all');     // all | b2c | b2b (App\Enums\OfferAudience)
            $table->string('agent_mode', 10)->default('stack');  // stack | replace (App\Enums\OfferAgentMode)
            $table->boolean('combinable')->default(false);       // with a promo code
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'ends_on']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('offer_discount_total', 10, 2)->default(0)->after('discount_total');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('offer_discount_total');
        });
        Schema::dropIfExists('offers');
    }
};
