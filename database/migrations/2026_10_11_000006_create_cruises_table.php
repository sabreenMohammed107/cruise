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
        Schema::create('cruises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ship_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->unsignedTinyInteger('nights');
            $table->string('departure_port');
            $table->string('arrival_port');
            $table->json('route');
            $table->json('tags')->nullable();
            $table->boolean('visa_free')->default(false);
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedTinyInteger('popularity')->default(0);
            $table->decimal('price_factor', 4, 2)->default(1);
            // Image slot keys (see image_slots).
            $table->string('image_slot', 40)->nullable();
            $table->json('gallery_slots')->nullable();
            $table->text('summary')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cruises');
    }
};
