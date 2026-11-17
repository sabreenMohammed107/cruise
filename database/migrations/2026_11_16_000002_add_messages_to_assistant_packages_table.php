<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitor messages answered during each package (Admin → Assistant tickets): counted when the message uses the
 * package's tickets (App\Services\Assistant\Tickets::charge()). A conversation can go on across a renewal, so its own
 * message count can't say how many were in one package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_packages', function (Blueprint $table) {
            $table->unsignedInteger('messages')->default(0)->after('tickets_used');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_packages', function (Blueprint $table) {
            $table->dropColumn('messages');
        });
    }
};
