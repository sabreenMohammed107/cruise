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
        Schema::create('age_bands', function (Blueprint $table) {
            $table->id();
            $table->string('key', 10)->unique();
            $table->string('label', 40);
            $table->string('label_ar', 40)->nullable();
            $table->string('hint')->nullable();
            // Age on the departure date, in whole months: min <= age < max_exclusive.
            $table->unsignedSmallInteger('min_months');
            $table->unsignedSmallInteger('max_months_exclusive')->nullable();
            // Port charges, taxes and fees per guest per night.
            $table->decimal('fee_per_night', 8, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('age_bands');
    }
};
