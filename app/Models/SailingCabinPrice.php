<?php

namespace App\Models;

use App\Enums\TravellerBand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SailingCabinPrice extends Model
{
    protected $fillable = [
        'sailing_id', 'cabin_category_id', 'price_adult', 'price_child', 'price_infant', 'capacity', 'is_sold_out',
    ];

    protected function casts(): array
    {
        return [
            'price_adult' => 'decimal:2',
            'price_child' => 'decimal:2',
            'price_infant' => 'decimal:2',
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

    public function priceFor(TravellerBand $band): float
    {
        return (float) $this->{'price_'.$band->value};
    }
}
