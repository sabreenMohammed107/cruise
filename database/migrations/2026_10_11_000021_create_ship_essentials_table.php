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
        // "Good to know" items on ship.html.
        Schema::create('ship_essentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ship_id')->constrained()->cascadeOnDelete();
            $table->string('icon', 40);
            $table->string('title');
            $table->text('text');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ship_essentials');
    }
};
