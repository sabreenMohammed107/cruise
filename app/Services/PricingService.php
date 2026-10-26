<?php

namespace App\Services;

use App\Enums\PromoType;
use App\Enums\TravellerBand;
use App\Models\Cruise;
use App\Models\PromoCode;
use App\Models\Sailing;
use App\Models\SailingCabinPrice;
use Illuminate\Support\Collection;

/**
 * Tiered occupancy pricing, the server side of SC.prices / SC.quote in public/assets/js/core.js.
 * The client-side quote is display-only; this class is the source of truth for totals.
 *
 * Every Sailing × Cabin has three cabin prices for the whole cruise, set in the admin panel:
 *   single  the cabin with 1 paying guest
 *   double  the cabin with 2 paying guests
 *   extra   the 3rd guest (adult or child)
 * Infants are free but take a place: a cabin holds at most 3 guests (config cruise.max_cabin_guests).
 */
class PricingService
{
    /**
     * Fare lines for a party in one cabin. Adults and children are paying guests:
     *
     *   1 paying guest   → single
     *   2 paying guests  → double
     *   3 paying guests  → double + extra
     *   infants          → free
     *
     * @param  array{adult?: int, child?: int, infant?: int}  $guests
     * @return list<array{band: string, label: string, unit: float, qty: int, total: float}>
     */
    public function fareLines(SailingCabinPrice $price, array $guests): array
    {
        $tiers = $price->tiers();
        $paying = (int) ($guests['adult'] ?? 0) + (int) ($guests['child'] ?? 0);
        $infants = (int) ($guests['infant'] ?? 0);
        $line = fn (string $tier, float $unit, int $qty) => [
            'band' => $tier, 'label' => __("site.pricing.{$tier}"), 'unit' => $unit, 'qty' => $qty, 'total' => $unit * $qty,
        ];

        $lines = [];
        if ($paying === 1) {
            $lines[] = $line('single', $tiers['single'], 1);
        }
        if ($paying >= 2) {
            $lines[] = $line('double', $tiers['double'], 1);
        }
        if ($paying > 2) {
            $lines[] = $line('extra', $tiers['extra'], $paying - 2);
        }
        if ($infants > 0) {
            $lines[] = $line('infant', 0.0, $infants);
        }

        return $lines;
    }

    /**
     * Full quote for a party in one cabin.
     *
     *   fare     = Σ fare lines (see fareLines())
     *   discount = promo applied to the fare only
     *   total    = fare − discount
     *
     * Cabin prices include tax: there are no separate taxes or port fees since step 21. "fees" stays in the quote
     * (always 0) so stored snapshots and older screens keep their shape.
     *
     * @param  array{adult?: int, child?: int, infant?: int}  $guests
     */
    public function quote(SailingCabinPrice $price, array $guests, ?PromoCode $promo = null): array
    {
        $lines = $this->fareLines($price, $guests);
        $fare = (float) array_sum(array_column($lines, 'total'));
        $feesTotal = 0.0;
        $count = 0;
        foreach (TravellerBand::cases() as $band) {
            $count += (int) ($guests[$band->value] ?? 0);
        }

        $discount = $promo ? $this->discount($promo, $fare) : 0.0;

        return [
            'prices' => $price->tiers(),
            'lines' => $lines,
            'fare' => $fare,
            'fees' => $feesTotal,
            'discount' => $discount,
            'promo' => $promo ? ['code' => $promo->code, 'type' => $promo->type->value, 'value' => (float) $promo->value, 'label' => $promo->label] : null,
            'total' => $fare + $feesTotal - $discount,
            'count' => $count,
        ];
    }

    /**
     * Quote for a booking of one or more cabins on the same sailing (step 15). Each cabin is priced on its own
     * (tiered: single / double / double + extra, infants free, tax included); the promo applies once, to the
     * total fare. Same shape as quote() plus "cabins", so a one-cabin booking gives the same numbers.
     *
     *   fare     = Σ cabin fares          (fees = 0, see quote())
     *   discount = promo on the total fare
     *   total    = fare − discount
     *
     * @param  list<array{price: SailingCabinPrice, guests: array{adult?: int, child?: int, infant?: int}}>  $cabins
     */
    public function quoteBooking(array $cabins, ?PromoCode $promo = null): array
    {
        $parts = [];
        foreach (array_values($cabins) as $i => $cabin) {
            $price = $cabin['price']->loadMissing('cabinCategory');
            $quote = $this->quote($price, $cabin['guests']);
            $parts[] = [
                'position' => $i + 1,
                'cabin' => $price->cabinCategory->slug,
                'name' => $price->cabinCategory->name_en,
                'guests' => array_map('intval', array_merge(['adult' => 0, 'child' => 0, 'infant' => 0], $cabin['guests'])),
                'prices' => $quote['prices'],
                'lines' => $quote['lines'],
                'fare' => $quote['fare'],
                'fees' => $quote['fees'],
                'total' => $quote['fare'] + $quote['fees'],
                'count' => $quote['count'],
            ];
        }

        $fare = (float) array_sum(array_column($parts, 'fare'));
        $fees = (float) array_sum(array_column($parts, 'fees'));
        $discount = $promo ? $this->discount($promo, $fare) : 0.0;

        // Every fare line, tagged with its cabin, for screens made before step 15
        $lines = [];
        foreach ($parts as $part) {
            foreach ($part['lines'] as $line) {
                $lines[] = $line + ['cabin' => $part['position']];
            }
        }

        return [
            'prices' => $parts[0]['prices'] ?? [],
            'lines' => $lines,
            'cabins' => $parts,
            'fare' => $fare,
            'fees' => $fees,
            'discount' => $discount,
            'promo' => $promo ? ['code' => $promo->code, 'type' => $promo->type->value, 'value' => (float) $promo->value, 'label' => $promo->label] : null,
            'total' => $fare + $fees - $discount,
            'count' => (int) array_sum(array_column($parts, 'count')),
        ];
    }

    /**
     * Starting prices for a new sailing: the price rows of the cruise's latest sailing that has them,
     * keyed by cabin_category_id. Empty when the cruise has no priced sailing yet.
     *
     * @return Collection<int, SailingCabinPrice>
     */
    public function latestPrices(Cruise $cruise, ?Sailing $except = null): Collection
    {
        $sailing = $cruise->sailings()
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->whereHas('cabinPrices')
            ->reorder('departure_date', 'desc')
            ->first();

        return $sailing ? $sailing->cabinPrices()->get()->keyBy('cabin_category_id') : collect();
    }

    private function discount(PromoCode $promo, float $fare): float
    {
        return match ($promo->type) {
            PromoType::Percent => $this->round5($fare * (float) $promo->value / 100),
            PromoType::Fixed => min((float) $promo->value, $fare),
        };
    }

    /**
     * Math.round(n / 5) * 5, using JS Math.round semantics (floor(x + 0.5)).
     * PHP's round() pre-rounds and can disagree with the template at .5 boundaries.
     */
    public function round5(float $n): float
    {
        return floor($n / 5 + 0.5) * 5;
    }
}
