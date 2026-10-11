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
        Schema::create('booking_travellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('band', 10);
            // 1..n within the band, matching the template's "adult-1", "child-2" slots.
            $table->unsignedTinyInteger('slot');
            $table->boolean('is_primary')->default(false);
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->string('gender', 10);
            $table->string('nationality');
            $table->timestamps();

            $table->unique(['booking_id', 'band', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_travellers');
    }
};
