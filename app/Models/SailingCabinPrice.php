<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tiered occupancy prices of one cabin category on one sailing, for the whole cruise:
 * single (1 paying guest), double (2 paying guests) and extra (the 3rd guest). Infants are free.
 */
class SailingCabinPrice extends Model
{
    protected $fillable = [
        'sailing_id', 'cabin_category_id', 'price_single', 'price_double', 'price_extra', 'capacity', 'is_sold_out',
    ];

    protected function casts(): array
    {
        return [
            'price_single' => 'decimal:2',
            'price_double' => 'decimal:2',
            'price_extra' => 'decimal:2',
            'is_sold_out' => 'boolean',
        ];
    }

    public function sailing(): BelongsTo
    {
        return $this->belongsTo(Sailing::class);
    }

    public function cabinCategory(): BelongsTo
    {
        return $this->belongsTo(CabinCategory::class);
    }

    /** @return array{single: float, double: float, extra: float, infant: float} */
    public function tiers(): array
    {
        return [
            'single' => (float) $this->price_single,
            'double' => (float) $this->price_double,
            'extra' => (float) $this->price_extra,
            'infant' => 0.0,
        ];
    }
}
