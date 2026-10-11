<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\TravellerBand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTraveller extends Model
{
    protected $fillable = [
        'booking_id', 'band', 'slot', 'is_primary', 'first_name', 'last_name', 'date_of_birth', 'gender', 'nationality',
    ];

    protected function casts(): array
    {
        return [
            'band' => TravellerBand::class,
            'gender' => Gender::class,
            'is_primary' => 'boolean',
            'date_of_birth' => 'date',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
