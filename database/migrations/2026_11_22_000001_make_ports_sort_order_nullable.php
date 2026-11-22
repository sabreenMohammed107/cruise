<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Route ports: "Order" (sort_order) may be left empty. Ports with a number come first (smallest first); ports
 * without one follow (on the website's departure port filter: by soonest sailing, then by name). Existing numbers
 * are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->nullable(false)->default(0)->change();
        });
    }
};
