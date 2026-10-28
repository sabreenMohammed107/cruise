<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Services\CurrencyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'sailing_id', 'cabin_category_id', 'promo_code_id', 'status', 'payment_status',
        'adults', 'children', 'infants', 'contact_email', 'contact_dial', 'contact_phone',
        'fare_total', 'fees_total', 'discount_total', 'grand_total', 'amount_paid', 'currency', 'display_currency', 'exchange_rate', 'locale',
        'price_snapshot', 'confirmed_at', 'paid_at', 'cancelled_at', 'admin_notes', 'seller_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'fare_total' => 'decimal:2',
            'fees_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'price_snapshot' => 'array',
            'confirmed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * Same shape as the template's reference: SC{yymm of departure}-{5 chars}, e.g. SC2611-K4P2Q.
     */
    public static function generateReference(CarbonInterface $departure): string
    {
        do {
            $ref = 'SC'.$departure->format('ym').'-'.Str::upper(Str::random(5));
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The seller following up this booking (Admin → Bookings → Assigned seller). */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function sailing(): BelongsTo
    {
        return $this->belongsTo(Sailing::class);
    }

    /** The first cabin's category (kept on the booking for lists and exports; see cabins() for all of them). */
    public function cabinCategory(): BelongsTo
    {
        return $this->belongsTo(CabinCategory::class);
    }

    /** The cabins of this booking, in the order the guest added them (step 15). */
    public function cabins(): HasMany
    {
        return $this->hasMany(BookingCabin::class)->orderBy('position');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function travellers(): HasMany
    {
        // grouped by cabin (step 15), then adults, children, infants
        return $this->hasMany(BookingTraveller::class)->orderBy('booking_cabin_id')->orderByRaw("CASE band WHEN 'adult' THEN 1 WHEN 'child' THEN 2 ELSE 3 END")->orderBy('slot');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('received_at');
    }

    /** Dashboard "Upcoming" tab: confirmed, departing today or later. */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', BookingStatus::Confirmed)
            ->whereHas('sailing', fn (Builder $q) => $q->whereDate('departure_date', '>=', today()));
    }

    /** Dashboard "Past" tab: confirmed, already departed. */
    public function scopePast(Builder $query): void
    {
        $query->where('status', BookingStatus::Confirmed)
            ->whereHas('sailing', fn (Builder $q) => $q->whereDate('departure_date', '<', today()));
    }

    /** Dashboard "Cancelled" tab. */
    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', BookingStatus::Cancelled);
    }

    /** Admin follow-up list: confirmed bookings the team still has to collect payment for. */
    public function scopeAwaitingPayment(Builder $query): void
    {
        $query->where('status', BookingStatus::Confirmed)
            ->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::Partial]);
    }

    /**
     * The cabins in English for the panel and team emails: "Balcony Cabin", or "2 × Balcony Cabin, Interior Cabin".
     * Falls back to the booking's cabin for rows saved before step 15.
     */
    public function cabinSummary(): string
    {
        $names = $this->cabins->isNotEmpty()
            ? $this->cabins->map(fn (BookingCabin $c) => $c->cabinCategory->name_en)
            : collect([$this->cabinCategory?->name_en]);

        return $names->filter()->countBy()->map(fn ($n, $name) => $n > 1 ? "$n × $name" : $name)->join(', ');
    }

    public function guestCounts(): array
    {
        return ['adult' => $this->adults, 'child' => $this->children, 'infant' => $this->infants];
    }

    /** "en" or "ar": the website language the guest booked in (step 20). */
    public function emailLocale(): string
    {
        return in_array($this->locale, ['en', 'ar'], true) ? $this->locale : 'en';
    }

    /** The primary guest (cabin 1, first adult), who also gave the contact details. */
    public function primaryTraveller(): ?BookingTraveller
    {
        return $this->travellers->firstWhere('is_primary', true) ?? $this->travellers->first();
    }

    /** Booked while the website showed another currency than USD (EGP): guest screens show that currency. */
    public function inDisplayCurrency(): bool
    {
        return ($this->display_currency ?: CurrencyService::BASE) !== CurrencyService::BASE;
    }

    /**
     * A USD amount of this booking as the guest saw it: "EGP 129,563" at the rate frozen when booking, or "$2,310".
     * Guest voucher, guest email, "My bookings". The panel and the team email use Format::money (USD).
     */
    public function money(float|int|string|null $usd): string
    {
        return app(CurrencyService::class)->format($usd, $this->display_currency ?: CurrencyService::BASE, (float) $this->exchange_rate ?: 1.0);
    }

    public function amountDue(): float
    {
        return max(0, (float) $this->grand_total - (float) $this->amount_paid);
    }

    /**
     * Recompute amount_paid, payment_status and paid_at from the recorded payments and refunds.
     * Call after adding or removing a payment.
     */
    public function syncPaymentTotals(): void
    {
        $payments = $this->payments()->get();
        $paid = (float) $payments->where('type', PaymentType::Payment)->sum('amount');
        $refunded = (float) $payments->where('type', PaymentType::Refund)->sum('amount');
        $net = $paid - $refunded;

        $status = match (true) {
            // Fully refunded, or a cancelled booking refunded minus the cancellation penalty.
            $refunded > 0 && ($net <= 0 || $this->status === BookingStatus::Cancelled) => PaymentStatus::Refunded,
            $net >= (float) $this->grand_total => PaymentStatus::Paid,
            $net > 0 => PaymentStatus::Partial,
            default => PaymentStatus::Unpaid,
        };

        $this->forceFill([
            'amount_paid' => $net,
            'payment_status' => $status,
            'paid_at' => $status === PaymentStatus::Paid
                ? $payments->where('type', PaymentType::Payment)->max('received_at')
                : null,
        ])->save();
    }
}
