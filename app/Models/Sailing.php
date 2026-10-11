<?php

namespace App\Models;

use App\Enums\SailingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sailing extends Model
{
    protected $fillable = ['cruise_id', 'code', 'departure_date', 'return_date', 'price_factor', 'status'];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'price_factor' => 'decimal:2',
            'status' => SailingStatus::class,
        ];
    }

    public function cruise(): BelongsTo
    {
        return $this->belongsTo(Cruise::class);
    }

    public function cabinPrices(): HasMany
    {
        return $this->hasMany(SailingCabinPrice::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Bookable departures: open and leaving after today (the template's daysUntil > 0).
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', SailingStatus::Open)
            ->whereDate('departure_date', '>', today());
    }

    /**
     * Lowest adult price among cabins that aren't sold out ("from" price on cards).
     */
    public function fromPrice(): ?float
    {
        $min = $this->cabinPrices->where('is_sold_out', false)->min('price_adult');

        return $min === null ? null : (float) $min;
    }
}
