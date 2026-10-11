<?php

namespace App\Models\Concerns;

use App\Support\Bi;
use Illuminate\Support\HtmlString;

/**
 * English + Arabic text columns ({field} and {field}_ar). The model lists its translated
 * columns in `public const TRANSLATABLE = [...]`.
 *
 * tr() returns the value for the current locale (set from the visitor's language cookie by
 * App\Http\Middleware\SetLocale). Empty Arabic falls back to English, and the other way round.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasArabicCopy
{
    /** Arabic twins are fillable, and JSON lists (story, features, …) are cast like their English column. */
    public function initializeHasArabicCopy(): void
    {
        foreach (static::translatable() as $column) {
            $this->mergeFillable([$column.'_ar']);
            if ($this->hasCast($column, ['array', 'json'])) {
                $this->mergeCasts([$column.'_ar' => 'array']);
            }
        }
    }

    public function tr(string $field): mixed
    {
        $en = $this->getAttribute($field);
        $ar = $this->getAttribute($field.'_ar');

        if (app()->getLocale() === 'ar') {
            return filled($ar) ? $ar : $en;
        }

        return filled($en) ? $en : $ar;
    }

    /** tr(), escaped, for Blade. */
    public function bi(string $field): HtmlString
    {
        return Bi::html($this->getAttribute($field), $this->getAttribute($field.'_ar'));
    }

    /** True when either language has text. */
    public function filledBi(string $field): bool
    {
        return filled($this->getAttribute($field)) || filled($this->getAttribute($field.'_ar'));
    }

    /** @return list<string> */
    public static function translatable(): array
    {
        return defined(static::class.'::TRANSLATABLE') ? static::TRANSLATABLE : [];
    }
}
