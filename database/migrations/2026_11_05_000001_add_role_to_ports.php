<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Route ports get a type (Admin → Cruises → Route ports): "home" = cruises depart from / arrive at it,
 * "call" = cruises only stop there. Ports already used as a cruise's departure or arrival port become "home".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->string('role', 10)->default('call')->after('name_ar');
        });

        $home = DB::table('cruises')->pluck('departure_port')
            ->merge(DB::table('cruises')->pluck('arrival_port'))
            ->filter()->unique()->values();

        DB::table('ports')->whereIn('name_en', $home)->update(['role' => 'home']);
    }

    public function down(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
