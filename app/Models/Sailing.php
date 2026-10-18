<?php

namespace App\Models;

use App\Enums\SailingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sailing extends Model
{
    protected $fillable = ['cruise_id', 'code', 'departure_date', 'return_date', 'status'];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'status' => SailingStatus::class,
        ];
    }

    /** Includes soft-deleted cruises so existing bookings keep their cruise; bookable paths check trashed(). */
    public function cruise(): BelongsTo
    {
        return $this->belongsTo(Cruise::class)->withTrashed();
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
     * "From, per person" on cards: the lowest double price among cabins that aren't sold out,
     * shown per person for two sharing (same as SC.sailingView in core.js).
     */
    public function fromPrice(): ?float
    {
        $min = $this->cabinPrices->where('is_sold_out', false)->min('price_double');

        return $min === null ? null : (float) $min / 2;
    }
}
