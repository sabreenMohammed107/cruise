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
        // Editable copy of the homepage sections (hero, ship, sailings, why, …), one row per section.
        // Which fields a section uses is set in config/home.php; the layout stays in pages/home.blade.php.
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('primary_label', 60)->nullable();
            $table->string('primary_url')->nullable();
            $table->string('secondary_label', 60)->nullable();
            $table->string('secondary_url')->nullable();
            // Uploaded photo, relative to public/ (uploads/home/…). Empty = config default / image slot.
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('caption_label', 80)->nullable();
            $table->string('caption_value', 120)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        // Repeating cards inside a section ("Why Safer Cruise" feature cards).
        Schema::create('home_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained()->cascadeOnDelete();
            $table->string('icon', 30)->nullable();
            $table->string('tone', 10)->default('ocean');
            $table->string('title');
            $table->text('text')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_section_items');
        Schema::dropIfExists('home_sections');
    }
};
