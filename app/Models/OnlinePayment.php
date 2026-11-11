<?php

namespace App\Models;

use App\Enums\OnlinePaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An online checkout attempt (EasyKash). Becomes a Payment only when the gateway confirms it (payment_id).
 */
class OnlinePayment extends Model
{
    protected $fillable = [
        'booking_id', 'gateway', 'customer_reference', 'gateway_reference', 'status', 'amount', 'currency',
        'amount_usd', 'exchange_rate', 'checkout_url', 'gateway_response', 'payment_id', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OnlinePaymentStatus::class,
            'amount' => 'decimal:2',
            'amount_usd' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** Numeric (some gateways accept digits only) and unique: yymmddHHiiss + 4 random digits. */
    public static function generateReference(): string
    {
        do {
            $ref = now()->format('ymdHis').random_int(1000, 9999);
        } while (static::where('customer_reference', $ref)->exists());

        return $ref;
    }
}
