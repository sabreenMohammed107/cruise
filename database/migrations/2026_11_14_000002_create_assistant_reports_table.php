<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reports the chat assistant made for a signed-in customer or agent (App\Services\Assistant\Reports). `spec` is the
 * report definition (dataset, period, columns, filters, sort): downloads run it again, so they show today's data.
 * A row with `template_name` is a template the user saved to run again later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assistant_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->json('spec');
            $table->string('template_name', 80)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'template_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_reports');
    }
};
