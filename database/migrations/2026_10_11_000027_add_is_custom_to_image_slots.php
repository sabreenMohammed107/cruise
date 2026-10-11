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
        // Slots added from the admin (Photo slots → Add a slot, or a direct upload in a gallery).
        // The template's own 22 slots stay is_custom = false and can't be deleted.
        Schema::table('image_slots', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('fallback_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('image_slots', function (Blueprint $table) {
            $table->dropColumn('is_custom');
        });
    }
};
