<?php

namespace App\Models;

use App\Enums\SailingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    /** Back from the cruise before today: shown in Admin → Cruises → Archive, not in the cruises list. */
    public function scopeFinished(Builder $query): void
    {
        $query->whereDate('return_date', '<', today());
    }

    /** Not back yet (departing later, or at sea today): what keeps a cruise in the admin's cruises list. */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereDate('return_date', '>=', today());
    }

    /**
     * Departing in one of these years, or in one month of them. Date ranges, not YEAR()/MONTH(), so the
     * departure_date index is used (and it works the same on MySQL and SQLite).
     *
     * @param  list<int>  $years
     */
    public function scopeDepartingIn(Builder $query, array $years, ?int $month = null): void
    {
        $query->where(function (Builder $q) use ($years, $month) {
            foreach ($years as $year) {
                $start = $month ? Carbon::create($year, $month) : Carbon::create($year);
                $end = $month ? $start->copy()->endOfMonth() : $start->copy()->endOfYear();
                $q->orWhereBetween('departure_date', [$start->toDateString(), $end->toDateString()]);
            }
        });
    }

    /** Sailings of cruises from this departure port and / or with this many nights (deleted cruises included). */
    public function scopeOfCruises(Builder $query, ?string $port, ?int $nights): void
    {
        if ($port === null && $nights === null) {
            return;
        }
        $query->whereIn('cruise_id', Cruise::withTrashed()->select('id')
            ->when($port, fn ($q) => $q->where('departure_port', $port))
            ->when($nights, fn ($q) => $q->where('nights', $nights)));
    }

    /**
     * "From, per person" on cards: the lowest Double price ÷ 2 among cabins that aren't sold out (never the Single
     * price); when every cabin is sold out, among all of them (the card shows it marked sold out). Cabins without a
     * Double price (0) are skipped unless no cabin has one. Same as SC.fromPrice / SC.sailingView in core.js.
     */
    public function fromPrice(): ?float
    {
        $prices = $this->isFullySoldOut() ? $this->cabinPrices : $this->cabinPrices->where('is_sold_out', false);
        $doubles = $prices->pluck('price_double')->map(fn ($p) => (float) $p);
        if ($doubles->isEmpty()) {
            return null;
        }
        $priced = $doubles->filter(fn ($p) => $p > 0);

        return ($priced->isNotEmpty() ? $priced : $doubles)->min() / 2;
    }

    /** Has cabin prices, and every one of those cabins is sold out. */
    public function isFullySoldOut(): bool
    {
        return $this->cabinPrices->isNotEmpty() && $this->cabinPrices->every(fn ($p) => $p->is_sold_out);
    }
}
