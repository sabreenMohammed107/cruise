<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional portrait photo for sections that support it (config/home.php: fields 'image_mobile', the homepage hero).
 * Phones and portrait tablets show it instead of the wide photo, so a campaign graphic isn't cropped away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $table->string('image_mobile_path')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $table->dropColumn('image_mobile_path');
        });
    }
};
