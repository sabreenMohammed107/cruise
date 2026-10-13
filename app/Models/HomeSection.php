<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use App\Support\MediaLibrary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Editable copy of one homepage section (config/home.php lists the sections and their fields).
 * Every text column has an Arabic twin ({field}_ar); links and the photo are shared.
 *
 * Image resolution order: uploaded photo (image_path, relative to public/) → the section's
 * default from config (an asset path or a photo of the ship) → none.
 */
class HomeSection extends Model
{
    use HasArabicCopy;

    public const TRANSLATABLE = ['eyebrow', 'title', 'body', 'primary_label', 'secondary_label', 'image_alt', 'caption_label', 'caption_value'];

    protected $fillable = [
        'page', 'key', 'eyebrow', 'eyebrow_ar', 'title', 'title_ar', 'body', 'body_ar',
        'primary_label', 'primary_label_ar', 'primary_url', 'secondary_label', 'secondary_label_ar', 'secondary_url',
        'image_path', 'image_alt', 'image_alt_ar', 'caption_label', 'caption_label_ar', 'caption_value', 'caption_value_ar',
        'is_visible',
    ];

    protected $attributes = ['page' => 'home', 'is_visible' => true];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(HomeSectionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Settings of a page: name, public route and sections. The homepage is in config/home.php,
     * the other pages in config/pages.php.
     *
     * @return array{name: string, route: ?string, sections: array}|null
     */
    public static function pageConfig(string $page): ?array
    {
        if ($page === 'home') {
            return ['name' => 'Homepage', 'route' => 'home', 'sections' => config('home.sections')];
        }

        return config('pages.'.$page);
    }

    /** @return list<string> page keys: home, then config/pages.php in order */
    public static function pages(): array
    {
        return ['home', ...array_keys(config('pages', []))];
    }

    /**
     * Every section of a page in page order, keyed by key. A section with no row (database not
     * seeded yet) is built from its config defaults, so the page always renders.
     *
     * @return \Illuminate\Support\Collection<string, HomeSection>
     */
    public static function forPage(string $page): \Illuminate\Support\Collection
    {
        $rows = static::with('items')->where('page', $page)->get()->keyBy('key');

        return collect(static::pageConfig($page)['sections'] ?? [])->map(function (array $config, string $key) use ($rows, $page) {
            if ($rows->has($key)) {
                return $rows[$key];
            }
            $section = new static(['page' => $page, 'key' => $key] + $config['content']);
            $section->setRelation('items', new Collection(
                array_map(fn (array $item) => new HomeSectionItem($item), $config['items'] ?? [])
            ));

            return $section;
        });
    }

    /** @return \Illuminate\Support\Collection<string, HomeSection> */
    public static function forHome(): \Illuminate\Support\Collection
    {
        return static::forPage('home');
    }

    /** Section settings from config/home.php or config/pages.php. */
    public function config(?string $setting = null, mixed $default = null): mixed
    {
        $section = static::pageConfig($this->page ?? 'home')['sections'][$this->key] ?? null;

        return $setting === null ? ($section ?? $default) : data_get($section, $setting, $default);
    }

    /** Admin label for a column: the section's own name for it (config 'labels') or the default. */
    public function label(string $column, string $default): string
    {
        return $this->config('labels.'.$column, $default);
    }

    public function editUrl(): string
    {
        return $this->page === 'home'
            ? route('admin.home-sections.edit', $this->key)
            : route('admin.pages.edit', [$this->page, $this->key]);
    }

    public function name(): string
    {
        return $this->config('name', $this->key);
    }

    /** Whether the admin can edit a field group (eyebrow, title, body, primary, secondary, image, caption, items). */
    public function supports(string $field): bool
    {
        return in_array($field, $this->config('fields', []), true);
    }

    /** Whether the section's buttons take a link (the search and newsletter buttons submit a form). */
    public function hasLinks(): bool
    {
        return $this->config('link', true);
    }

    public function hasUpload(): bool
    {
        return $this->image_path !== null && is_file(public_path($this->image_path));
    }

    public function imageUrl(): ?string
    {
        if ($this->hasUpload()) {
            return asset($this->image_path);
        }
        $default = $this->config('image', []);
        if (isset($default['asset'])) {
            return asset($default['asset']);
        }
        if (isset($default['ship'])) {
            return MediaLibrary::url(Ship::first()?->getAttribute($default['ship']));
        }

        return null;
    }

    public function primaryHref(): string
    {
        return static::href($this->primary_url);
    }

    public function secondaryHref(): string
    {
        return static::href($this->secondary_url);
    }

    /** Stored links are site paths (/cruises), anchors (#ship) or full URLs. */
    public static function href(?string $url): string
    {
        if ($url === null || $url === '') {
            return '#';
        }

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : $url;
    }
}
