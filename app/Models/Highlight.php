<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A cruise highlight (Admin → Cruises → Highlights), e.g. "Family friendly". Cruises keep the keys of theirs in
 * cruises.tags. Shown in the cruises-list filter, and as a tag on card photos / the cruise page when switched on.
 */
class Highlight extends Model
{
    public const TONES = ['ocean' => 'Blue', 'lagoon' => 'Turquoise', 'sun' => 'Orange'];

    protected $fillable = ['key', 'name_en', 'name_ar', 'show_on_cards', 'show_on_cruise_page', 'tone', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'show_on_cards' => 'boolean',
            'show_on_cruise_page' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name_en');
    }

    /** The name in the current language (Arabic falls back to English). */
    public function label(): string
    {
        return app()->getLocale() === 'ar' && filled($this->name_ar) ? $this->name_ar : $this->name_en;
    }

    /** Whether a cruise (soft-deleted ones included) has this highlight. */
    public function inUse(): bool
    {
        return Cruise::withTrashed()->pluck('tags')->flatten()->contains($this->key);
    }
}
