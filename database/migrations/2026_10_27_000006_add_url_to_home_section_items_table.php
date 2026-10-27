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
        // Optional link on a card (official visa pages, guardian forms on the pre-boarding guide).
        // Shared by both languages; only sections with 'item_links' in config/pages.php show the field.
        Schema::table('home_section_items', function (Blueprint $table) {
            $table->string('url')->nullable()->after('text_ar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('home_section_items', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
