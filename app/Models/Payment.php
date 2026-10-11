<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An offline payment or refund recorded by the team. The site itself takes no online payments.
 */
class Payment extends Model
{
    protected $fillable = [
        'booking_id', 'type', 'method', 'amount', 'currency', 'reference', 'notes', 'received_at', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
