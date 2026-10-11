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
        Schema::create('sailing_cabin_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sailing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cabin_category_id')->constrained()->cascadeOnDelete();
            // Per person, for the whole cruise.
            $table->decimal('price_adult', 10, 2);
            $table->decimal('price_child', 10, 2);
            $table->decimal('price_infant', 10, 2);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_sold_out')->default(false);
            $table->timestamps();

            $table->unique(['sailing_id', 'cabin_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sailing_cabin_prices');
    }
};
