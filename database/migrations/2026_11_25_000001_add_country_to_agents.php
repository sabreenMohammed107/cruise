<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The agent's country (ISO 3166 code, App\Support\Countries): asked on the "Become an agent" form, kept on the
 * application and copied to the agent account on approval (App\Services\AgentAccounts). Null for the applications
 * and agents from before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_requests', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('company_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('agent_requests', fn (Blueprint $table) => $table->dropColumn('country'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('country'));
    }
};
