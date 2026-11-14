<?php

namespace App\Services;

use App\Enums\OfferTarget;
use App\Enums\PromoType;
use App\Enums\TravellerBand;
use App\Models\Cruise;
use App\Models\Offer;
use App\Models\PromoCode;
use App\Models\Sailing;
use App\Models\SailingCabinPrice;
use App\Models\SiteSetting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
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
     * @param  SailingCabinPrice|array{single: float, double: float, extra: float}  $price  a price row, or its tiers
     * @param  array{adult?: int, child?: int, infant?: int}  $guests
     * @return list<array{band: string, label: string, unit: float, qty: int, total: float}>
     */
    public function fareLines(SailingCabinPrice|array $price, array $guests): array
    {
        $tiers = is_array($price) ? $price : $price->tiers();
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
     *   fees     = port & departure taxes × paying guests (adults + children; infants pay none), see portTaxPerGuest()
     *   total    = fare + fees − discount
     *
     * Cabin prices include VAT; the port & departure taxes are the one separate charge. They are never discounted:
     * the agent rate, offers and promo codes only touch the fare. "portTax" is the per-guest amount used.
     *
     * Agents (App\Services\AgentPricing): $agentPercent comes off every cabin price first, so the fare lines, the fare
     * and the promo are all at the agent rate. "agent" holds the public fare and what the agent rate saved.
     *
     * @param  array{adult?: int, child?: int, infant?: int}  $guests
     */
    public function quote(SailingCabinPrice $price, array $guests, ?PromoCode $promo = null, float $agentPercent = 0.0): array
    {
        $public = $price->tiers();
        $tiers = $agentPercent > 0 ? AgentPricing::discounted($public, $agentPercent) : $public;
        $lines = $this->fareLines($tiers, $guests);
        $fare = (float) array_sum(array_column($lines, 'total'));
        $publicFare = $agentPercent > 0 ? (float) array_sum(array_column($this->fareLines($public, $guests), 'total')) : $fare;
        $count = 0;
        foreach (TravellerBand::cases() as $band) {
            $count += (int) ($guests[$band->value] ?? 0);
        }
        $portTax = self::portTaxPerGuest();
        $feesTotal = self::cents($portTax * ((int) ($guests['adult'] ?? 0) + (int) ($guests['child'] ?? 0)));

        $discount = $promo ? $this->discount($promo, $fare) : 0.0;

        return [
            'prices' => $tiers,
            'lines' => $lines,
            'fare' => $fare,
            'fees' => $feesTotal,
            'portTax' => $portTax,
            'discount' => $discount,
            'promo' => $promo ? ['code' => $promo->code, 'type' => $promo->type->value, 'value' => (float) $promo->value, 'label' => $promo->label] : null,
            'agent' => $agentPercent > 0 ? $this->agentSaving($agentPercent, $publicFare, $fare) : null,
            'total' => $fare + $feesTotal - $discount,
            'count' => $count,
        ];
    }

    /** @return array{percent: ?float, publicFare: float, saving: float} percent is null when cabins had different rates */
    private function agentSaving(?float $percent, float $publicFare, float $fare): array
    {
        return ['percent' => $percent, 'publicFare' => $publicFare, 'saving' => round($publicFare - $fare, 2)];
    }

    /**
     * Quote for a booking of one or more cabins on the same sailing (step 15). Each cabin is priced on its own
     * (tiered: single / double / double + extra, infants free, tax included); the promo applies once, to the
     * total fare. Same shape as quote() plus "cabins", so a one-cabin booking gives the same numbers.
     *
     *   fare     = Σ cabin fares
     *   fees     = Σ cabin port & departure taxes (see quote())
     *   discount = promo on the total fare
     *   total    = fare + fees − discount
     *
     * For an agent each cabin carries its agentPercent (AgentPricing::percentFor()): fares are then agent fares and
     * "agent" sums the public fare and the saving over the cabins.
     *
     * Offers (App\Models\Offer, step 26), after the agent rate and before the promo code — selectOffers() picks them.
     * In each cabin of fare F, in this order (percent / fixed / free / fixed price, see Offer::amountOff()):
     *
     *   upgrade      F − the same guests' fare in the upgrade_from cabin (its own agent rate for agents)
     *   fare         what is left of F (fixed and fixed price: per cabin)
     *   free nights  what is left × free nights ÷ the cruise's nights (always free)
     *                                                           → left fraction k = (F − the three above) ÷ F
     *   3rd guest    the 3rd-guest line × k (fixed: per 3rd guest)
     *   single       the single supplement (single − double ÷ 2) × k, for a guest alone in the cabin
     *   2nd guest    what the 2nd guest adds (double − single) × k, with two paying guests or more
     *   age          each paying guest of the range, on their own price from the grid (guestPrice(): alone → single,
     *                1st / 2nd → double ÷ 2, 3rd and later → 3rd-guest price; adults first, then children) × k,
     *                less what the 3rd guest / single / 2nd guest offers took from that guest (fixed: per guest)
     *   per offer    summed over the booking, rounded to the cent, then capped at max_discount
     *   promo        on fare − offers, and only when every offer used is combinable (otherwise left out:
     *                ConfirmBookingRequest refuses that code first)
     *   total        fare + fees − offers − promo (fees: port & departure taxes, never discounted)
     *
     * An offer marked "instead of the commission" drops the agent rate: the booking is priced at the public prices.
     * Same as SC.quoteRooms() in core.js.
     *
     * @param  list<array{price: SailingCabinPrice, guests: array{adult?: int, child?: int, infant?: int}, agentPercent?: float, travellers?: array<string, array>}>  $cabins
     * @param  iterable<Offer>  $offers  live offers of this sailing for this visitor (OfferService::forSailing())
     * @param  User|null  $agent  the booking agent: the upgrade offer prices the cheaper cabin at its agent rate
     */
    public function quoteBooking(array $cabins, ?PromoCode $promo = null, iterable $offers = [], ?CarbonInterface $departure = null, ?User $agent = null): array
    {
        $cabins = array_values($cabins);
        $chosen = $this->selectOffers($cabins, $offers, $departure);

        // Priced at the agent rates, unless an offer "instead of the commission" really takes something off at the
        // public prices: then the whole booking is at the public prices (no commission). An offer that ends up taking
        // nothing off never drops the commission.
        [$parts, $context] = $this->priceCabins($cabins, $chosen, false, $agent);
        $used = $this->offerLines($parts, $chosen, $context);
        $hasCommission = collect($cabins)->contains(fn ($c) => (float) ($c['agentPercent'] ?? 0) > 0);
        if ($hasCommission && collect($chosen['applied'])->contains(fn (Offer $o) => $o->replacesCommission())) {
            [$publicParts, $publicContext] = $this->priceCabins($cabins, $chosen, true, $agent);
            $publicUsed = $this->offerLines($publicParts, $chosen, $publicContext);
            if (collect($publicUsed)->contains(fn ($l) => $l['offer']->replacesCommission())) {
                [$parts, $used] = [$publicParts, $publicUsed];
            }
        }

        $fare = (float) array_sum(array_column($parts, 'fare'));
        $fees = (float) array_sum(array_column($parts, 'fees'));

        $offerLines = array_map(fn ($l) => array_diff_key($l, ['offer' => true]), $used);
        $offerDiscount = self::cents(array_sum(array_column($offerLines, 'discount')));
        // A promo code only with offers that allow it (among the ones that took something off)
        $promoBlocked = $promo && collect($used)->contains(fn ($l) => ! $l['offer']->combinable);
        if ($promoBlocked) {
            $promo = null;
        }
        $discount = $promo ? $this->discount($promo, $fare - $offerDiscount) : 0.0;

        // One percent when every cabin had the same rate (cabins without a rate count as 0)
        $agent = null;
        if (array_filter(array_column($parts, 'agent'))) {
            $percents = array_unique(array_map(fn ($p) => (float) ($p['agent']['percent'] ?? 0), $parts));
            $publicFare = (float) array_sum(array_map(fn ($p) => $p['agent']['publicFare'] ?? $p['fare'], $parts));
            $agent = $this->agentSaving(count($percents) === 1 ? reset($percents) : null, $publicFare, $fare);
        }

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
            'portTax' => self::portTaxPerGuest(),
            // Offers used, each with what it took off (step 26); offerDiscount is their sum
            'offers' => $offerLines,
            'offerDiscount' => $offerDiscount,
            'discount' => $discount,
            'promo' => $promo ? ['code' => $promo->code, 'type' => $promo->type->value, 'value' => (float) $promo->value, 'label' => $promo->label] : null,
            'promoBlocked' => $promoBlocked,
            'agent' => $agent,
            'total' => $fare + $fees - $offerDiscount - $discount,
            'count' => (int) array_sum(array_column($parts, 'count')),
        ];
    }

    /**
     * Each cabin's quote, at the agent rates or ($public) the public prices, and what offerLines() needs per cabin:
     * the cruise's nights and the same guests' fare in the upgrade offer's cabin (its own agent rate for agents).
     *
     * @return array{0: list<array>, 1: list<array{nights: int, upgradeFare: ?float}>}
     */
    private function priceCabins(array $cabins, array $chosen, bool $public, ?User $agent): array
    {
        $parts = [];
        $context = [];
        foreach ($cabins as $i => $cabin) {
            $price = $cabin['price']->loadMissing('cabinCategory');
            $quote = $this->quote($price, $cabin['guests'], null, $public ? 0.0 : (float) ($cabin['agentPercent'] ?? 0));

            $context[$i] = ['nights' => 0, 'upgradeFare' => null];
            if ($chosen['cabins'][$i]['nights']) {
                $context[$i]['nights'] = (int) $price->sailing->cruise->nights;
            }
            if ($upgrade = $chosen['cabins'][$i]['upgrade']) {
                $from = $chosen['upgradePrices'][$upgrade->upgrade_from];
                $fromPercent = $agent && ! $public ? app(AgentPricing::class)->percentFor($from->setRelation('sailing', $price->sailing), $agent) : 0.0;
                $context[$i]['upgradeFare'] = $this->quote($from, $cabin['guests'], null, $fromPercent)['fare'];
            }
            $parts[] = [
                'position' => $i + 1,
                'cabin' => $price->cabinCategory->slug,
                'name' => $price->cabinCategory->name_en,
                'guests' => array_map('intval', array_merge(['adult' => 0, 'child' => 0, 'infant' => 0], $cabin['guests'])),
                'prices' => $quote['prices'],
                'lines' => $quote['lines'],
                'fare' => $quote['fare'],
                'fees' => $quote['fees'],
                'agent' => $quote['agent'],
                'total' => $quote['fare'] + $quote['fees'],
                'count' => $quote['count'],
            ];
        }

        return [$parts, $context];
    }

    /**
     * Which offers apply, before any amount is worked out. Cabin by cabin, among the offers whose cabin types and
     * nationalities (every traveller of the cabin) fit, the most specific (sailing → cruise → every cruise), then the
     * newest:
     *
     *   fare     one per cabin
     *   nights   one free-nights offer per cabin
     *   upgrade  one per cabin, when its upgrade_from cabin is another cabin priced on this sailing
     *   third    one per cabin with a 3rd paying guest
     *   single   one per cabin with a single paying guest
     *   second   one per cabin with two paying guests or more
     *   age      one per paying guest (adults, then children) with a date of birth, whose age on the departure date
     *            is in range and whose own nationality fits; infants are free, so they get none
     *
     * Nationalities and ages come from the travellers: without them (before the travellers step) those offers wait.
     *
     * @param  list<array{price: SailingCabinPrice, guests: array, travellers?: array<string, array>}>  $cabins
     * @param  iterable<Offer>  $offers
     * Whether the commission is dropped and a promo code allowed is decided in quoteBooking(), on the offers that
     * actually take something off.
     *
     * @return array{cabins: list<array{fare: ?Offer, nights: ?Offer, upgrade: ?Offer, third: ?Offer, single: ?Offer, second: ?Offer, age: list<Offer>, agePositions: list<int>}>, upgradePrices: array<string, SailingCabinPrice>, applied: list<Offer>}
     */
    public function selectOffers(array $cabins, iterable $offers, ?CarbonInterface $departure = null): array
    {
        $cabins = array_values($cabins);
        $offers = collect($offers);
        $best =fn (Collection $list) => $list->sort(fn (Offer $a, Offer $b) => [$b->level(), $b->id] <=> [$a->level(), $a->id])->first();

        // Upgrade offers: the price rows of their "price of" cabins on this sailing (none: the offer can't apply)
        $upgradePrices = [];
        $upgradeSlugs = $offers->where('target', OfferTarget::Upgrade)->pluck('upgrade_from')->filter()->unique()->values();
        if ($upgradeSlugs->isNotEmpty() && $cabins) {
            $upgradePrices = SailingCabinPrice::with('cabinCategory')->where('sailing_id', $cabins[0]['price']->sailing_id)
                ->whereHas('cabinCategory', fn ($q) => $q->whereIn('slug', $upgradeSlugs))->get()
                ->keyBy(fn (SailingCabinPrice $p) => $p->cabinCategory->slug)->all();
        }

        $chosen = [];
        foreach ($cabins as $cabin) {
            $slug = $cabin['price']->loadMissing('cabinCategory')->cabinCategory->slug;
            $travellers = (array) ($cabin['travellers'] ?? []);
            $nationalities = array_column($travellers, 'nationality');
            $here = $offers->filter(fn (Offer $o) => $o->fitsCabin($slug));
            $whole = $here->filter(fn (Offer $o) => $o->fitsNationalities($nationalities));
            $paying = (int) ($cabin['guests']['adult'] ?? 0) + (int) ($cabin['guests']['child'] ?? 0);

            // Age offers, with each guest's place among the paying guests (adults first, then children): 1st and 2nd
            // share the double price, the 3rd and later pay the 3rd-guest price (see guestPrice())
            $age = [];
            $agePositions = [];
            $ageOffers = $here->where('target', OfferTarget::Age);
            if ($ageOffers->isNotEmpty()) {
                $departure ??= $cabin['price']->sailing->departure_date;
                $position = 0;
                foreach (['adult', 'child'] as $band) {
                    for ($n = 1; $n <= (int) ($cabin['guests'][$band] ?? 0); $n++) {
                        $position++;
                        $t = $travellers["$band-$n"] ?? [];
                        if (empty($t['dob'])) {
                            continue;
                        }
                        $years = self::yearsOn($t['dob'], $departure);
                        $fits = $ageOffers->filter(fn (Offer $o) => $o->fitsAge($years) && $o->fitsNationalities([$t['nationality'] ?? null]));
                        if ($offer = $best($fits)) {
                            $age[] = $offer;
                            $agePositions[] = $position;
                        }
                    }
                }
            }

            $chosen[] = [
                'fare' => $best($whole->where('target', OfferTarget::Fare)),
                'nights' => $best($whole->where('target', OfferTarget::FreeNights)),
                'upgrade' => $best($whole->where('target', OfferTarget::Upgrade)
                    ->filter(fn (Offer $o) => $o->upgrade_from !== $slug && isset($upgradePrices[$o->upgrade_from]))),
                'third' => $paying > 2 ? $best($whole->where('target', OfferTarget::ThirdGuest)) : null,
                'single' => $paying === 1 ? $best($whole->where('target', OfferTarget::Single)) : null,
                'second' => $paying >= 2 ? $best($whole->where('target', OfferTarget::SecondGuest)) : null,
                'age' => $age,
                'agePositions' => $agePositions,
            ];
        }

        $applied = [];
        foreach ($chosen as $c) {
            array_push($applied, ...array_filter([$c['upgrade'], $c['fare'], $c['nights'], $c['third'], $c['single'], $c['second'], ...$c['age']]));
        }

        return [
            'cabins' => $chosen,
            'upgradePrices' => $upgradePrices,
            'applied' => $applied,
        ];
    }

    /**
     * What each chosen offer takes off the booking, in the order they were met (see quoteBooking()); only offers that
     * take something off. "offer" is the model, for quoteBooking() (left out of the saved quote).
     *
     * @param  list<array{nights: int, upgradeFare: ?float}>  $context  per cabin, from priceCabins()
     * @return list<array{id: int, name: string, nameAr: ?string, target: string, discount: float, offer: Offer}>
     */
    private function offerLines(array $parts, array $chosen, array $context): array
    {
        $totals = [];
        $add = function (Offer $offer, float $amount) use (&$totals) {
            $totals[$offer->id] ??= ['offer' => $offer, 'amount' => 0.0];
            $totals[$offer->id]['amount'] += $amount;
        };

        foreach ($parts as $i => $part) {
            $c = $chosen['cabins'][$i];
            $fare = (float) $part['fare'];
            $paying = $part['guests']['adult'] + $part['guests']['child'];
            $taken = 0.0; // off this cabin so far

            // Whole-cabin offers, one after the other on what is left
            if ($c['upgrade']) {
                $off = $c['upgrade']->amountOff(max(0.0, $fare - (float) $context[$i]['upgradeFare']));
                $add($c['upgrade'], $off);
                $taken += $off;
            }
            if ($c['fare']) {
                $off = $c['fare']->amountOff($fare - $taken);
                $add($c['fare'], $off);
                $taken += $off;
            }
            if ($c['nights'] && $context[$i]['nights'] > 0) {
                $off = ($fare - $taken) * min($c['nights']->free_nights, $context[$i]['nights']) / $context[$i]['nights'];
                $add($c['nights'], $off);
                $taken += $off;
            }
            $left = $fare > 0 ? ($fare - $taken) / $fare : 0.0;

            // Parts of the cabin price, at what is left of it
            $thirdOff = $singleOff = $secondOff = 0.0;
            if ($c['third']) {
                $extra = (float) array_sum(array_column(array_filter($part['lines'], fn ($l) => $l['band'] === 'extra'), 'total'));
                $thirdOff = $c['third']->amountOff($extra * $left, max(0, $paying - 2));
                $add($c['third'], $thirdOff);
            }
            if ($c['single']) {
                $supplement = max(0.0, (float) $part['prices']['single'] - (float) $part['prices']['double'] / 2);
                $singleOff = $c['single']->amountOff($supplement * $left);
                $add($c['single'], $singleOff);
            }
            if ($c['second']) {
                $second = max(0.0, (float) $part['prices']['double'] - (float) $part['prices']['single']);
                $secondOff = $c['second']->amountOff($second * $left);
                $add($c['second'], $secondOff);
            }

            // Age offers: on the guest's own price (guestPrice()), less what the offers above took from that guest
            foreach ($c['age'] as $k => $offer) {
                $pos = $c['agePositions'][$k] ?? 1;
                $price = self::guestPrice($part['prices'], $paying, $pos) * $left
                    - ($pos > 2 ? $thirdOff / max(1, $paying - 2) : 0.0)
                    - ($paying === 1 ? $singleOff : 0.0)
                    - ($paying >= 2 && $pos <= 2 ? $secondOff / 2 : 0.0);
                $add($offer, $offer->amountOff(max(0.0, $price)));
            }
        }

        $lines = [];
        foreach ($totals as ['offer' => $offer, 'amount' => $amount]) {
            $amount = self::cents($amount);
            if ($offer->max_discount !== null) {
                $amount = min($amount, (float) $offer->max_discount);
            }
            if ($amount > 0) {
                $lines[] = ['id' => $offer->id, 'name' => $offer->name, 'nameAr' => $offer->name_ar, 'target' => $offer->target->value, 'discount' => $amount, 'offer' => $offer];
            }
        }

        return $lines;
    }

    /**
     * One paying guest's own price in a cabin, from the price grid: alone → single; 1st and 2nd guest → half the
     * double each; 3rd guest and later → the 3rd-guest price. Adds up to the cabin fare. Same as guestPrice() in core.js.
     *
     * @param  array{single: float, double: float, extra: float}  $prices
     * @param  int  $position  1-based, adults first then children
     */
    public static function guestPrice(array $prices, int $paying, int $position): float
    {
        return match (true) {
            $paying === 1 => (float) $prices['single'],
            $position <= 2 => (float) $prices['double'] / 2,
            default => (float) $prices['extra'],
        };
    }

    /**
     * Port & departure taxes in USD per paying guest (adults and children): the admin's amount (Admin → Pricing &
     * offers → Port taxes, site_settings "port_tax_per_guest"), or config cruise.port_tax_per_guest until one is
     * saved. 0 means no port taxes. Sent to the browser as SC_DATA.portTax (ScDataPresenter).
     */
    public static function portTaxPerGuest(): float
    {
        $saved = SiteSetting::value('port_tax_per_guest');

        return max(0.0, (float) ($saved !== '' ? $saved : config('cruise.port_tax_per_guest')));
    }

    /** Whole years on a date (the departure date), as SC.ageAt() in core.js and the age band checks. */
    public static function yearsOn(string $dob, CarbonInterface $on): int
    {
        $born = Carbon::parse($dob);
        $months = ($on->year - $born->year) * 12 + ($on->month - $born->month) - ($on->day < $born->day ? 1 : 0);

        return (int) floor($months / 12);
    }

    /** To the cent, rounding halves up — the same float steps as cents() in core.js, so both give the same amount. */
    public static function cents(float $n): float
    {
        return floor($n * 100 + 0.5) / 100;
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
