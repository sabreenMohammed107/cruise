<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Only departure & arrival ports have an "Order" (Admin → Route ports): ports of call are listed by name, so their
 * old numbers are cleared. Departure & arrival ports keep theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ports')->where('role', 'call')->update(['sort_order' => null]);
    }

    public function down(): void
    {
        // The old numbers aren't kept; the ports stay listed by name
    }
};
