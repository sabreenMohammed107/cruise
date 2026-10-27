<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages sent from the website's "Contact us" page (Admin → Contact messages).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            // Admin list: newest first, and the unread count / filter
            $table->index(['created_at', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
