<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One cabin of a booking ("Cabin 1", "Cabin 2", …): its category, its guests and its own tiered price.
 * The booking holds the totals, the discount and the contact details.
 */
class BookingCabin extends Model
{
    protected $fillable = [
        'booking_id', 'position', 'cabin_category_id', 'adults', 'children', 'infants', 'fare_total', 'fees_total', 'price_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'fare_total' => 'decimal:2',
            'fees_total' => 'decimal:2',
            'price_snapshot' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function cabinCategory(): BelongsTo
    {
        return $this->belongsTo(CabinCategory::class);
    }

    public function travellers(): HasMany
    {
        return $this->hasMany(BookingTraveller::class)->orderByRaw("CASE band WHEN 'adult' THEN 1 WHEN 'child' THEN 2 ELSE 3 END")->orderBy('slot');
    }

    public function guestCounts(): array
    {
        return ['adult' => $this->adults, 'child' => $this->children, 'infant' => $this->infants];
    }

    /** Cabin name in the page language. */
    public function name(): string
    {
        $cabin = $this->cabinCategory;

        return app()->getLocale() === 'ar' ? ($cabin->name_ar ?: $cabin->name_en) : $cabin->name_en;
    }
}
