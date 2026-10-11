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

    protected $fillable = ['icon', 'tone', 'title', 'title_ar', 'text', 'text_ar', 'sort_order'];

    protected $attributes = ['tone' => 'ocean', 'sort_order' => 0];

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }
}
