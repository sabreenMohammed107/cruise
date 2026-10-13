<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Photo slots are gone: every record now stores its image itself, as a path under public/
 * (e.g. uploads/media/deck-1a2b3c.jpg) or a photo URL, picked from the admin media library.
 *
 * Existing slot keys are replaced by what the slot showed: its uploaded photo, else the official
 * assets/img/aroya/{key}.jpg, else its stock photo URL, else its SVG illustration. Uploaded files stay
 * where they are (public/uploads/image-slots) and remain in the media library.
 */
return new class extends Migration
{
    /** table => [old column => new column]; list columns hold JSON arrays of keys */
    private const COLUMNS = [
        'ships' => ['image_slot' => 'image', 'hero_image_slot' => 'hero_image'],
        'ship_zones' => ['image_slot' => 'image'],
        'destinations' => ['image_slot' => 'image'],
        'cruises' => ['image_slot' => 'image', 'gallery_slots' => 'gallery'],
        'cabin_categories' => ['image_slots' => 'images'],
    ];

    private const LISTS = ['gallery_slots', 'image_slots'];

    public function up(): void
    {
        $images = $this->slotImages();

        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $old => $new) {
                    in_array($old, self::LISTS, true) ? $t->json($new)->nullable() : $t->string($new, 500)->nullable();
                }
            });

            foreach (DB::table($table)->get() as $row) {
                $values = [];
                foreach ($columns as $old => $new) {
                    if (in_array($old, self::LISTS, true)) {
                        $keys = json_decode((string) $row->{$old}, true) ?: [];
                        $values[$new] = json_encode(array_values(array_filter(array_map(fn ($k) => $images[$k] ?? null, $keys))));
                    } else {
                        $values[$new] = $images[$row->{$old}] ?? null;
                    }
                }
                DB::table($table)->where('id', $row->id)->update($values);
            }

            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(array_keys($columns)));
        }

        Schema::dropIfExists('image_slots');
    }

    /**
     * Brings back the old columns and an empty image_slots table. Records keep their images in the
     * new columns; slot keys can't be rebuilt from them.
     */
    public function down(): void
    {
        Schema::create('image_slots', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('label');
            $table->string('recommended_size', 20)->nullable();
            $table->string('path')->nullable();
            $table->string('stock_url', 500)->nullable();
            $table->string('fallback_path')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach (array_keys($columns) as $old) {
                    in_array($old, self::LISTS, true) ? $t->json($old)->nullable() : $t->string($old, 40)->nullable();
                }
                $t->dropColumn(array_values($columns));
            });
        }
    }

    /** slot key => the image it showed (path under public/ or URL) */
    private function slotImages(): array
    {
        if (! Schema::hasTable('image_slots')) {
            return [];
        }

        $images = [];
        foreach (DB::table('image_slots')->get() as $slot) {
            $official = "assets/img/aroya/{$slot->key}.jpg";
            $images[$slot->key] = match (true) {
                $slot->path && is_file(public_path($slot->path)) => $slot->path,
                is_file(public_path($official)) => $official,
                (bool) $slot->stock_url => $slot->stock_url,
                default => $slot->fallback_path,
            };
        }

        return $images;
    }
};
