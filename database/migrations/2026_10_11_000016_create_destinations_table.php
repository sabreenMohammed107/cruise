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
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            // Must match the port name used in cruises.route (e.g. "Al Wajh"), which links ports to cruises.
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('country');
            $table->string('image_slot', 40)->nullable();
            $table->string('blurb')->nullable();
            $table->text('description')->nullable();
            $table->json('highlights')->nullable();
            $table->text('visa_note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destinations');
    }
};
