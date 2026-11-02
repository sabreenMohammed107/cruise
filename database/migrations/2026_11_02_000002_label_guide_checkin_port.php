<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The pre-boarding guide now has check-in times per port (config/pages.php → guide: checkin = Alexandria,
 * checkin_istanbul = Istanbul), each shown with its port name (eyebrow). The existing check-in section gets
 * "Alexandria" where the admin has not set a port yet; the Istanbul sections are created from config.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('home_sections')
            ->where('page', 'guide')->where('key', 'checkin')
            ->where(fn ($q) => $q->whereNull('eyebrow')->orWhere('eyebrow', ''))
            ->update(['eyebrow' => 'Alexandria', 'eyebrow_ar' => 'الإسكندرية']);
    }

    public function down(): void
    {
        DB::table('home_sections')
            ->where('page', 'guide')->where('key', 'checkin')->where('eyebrow', 'Alexandria')
            ->update(['eyebrow' => null, 'eyebrow_ar' => null]);
    }
};
