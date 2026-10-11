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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone_dial', 6)->nullable()->after('email');
            $table->string('phone', 20)->nullable()->after('phone_dial');
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->string('nationality')->nullable()->after('date_of_birth');
            $table->string('role', 20)->default('customer')->index()->after('nationality');
            $table->json('notification_prefs')->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['first_name', 'last_name', 'phone_dial', 'phone', 'date_of_birth', 'nationality', 'role', 'notification_prefs']);
        });
    }
};
