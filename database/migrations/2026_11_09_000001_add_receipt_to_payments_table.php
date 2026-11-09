<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof of a payment or refund (transfer slip, InstaPay screenshot, PDF), attached in Admin → Bookings → Record a
 * payment. The file is private: storage/app/private/payment-receipts, opened only through the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Path on the "local" (private) disk, and the file's name as uploaded, for the download
            $table->string('receipt_path')->nullable()->after('notes');
            $table->string('receipt_name')->nullable()->after('receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['receipt_path', 'receipt_name']);
        });
    }
};
