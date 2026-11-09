<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several receipts per payment (transfer slip + InstaPay screenshot + PDF…): payment_receipts replaces the single
 * payments.receipt_path / receipt_name of the previous migration. A receipt already saved there is moved over.
 * Files stay where they are (storage/app/private/payment-receipts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            // Path on the "local" (private) disk, and the file's name as uploaded, for the list and the download
            $table->string('path');
            $table->string('name');
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('payments')->whereNotNull('receipt_path')->orderBy('id')->get(['id', 'receipt_path', 'receipt_name'])
            ->each(fn ($p) => DB::table('payment_receipts')->insert([
                'payment_id' => $p->id,
                'path' => $p->receipt_path,
                'name' => $p->receipt_name ?: basename($p->receipt_path),
                'created_at' => $now,
                'updated_at' => $now,
            ]));

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['receipt_path', 'receipt_name']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('notes');
            $table->string('receipt_name')->nullable()->after('receipt_path');
        });

        // Only one fits back: the first receipt of each payment (the files of the others stay on disk)
        DB::table('payment_receipts')->orderBy('id')->get()->unique('payment_id')
            ->each(fn ($r) => DB::table('payments')->where('id', $r->payment_id)->update(['receipt_path' => $r->path, 'receipt_name' => $r->name]));

        Schema::dropIfExists('payment_receipts');
    }
};
