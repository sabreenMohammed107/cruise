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
        // Named image slots (photos.html). Each slot resolves, in order, to:
        //   1. an uploaded photo (path, relative to public/), or public/assets/img/aroya/{key}.jpg
        //   2. a royalty-free stock photo (stock_url)
        //   3. a local SVG illustration (fallback_path)
        // Ships, cruises, cabins, destinations and ship zones store slot keys, not URLs.
        Schema::create('image_slots', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('label');
            $table->string('recommended_size', 20)->nullable();
            $table->string('path')->nullable();
            $table->string('stock_url', 500)->nullable();
            $table->string('fallback_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_slots');
    }
};
