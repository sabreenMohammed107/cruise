<?php

namespace App\Models;

use App\Enums\OfferAgentMode;
use App\Enums\OfferAudience;
use App\Enums\OfferTarget;
use App\Enums\OfferType;
use App\Models\Concerns\HasArabicCopy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A time-limited offer (Admin → Pricing & offers → Offers), priced by App\Services\PricingService::quoteBooking().
 *
 *   scope       every cruise · one cruise (all its sailings) · one sailing — the most specific one wins
 *   target      fare: the whole cabin price · age: each paying guest of an age range · third_guest / second_guest:
 *               what that guest adds · single: the single supplement of a guest alone in a cabin · free_nights:
 *               nights off the cabin price · upgrade: the cabin at the price of a cheaper cabin (upgrade_from)
 *   type        percent · fixed (per cabin or per guest) · free (all of it) · fixed_price (what is left to pay)
 *   conditions  both optional, both must be met (PricingService::selectOffers(), SC.selectOffers in core.js):
 *                 cabin_categories  only these cabin types (slugs)
 *                 nationalities     every traveller of the cabin for cabin offers, the guest's own for age offers
 *   limits      max_discount (cap per booking) and / or max_uses (bookings)
 *   window      starts_on (empty: now) to ends_on, both days included
 *   audience    all · b2c (everyone but agents) · b2b (agents); agent_mode: on top of the commission, or instead of it
 *   promo       combinable: can a promo code be used with it
 */
class Offer extends Model
{
    use HasArabicCopy;

    public const TRANSLATABLE = ['name'];

    protected $fillable = [
        'name', 'cruise_id', 'sailing_id', 'target', 'min_age', 'max_age', 'free_nights', 'upgrade_from', 'type', 'value', 'max_discount', 'max_uses',
        'cabin_categories', 'nationalities', 'starts_on', 'ends_on', 'audience', 'agent_mode', 'combinable', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target' => OfferTarget::class,
            'type' => OfferType::class,
            'audience' => OfferAudience::class,
            'agent_mode' => OfferAgentMode::class,
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'free_nights' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'cabin_categories' => 'array',
            'nationalities' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'combinable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function cruise(): BelongsTo
    {
        return $this->belongsTo(Cruise::class)->withTrashed();
    }

    public function sailing(): BelongsTo
    {
        return $this->belongsTo(Sailing::class);
    }

    /** Active, inside its dates (both days included) and with bookings left. */
    public function scopeLive(Builder $query, ?Carbon $at = null): void
    {
        $day = ($at ?? now())->toDateString();
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $day))
            ->whereDate('ends_on', '>=', $day)
            ->where(fn (Builder $q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'));
    }

    /** 2 = one sailing, 1 = one cruise, 0 = every cruise: the higher wins, then the newest offer. */
    public function level(): int
    {
        return $this->sailing_id ? 2 : ($this->cruise_id ? 1 : 0);
    }

    public function covers(Sailing $sailing): bool
    {
        return match ($this->level()) {
            2 => (int) $this->sailing_id === (int) $sailing->id,
            1 => (int) $this->cruise_id === (int) $sailing->cruise_id,
            default => true,
        };
    }

    public function isFor(bool $agent): bool
    {
        return match ($this->audience) {
            OfferAudience::Customers => ! $agent,
            OfferAudience::Agents => $agent,
            default => true,
        };
    }

    public function fitsAge(int $years): bool
    {
        return ($this->min_age === null || $years >= $this->min_age) && ($this->max_age === null || $years <= $this->max_age);
    }

    public function fitsCabin(string $slug): bool
    {
        return ! $this->cabin_categories || in_array($slug, $this->cabin_categories, true);
    }

    /** No nationality condition, or every given nationality is on the list (and at least one is given). */
    public function fitsNationalities(array $nationalities): bool
    {
        if (! $this->nationalities) {
            return true;
        }
        $given = array_values(array_filter($nationalities));

        return $given !== [] && array_diff($given, $this->nationalities) === [];
    }

    public function replacesCommission(): bool
    {
        return $this->agent_mode === OfferAgentMode::Replace;
    }

    /**
     * The discount on an amount: a percent of it, the fixed value per unit (never more than the amount), all of it,
     * or what is above a final price of value per unit. Not rounded: PricingService rounds each offer's total.
     */
    public function amountOff(float $base, int $units = 1): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        return match ($this->type) {
            OfferType::Free => $base,
            OfferType::Fixed => min((float) $this->value * $units, $base),
            OfferType::FixedPrice => max(0.0, $base - (float) $this->value * $units),
            default => $base * (float) $this->value / 100,
        };
    }

    /** Admin list: Live / Scheduled / Ended / Used up / Off. */
    public function getStatusLabelAttribute(): string
    {
        $today = today();

        return match (true) {
            ! $this->is_active => 'Off',
            $this->ends_on && $this->ends_on->lt($today) => 'Ended',
            $this->max_uses !== null && $this->used_count >= $this->max_uses => 'Used up',
            $this->starts_on && $this->starts_on->gt($today) => 'Scheduled',
            default => 'Live',
        };
    }

    /** Admin list: "Red Sea Discovery · RSD-261213", "Red Sea Discovery (all dates)" or "All cruises". */
    public function getScopeLabelAttribute(): string
    {
        return match ($this->level()) {
            2 => ($this->cruise?->name ?? '').' · '.($this->sailing?->code ?? ''),
            1 => ($this->cruise?->name ?? '').' (all dates)',
            default => 'All cruises',
        };
    }

    /** Admin list: what it takes off, e.g. "20% · Guests of an age range (2–5)". */
    public function getDiscountLabelAttribute(): string
    {
        $number = rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.');
        if ($this->target === OfferTarget::FreeNights) {
            return $this->free_nights.' free '.($this->free_nights === 1 ? 'night' : 'nights');
        }
        $amount = match ($this->type) {
            OfferType::Free => 'Free',
            OfferType::Fixed => '$'.$number.' off',
            OfferType::FixedPrice => 'Price $'.$number,
            default => $number.'%',
        };
        $detail = match ($this->target) {
            OfferTarget::Age => ' ('.($this->min_age ?? 0).'–'.($this->max_age ?? '∞').')',
            OfferTarget::Upgrade => ' (pays the '.$this->upgrade_from.' price)',
            default => '',
        };

        return "$amount · {$this->target->label()}$detail";
    }

    /** What the page scripts need (SC_DATA cruises[].sailings[].offers, read by SC.selectOffers in core.js). */
    public function toScript(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->tr('name'),
            'level' => $this->level(),
            'target' => $this->target->value,
            'minAge' => $this->min_age,
            'maxAge' => $this->max_age,
            'freeNights' => $this->free_nights,
            'upgradeFrom' => $this->upgrade_from,
            'type' => $this->type->value,
            'value' => (float) $this->value,
            'maxDiscount' => $this->max_discount === null ? null : (float) $this->max_discount,
            'cabins' => $this->cabin_categories ?: null,
            'nationalities' => $this->nationalities ?: null,
            'combinable' => $this->combinable,
            'replaceAgent' => $this->replacesCommission(),
            'endsOn' => $this->ends_on->toDateString(),
        ];
    }
}
