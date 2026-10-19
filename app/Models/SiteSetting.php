<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use App\Support\Bi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Footer & contact details (Admin → Website content → Footer & contact). The fields and their defaults are in
 * config/site.php; a row here holds a value an admin saved (value_ar for fields in both languages).
 */
class SiteSetting extends Model
{
    use HasArabicCopy;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['value'];

    protected $fillable = ['key', 'value'];

    /** Container key of the saved rows, loaded once per request. */
    private const LOADED = 'site_settings.rows';

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }

    /** @return array<string, array> every field of config/site.php by key */
    public static function fields(): array
    {
        return collect(config('site.footer'))->collapse()->all();
    }

    private static function row(string $key): ?self
    {
        if (! app()->bound(self::LOADED)) {
            app()->instance(self::LOADED, static::all()->keyBy('key'));
        }

        return app(self::LOADED)->get($key);
    }

    /** A field's value (English for fields in both languages), or its default when never saved. */
    public static function value(string $key, string $lang = 'en'): string
    {
        $row = static::row($key);
        if ($row) {
            return (string) $row->getAttribute($lang === 'ar' ? 'value_ar' : 'value');
        }
        $default = static::fields()[$key]['default'] ?? '';

        return (string) (is_array($default) ? ($default[$lang] ?? '') : ($lang === 'ar' ? '' : $default));
    }

    /** A field in both languages, escaped, in the page language (empty Arabic shows the English). */
    public static function text(string $key): HtmlString
    {
        return Bi::html(static::value($key), static::value($key, 'ar'));
    }

    /** WhatsApp number (digits) for wa.me links; config/cruise.php if the field was emptied. */
    public static function whatsapp(): string
    {
        return preg_replace('/\D/', '', static::value('whatsapp')) ?: (string) config('cruise.whatsapp_number');
    }

    /** "+90 552 150 1111" → "tel:+905521501111". */
    public static function tel(string $number): string
    {
        return 'tel:'.(str_starts_with(trim($number), '+') ? '+' : '').preg_replace('/\D/', '', $number);
    }

    /** @return array<string, array{label: string, url: string}> social links that have an address, in config order */
    public static function socialLinks(): array
    {
        return collect(static::fields())->filter(fn ($f) => $f['social'] ?? false)
            ->map(fn ($f, $key) => ['label' => $f['label'], 'url' => static::value($key)])
            ->filter(fn ($link) => $link['url'] !== '')
            ->all();
    }

    /** Forget the loaded rows (after a save). */
    public static function flush(): void
    {
        app()->forgetInstance(self::LOADED);
    }
}
