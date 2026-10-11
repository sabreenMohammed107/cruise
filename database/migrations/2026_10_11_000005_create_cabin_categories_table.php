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
        Schema::create('cabin_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ship_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 40);
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->string('badge', 40)->nullable();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('max_guests');
            $table->string('size', 40)->nullable();
            $table->string('balcony_size', 40)->nullable();
            $table->string('beds')->nullable();
            $table->text('bedroom')->nullable();
            $table->json('features')->nullable();
            // Ordered list of image slot keys (see image_slots).
            $table->json('image_slots')->nullable();
            // Per-night rate card. Sailing prices are derived from these (see PricingService).
            $table->decimal('base_rate_adult', 10, 2);
            $table->decimal('base_rate_child', 10, 2);
            $table->decimal('base_rate_infant', 10, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['ship_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabin_categories');
    }
};
