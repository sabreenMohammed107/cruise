<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent types (Admin → Agents → Agent types): Standard, Gold, a special contract… Each agent has one, and agent rates
 * can be for one type or for every type (agent_tier_id null). App\Services\AgentPricing: the agent's type first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('note')->nullable(); // e.g. "Special contract", shown next to the name in the admin
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agent_tier_id')->nullable()->after('company_name')->constrained('agent_tiers')->nullOnDelete();
        });

        Schema::table('agent_rates', function (Blueprint $table) {
            // A type's rates go with it
            $table->foreignId('agent_tier_id')->nullable()->after('id')->constrained('agent_tiers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_rates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_tier_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_tier_id');
        });

        Schema::dropIfExists('agent_tiers');
    }
};
