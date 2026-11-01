<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agent bookings vs B2C (Admin → Bookings filters): the agent who booked and their type at booking time, kept on the
 * booking so a later change of role or type doesn't move old bookings. Bookings already made by agents are filled in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('agent_tier_id')->nullable()->after('agent_id')->constrained('agent_tiers')->nullOnDelete();
        });

        $agents = DB::table('users')->where('role', UserRole::Agent->value)->get(['id', 'agent_tier_id']);
        foreach ($agents as $agent) {
            DB::table('bookings')->where('user_id', $agent->id)->update(['agent_id' => $agent->id, 'agent_tier_id' => $agent->agent_tier_id]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_tier_id');
            $table->dropConstrainedForeignId('agent_id');
        });
    }
};
