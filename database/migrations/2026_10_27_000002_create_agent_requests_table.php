<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applications from the website's "Become an agent" page (Admin → Agents → Agent requests).
 * The page's texts are page sections (config/pages.php → agents), edited in Admin → Cruise pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_requests', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone', 20);
            $table->string('status', 20)->default('pending'); // App\Enums\AgentRequestStatus
            $table->timestamps();

            // Admin list: newest first, filtered by status; the pending count in the sidebar
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_requests');
    }
};
