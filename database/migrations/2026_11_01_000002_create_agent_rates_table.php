<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent discounts (Admin → Agents → Agent rates): a percent off the cabin prices for signed-in agents.
 * Empty cruise / cabin means "every cruise" / "every cabin"; the most specific active rate wins
 * (App\Services\AgentPricing::percent()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cruise_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('cabin_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('percent', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // NULLs don't collide in a unique index, so the "one rate per cruise × cabin" rule is in AgentRateResource
            $table->index(['cruise_id', 'cabin_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_rates');
    }
};
