<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An offline payment or refund recorded by the team. The site itself takes no online payments.
 * It can carry several receipts (PaymentReceipt: private files, opened through the admin panel).
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

    protected static function booted(): void
    {
        // One by one, so each receipt deletes its file (the database cascade alone would leave the files)
        static::deleting(fn (Payment $payment) => $payment->receipts->each->delete());
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class)->orderBy('id');
    }
}
