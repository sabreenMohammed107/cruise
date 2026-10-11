<?php

namespace App\Services;

use App\Enums\PromoType;
use App\Enums\TravellerBand;
use App\Models\AgeBand;
use App\Models\CabinCategory;
use App\Models\PromoCode;
use App\Models\Sailing;
use App\Models\SailingCabinPrice;

/**
 * Server-side port of the template's pricing engine (SC.prices / SC.quote in core.js).
 * The client-side quote is display-only; this class is the source of truth for totals.
 */
class PricingService
{
    /**
     * Rate-card prices for one Sailing × Cabin, per person for the whole cruise.
     *
     *   adult/child = round5(base_rate × nights × cruise.price_factor × sailing.price_factor)
     *   infant      = round5(base_rate × nights)            (no factors)
     *
     * @return array{adult: float, child: float, infant: float}
     */
    public function rateCardPrices(Sailing $sailing, CabinCategory $cabin): array
    {
        $cruise = $sailing->cruise;
        // Same operand order as the JS so floating-point results match exactly.
        $k = $cruise->nights * (float) $cruise->price_factor * (float) $sailing->price_factor;

        return [
            'adult' => $this->round5((float) $cabin->base_rate_adult * $k),
            'child' => $this->round5((float) $cabin->base_rate_child * $k),
            'infant' => $this->round5((float) $cabin->base_rate_infant * $cruise->nights),
        ];
    }

    /**
     * Write rate-card prices for every cabin category on the sailing's ship.
     * Sold-out flags and capacity on existing rows are left alone.
     */
    public function syncSailingPrices(Sailing $sailing): void
    {
        $sailing->loadMissing('cruise.ship.cabinCategories');

        foreach ($sailing->cruise->ship->cabinCategories as $cabin) {
            $prices = $this->rateCardPrices($sailing, $cabin);

            SailingCabinPrice::updateOrCreate(
                ['sailing_id' => $sailing->id, 'cabin_category_id' => $cabin->id],
                ['price_adult' => $prices['adult'], 'price_child' => $prices['child'], 'price_infant' => $prices['infant']],
            );
        }
    }

    /**
     * Full quote for a party in one cabin.
     *
     *   fare     = Σ price[band] × qty[band]
     *   fees     = Σ qty[band] × fee_per_night[band] × nights
     *   discount = promo applied to the fare only
     *   total    = fare + fees − discount
     *
     * @param  array{adult?: int, child?: int, infant?: int}  $guests
     */
    public function quote(SailingCabinPrice $price, array $guests, ?PromoCode $promo = null): array
    {
        $price->loadMissing('sailing.cruise');
        $nights = $price->sailing->cruise->nights;
        $bands = AgeBand::all()->keyBy(fn (AgeBand $band) => $band->key->value);

        $lines = [];
        $fare = 0.0;
        $feesTotal = 0.0;
        $count = 0;

        foreach (TravellerBand::cases() as $band) {
            $qty = (int) ($guests[$band->value] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $unit = $price->priceFor($band);
            $lines[] = [
                'band' => $band->value,
                'label' => $bands->get($band->value)?->label ?? ucfirst($band->value),
                'unit' => $unit,
                'qty' => $qty,
                'total' => $unit * $qty,
            ];
            $fare += $unit * $qty;
            $feesTotal += $qty * (float) ($bands->get($band->value)?->fee_per_night ?? 0) * $nights;
            $count += $qty;
        }

        $discount = $promo ? $this->discount($promo, $fare) : 0.0;

        return [
            'prices' => [
                'adult' => $price->priceFor(TravellerBand::Adult),
                'child' => $price->priceFor(TravellerBand::Child),
                'infant' => $price->priceFor(TravellerBand::Infant),
            ],
            'lines' => $lines,
            'fare' => $fare,
            'fees' => $feesTotal,
            'discount' => $discount,
            'promo' => $promo ? ['code' => $promo->code, 'type' => $promo->type->value, 'value' => (float) $promo->value, 'label' => $promo->label] : null,
            'total' => $fare + $feesTotal - $discount,
            'count' => $count,
        ];
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
