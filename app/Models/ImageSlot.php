<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * A named image slot from the template's photo system (photos.html, assets/img/aroya/README.md).
 *
 * Resolution order:
 *   1. a photo uploaded from the admin panel (path, relative to public/, e.g. uploads/image-slots/hero-1a2b.jpg)
 *   2. an official photo dropped in public/assets/img/aroya/{key}.jpg
 *   3. the royalty-free stock photo (stock_url)
 *   4. the local SVG illustration (fallback_path), always available for the template's slots
 *
 * Custom slots (is_custom) are added from the admin: Photo slots → Add a slot, or a direct upload
 * in a gallery / photo field. They always have an uploaded photo and can be deleted when unused.
 */
class ImageSlot extends Model
{
    /** Uploads live under public/ so no storage symlink is needed on Windows/XAMPP. */
    public const UPLOAD_DIR = 'uploads/image-slots';

    protected $fillable = ['key', 'label', 'recommended_size', 'path', 'stock_url', 'fallback_path', 'is_custom', 'sort_order'];

    protected function casts(): array
    {
        return ['is_custom' => 'boolean'];
    }

    /**
     * New custom slot holding an uploaded photo. The key is made unique from $key (or the label).
     */
    public static function fromUpload(UploadedFile $file, string $label, ?string $key = null, ?string $recommendedSize = null): self
    {
        $slot = static::newCustom($label, $key, $recommendedSize);
        $slot->storeUpload($file);
        $slot->save();

        return $slot;
    }

    /**
     * New custom slot holding a copy of an image already on the server (a media library pick).
     */
    public static function fromFile(string $source, string $label, ?string $key = null): self
    {
        $slot = static::newCustom($label, $key);
        $name = $slot->key.'-'.Str::lower(Str::random(6)).'.'.strtolower(pathinfo($source, PATHINFO_EXTENSION));
        \Illuminate\Support\Facades\File::ensureDirectoryExists(public_path(self::UPLOAD_DIR));
        copy($source, public_path(self::UPLOAD_DIR.'/'.$name));
        $slot->path = self::UPLOAD_DIR.'/'.$name;
        $slot->save();

        return $slot;
    }

    /** Unsaved custom slot with a unique key made from $key (or the label). */
    private static function newCustom(string $label, ?string $key = null, ?string $recommendedSize = null): self
    {
        $base = Str::limit(Str::slug($key ?: $label) ?: 'photo', 30, '');
        $key = $base;
        while (static::where('key', $key)->exists()) {
            $key = $base.'-'.Str::lower(Str::random(4));
        }

        return new static([
            'key' => $key,
            'label' => Str::limit($label, 250, ''),
            'recommended_size' => $recommendedSize,
            'is_custom' => true,
            'sort_order' => (int) static::max('sort_order') + 1,
        ]);
    }

    /** Put an uploaded photo in the slot (replacing a previous upload). Doesn't save the model. */
    public function storeUpload(UploadedFile $file): void
    {
        $name = $this->key.'-'.Str::lower(Str::random(6)).'.'.$file->extension();
        $file->move(public_path(self::UPLOAD_DIR), $name);
        $this->deleteUpload();
        $this->path = self::UPLOAD_DIR.'/'.$name;
    }

    /** Remove the uploaded file (only ever inside the uploads folder). Doesn't save the model. */
    public function deleteUpload(): void
    {
        if ($this->path && str_starts_with($this->path, self::UPLOAD_DIR.'/') && ! str_contains($this->path, '..')
            && is_file(public_path($this->path))) {
            unlink(public_path($this->path));
        }
        $this->path = null;
    }

    /**
     * Where each slot is used, keyed by slot key: ["Cruise “Red Sea Discovery” (gallery)", …].
     *
     * @return array<string, list<string>>
     */
    public static function usageMap(): array
    {
        $map = [];
        $add = function (?string $key, string $where) use (&$map) {
            if ($key) {
                $map[$key][] = $where;
            }
        };

        foreach (Ship::all() as $ship) {
            $add($ship->image_slot, "Ship “{$ship->name}” (side view)");
            $add($ship->hero_image_slot, "Ship “{$ship->name}” (hero)");
        }
        foreach (ShipZone::all() as $zone) {
            $add($zone->image_slot, "Life on board “{$zone->title}”");
        }
        foreach (Destination::all() as $destination) {
            $add($destination->image_slot, "Destination “{$destination->name}”");
        }
        foreach (Cruise::withTrashed()->get() as $cruise) {
            $add($cruise->image_slot, "Cruise “{$cruise->name}” (card)");
            foreach ($cruise->gallery_slots ?? [] as $key) {
                $add($key, "Cruise “{$cruise->name}” (gallery)");
            }
        }
        foreach (CabinCategory::all() as $cabin) {
            foreach ($cabin->image_slots ?? [] as $key) {
                $add($key, "Cabin “{$cabin->name_en}”");
            }
        }
        foreach ([...array_values(config('home.sections')), ...collect(config('pages'))->flatMap(fn ($p) => array_values($p['sections']))->all()] as $section) {
            $add($section['image']['slot'] ?? null, 'Page section default photo');
        }
        foreach (\App\Support\ScDataPresenter::SCRIPT_SCENES as $scene) {
            $add(\App\Support\ScDataPresenter::SCENES[$scene] ?? null, 'Website template (fallback photo)');
        }

        return array_map(fn ($list) => array_values(array_unique($list)), $map);
    }

    /** File name the template expects for a dropped-in official photo. */
    public function officialPath(): string
    {
        return "assets/img/aroya/{$this->key}.jpg";
    }

    /** True when an official AROYA photo fills the slot (photos.html "✓ AROYA photo in use"). */
    public function isFilled(): bool
    {
        return $this->uploadedUrl() !== null || $this->officialFileExists();
    }

    /** Best available URL for the slot. */
    public function url(): string
    {
        return $this->candidates()[0] ?? '';
    }

    /**
     * Every usable URL in fallback order. The front end walks the rest of the chain
     * when one fails to load (SC_DATA.imageFallbacks in core.js).
     *
     * @return list<string>
     */
    public function candidates(): array
    {
        return array_values(array_filter([
            $this->uploadedUrl(),
            $this->officialFileExists() ? asset($this->officialPath()) : null,
            $this->stock_url,
            $this->fallback_path ? asset($this->fallback_path) : null,
        ]));
    }

    /**
     * Resolve slot keys to URLs in one query, keyed by slot key.
     *
     * @param  iterable<string|null>  $keys
     * @return array<string, string>
     */
    public static function urls(iterable $keys): array
    {
        $keys = collect($keys)->filter()->unique()->values();

        return static::whereIn('key', $keys)->get()
            ->mapWithKeys(fn (ImageSlot $slot) => [$slot->key => $slot->url()])
            ->all();
    }

    private function uploadedUrl(): ?string
    {
        return $this->path && is_file(public_path($this->path)) ? asset($this->path) : null;
    }

    private function officialFileExists(): bool
    {
        return is_file(public_path($this->officialPath()));
    }
}
