<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CabinCategory extends Model
{
    use HasArabicCopy;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['badge', 'short_description', 'description', 'size', 'balcony_size', 'beds', 'bedroom', 'features'];

    protected $fillable = [
        'ship_id', 'slug', 'name_en', 'name_ar', 'badge', 'short_description', 'description', 'max_guests',
        'size', 'balcony_size', 'beds', 'bedroom', 'features', 'images', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'images' => 'array',
        ];
    }

    /**
     * Guests this cabin takes, adults + children + infants together: its own limit, never more than
     * cruise.max_cabin_guests (3).
     */
    public function capacity(): int
    {
        return min((int) $this->max_guests, (int) config('cruise.max_cabin_guests'));
    }

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    public function sailingPrices(): HasMany
    {
        return $this->hasMany(SailingCabinPrice::class);
    }
}
