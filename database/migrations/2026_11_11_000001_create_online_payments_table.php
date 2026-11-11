<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per online checkout attempt (EasyKash). An attempt is not money received: only once the gateway reports it
 * paid is a row added to `payments` (payment_id), which then updates the booking through Booking::syncPaymentTotals.
 * Pending, failed and expired attempts stay here so they never count towards amount_paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20)->default('easykash');
            // Our reference sent to the gateway (customerReference) and echoed back in the webhook
            $table->string('customer_reference', 40)->unique();
            // The gateway's own reference (easykashRef), known once the guest pays
            $table->string('gateway_reference')->nullable()->index();
            $table->string('status', 20)->default('pending');
            // What the guest is charged (EGP) …
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('EGP');
            // … and what that covers of the booking (bookings are kept in USD), at the rate used
            $table->decimal('amount_usd', 10, 2);
            $table->decimal('exchange_rate', 10, 4);
            $table->text('checkout_url')->nullable();
            $table->json('gateway_response')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};
