<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;

/**
 * English / Arabic text for the current locale (App\Http\Middleware\SetLocale reads the visitor's
 * sc_lang cookie, which the header's عربي button sets). Escaped; falls back to the other language
 * when one is empty.
 */
class Bi
{
    public static function html(?string $en, ?string $ar): HtmlString
    {
        $en = (string) $en;
        $ar = (string) $ar;
        $text = app()->getLocale() === 'ar' ? ($ar !== '' ? $ar : $en) : ($en !== '' ? $en : $ar);

        return new HtmlString(e($text));
    }

    /** A fixed website word from lang/{en,ar}/*.php, e.g. Bi::trans('site.search.guests') — Blade: @bi('…'). */
    public static function trans(string $key, array $replace = []): HtmlString
    {
        return static::html(__($key, $replace, 'en'), __($key, $replace, 'ar'));
    }

    /** Both language files of a group, for the page scripts (SC_DATA.i18n). */
    public static function group(string $group): array
    {
        return ['en' => trans($group, [], 'en'), 'ar' => trans($group, [], 'ar')];
    }

    /**
     * Words for the page scripts (SC_DATA.i18n): lang/*\/site.php at the top level, lang/*\/ui.php under "ui".
     *
     * @return array{en: array, ar: array}
     */
    public static function scriptStrings(): array
    {
        $for = fn (string $locale) => trans('site', [], $locale) + ['ui' => trans('ui', [], $locale)];

        return ['en' => $for('en'), 'ar' => $for('ar')];
    }

    public static function isArabic(): bool
    {
        return app()->getLocale() === 'ar';
    }

    /**
     * A port as SC.port() shows it: "Safaga, Egypt" → "سفاجا، مصر" in Arabic, from the destinations'
     * Arabic names. English, or a name without an Arabic twin, is returned unchanged.
     */
    public static function port(?string $port): string
    {
        if (! static::isArabic() || ! $port) {
            return (string) $port;
        }
        static $names = null;
        $names ??= \App\Models\Destination::all()->flatMap(fn ($d) => array_filter([
            $d->name => $d->name_ar, $d->country => $d->country_ar,
        ]))->put('At sea', __('site.at_sea'))->all();

        return collect(explode(', ', $port))->map(fn ($part) => $names[$part] ?? $part)->join('، ');
    }

    /** A traveller-form value (gender, nationality) in the page language; the stored value is the English key. */
    public static function value(string $group, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Lang::has("site.$group.$value") ? __("site.$group.$value") : $value;
    }
}
