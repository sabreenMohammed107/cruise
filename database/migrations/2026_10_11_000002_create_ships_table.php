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
        Schema::create('ships', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->string('flag')->nullable();
            $table->string('home_port')->nullable();
            $table->text('description')->nullable();
            // Paragraphs for "Built for the Red Sea" on ship.html.
            $table->json('story')->nullable();
            $table->string('image_slot', 40)->nullable();
            $table->string('hero_image_slot', 40)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ships');
    }
};
