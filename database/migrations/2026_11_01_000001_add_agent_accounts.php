<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent accounts (role "agent", App\Enums\UserRole): an approved application from "Become an agent" gets a website
 * account (Admin\AgentRequestController::approve). Bookings by an agent keep what the agent rate took off the fare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('last_name');
        });

        Schema::table('agent_requests', function (Blueprint $table) {
            // The account made (or upgraded) when the application was approved
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('status');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Public fare − agent fare; fare_total is already the agent fare, so grand_total = fare_total − discount_total
            $table->decimal('agent_discount_total', 10, 2)->default(0)->after('discount_total');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('agent_discount_total');
        });

        Schema::table('agent_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('approved_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('company_name');
        });
    }
};
