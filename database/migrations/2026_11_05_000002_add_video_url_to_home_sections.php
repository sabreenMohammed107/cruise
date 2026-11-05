<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A YouTube link for page sections that show a video (config/home.php: fields 'video'), e.g. the homepage
 * "Video" section. Shared by both languages, like the photos and button links. The heading becomes optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $table->string('video_url', 255)->nullable()->after('image_alt_ar');
            // The video section may have no heading (config 'title_required' => false); others still require one
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
