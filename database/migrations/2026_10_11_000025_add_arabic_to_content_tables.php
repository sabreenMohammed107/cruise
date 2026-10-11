<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arabic twin ({column}_ar) of every visitor-facing text column. Each model lists its translated
     * columns in TRANSLATABLE (HasArabicCopy). Empty Arabic falls back to the English value on the site.
     *
     * Not translated: identifiers and codes (slugs, keys, port names in routes, promo codes), and personal
     * or operational data (users, bookings, travellers, payments, newsletter, sessions, jobs).
     */
    private const COLUMNS = [
        'ships' => [
            'name_ar' => 'string', 'tagline_ar' => 'string', 'flag_ar' => 'string', 'home_port_ar' => 'string',
            'description_ar' => 'text', 'story_ar' => 'json',
        ],
        'ship_specs' => ['label_ar' => 'string', 'value_ar' => 'string:40', 'unit_ar' => 'string:20'],
        'ship_zones' => ['title_ar' => 'string', 'text_ar' => 'text'],
        'ship_essentials' => ['title_ar' => 'string', 'text_ar' => 'text'],
        'ship_faqs' => ['question_ar' => 'string', 'answer_ar' => 'text'],
        'facilities' => ['value_ar' => 'string:40', 'label_ar' => 'string', 'description_ar' => 'text'],
        'cabin_categories' => [
            'badge_ar' => 'string:40', 'short_description_ar' => 'string', 'description_ar' => 'text', 'size_ar' => 'string:40',
            'balcony_size_ar' => 'string:40', 'beds_ar' => 'string', 'bedroom_ar' => 'text', 'features_ar' => 'json',
        ],
        'cruises' => ['summary_ar' => 'text'],
        'itinerary_days' => ['time_label_ar' => 'string:40', 'description_ar' => 'text'],
        'destinations' => [
            'country_ar' => 'string', 'blurb_ar' => 'string', 'description_ar' => 'text', 'highlights_ar' => 'json', 'visa_note_ar' => 'text',
        ],
        'testimonials' => ['name_ar' => 'string', 'from_city_ar' => 'string', 'text_ar' => 'text'],
        'age_bands' => ['hint_ar' => 'string'],
        'promo_codes' => ['label_ar' => 'string'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column => $type) {
                    [$kind, $length] = array_pad(explode(':', $type), 2, null);
                    $after = substr($column, 0, -3);
                    match ($kind) {
                        'string' => $t->string($column, (int) ($length ?: 255))->nullable()->after($after),
                        'text' => $t->text($column)->nullable()->after($after),
                        'json' => $t->json($column)->nullable()->after($after),
                    };
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(array_keys($columns)));
        }
    }
};
