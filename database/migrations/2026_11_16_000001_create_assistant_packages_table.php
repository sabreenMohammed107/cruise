<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website chat packages (Admin → Assistant tickets, App\Services\Assistant\Tickets): a number of tickets for a USD
 * amount, valid one month from `starts_at`. Each visitor message uses tickets in proportion to what it cost
 * (`tickets_used`); the chat stops when they are used up or the month is over, until the super admin renews.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tickets');
            $table->decimal('price_usd', 10, 2);
            $table->decimal('tickets_used', 14, 4)->default(0);
            // dateTime, not timestamp: MySQL refuses two NOT NULL timestamps without defaults on some servers
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_packages');
    }
};
