<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Contact us" and "Become an agent" take the mobile number with the site's phone widget, like the booking
 * and account forms: the country code ("+966") apart from the number ("501234567").
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['contact_messages', 'agent_requests'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('phone_dial', 6)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        foreach (['contact_messages', 'agent_requests'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('phone_dial');
            });
        }
    }
};
