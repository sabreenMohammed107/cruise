<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // No online payment: a booking is confirmed when the guest submits it, and the team
        // arranges payment offline. payment_status tracks that separately from status.
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sailing_id')->constrained()->restrictOnDelete();
            $table->foreignId('cabin_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('confirmed');
            $table->string('payment_status', 20)->default('unpaid');
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);
            $table->string('contact_email');
            $table->string('contact_dial', 6);
            $table->string('contact_phone', 20);
            $table->decimal('fare_total', 10, 2);
            $table->decimal('fees_total', 10, 2);
            $table->decimal('discount_total', 10, 2)->default(0);
            // Amount due from the guest (the template's amountDue).
            $table->decimal('grand_total', 10, 2);
            // Sum of recorded payments minus refunds.
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            // Unit prices and quote lines as they were when the guest confirmed.
            $table->json('price_snapshot')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
