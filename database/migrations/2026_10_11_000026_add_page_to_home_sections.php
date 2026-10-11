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
        // Page sections for every page, not only the homepage: home (config/home.php) and ship, catalog,
        // cruise, destinations (config/pages.php). A section key is unique within its page.
        Schema::table('home_sections', function (Blueprint $table) {
            $table->string('page', 30)->default('home')->after('id');
            $table->dropUnique(['key']);
            $table->unique(['page', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $table->dropUnique(['page', 'key']);
            $table->unique(['key']);
            $table->dropColumn('page');
        });
    }
};
