<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A card inside a homepage section (e.g. "Why Safer Cruise"), in English and Arabic. */
class HomeSectionItem extends Model
{
    use HasArabicCopy;

    public const TRANSLATABLE = ['title', 'text'];

    protected $fillable = ['icon', 'tone', 'title', 'title_ar', 'text', 'text_ar', 'url', 'sort_order'];

    protected $attributes = ['tone' => 'ocean', 'sort_order' => 0];

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    /** The card's link (sections with 'item_links'), or null when it has none. */
    public function href(): ?string
    {
        return filled($this->url) ? HomeSection::href($this->url) : null;
    }

    /** Full https:// links open in a new tab; site paths and anchors don't. */
    public function isExternal(): bool
    {
        return (bool) preg_match('/^https?:\/\//i', (string) $this->url);
    }
}
