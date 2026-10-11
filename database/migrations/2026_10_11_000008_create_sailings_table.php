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
        Schema::create('sailings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cruise_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->date('departure_date');
            // Stored (departure + nights) so catalog filters don't need a join.
            $table->date('return_date');
            $table->decimal('price_factor', 4, 2)->default(1);
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['departure_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sailings');
    }
};
