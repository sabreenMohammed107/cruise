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
        // Arabic copy next to every English text column (links and photos are shared).
        // Empty Arabic falls back to the English text on the site.
        Schema::table('home_sections', function (Blueprint $table) {
            $table->string('eyebrow_ar')->nullable()->after('eyebrow');
            $table->string('title_ar')->nullable()->after('title');
            $table->text('body_ar')->nullable()->after('body');
            $table->string('primary_label_ar', 60)->nullable()->after('primary_label');
            $table->string('secondary_label_ar', 60)->nullable()->after('secondary_label');
            $table->string('image_alt_ar')->nullable()->after('image_alt');
            $table->string('caption_label_ar', 80)->nullable()->after('caption_label');
            $table->string('caption_value_ar', 120)->nullable()->after('caption_value');
        });

        Schema::table('home_section_items', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->text('text_ar')->nullable()->after('text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('home_section_items', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'text_ar']);
        });

        Schema::table('home_sections', function (Blueprint $table) {
            $table->dropColumn([
                'eyebrow_ar', 'title_ar', 'body_ar', 'primary_label_ar', 'secondary_label_ar',
                'image_alt_ar', 'caption_label_ar', 'caption_value_ar',
            ]);
        });
    }
};
