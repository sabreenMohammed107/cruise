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
        'size', 'balcony_size', 'beds', 'bedroom', 'features', 'images',
        'base_rate_adult', 'base_rate_child', 'base_rate_infant', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'images' => 'array',
            'base_rate_adult' => 'decimal:2',
            'base_rate_child' => 'decimal:2',
            'base_rate_infant' => 'decimal:2',
        ];
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
